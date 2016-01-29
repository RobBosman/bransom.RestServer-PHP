<?php

Bootstrap::import('nl.bransom.persistency.DbConnection');
Bootstrap::import('nl.bransom.persistency.meta.DbConstants');
Bootstrap::import('nl.bransom.persistency.meta.LinkEntity');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.OneToManyRelationship');

/**
 * Description of Schema
 *
 * @author Rob Bosman
 */
class Schema {

    private $name;
    private $dbConnection; // Keep the dbConnection alive as long as this Schema-instance exists.
    private $objectEntities;
    private $linkEntities;

    function __construct($name) {
        $this->name = strtolower($name);
    }

    private function init() {
        if ($this->dbConnection != NULL) {
            return;
        }
        $this->dbConnection = new DbConnection($this->name);
        $mySQLi = $this->dbConnection->getMySQLi();
        $mySQLi->autocommit(FALSE);

        $this->fetchEntities($mySQLi);
        $this->fetchOneToManyRelationship($mySQLi);
        foreach ($this->linkEntities as $linkEntity) {
            $linkEntity->deployManyToManyRelationships();
        }

        // Sort all entities  by 'foreign key dependency'.
        uasort($this->objectEntities, 'ObjectEntity::compareFkDependency');
        // Sort twice, because for some reason usort sometimes 'forgets' to compare all items.
        uasort($this->objectEntities, 'ObjectEntity::compareFkDependency');
    }

    public function getName() {
        return $this->name;
    }

    public function getMySQLi() {
        $this->init();
        return $this->dbConnection->getMySQLi();
    }

    public function getAllEntities() {
        $this->init();
        return array_merge($this->objectEntities, $this->linkEntities);
    }

    public function getObjectEntities() {
        $this->init();
        return $this->objectEntities;
    }

    public function getObjectEntity($entityName, $mustExist = TRUE) {
        $this->init();
        $entityName = strtolower($entityName);
        if (array_key_exists($entityName, $this->objectEntities)) {
            return $this->objectEntities[$entityName];
        } else if ($mustExist == TRUE) {
            throw new Exception("Unknown entity '$entityName'.");
        } else {
            return NULL;
        }
    }

    public function getLinkEntity($entityName) {
        $this->init();
        $entityName = strtolower($entityName);
        if (array_key_exists($entityName, $this->linkEntities)) {
            return $this->linkEntities[$entityName];
        } else {
            throw new Exception("Unknown link-entity '$entityName'.");
        }
    }

    public function sortObjects(array &$unsortedObjects, $reversedOrder) {
        if (count($unsortedObjects) == 0) {
            return;
        }
        $this->init();
        // Use the sorted entities to put all ParsedObjects in the right order.
        $sortedEntities = $this->objectEntities;
        if ($reversedOrder) {
            $sortedEntities = array_reverse($sortedEntities);
        }
        $sortedObjects = array();
        foreach ($sortedEntities as $sortedEntity) {
            foreach ($unsortedObjects as $unsortedObject) {
                if ($unsortedObject->getEntity() == $sortedEntity) {
                    $sortedObjects[] = $unsortedObject;
                }
            }
        }
        $unsortedObjects = $sortedObjects;
    }

    public function getLinkRelationship($entityA, $entityB, $mustExist = TRUE) {
        $this->init();
        foreach ($entityA->getRelationships() as $relationship) {
            if (($relationship->getFkEntity() != $entityA) and ($relationship->getFkEntity() != $entityB)
                    and ($relationship->getOppositeEntity($entityA) == $entityB)) {
                // This is the relationship betweed entity A and B for which $linkEntity maintains the foreign keys.
                return $relationship;
            }
        }
        if ($mustExist) {
            throw new Exception("Cannot find a many-to-many relationship between entities '" . $entityA->getName()
                    . "' and '" . $entityB->getName() . "'.");
        } else {
            return NULL;
        }
    }

    private function fetchEntities(MySQLi $mySQLi) {
        $this->objectEntities = array();
        $this->linkEntities = array();
        $queryString = "SELECT id, name, id_object_column_name, id_state_column_name FROM " . DbConstants::TABLE_ENTITY;
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching entity metadata - " . $mySQLi->error . "\n<!--\n$queryString\n-->");
        }
        while ($dbObject = $queryResult->fetch_assoc()) {
            $entityName = $dbObject['name'];
            $idObjectColumnName = $dbObject['id_object_column_name'];
            if ($idObjectColumnName != NULL) {
                $objectEntity = new ObjectEntity($dbObject['id'], $entityName, $idObjectColumnName,
                        $dbObject['id_state_column_name'], $mySQLi);
                $this->objectEntities[$entityName] = $objectEntity;
            } else {
                $linkEntity = new LinkEntity($dbObject['id'], $entityName, $dbObject['id_state_column_name'], $mySQLi);
                $this->linkEntities[$entityName] = $linkEntity;
            }
        }
        $queryResult->close();
    }

    private function fetchOneToManyRelationship(MySQLi $mySQLi) {
        $queryString = "SELECT id_fk_entity, fk_column_name, id_referred_entity, id_owner_entity FROM "
                . DbConstants::TABLE_RELATIONSHIP;
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching relationship metadata - " . $mySQLi->error
                    . "\n<!--\n$queryString\n-->");
        }
        while ($dbObject = $queryResult->fetch_assoc()) {
            $fkEntity = $this->getEntityById($dbObject['id_fk_entity']);
            $referredEntity = $this->getEntityById($dbObject['id_referred_entity']);
            $ownerEntity = $this->getEntityById($dbObject['id_owner_entity']);
            $relationship = new OneToManyRelationship($fkEntity, $dbObject['fk_column_name'], $referredEntity,
                    $ownerEntity);
            $fkEntity->addRelationship($relationship);
            $referredEntity->addRelationship($relationship);
        }
        $queryResult->close();
    }

    private function getEntityById($entityId) {
        foreach ($this->objectEntities as $entity) {
            if ($entity->getId() == $entityId) {
                return $entity;
            }
        }
        foreach ($this->linkEntities as $entity) {
            if ($entity->getId() == $entityId) {
                return $entity;
            }
        }
        throw new Exception("Cannot find entity with id '$entityId'.");
    }
}

?>
