<?php

Bootstrap::import('nl.bransom.persistency.DbConnection');
Bootstrap::import('nl.bransom.persistency.meta.Property');
Bootstrap::import('nl.bransom.persistency.meta.Relationship');

/**
 * Entity
 *
 * @author Rob Bosman
 */
abstract class Entity {

    private $id;
    private $name;
    private $stateIdColumnName;
    private $properties;
    private $relationships;

    function __construct($entityId, $entityName, $stateIdColumnName, $mySQLi) {
        $this->id = $entityId;
        $this->name = $entityName;
        $this->properties = array();
        $this->relationships = array();

        $queryResult = $mySQLi->query("SHOW COLUMNS FROM $this->name");
        if (!$queryResult) {
            throw new Exception("Error fetching column metadata of '$entityName' - " . $mySQLi->error);
        }
        while ($metaDataColumn = $queryResult->fetch_assoc()) {
            $propertyName = $metaDataColumn['Field'];
            $this->properties[$propertyName] = new Property($propertyName, $metaDataColumn);
        }
        $queryResult->close();

        $stateProperty = $this->getProperty($stateIdColumnName, FALSE);
        if ($stateProperty != NULL) {
            $this->stateIdColumnName = $stateProperty->getName();
        }
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getStateIdColumnName() {
        return $this->stateIdColumnName;
    }

    public function getProperties() {
        return $this->properties;
    }

    public function getProperty($propertyName, $mustExist = TRUE) {
        foreach ($this->properties as $key => $property) {
            if (strcasecmp($key, $propertyName) == 0) {
                return $property;
            }
        }
        if ($mustExist) {
            throw new Exception("Unknown property '$propertyName' of entity '" . $this->name . "'.");
        }
        return NULL;
    }

    public function getRelationships() {
        return $this->relationships;
    }

    public function addRelationship(Relationship $relationship) {
        if (($relationship->getOneEntity() != $this) and ($relationship->getOtherEntity() != $this)) {
            throw new Exception("Invalid argument: cannot add relationship with entity '"
                    . $relationship->getOtherEntity()->getName() . "' as a relationship of entity '$this->name'.");
        }
        $this->relationships[] = $relationship;
    }

    public abstract function isObjectEntity();

}

?>
