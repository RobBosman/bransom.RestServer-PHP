<?php

Bootstrap::import('nl.bransom.persistency.ObjectRef');
Bootstrap::import('nl.bransom.persistency.Scope');
Bootstrap::import('nl.bransom.persistency.XmlConstants');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.rest.RestUrlParams');
Bootstrap::import('nl.bransom.rest.RestResponse');
Bootstrap::import('nl.bransom.rest.TemporaryIdMap');

/**
 * Description of ParsedObject
 *
 * @author Rob Bosman
 */
class ParsedObject {

    const CREATED = 'CREATED';
    const CHANGED = 'CHANGED';
    const DELETED = 'DELETED';

    const DEFAULT_SCOPE = 'Pca';

    private $entity;
    private $id;
    private $propertyValues;
    private $modifyingAction;
    private $scope;
    private $relatedObjects;
    private $key;

    function __construct(ObjectEntity $entity, $id, array $propertyValues, $modifyingAction, $scopeValue = NULL) {
        $this->entity = $entity;
        $this->id = $id;
        $this->propertyValues = $propertyValues;
        $this->modifyingAction = $modifyingAction;
        if ($scopeValue == NULL) {
            $scopeValue = self::DEFAULT_SCOPE;
        }
        $this->scope = Scope::parseValue($scopeValue);
        $this->relatedObjects = array();
        $this->key = $this->entity->getName() . "[$this->id]";
    }

    public function getEntity() {
        return $this->entity;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        if ($id == $this->id) {
            return;
        }
        if (TemporaryIdMap::isPersistedId($this->id)) {
            throw new Exception("Cannot set the id of persisted object $this->key to '$id'.",
                    RestResponse::CLIENT_ERROR);
        }
        if (!TemporaryIdMap::isPersistedId($id)) {
            throw new Exception("Cannot set temporary id '$id' to $this->key.", RestResponse::CLIENT_ERROR);
        }
        $this->id = $id;
    }

    public function getPropertyValues() {
        return $this->propertyValues;
    }

    public function getAction() {
        return $this->modifyingAction;
    }

    public function getScope() {
        return $this->scope;
    }

    public function addRelatedObject(ParsedObject $relatedObject) {
        $this->relatedObjects[$relatedObject->key] = $relatedObject;
    }

    private function removeRelatedObject(ParsedObject $otherObject) {
        unset($this->relatedObjects[$otherObject->key]);
    }

    public function getRelatedObjects() {
        return $this->relatedObjects;
    }

    public function hasEqualProperties(array $otherPropertyValues) {
        $properties = $this->entity->getProperties();
        foreach ($otherPropertyValues as $propertyName => $otherValue) {
            $property = $this->entity->getProperty($propertyName);
            if ($propertyName == $this->entity->getStateIdColumnName()) {
                // Ignore property 'id_state'.
            } else if ($propertyName == $this->entity->getObjectIdColumnName()) {
                // Ignore property 'id_object', unless it is not equal to the id of self.
                if ($otherValue != $this->id) {
                    throw new Exception("Cannot compare properties of " . $this->entity->getName()
                            . "[$otherValue]; expected [$this->id].");
                }
            } else if (array_key_exists($propertyName, $this->propertyValues)) {
                // Ignore empty values.
                $thisValue = $this->propertyValues[$propertyName];
                if ((($otherValue == NULL) and ($thisValue != NULL))
                        or (($otherValue != NULL) and ($thisValue == NULL))
                        or (($otherValue != NULL) and (strcmp($thisValue, $otherValue) != 0))) {
                    // If this is a lob-property...
                    if ($property->getTypeIndicator() == Property::TYPE_BINARY) {
                        // ...then ignore the difference if this is a suppressed lob-property (if a value is present
                        // but NULL).
                        if ($thisValue != NULL) {
                            // This is not a suppressed lob-value, so compare the base64-encoded values.
                            if (strcmp(base64_decode($thisValue), base64_decode($otherValue)) != 0) {
                                return FALSE;
                            }
                        }
                    } else {
                        // Found a mismatching property value.
                        return FALSE;
                    }
                }
            } else if ($otherValue != NULL) {
                // Now here's a mismatch: a property that is present in other, but not in self.
                return FALSE;
            }
        }
        // Finally check if there are properties present in self that are absent in other.
        foreach ($this->propertyValues as $propertyName=> $thisValue) {
            if (!array_key_exists($propertyName, $otherPropertyValues)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    public function adjustForeignIdProperties() {
        foreach ($this->entity->getRelationships() as $relationship) {
            if ($relationship->getFkEntity() == $this->entity) {
                // This is a relationship of which 'self' maintains the foreign key.
                // Now get the id of that foreign key and add it to the properties.
                $foreignEntity = $relationship->getOppositeEntity($this->entity);
                $fkColumnName = $relationship->getFkColumnName($foreignEntity);
                $newForeignId = $this->getRelatedObjectId($foreignEntity);
                if ($newForeignId != NULL) {
                    // If the foreign key has already been set...
                    if (array_key_exists($fkColumnName, $this->propertyValues)) {
                        $oldForeignId = $this->propertyValues[$fkColumnName];
                        // ...then check if it equals the new value.
                        if (($oldForeignId != NULL) and (strcasecmp($oldForeignId, $newForeignId) != 0)) {
                            $foreignEntityName = $foreignEntity->getName();
                            throw new Exception("Incorrect relationship: $this->key.$fkColumnName already refers to "
                                    . $foreignEntityName . "[$oldForeignId] and cannot simultaneously refer to "
                                    . $foreignEntityName . "[$newForeignId].", RestResponse::CLIENT_ERROR);
                        }
                    } else {
                        // ...else just set it.
                        $this->propertyValues[$fkColumnName] = $newForeignId;
                    }
                }
            }
        }
    }
    
    private function getRelatedObjectId(ObjectEntity $foreignEntity) {
        $foreignId = NULL;
        foreach ($this->relatedObjects as $key => $relatedObject) {
            if ($foreignEntity == $relatedObject->entity) {
                if (($foreignId != NULL) and ($relatedObject->id != $foreignId)) {
                    throw new Exception("Error in relationship between '" . $this->entity->getName() . "' and '"
                            . $foreignEntity->getName()
                            . "'; expected one id, but got [$foreignId] and [$relatedObject->id].",
                            RestResponse::CLIENT_ERROR);
                }
                $foreignId = $relatedObject->id;
            }
        }
        return $foreignId;
    }
    
    public function establishLinks(Schema $schema, ObjectModifier $objectModifier, Audit $audit) {
        if ($this->scope->includes(Scope::TAG_ASSOCIATES) == Scope::INCLUDES_NONE) {
            return;
        }
        // Create and/or delete all remaining relationships.
        // Group all relationships per relatedEntity.
        $relatedEntityObjectsMap = array();
        foreach ($this->relatedObjects as $relatedObject) {
            $relatedEntityName = $relatedObject->entity->getName();
            if (!array_key_exists($relatedEntityName, $relatedEntityObjectsMap)) {
                $relatedEntityObjectsMap[$relatedEntityName] = array();
            }
            $relatedEntityObjectsMap[$relatedEntityName][] = $relatedObject;
        }
        // Now add or delete links corresponding to the specifoed relatedObjects.
        $processedLinkRelationships = array();
        foreach ($relatedEntityObjectsMap as $relatedEntityName => $relatedObjects) {
            $linkRelationship = $schema->getLinkRelationship($this->entity,
                    $schema->getObjectEntity($relatedEntityName), FALSE);
            if ($linkRelationship != NULL) {
                $otherIds = array();
                foreach ($relatedObjects as $relatedObject) {
                    $otherIds[] = $relatedObject->id;
                }
                $objectModifier->establishLinks($schema, $linkRelationship->getFkEntity(),
                        $linkRelationship->getFkColumnName($this->entity), $this->id,
                        $linkRelationship->getFkColumnName($relatedObject->entity), $otherIds, $audit);
                // Remove the reversed direction of the links, so they won't be processed twice.
                foreach ($relatedObjects as $relatedObject) {
                    $relatedObject->removeRelatedObject($this);
                }
                // Keep track of all processed link-relationships.
                $processedLinkRelationships[] = $linkRelationship;
            }
        }

        // Delete any link that was NOT specified in the ParsedObject.
        foreach ($this->entity->getRelationships() as $relationship) {
            $oppositeEntity = $relationship->getOppositeEntity($this->entity);
            if ((!$relationship->getFkEntity()->isObjectEntity())
                    and ($oppositeEntity->isObjectEntity())
                    and (array_search($relationship, $processedLinkRelationships) === FALSE)) {
                // Call establishLinks() with an empty list of referring id's, so any persisted link will be deleted.
                $objectModifier->establishLinks($schema, $relationship->getFkEntity(),
                        $relationship->getFkColumnName($this->entity), $this->id,
                        $relationship->getFkColumnName($oppositeEntity), array(), $audit);
            }
        }
    }

    /**
     * Checks each foreign key property if it contains a temporary id and substitutes it with its persisted value,
     * if that is available in $temporaryIdMap.
     *
     * @param TemporaryIdMap $temporaryIdMap
     */
    public function substituteTemporaryIds(TemporaryIdMap $temporaryIdMap) {
        // For each relationship...
        foreach ($this->entity->getRelationships() as $relationship) {
            // ...of which I am the holder of the foreign key...
            if ($relationship->getFkEntity() == $this->entity) {
                $foreignEntity = $relationship->getOppositeEntity($this->entity);
                $fkName = $relationship->getFkColumnName($foreignEntity);
                // ...and who's foreign key property is present in the set of values...
                if (array_key_exists($fkName, $this->propertyValues)) {
                    // ...check if it contains a temporary key.
                    $persistedId = $temporaryIdMap->getPersistedId($foreignEntity, $this->propertyValues[$fkName],
                            FALSE);
                    if ($persistedId != NULL) {
                        // If so, then substitute it!
                        $this->propertyValues[$fkName] = $persistedId;
                    }
                }
            }
        }
    }
}

?>