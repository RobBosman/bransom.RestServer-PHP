<?php

Bootstrap::import('nl.bransom.persistency.meta.Entity');

/**
 * Description of Relationship
 *
 * @author Rob Bosman
 */
abstract class Relationship {

    private $fkEntity;
    private $fkColumnNames;
    private $relatedEntities;
    private $ownerEntity;

    public function __construct(Entity $fkEntity, array $fkColumnNameToEntityMap, $ownerEntity) {
        $this->fkEntity = $fkEntity;
        $this->fkColumnNames = array();
        $this->relatedEntities = array();
        if (($fkEntity->isObjectEntity()) or (count($fkColumnNameToEntityMap) < 2)) {
            $this->relatedEntities[] = $fkEntity;
        }
        foreach ($fkColumnNameToEntityMap as $fkColumnName => $toEntity) {
            $this->fkColumnNames[$toEntity->getId()] = $fkColumnName;
            $this->relatedEntities[] = $toEntity;
        }
        if (count($this->relatedEntities) < 2) {
            throw new Exception("Cannot create a Relationship between less than two entities.");
        }
        if (($ownerEntity != NULL) and (array_search($ownerEntity, $this->relatedEntities) === FALSE)) {
            throw new Exception("Owner-entity '" . $ownerEntity->getName() . "' must be either NULL, '"
                    . $this->getOneEntity()->getName() . "' or '" . $this->getOtherEntity()->getName() . "'.");
        }
        $this->ownerEntity = $ownerEntity;
    }

    public function getFkEntity() {
        return $this->fkEntity;
    }

    public function getFkColumnName(Entity $toEntity) {
        if (!array_key_exists($toEntity->getId(), $this->fkColumnNames)) {
            throw new Exception("Entity '" . $this->fkEntity->getName() . "' has no foreign key referring to entity '"
                    . $toEntity->getName() . "'.");
        }
        return $this->fkColumnNames[$toEntity->getId()];
    }

    public function getOneEntity() {
        return $this->relatedEntities[0];
    }

    public function getOtherEntity() {
        return $this->relatedEntities[1];
    }

    public function getOwnerEntity() {
        return $this->ownerEntity;
    }

    public function getOppositeEntity(Entity $entity) {
        if ($entity == $this->getOneEntity()) {
            return $this->getOtherEntity();
        } else if ($entity == $this->getOtherEntity()) {
            return $this->getOneEntity();
        } else {
            throw new Exception("Entity '" . $entity->getName() . "' is related to neither '"
                    . $this->getOneEntity()->getName() . "' nor '" . $this->getOtherEntity()->getName() . "'.");
        }
    }

}

?>
