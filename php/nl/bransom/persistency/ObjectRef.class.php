<?php

Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');

/**
 * Description of ObjectRef
 *
 * @author Rob Bosman
 */
class ObjectRef {

    private $objectEntity;
    private $id;

    function __construct(ObjectEntity $objectEntity, $id) {
        $this->objectEntity = $objectEntity;
        $this->id = $id;
    }

    public function getEntity() {
        return $this->objectEntity;
    }

    public function getId() {
        return $this->id;
    }

    /*
     * Returns an array with ObjectRefs of all objects of the given entities that are related to self.
     */
    public function fetchAllRelatedObjectRefs(MySQLi $mySQLi, array $otherEntities, QueryContext $queryContext,
            Scope $scope) {
        $fetchedObjectRefs = array();
        foreach ($otherEntities as $otherEntity) {
            $this->fetchRelatedObjectRefsOfEntity($mySQLi, $otherEntity, $queryContext, $scope, $fetchedObjectRefs);
        }
        return $fetchedObjectRefs;
    }

    /*
     * Returns an array with ids of all objects of the given entity that are related to self.
     */
    public function fetchRelatedObjectIdsOfEntity(MySQLi $mySQLi, Entity $otherEntity) {
        $queryContext = new QueryContext(array(), NULL);
        $scope = Scope::parseValue(Scope::VALUE_C_REF . Scope::VALUE_A_REF . Scope::VALUE_O_REF);
        $fetchedObjectIds = array();
        if ($otherEntity->isObjectEntity()) {
            $fetchedObjectRefs = array();
            $this->fetchRelatedObjectRefsOfEntity($mySQLi, $otherEntity, $queryContext, $scope, $fetchedObjectRefs);
            foreach ($fetchedObjectRefs as $fetchedObjectRef) {
                $fetchedObjectIds[] = $fetchedObjectRef->id;
            }
        } else {
            $fetchedObjectIds[] = 0;
        }
        return $fetchedObjectIds;
    }

    /*
     * Fills an array with ObjectRefs of all objects of the given entity that are related to self.
     */
    public function fetchRelatedObjectRefsOfEntity(MySQLi $mySQLi, ObjectEntity $otherEntity,
            QueryContext $queryContext, Scope $scope, array &$fetchedObjectRefs) {
        // Find the corresponding relationship, ignoring link-relationships.
        $hasTerminatedObjects = FALSE;
        foreach ($this->objectEntity->getRelationships() as $relationship) {
            if ($relationship->getOppositeEntity($this->objectEntity) != $otherEntity) {
                continue;
            }
            // Select the id of the related object.
            $objectIdColumnName = $otherEntity->getObjectIdColumnName();
            $query = new QueryRelatedEntity($this->objectEntity, $this->id, $relationship, $queryContext, $scope);
            $query->setPropertyNames(array($objectIdColumnName));
            $queryString = $query->getQueryString();
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error fetching ObjectRefs of entity '" . $otherEntity->getName()
                        . "', associated to '" . $this->objectEntity->getName() . "[$this->id]' - "
                        . $mySQLi->error . "\n<!--\n$queryString\n-->");
            }
            while ($dbObject = $queryResult->fetch_assoc()) {
                $objectRef = new ObjectRef($otherEntity, $dbObject[$objectIdColumnName]);
                if (!$queryContext->getParameterPublished() and isset($dbObject[QueryEntity::ID_TERMINATED])) {
                    $hasTerminatedObjects = TRUE;
                } else {
                    // Always use the objectRef if we're fetching published data or if it's not terminated.
                    $fetchedObjectRefs[] = $objectRef;
                }
            }
            $queryResult->close();
        }
        return $hasTerminatedObjects;
    }

    public function __toString() {
        return $this->objectEntity->getName() . "[$this->id]";
    }
}
?>
