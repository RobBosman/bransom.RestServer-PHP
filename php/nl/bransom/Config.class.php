<?php

/**
 * Description of Config
 *
 * @author Rob Bosman
 */
class Config {

    private static $instance = NULL;

    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    private $iniData;

    private function __construct() {
        $iniFileName = Bootstrap::getConfigFile();
        if ($iniFileName == NULL) {
            throw new Exception("Het configuratiebestand is niet gespecificeerd.");
        }
        $iniFileName = realpath($iniFileName);
        if (!is_readable($iniFileName)) {
            throw new Exception("Het configuratiebestand met absoluut pad '" . Bootstrap::getConfigFile()
                    . "' kan niet worden gelezen.");
        }
        $this->iniData = parse_ini_file($iniFileName, true);
    }

    public function getSection($sectionName) {
        if (array_key_exists($sectionName, $this->iniData)) {
            return $this->iniData[$sectionName];
        } else {
            return array();
        }
    }
}

?>
