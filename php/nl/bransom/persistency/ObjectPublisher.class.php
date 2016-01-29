<?php

Bootstrap::import('nl.bransom.Audit');
Bootstrap::import('nl.bransom.persistency.ObjectRef');
Bootstrap::import('nl.bransom.persistency.QueryEntity');
Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.Property');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.persistency.Scope');
Bootstrap::import('nl.bransom.persistency.XmlConstants');

/**
 * Description of ObjectPublisher
 *
 * @author Rob Bosman
 */
class ObjectPublisher {

    public function publish(Schema $schema, Entity $entity, $id, Audit $audit) {
        $this->unpublishObject($schema, $entity, $id);
        return $this->publishObject($schema, $entity, $id, $audit);
    }

    public function unpublishObject(Schema $schema, ObjectEntity $entity, $id) {
        $numUnpublished = 0;
        $mySQLi = $schema->getMySQLi();
        // Find any related objects that are (still) published.
        $ownedObjectRefs = $this->getOwnedObjectRefs($schema, $entity, $id, TRUE);

        if ($entity->getStateIdColumnName() != NULL) {
            // Unpublish the object at hand.
            $entityName = $entity->getName();
            $queryString = "UPDATE " . DbConstants::TABLE_STATE . " s, $entityName e SET"
                    . " s.id_published = NULL"
                    . " WHERE e." . $entity->getObjectIdColumnName() . " = $id"
                    . " AND s.id = e." . $entity->getStateIdColumnName()
                    . " AND s.id_published IS NOT NULL";
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error resetting published state of terminated object '$entityName'[$id]"
                        . " - $mySQLi->error\n<!-\n$queryString\n-->");
            }
            $numUnpublished += $mySQLi->affected_rows;
        }

        // Traverse the object tree and recursively unpublish all related objects.
        if ($ownedObjectRefs != NULL) {
            foreach ($ownedObjectRefs as $ownedObjectRef) {
                $numUnpublished += $this->unpublishObject($schema, $ownedObjectRef->getEntity(),
                        $ownedObjectRef->getId());
            }
        }
        return $numUnpublished;
    }

    private function publishObject(Schema $schema, Entity $entity, $id, Audit $audit) {
        $numPublished = 0;
        $mySQLi = $schema->getMySQLi();
        // Get a set of entities 'owned' by the given one and then fetch the ObjectRefs.
        $ownedObjectRefs = $this->getOwnedObjectRefs($schema, $entity, $id, FALSE);

        // Traverse the object tree and recursively publish all objects.
        if ($ownedObjectRefs != NULL) {
            foreach ($ownedObjectRefs as $ownedObjectRef) {
                $numPublished += $this->publishObject($schema, $ownedObjectRef->getEntity(), $ownedObjectRef->getId(),
                        $audit);
            }
        }

        if ($entity->getStateIdColumnName() != NULL) {
            $entityName = $entity->getName();
            $queryString = "UPDATE " . DbConstants::TABLE_STATE . " s, $entityName e SET"
                    . " s.id_published = " . $audit->getId()
                    . " WHERE s.id = e." . $entity->getStateIdColumnName()
                    . " AND e." . $entity->getObjectIdColumnName() . " = $id"
                    . " AND s.id_published IS NULL"
                    . " AND s.id_terminated IS NULL";
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error publishing '$entityName'[$id] - $mySQLi->error\n<!-\n$queryString\n-->");
            }
            $numPublished += $mySQLi->affected_rows;
        }
        return $numPublished;
    }
    
    private function getOwnedObjectRefs(Schema $schema, ObjectEntity $entity, $id, $isPublished) {
        // Find any related objects that are (still) published.
        // First create a set of entities that are 'owned' by the given one...
        $ownedEntities = array();
        foreach ($entity->getRelationships() as $relationship) {
            if ($relationship->getOwnerEntity() == $entity) {
                $oppositeEntity = $relationship->getOppositeEntity($entity);
                if ($oppositeEntity->isObjectEntity()) {
                    $ownedEntities[] = $oppositeEntity;
                }
            }
        }
        // ...and then fetch the published ObjectRefs.
        if (count($ownedEntities) == 0) {
            return NULL;
        }
        $objectRef = new ObjectRef($entity, $id);
        $params = array(RestUrlParams::PUBLISHED => ($isPublished ? 'true' : 'false'));
        $queryContext = new QueryContext($params, NULL);
        $scope = Scope::parseValue(Scope::VALUE_C_REF . Scope::VALUE_A_REF . Scope::VALUE_O_REF);
        return $objectRef->fetchAllRelatedObjectRefs($schema->getMySQLi(), $ownedEntities, $queryContext, $scope);
    }

}

?>
