<?php

Bootstrap::import('nl.bransom.Audit');
Bootstrap::import('nl.bransom.persistency.ObjectPublisher');
Bootstrap::import('nl.bransom.persistency.QueryEntity');
Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.Property');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.persistency.Scope');

/**
 * Description of ObjectModifier
 *
 * @author Rob Bosman
 */
class ObjectModifier {

    private $objectPublisher;
    private $defaultQueryContext;

    function  __construct(ObjectPublisher $objectPublisher) {
        $this->objectPublisher = $objectPublisher;
        $this->defaultQueryContext = new QueryContext(array(), NULL);
    }

    /**
     * Inserts a new object in the database and returns the newly created ID.
     *
     * @param Schema $schema
     * @param ObjectEntity $entity
     * @param array $content
     * @param Audit $audit
     * @return persisted id
     */
    public function createObject(Schema $schema, ObjectEntity $entity, array $content, Audit $audit) {
        // Pass a NULL to indicate that a new id must be generated and assigned.
        return $this->insertOrUpdate($schema, $entity, NULL, $content, $audit);
    }

    /**
     *
     * @param Schema $schema
     * @param ObjectEntity $entity
     * @param <type> $id
     * @param array $content
     * @param Audit $audit
     * @return $id if the modification has been persisted, NULL if not.
     */
    public function modifyObject(Schema $schema, ObjectEntity $entity, $id, array $content, Audit $audit) {
        // If this entity stores its state...
        if ($entity->getStateIdColumnName() != NULL) {
            // ...then terminate the old version of the object.
            $numTerminated = $this->terminateObject($schema, $entity, $id, $audit);
            if ($numTerminated <= 0) {
                // Stop if that didn't work.
                throw new Exception("Could not terminate previous version of modified "
                        . $entity->getName() . "[$id] - $mySQLi->error\n<!--\n$queryString\n-->");
            }
        }
        // Ensure that all properties that have not been specified in $content are set to NULL.
        foreach ($entity->getProperties() as $property) {
            // Skip primary keys; they will be set lateron.
            if ($property->getKeyIndicator() != Property::KEY_PRIMARY) {
                $propertyName = $property->getName();
                $isPropertySpecified = FALSE;
                foreach ($content as $contentKey => $value) {
                    if (strcasecmp($contentKey, $propertyName) == 0) {
                        $isPropertySpecified = TRUE;
                        break;
                    }
                }
                if ($isPropertySpecified == FALSE) {
                    $content[$propertyName] = $property->getDefaultValue();
                }
            }
        }
        // Now insert or update the new version.
        return $this->insertOrUpdate($schema, $entity, $id, $content, $audit);
    }

    public function deleteObjectTree(Schema $schema, ObjectEntity $entity, $id, Audit $audit) {
        $numTerminated = 0;

        // Get a set of ObjectRefs to objects that are 'owned' by the given entity and id.
        // Also determine the set of all related link entities.
        $ownedEntitiesWithFk = array();
        $ownedEntitiesWithoutFk = array();
        $ownerEntitiesWithState = array();
        $uncheckedEntities = array();
        foreach ($entity->getRelationships() as $relationship) {
            $otherEntity = $relationship->getOppositeEntity($entity);
            $otherEntityName = $otherEntity->getName();
            // If this is a link relationship...
            if (!$relationship->getFkEntity()->isObjectEntity()) {
                // ...then delete any link object referring to the current (to be deleted) object.
                $propertyValues = array();
                $propertyValues[$relationship->getFkColumnName($entity)] = $id;
                $this->terminateLinks($schema, $relationship->getFkEntity(), $propertyValues, $audit);
            } else if ($relationship->getOwnerEntity() == $entity) {
                if ($relationship->getFkEntity() == $otherEntity) {
                    $ownedEntitiesWithFk[$otherEntityName] = $otherEntity;
                } else {
                    $ownedEntitiesWithoutFk[$otherEntityName] = $otherEntity;
                }
            } else if ($otherEntity->getStateIdColumnName() != NULL) {
                $ownerEntitiesWithState[$otherEntityName] = $otherEntity;
            } else {
                $uncheckedEntities[$otherEntityName] = $otherEntity;
            }
        }

        // Now fetch the ObjectRefs.
        $objectRef = new ObjectRef($entity, $id);
        $scope = Scope::parseValue(Scope::VALUE_C_REF . Scope::VALUE_A_REF);
        $ownedObjectRefs = $objectRef->fetchAllRelatedObjectRefs($schema->getMySQLi(),
                array_merge($ownedEntitiesWithFk, $ownedEntitiesWithoutFk), $this->defaultQueryContext, $scope);
        // If the object supports the 'terminated' state...
        if ($entity->getStateIdColumnName() != NULL) {
            // ...then terminate it.
            $numTerminated += $this->terminateObject($schema, $entity, $id, $audit);
            if ($numTerminated > 0) {
                // Traverse the object tree and recursively delete children.
                foreach ($ownedObjectRefs as $ownedObjectRef) {
                    $numTerminated += $this->deleteObjectTree($schema, $ownedObjectRef->getEntity(),
                            $ownedObjectRef->getId(), $audit);
                }
            }
        } else {
            // The object does not support the 'terminated' state, so it must be deleted permanently.
            // Check if any 'owner-objects-with-state' refer to the given object, either now or in the past.
            if (count($ownerEntitiesWithState) > 0) {
                $queryContext = new QueryContext(array(RestUrlParams::AT => RestUrlParams::ALL_TIMES), NULL);
                $scope = Scope::parseValue(Scope::VALUE_O_REF);
                $ownerObjectRefs = $objectRef->fetchAllRelatedObjectRefs($schema->getMySQLi(), $ownerEntitiesWithState,
                        $queryContext, $scope);
                if (count($ownerObjectRefs) > 0) {
                    // Some terminated owner objects are still referring to this object, so we cannot delete it.
                    return $numTerminated;
                }
            }

            // Split the set of $ownedObjectRefs in two: one set that keeps a foreign key and another that doesn't.
            $ownedObjectRefsWithFk = array();
            $ownedObjectRefsWithoutFk = array();
            foreach ($ownedObjectRefs as $ownedObjectRef) {
                if (array_search($ownedObjectRef->getEntity(), $ownedEntitiesWithFk) !== FALSE) {
                    $ownedObjectRefsWithFk[] = $ownedObjectRef;
                } else {
                    $ownedObjectRefsWithoutFk[] = $ownedObjectRef;
                }
            }

            // First get rid of all owned objects that keep a foreign key to self...
            $numTerminated += $this->deleteAndPurgeObjectTrees($schema, $entity, $id, $ownedObjectRefsWithFk, $audit);

            // ...then delete the object itself.
            // If deleting an _account, then make sure that any reference to it is 'patched'...
            $this->patchAccountRefsIfNecessary($schema, $entity, $id, $audit);
            // ...and then delete the object itself.
            $queryString = "DELETE d FROM " . $entity->getName() . " d"
                    . " WHERE d." . $entity->getObjectIdColumnName() . " = $id";
            $mySQLi = $schema->getMySQLi();
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error deleting objects of entity '" . $entity->getName()
                        . "' - $mySQLi->error\n<!--\n$queryString\n-->");
            }
            $numTerminated += $mySQLi->affected_rows;

            // Finally, after all foreign keys referring to them have been deleted, delete the remaining owned objects.
            $numTerminated += $this->deleteAndPurgeObjectTrees($schema, $entity, $id, $ownedObjectRefsWithoutFk,
                    $audit);
        }

        return $numTerminated;
    }

    private function patchAccountRefsIfNecessary(Schema $schema, ObjectEntity $entity, $id, Audit $audit) {
        // If deleting an _account...
        if (strcasecmp($entity->getName(),  DbConstants::TABLE_ACCOUNT) == 0) {
            // ...then replace the remaining values of _autit.id_account with the id_account of $audit.
            // This is to prevent foreign key constraint errors lateron.
            $queryString = "UPDATE " . DbConstants::TABLE_AUDIT . " SET"
                    . " id_account = " . $audit->getAccountId()
                    . " WHERE id_account = $id";
            $mySQLi = $schema->getMySQLi();
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error patching '" . DbConstants::TABLE_AUDIT
                        . "' - $mySQLi->error\n<!--\n$queryString\n-->");
            }
        }
    }

    private function deleteAndPurgeObjectTrees(Schema $schema, ObjectEntity $entity, $id, $objectRefs, Audit $audit) {
        $numTerminated = 0;
        // Unpublish everything and then terminate all related objects...
        foreach ($objectRefs as $objectRef) {
            $this->objectPublisher->unpublishObject($schema, $objectRef->getEntity(), $objectRef->getId());
            $numTerminated += $this->deleteObjectTree($schema, $objectRef->getEntity(), $objectRef->getId(), $audit);
        }
        // ...and finally purge everything.
        $this->purge($schema, $entity, $id, $audit);
        return $numTerminated;
    }

    public function establishLinks(Schema $schema, LinkEntity $linkEntity, $oneFkColumnName, $oneId,
            $otherFkColumnName, array $otherIds, Audit $audit) {
        // Get the id's of all existing links.
        $scope = Scope::parseValue(Scope::VALUE_P_ALL);
        $query = new QueryEntity($linkEntity, $this->defaultQueryContext, $scope);
        $query->addWhereClause($oneFkColumnName, $oneId);
        $mySQLi = $schema->getMySQLi();
        $queryString = $query->getQueryString();
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching '$otherFkColumnName' properties of link entity "
                    . $linkEntity->getName() . "[$oneId] - $mySQLi->error\n<!--\n$queryString\n-->");
        }
        // Compose a list of otherIds that must be deleted and adjust $otherIds, so it will only contain links that
        // must be created.
        $surplusOtherIds = array();
        while ($dbObject = $queryResult->fetch_assoc()) {
            $existingOtherId = $dbObject[$otherFkColumnName];
            $key = array_search($existingOtherId, $otherIds);
            if ($key !== FALSE) {
                // The link is already there.
                unset($otherIds[$key]);
            } else {
                $surplusOtherIds[] = $existingOtherId;
            }
        }
        $queryResult->close();

        // Delete surplus links.
        if (count($surplusOtherIds) > 0) {
            $propertyValues = array();
            $propertyValues[$oneFkColumnName] = $oneId;
            $propertyValues[$otherFkColumnName] = $surplusOtherIds;
            $this->terminateLinks($schema, $linkEntity, $propertyValues, $audit);
        }

        // Create missing links.
        if (count($otherIds) > 0) {
            $propertyValues = array();
            $propertyValues[$oneFkColumnName] = $oneId;
            foreach ($otherIds as $otherId) {
                // Pass a NULL to indicate that a new link object must be inserted.
                $propertyValues[$otherFkColumnName] = $otherId;
                $this->insertOrUpdate($schema, $linkEntity, NULL, $propertyValues, $audit);
            }
        }
    }

    private function terminateLinks(Schema $schema, LinkEntity $entity, array $propertyValues, Audit $audit) {
        $mySQLi = $schema->getMySQLi();
        $queryString = NULL;
        if ($entity->getStateIdColumnName() != NULL) {
            $queryString = "UPDATE " . DbConstants::TABLE_STATE . " s, " . $entity->getName() . " e SET"
                    . " s.id_terminated = " . $audit->getId()
                    . " WHERE s.id = e." . $entity->getStateIdColumnName()
                    . " AND s.id_terminated IS NULL";
        } else {
            $queryString = "DELETE FROM " . $entity->getName() . " WHERE TRUE";
        }
        foreach ($propertyValues as $propertyName => $value) {
            if (is_array($value)) {
                if (count($value) == 1) {
                    $value = $value[0];
                } else if (count($value) == 0) {
                    throw new Exception("Error terminating link " . $entity->getName()
                            . ": no id's specified for property '$propertyName'.");
                }
            }
            if (is_array($value)) {
                $queryString .= " AND $propertyName IN('" . implode("','", $value) . "')";
            } else {
                $queryString .= " AND $propertyName='$value'";
            }
        }
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error terminating link " . $entity->getName()
                    . " - $mySQLi->error\n<!--\n$queryString\n-->");
        }
        return $mySQLi->affected_rows;
    }

    private function insertOrUpdate(Schema $schema, Entity $entity, $id, array $propertyValues, Audit $audit) {
        $mustInsert = ($id == NULL);
        $mySQLi = $schema->getMySQLi();
        $entityName = $entity->getName();
        $stateIdColumnName = $entity->getStateIdColumnName();
        // Create a state object if the given entity supports states.
        $stateId = NULL;
        if ($stateIdColumnName != NULL) {
            $queryString = "INSERT INTO " . DbConstants::TABLE_STATE . " (id_created) VALUES(" . $audit->getId() . ")";
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error creating state for new object of '$entityName'"
                        . " - $mySQLi->error\n<!--\n$queryString\n-->");
            }
            $stateId = $mySQLi->insert_id;

            // Statefull entities must always be inserted.
            $mustInsert = TRUE;

            // If no id was given...
            if ($id == NULL) {
                // ...then use the (auto-incremented) stateId as id.
                $id = $stateId;
            }
        }

        // Prepare the input parameters for the insert-query.
        $columnNames = array();
        $types = '';
        $values = array();
        // Add the object-id if applicable.
        if ($entity->isObjectEntity()) {
            // Ensure that primary key properties are not specified explicitly.
            foreach ($propertyValues as $propertyName => $value) {
                if ($entity->getProperty($propertyName)->getKeyIndicator() == Property::KEY_PRIMARY) {
                    throw new Exception(
                            "Property '$propertyName' of '$entityName' is a primary key and cannot be changed.");
                }
            }
            // Add the object-id.
            $columnNames[] = $entity->getObjectIdColumnName();
            $types .= 'i';
            $values[] = $id;
        }
        // Add the state-info if applicable.
        if ($stateIdColumnName != NULL) {
            $columnNames[] = $stateIdColumnName;
            $types .= 'i';
            $values[] = $stateId;
        }
        $blobs = array();
        foreach ($propertyValues as $columnName => $value) {
            $property = $entity->getProperty($columnName);
            $columnNames[] = $property->getName();
            $typeIndicator = $property->getTypeIndicator();
            switch ($typeIndicator) {
                case Property::TYPE_TEXT:
                case Property::TYPE_TIMESTAMP:
                    $types .= 's';
                    break;
                case Property::TYPE_BINARY:
                    $types .= 'b';
                    $blobs[count($values)] = $value;
                    $value = NULL;
                    break;
                case Property::TYPE_DOUBLE:
                    $types .= 'd';
                    break;
                case Property::TYPE_INTEGER:
                    $types .= 'i';
                    break;
                default:
                    throw new Exception("Unknown type indicator '$typeIndicator'.");
            }
            $values[] = $value;
        }

        // Compose the query string...
        $queryString = NULL;
        if ($mustInsert) {
            $queryString = "INSERT INTO $entityName (" . implode(',', $columnNames) . ") VALUES(";
            for ($i = 0; $i < count($columnNames); $i++) {
                $queryString .= '?,';
            }
            // Remove the last comma.
            $queryString = substr($queryString, 0, strlen($queryString) - 1);
            $queryString .= ")";
        } else {
            $queryString = "UPDATE $entityName SET ";
            foreach ($columnNames as $columnName) {
                $queryString .= "$columnName=?,";
            }
            // Remove the last comma.
            $queryString = substr($queryString, 0, strlen($queryString) - 1);
            if ($entity->isObjectEntity()) {
                $queryString .= " WHERE " . $entity->getObjectIdColumnName() . " = " . $id;
            }
        }
        // ...and prepare the query.
        $stmt = $mySQLi->prepare($queryString);
        if ($stmt === FALSE) {
            throw new Exception("Error creating prepared statement to insert '$entityName' - "
                    . "$mySQLi->error\n<!--\n$queryString\n-->");
        }
        
        // Don't throw exceptions before closing the prepared statement.
        try {
            // Fill-in the parameters.
            $bindParams = array(&$types);
            for ($i = 0; $i < count($values); $i++) {
                $bindParams[] = &$values[$i];
            }
            call_user_func_array(array($stmt, "bind_param"), $bindParams);
            foreach ($blobs as $index => $value) {
                $stmt->send_long_data($index, base64_decode($value));
            }
            // Execute the query.
            $queryResult = $stmt->execute();
            if (!$queryResult) {
                $formattedPropertyValues = array();
                foreach ($propertyValues as $columnName => $value) {
                    $formattedPropertyValues[] = "$columnName=$value";
                }
                throw new Exception("Error " . ($mustInsert ? 'inserting' : 'updating')
                        . " object '$entityName' - $mySQLi->error\n<!-- " . implode(', ', $formattedPropertyValues)
                        . " -->");
            } else if ($id == NULL) {
                $id = $mySQLi->insert_id;
            }
            return $id;
        } finally {
            // Close the prepared statement before throwing any exception.
            $stmt->close();
        }
    }

    private function terminateObject(Schema $schema, ObjectEntity $entity, $id, Audit $audit) {
        if ($entity->getStateIdColumnName() == NULL) {
            return 0;
        }
        $mySQLi = $schema->getMySQLi();
        $queryString = "UPDATE " . DbConstants::TABLE_STATE . " s, " . $entity->getName() . " e SET"
                . " s.id_terminated = " . $audit->getId()
                . " WHERE s.id = e." . $entity->getStateIdColumnName()
                . " AND s.id_terminated IS NULL"
                . " AND e." . $entity->getObjectIdColumnName() . " = $id";
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error terminating object " . $entity->getName()
                    . "[$id] - $mySQLi->error\n<!--\n$queryString\n-->");
        }
        return $mySQLi->affected_rows;
    }

    public function purge(Schema $schema, Entity $entity, $id, Audit $audit) {
        // Purge entities
        $purgedEntries = 0;
        $purgedEntries += $this->purgeTerminatedObjectsAndLinks($schema, $entity, $id);
        // Purge state and audit objects.
        $purgedEntries += $this->purgeObsoleteStates($schema, array_values($schema->getAllEntities()));
        $purgedEntries += $this->purgeObsoleteAudits($schema, $audit);
        return $purgedEntries;
    }

    /**
     * Removed the given terminated object and all related terminated objects from the database.
     * This is done in two steps: first the complete set of objects-to-be-deleted is composed by recursively walking
     * down the object hierarchy. Then this set of objects is sorted by their entity and deleted. This sorting prevents
     * violating foreign key constraints.
     *
     * @param Schema $schema
     * @param ObjectEntity $entity
     * @param uint $id
     * @return int $numPurged
     */
    private function purgeTerminatedObjectsAndLinks(Schema $schema, ObjectEntity $entity, $id) {
        // Gather all objects-to-be-deleted in a map with a list of id's per object entity. Gather links-to-be-deleted
        // in another map with per link entity a list of fkIds per fkColumnName.
        $objectsVisited = array();
        $objectsToBeDeleted = array();
        $linksToBeDeleted = array();
        $this->fetchTerminatedObjectsAndLinks($schema, $entity, $id, $objectsVisited, $objectsToBeDeleted,
                $linksToBeDeleted);
        $numPurged = 0;
        $numPurged += $this->purgeTerminatedObjects($schema, $objectsToBeDeleted);
        $numPurged += $this->purgeTerminatedLinks($schema, $linksToBeDeleted);
        return $numPurged;
    }

    private function purgeTerminatedObjects(Schema $schema, array $objectsToBeDeleted) {
        $numPurged = 0;
        if (count($objectsToBeDeleted) == 0) {
            return $numPurged;
        }
        // Get a list of all entities (they have been sorted already).
        $sortedEntities = $schema->getObjectEntities();
        // Now delete all fetched ids in the correct order.
        $mySQLi = $schema->getMySQLi();
        foreach ($sortedEntities as $sortedEntity) {
            $sortedEntityName = $sortedEntity->getName();
            if ((array_key_exists($sortedEntityName, $objectsToBeDeleted))
                    and (count($objectsToBeDeleted[$sortedEntityName]) > 0)) {
                $queryString = "DELETE d FROM $sortedEntityName d";
                if ($sortedEntity->getStateIdColumnName() != NULL) {
                    $queryString .= ", " . DbConstants::TABLE_STATE . " s";
                }
                $queryString .= " WHERE d." . $sortedEntity->getObjectIdColumnName()
                        . " IN(" . implode(',', $objectsToBeDeleted[$sortedEntityName]) . ")";
                if ($sortedEntity->getStateIdColumnName() != NULL) {
                    $queryString .= " AND s.id = d." . $sortedEntity->getStateIdColumnName()
                            . " AND s.id_terminated IS NOT NULL";
                }

                // Execute the query.
                $queryResult = $mySQLi->query($queryString);
                if (!$queryResult) {
                    // If things went wrong, then disable the foreign key constraints and try again.
                    $exception = NULL;
                    $mySQLi->query("SET foreign_key_checks = 0");
                    $queryResult = $mySQLi->query($queryString);
                    if (!$queryResult) {
                        $exception = new Exception("Error deleting terminated objects of entity '$sortedEntityName' - "
                            . $mySQLi->error . "\n<!--\n$queryString\n-->");
                    }
                    // Ensure that foreign key constraints are enabled again before throwing exceptions!
                    $mySQLi->query("SET foreign_key_checks = 1");
                    if ($exception != NULL) {
                        throw $exception;
                    }
                }
                $numPurged += $mySQLi->affected_rows;
            }
        }
        return $numPurged;
    }

    private function purgeTerminatedLinks(Schema $schema, array $linksToBeDeleted) {
        $numPurged = 0;
        if (count($linksToBeDeleted) == 0) {
            return $numPurged;
        }

        $mySQLi = $schema->getMySQLi();
        foreach ($linksToBeDeleted as $linkEntityName => $linksOfEntityToBeDeleted) {
            $linkEntity = $schema->getLinkEntity($linkEntityName);
            $whereClauseParts = array();
            foreach ($linksOfEntityToBeDeleted as $fkColumnName => $fkIdsToBeDeleted) {
                $whereClauseParts[] = "d.$fkColumnName IN(" . implode(',', $fkIdsToBeDeleted) . ")";
            }

            $queryString = "DELETE d FROM $linkEntityName d";
            if ($linkEntity->getStateIdColumnName() != NULL) {
                $queryString .= ", " . DbConstants::TABLE_STATE . " s";
            }
            $queryString .= " WHERE TRUE";
            if (count($whereClauseParts) > 0) {
                $queryString .= " AND (" . implode(' OR ', $whereClauseParts) . ")";
            }
            if ($linkEntity->getStateIdColumnName() != NULL) {
                $queryString .= " AND s.id = d." . $linkEntity->getStateIdColumnName()
                        . " AND s.id_terminated IS NOT NULL";
            }

            // Execute the query.
            $queryResult = $mySQLi->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error deleting terminated links of entity '$linkEntityName' - "
                        . "$mySQLi->error\n<!--\n$queryString\n-->");
            }
            $numPurged += $mySQLi->affected_rows;
        }
        return $numPurged;
    }

    /**
     * Recursively fetches all objects-to-be-deleted and puts them in a map with a list of id's per entity.
     * It also fetches links-to-be-deleted in another map with per link entity a list fkIds per fkColumnName.
     *
     * @param Schema $schema
     * @param ObjectEntity $oneEntity
     * @param <type> $id
     * @param array $objectsToBeDeleted [
     *   entityName => [ id, ... ]
     * ]
     * @param array $linksToBeDeleted [
     *   entityName => [
     *     fkColumnName => [ fkId, ... ]
     *   ]
     * ]
     */
    private function fetchTerminatedObjectsAndLinks(Schema $schema, ObjectEntity $oneEntity, $oneId,
            array &$objectsVisited, array &$objectsToBeDeleted, array &$linksToBeDeleted) {
        $mySQLi = $schema->getMySQLi();
        $oneEntityName = $oneEntity->getName();
        // Find any related object that is 'owned' by the current object.
        foreach ($oneEntity->getRelationships() as $relationship) {
            if ($relationship->getOwnerEntity() != $oneEntity) {
                // Do nothing if $oneEntity is not the owner of the other entity.
                continue;
            }
            
            // Get the 'other' entity of the relationship.
            $otherEntity = $relationship->getOppositeEntity($oneEntity);
            $otherEntityName = $otherEntity->getName();

            if (!$otherEntity->isObjectEntity()) {
                // Deal with many-to-many relationships.
                $fkColumnNameOne = $relationship->getFkColumnName($oneEntity);

                // Prepare to add any fetched links to the map.
                if (!array_key_exists($otherEntityName, $linksToBeDeleted)) {
                    $linksToBeDeleted[$otherEntityName] = array();
                }
                $fksOfEntityToBeDeleted = &$linksToBeDeleted[$otherEntityName];

                // Prepare to add the fkId.
                if (!array_key_exists($fkColumnNameOne, $fksOfEntityToBeDeleted)) {
                    $fksOfEntityToBeDeleted[$fkColumnNameOne] = array();
                }
                $fkIdsToBeDeleted = &$fksOfEntityToBeDeleted[$fkColumnNameOne];

                $fkIdsToBeDeleted[] = $oneId;
            } else {
                // Deal with one-to-many relationships.
                $fetchedOtherIds = array();
                // Prepare to add any fetched objects to the map.
                if (!array_key_exists($otherEntityName, $objectsToBeDeleted)) {
                    $objectsToBeDeleted[$otherEntityName] = array();
                }
                $otherIdsToBeDeleted = &$objectsToBeDeleted[$otherEntityName];

                if ($relationship->getFkEntity() == $oneEntity) {
                    // If the object at hand holds the foreign key, then fetch those foreign keys.
                    $fkColumnName = $relationship->getFkColumnName($otherEntity);
                    $oneObjectIdColumnName = $oneEntity->getObjectIdColumnName();
                    $queryString = "SELECT DISTINCT d.$fkColumnName";
                    if ($oneEntity->getStateIdColumnName() != NULL) {
                        $queryString .= ", s.id_terminated";
                    }
                    $queryString .= " FROM $oneEntityName d";
                    if ($oneEntity->getStateIdColumnName() != NULL) {
                        $queryString .= ", " . DbConstants::TABLE_STATE . " s";
                    }
                    $queryString .= " WHERE d.$oneObjectIdColumnName = $oneId";
                    if ($oneEntity->getStateIdColumnName() != NULL) {
                        $queryString .= " AND s.id = d.$oneObjectIdColumnName";
                    }
                    // Execute the query
                    $queryResult = $mySQLi->query($queryString);
                    if (!$queryResult) {
                        throw new Exception("Error fetching foreign key '$fkColumnName' of $oneEntityName"
                                . "[$oneId] - $mySQLi->error\n<!--\n$queryString\n-->");
                    }
                    while ($queryData = $queryResult->fetch_assoc()) {
                        $otherId = $queryData[$fkColumnName];
                        if ($otherId != NULL) {
                            $fetchedOtherIds[] = $otherId;
                            if ($queryData['id_terminated'] != NULL) {
                                $otherIdsToBeDeleted[] = $otherId;
                            }
                        }
                    }
                    $queryResult->close();
                } else {
                    // The related objects hold the foreign keys, so fetch their ids.
                    $fkColumnName = $relationship->getFkColumnName($oneEntity);
                    $otherObjectIdColumnName = $otherEntity->getObjectIdColumnName();
                    $queryString = "SELECT DISTINCT d.$otherObjectIdColumnName";
                    if ($otherEntity->getStateIdColumnName() != NULL) {
                        $queryString .= ", s.id_terminated";
                    }
                    $queryString .= " FROM $otherEntityName d";
                    if ($otherEntity->getStateIdColumnName() != NULL) {
                        $queryString .= ", " . DbConstants::TABLE_STATE . " s";
                    }
                    $queryString .= " WHERE d.$fkColumnName = $oneId";
                    if ($otherEntity->getStateIdColumnName() != NULL) {
                        $queryString .= " AND s.id = d." . $otherEntity->getStateIdColumnName();
                    }
                    // Execute the query
                    $queryResult = $mySQLi->query($queryString);
                    if (!$queryResult) {
                        throw new Exception("Error fetching terminated object ids of entity '$otherEntityName'"
                                . " referring to $oneEntityName" . "[$oneId]"
                                . " - $mySQLi->error\n<!--\n$queryString\n-->");
                    }
                    while ($queryData = $queryResult->fetch_assoc()) {
                        $otherId = $queryData[$otherObjectIdColumnName];
                        if ($otherId != NULL) {
                            $fetchedOtherIds[] = $otherId;
                            if ($queryData['id_terminated'] != NULL) {
                                $otherIdsToBeDeleted[] = $otherId;
                            }
                        }
                    }
                    $queryResult->close();
                }

                // If the other entity is not a link...
                if (count($fetchedOtherIds) > 0 && $relationship->getFkEntity()->isObjectEntity()) {
                    // ...then continue recursing down the object hierarchy.
                    // Keep track of all visited objects to prevent endless recursion.
                    if (!array_key_exists($otherEntityName, $objectsVisited)) {
                        $objectsVisited[$otherEntityName] = array();
                    }
                    $otherIdsVisited = &$objectsVisited[$otherEntityName];
                    foreach ($fetchedOtherIds as $otherId) {
                        if (!in_array($otherId, $otherIdsVisited)) {
                            $otherIdsVisited[] = $otherId;
                            $this->fetchTerminatedObjectsAndLinks($schema, $otherEntity, $otherId, $objectsVisited,
                                    $objectsToBeDeleted, $linksToBeDeleted);
                        }
                    }
                }
            }
        }
    }

    private function purgeObsoleteStates(Schema $schema, array $entities) {
        $mySQLi = $schema->getMySQLi();
        $queryString = "DELETE s FROM " . DbConstants::TABLE_STATE . " s WHERE TRUE";
        foreach ($entities as $entity) {
            if ($entity->getStateIdColumnName() != NULL) {
                $queryString .= " AND NOT EXISTS(SELECT 1 FROM " . $entity->getName() . " e WHERE e."
                        . $entity->getStateIdColumnName() . " = s.id)";
            }
        }
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error purging '" . DbConstants::TABLE_STATE
                    . "' - $mySQLi->error\n<!--\n$queryString\n-->");
        }
        return $mySQLi->affected_rows;
    }

    private function purgeObsoleteAudits(Schema $schema, Audit $audit) {
        $mySQLi = $schema->getMySQLi();
        $queryString = "DELETE a FROM " . DbConstants::TABLE_AUDIT . " a"
            . " WHERE a.id_account IN(1, " . $audit->getAccountId() . ")"
            . " AND NOT EXISTS (SELECT 1 FROM " . DbConstants::TABLE_STATE . " WHERE id_created = a.id)"
            . " AND NOT EXISTS (SELECT 1 FROM " . DbConstants::TABLE_STATE . " WHERE id_published = a.id)"
            . " AND NOT EXISTS (SELECT 1 FROM " . DbConstants::TABLE_STATE . " WHERE id_terminated = a.id)";
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error purging '" . DbConstants::TABLE_AUDIT
                    . "' - $mySQLi->error\n<!--\n$queryString\n-->");
        }
        return $mySQLi->affected_rows;
    }

}

?>
