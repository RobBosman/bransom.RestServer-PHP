<?php

Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');

/**
 * TemporaryIdMap keeps track of temporary id's and their persisted counterparts.
 *
 * [ entity => [ temporaryId => persistedId ] ]
 *
 * @author Rob Bosman
 */
class TemporaryIdMap {

    const TEMPORARY_ID_PREFIX = '~';
    const XML_NAMESPACE = 'http://ns.bransom.nl/persistency/ids/v20120101';
    const XML_SET = 'set';
    const XML_ID = 'id';
    const XML_TEMPORARY = 'temporary';

    private static $temporaryIds = 0;

    public static function generateTemporaryId() {
        return self::TEMPORARY_ID_PREFIX . self::$temporaryIds++;;
    }

    public static function isPersistedId($id) {
        return ((strlen($id) > 0) and (preg_replace("/[0-9]+/", '', $id) == ''));
    }

    public static function deserializeTemporaryIds(Schema $schema, $serializedIds) {
        $temporaryIdMap = new TemporaryIdMap();
        foreach (explode("\n", $serializedIds) as $entry) {
            $elements = explode(" ", $entry);
            if (count($elements) == 3) {
                $entity = $schema->getObjectEntity($elements[0]);
                $temporaryIdMap->setId($entity, $elements[1], $elements[2]);
            }
        }
        return $temporaryIdMap;
    }

    private $parent;
    private $entityTemporaryIdMap;

    public function __construct($parentTemporaryIdMap = NULL) {
        $this->parent = $parentTemporaryIdMap;
        $this->entityTemporaryIdMap = array();
    }

    public function setId(ObjectEntity $entity, $temporaryId, $persistedId) {
        if ($temporaryId == NULL) {
            if ($persistedId == NULL) {
                return;
            }
            $temporaryId = $persistedId;
        } else if ((self::isPersistedId($temporaryId)) and ($temporaryId != $persistedId)) {
            throw new Exception("TemporaryId '$temporaryId' appears to be a persistent id and"
                    . " must therefore be equal to '$persistedId'.", RestResponse::CLIENT_ERROR);
        } else {
            $existingPersistedId = $this->getPersistedId($entity, $temporaryId, FALSE);
            if (($existingPersistedId != NULL) and ($persistedId != $existingPersistedId)) {
                throw new Exception("TemporaryId '$temporaryId' has already been set to '$existingPersistedId'.",
                        RestResponse::CLIENT_ERROR);
            }
        }

        $entityName = $entity->getName();
        if (!array_key_exists($entityName, $this->entityTemporaryIdMap)) {
            $this->entityTemporaryIdMap[$entityName] = array();
        }
        $this->entityTemporaryIdMap[$entityName][$temporaryId] = $persistedId;
    }

    public function getPersistedId(ObjectEntity $entity, $temporaryId, $mustExist = TRUE) {
        // If it is a persisted id already...
        if (self::isPersistedId($temporaryId)) {
            // ...then return it.
            return $temporaryId;
        }
        // If it's in the entityTemporaryIdMap...
        if (array_key_exists($entity->getName(), $this->entityTemporaryIdMap)) {
            $temporaryIdMap = $this->entityTemporaryIdMap[$entity->getName()];
            if (array_key_exists($temporaryId, $temporaryIdMap)) {
                // ...then return it.
                return $temporaryIdMap[$temporaryId];
            }
        }
        // If a parent is available...
        if ($this->parent != null) {
            // ...then delegate the call.
            return $this->parent->getPersistedId($entity, $temporaryId, $mustExist);
        }
        // OK, so a persisted id is not available. Now what?
        if ($mustExist) {
            throw new Exception("Could not find the persisted equivalent of temporary id '$temporaryId' of entity '"
                    . $entity->getName() . "'.", RestResponse::CLIENT_ERROR);
        }
        return NULL;
    }

    /**
     * @return as XML
     *   <set>
     *     <entity>
     *       <id>2</id>
     *       <id temporary="T0">4</id>
     *     </entity>
     *   </set>
     */
    public function toXmlString() {
        $domImplementation = new DOMImplementation();
        $doc = $domImplementation->createDocument();
        $setXml = $doc->createElementNS(self::XML_NAMESPACE, self::XML_SET);
        $doc->appendChild($setXml);
        // Alphabetically sort the entities in the map. This allows for easier unit testing.
        ksort($this->entityTemporaryIdMap);
        foreach ($this->entityTemporaryIdMap as $entityName => $temporaryIdMap) {
            $entityXml = $doc->createElementNS(self::XML_NAMESPACE, $entityName);
            $setXml->appendChild($entityXml);
            foreach ($temporaryIdMap as $temporaryId => $persistedId) {
                $idXml = $doc->createElementNS(self::XML_NAMESPACE, self::XML_ID, $persistedId);
                $entityXml->appendChild($idXml);
                // Show the temporary key if it's present and if it has not been generated internally.
                if (self::isExternalTemporaryId($temporaryId)) {
                    $idXml->setAttribute(self::XML_TEMPORARY, $temporaryId);
                }
            }
        }
        return $doc->saveXML();
    }

    public function serializeTemporaryIds() {
        $temporaryIdEntries = array();
        $temporaryIdMap = $this;
        while ($temporaryIdMap != NULL) {
            foreach ($temporaryIdMap->entityTemporaryIdMap as $entityName => $idMap) {
                foreach ($idMap as $temporaryId => $persistedId) {
                    if (self::isExternalTemporaryId($temporaryId)) {
                        $temporaryIdEntries[] = "$entityName $temporaryId $persistedId";
                    }
                }
            }
            $temporaryIdMap = $temporaryIdMap->parent;
        }
        return implode("\n", $temporaryIdEntries);
    }

    private static function isExternalTemporaryId($id) {
        if (($id != NULL) and (!self::isPersistedId($id))) {
            // It's a temporary id. Check if it's external.
            $index = strpos($id, self::TEMPORARY_ID_PREFIX);
            return (($index === FALSE) or ($index != 0));
        } else {
            return FALSE;
        }
    }
}
?>
