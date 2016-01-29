<?php

Bootstrap::import('nl.bransom.Config');
Bootstrap::import('nl.bransom.persistency.meta.Schema');

/**
 * Description of MetaData
 *
 * @author Rob Bosman
 */
class MetaData {
    
    private static $instance;
    
    public static function getInstance() {
        if (!isset(MetaData::$instance)) {
            MetaData::$instance = new MetaData();
        }
        return MetaData::$instance;
    }

    private $appSchemaMap;
    private $appNamespaceUriMap;
    private $appContextRootMap;

    private function __construct() {
        $this->appSchemaMap = array();
        $this->appNamespaceUriMap = array();
        $this->appContextRootMap = array();
        $config = Config::getInstance();
        foreach ($config->getSection('db') as $propertyId => $value) {
            $prefix = 'schema-for-app.';
            if (strpos($propertyId, $prefix) === 0) {
                $this->appSchemaMap[substr($propertyId, strlen($prefix))] = new Schema($value);
            }
        }
        foreach ($config->getSection('xml') as $propertyId => $value) {
            $prefix = 'namespaceUri-for-app.';
            if (strpos($propertyId, $prefix) === 0) {
                $this->appNamespaceUriMap[substr($propertyId, strlen($prefix))] = $value;
            }
        }
        foreach ($config->getSection('url') as $propertyId => $value) {
            $prefix = 'context-root-for-app.';
            if (strpos($propertyId, $prefix) === 0) {
                $this->appContextRootMap[substr($propertyId, strlen($prefix))] = $value;
            }
        }
    }

    public function getNamespaceUri($appName) {
        if (array_key_exists($appName, $this->appNamespaceUriMap)) {
            return $this->appNamespaceUriMap[$appName];
        } else {
            return NULL;
        }
    }

    public function getSchema($appName) {
        if (array_key_exists($appName, $this->appSchemaMap)) {
            return $this->appSchemaMap[$appName];
        }
        throw new Exception("Schema for app '$appName' is not configured.");
    }

    public function getContextRoot($appName) {
        if (array_key_exists($appName, $this->appContextRootMap)) {
            return $this->appContextRootMap[$appName];
        } else {
            return $appName;
        }
    }

    public function getAppNames() {
        return array_keys($this->appSchemaMap);
    }
}

?>
