<?php

Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.OneToManyRelationship');
Bootstrap::import('nl.bransom.persistency.meta.ManyToManyRelationship');

/**
 * LinkEntity
 *
 * @author Rob Bosman
 */
class LinkEntity extends Entity {

    function __construct($entityId, $entityName, $stateIdColumnName, $mySQLi) {
        parent::__construct($entityId, $entityName, $stateIdColumnName, $mySQLi);
    }

    public function isObjectEntity() {
        return FALSE;
    }

    /**
     * Shares a single many-to-many relationship among all related entities.
     */
    public function deployManyToManyRelationships() {
        // Collect all related entities and foreign key column names.
        $fkColumnNameEntityMap = array();
        foreach ($this->getRelationships() as $relationship) {
            if ($relationship->getFkEntity() == $this) {
                $relatedEntity = $relationship->getOppositeEntity($this);
                $fkColumnNameEntityMap[$relationship->getFkColumnName($relatedEntity)] = $relatedEntity;
            }
        }
        // Create the many-to-many relationship.
        $manyToManyRelationship = new ManyToManyRelationship($this, $fkColumnNameEntityMap);
        // Then share that relationship among all related entities.
        foreach (array_values($fkColumnNameEntityMap) as $relatedEntity) {
            $relatedEntity->addRelationship($manyToManyRelationship);
        }
    }
}

?>
