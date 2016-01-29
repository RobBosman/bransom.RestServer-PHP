<?php

Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.Relationship');

/**
 * ObjectEntity
 *
 * @author Rob Bosman
 */
class ObjectEntity extends Entity {

    private $objectIdColumnName;

    function __construct($entityId, $entityName, $objectIdColumnName, $stateIdColumnName, $mySQLi) {
        parent::__construct($entityId, $entityName, $stateIdColumnName, $mySQLi);
        $idProperty = $this->getProperty($objectIdColumnName, FALSE);
        if ($idProperty != NULL) {
            $this->objectIdColumnName = $idProperty->getName();
        }
    }

    public function getObjectIdColumnName() {
        return $this->objectIdColumnName;
    }

    public function isObjectEntity() {
        return TRUE;
    }

    /**
     * Compares two entities A and B. If A contains a foreign key referring to B, then A > B. So when sorting a list of
     * entities this way, the entities will be sorted in creation order. (In this case B < A, because B must be created
     * before A.)
     *
     * @param Entity $entityA
     * @param Entity $entityB
     * @return int compare
     */
    public static function compareFkDependency(Entity $entityA, Entity $entityB) {
        if ($entityA == $entityB) {
            return 0;
        }
        // Check if A has a foreign key that (directly or indirectly) refers to B.
        $levelAtoB = $entityA->contaisFkReferringTo($entityB);
        // Check if B has a foreign key that (directly or indirectly) refers to A.
        $levelBtoA = $entityB->contaisFkReferringTo($entityA);
        if ($levelAtoB < 0) {
            // A does not contain a reference to B.
            if ($levelBtoA < 0) {
                // B does not contain a reference to A either, so A and B are not related.
                return 0;
            } else {
                // B contains a reference to A, so B < A.
                return -1;
            }
        } else {
            // A contains a reference to B...
            if ($levelBtoA < 0) {
                // ...and B does not contain a reference to A, so A < B.
                return 1;
            } else {
                // ...and B contains a reference to A too. The strongest wins!
                return ($levelAtoB < $levelBtoA ? 1 : -1);
            }
        }
    }

    private function contaisFkReferringTo(Entity $otherEntity, $level = 0) {
        // Recursively check if the entity (self) contais a foreign key that refers to $otherEntity, either directly
        // (level = 0) or indirectly (level > 0).
        // A value of -1 is returned if no fk-relationship could be found.
        if ($otherEntity == $this) {
            return -1;
        }
        foreach ($this->getRelationships() as $relationship) {
            if ($relationship->getFkEntity() == $this) {
                $foreignEntity = $relationship->getOppositeEntity($this);
                if ($foreignEntity == $otherEntity) {
                    return $level;
                }
                $compare = $foreignEntity->contaisFkReferringTo($otherEntity, $level + 1);
                if ($compare > 0) {
                    return $compare;
                }
            }
        }
        return -1;
    }
}

?>
