<?php

Bootstrap::import('nl.bransom.persistency.ObjectFetcher');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.rest.ParsedObject');
Bootstrap::import('nl.bransom.rest.TemporaryIdMap');

/**
 * Description of RestParser
 *
 * @author Rob Bosman
 */
class RestParser {

    private $parsedObjects;
    private $createdObjects;
    private $changedObjects;
    private $deletedObjects;
    private $touchedObjects;
    private $publishedObjects;

    function __construct() {
        $this->parsedObjects = array();
        $this->createdObjects = array();
        $this->changedObjects = array();
        $this->deletedObjects = array();
        $this->touchedObjects = array();
        $this->publishedObjects = array();
    }

    public function getParsedObjects() {
        return $this->parsedObjects;
    }

    public function getCreatedObjects() {
        return $this->createdObjects;
    }

    public function getChangedObjects() {
        return $this->changedObjects;
    }

    public function getChangedAndTouchedObjects() {
        return array_merge($this->changedObjects, $this->touchedObjects);
    }

    public function getDeletedObjects() {
        return $this->deletedObjects;
    }

    public function getPublishedObjects() {
        return $this->publishedObjects;
    }

    public function parse(Schema $schema, array $xmlElements, TemporaryIdMap $temporaryIdMap,
            ObjectFetcher $objectFetcher) {
        // Parse all nodes.
        foreach ($xmlElements as $xmlElement) {
            $this->parseElementTree($schema, $xmlElement, $temporaryIdMap);
        }
    }

    /**
     * XML nodes represent objects that are CREATED, CHANGED or DELETED, depending on the presence or absence of
     * a @deleted-attribute and a persisted or temporary id.
     *   CREATED means that the object has been created in menory and must be persisted (inserted) in the database.
     *   CHANGED means that the object has been modified in menory and that its persistent version in the database must
     *           be updated.
     *   DELETED means that the object has been deleted and that its persistent version must be removed (deleted) from
     *           the database.
     * Nodes without such attribute or id represent either properties of an object, references to related objects or
     * XML place holders. For each object node that has no @delete-attribute but does have child nodes (i.e. it is not
     * a reference), then a default action will be used:
     *   @id attribute absent or empty => CREATED
     *   @id attribute with temporary id => CREATED
     *   @id attribute with persisted id => CHANGED
     * 
     * A node represents an object if its node name equals the name of an ObjectEntity. Only object nodes can have a
     * @delete-attribute. If the modify action is CHANGED or DELETED, then the node *must* have an @id attribute with
     * a known (persisted) id. If the action is CREATED, the node may have an @id attribute with a temporary id. A
     * temporary id will be generated for objects with action CREATED that have no @id attribute.
     * Any object node that is child of a CREATED object node will be considered CREATED if it has no @delete-attribute
     * and if it either has no @id attribute or if the @id attribute contains a temporary id. If such node has an
     * @id attribute with a persisted id, then it will be treated as a reference to an object related to the object of
     * the parent node.
     * 
     * Nodes with names that are not ObjectEntity names will only be ignored if their parent node is not an object node.
     * However, if such node is a child of an object node, then its names *must* correspond with that of a property
     * of the parent object. Equally named sibling nodes are not allowed as children of an object node, since the
     * properties of the object must be uniquely specified.
     * 
     * Names of node and attribute are parsed case-insensitive.
     * Object nodes with a certain id and a non-NULL modify-action may occur only once. Multiple occurrences of the
     * same object is only allowed if a modify-action is specifeid for only one of them.
     * 
     * @param Schema $schema
     * @param DOMElement $xmlElement
     * @param TemporaryIdMap $temporaryIdMap
     */
    private function parseElementTree(Schema $schema, DOMElement $xmlElement, TemporaryIdMap $temporaryIdMap) {
        // Get the entity and its id.
        $entityName = $xmlElement->nodeName;
        $entity = $schema->getObjectEntity($entityName, FALSE);
        $id = $xmlElement->getAttribute(XmlConstants::ID);

        // Determine what to do with this node: create, update or delete the corresponding object or do nothing.
        $modifyingAction = NULL;
        if ($entity != NULL) {
            $modifyingAction = $this->determineModifyingAction($xmlElement, $entity, $id, $temporaryIdMap);
            // If a modify-action was determined for this object...
            if ($modifyingAction != NULL) {
                // ...then beware of multiple object definitions.
                foreach ($this->parsedObjects as $alreadyParsedObject) {
                    if (($alreadyParsedObject->getEntity() == $entity) and ($alreadyParsedObject->getId() == $id)) {
                        throw new Exception("Object '" . $entityName . "[$id]' is specified more than once.",
                                RestResponse::CLIENT_ERROR);
                    }
                }
            }
        }

        // Parse the property nodes and entity (reference) nodes and nested entity nodes. Determine for each node if it
        // represents a property or a related object. The names of property nodes must correspond to property names of
        // the parsed entity. However, entity nodes must always contain (at least) an @id attribute.
        $content = array();
        $childEntityNodes = array();
        for ($i = 0; $i < $xmlElement->childNodes->length; $i++) {
            $childNode = $xmlElement->childNodes->item($i);
            // Only consider 'true' child nodes; skip text nodes.
            if ($childNode->nodeType == XML_ELEMENT_NODE) {
                $parseRecursively = TRUE;
                // If we're parsing the content of an entity node...
                if ($entity != NULL) {
                    // ...then check if the node name equals that of a property of the entity at hand.
                    $propertyName = strtolower($childNode->nodeName);
                    $property = $entity->getProperty($propertyName, FALSE);
                    if ($property != NULL) {
                        // Yes it does, so add the property value to the content map.
                        // However, first verify that properties are specified only once.
                        if (array_key_exists($propertyName, $content)) {
                            throw new Exception("Property '$childNode->nodeName' of entity '" . $entity->getName()
                                    . "' is specified more than once.", RestResponse::CLIENT_ERROR);
                        }
                        $content[$propertyName] = $childNode->nodeValue;
                        $parseRecursively = FALSE;
                    } else {
                        // ...else ceck if the child node describes an entity node.
                        $childEntity = $schema->getObjectEntity($childNode->nodeName, FALSE);
                        if ($childEntity == NULL) {
                            // The child node neither describes a property, nor an entity.
                            // This is an error if we're parsing an entity node.
                            throw new Exception("Unknown property '$childNode->nodeName' of entity '"
                                    . $entity->getName() . "'.", RestResponse::CLIENT_ERROR);
                        }

                        // So the child node is an entity. It can represent an existing object, a newly created object
                        // or a reference. In either case we must create an ObjectRef of it and add that to
                        // $relatedObjectRefs.
                        // The child node must have an @id attribute.
                        $childId = $childNode->getAttribute(XmlConstants::ID);
                        if ($childId == NULL) {
                            // Check if the child node is an object reference.
                            if ($childNode->childNodes->length == 0) {
                                throw new Exception(
                                        "No id was specified for a reference to entity '$childNode->nodeName'.",
                                        RestResponse::CLIENT_ERROR);
                            }
                            // Generate a temporary id for the child node and add it to the XML, so the same id will be
                            // parsed lateron.
                            $childId = TemporaryIdMap::generateTemporaryId();
                            $childNode->setAttribute(XmlConstants::ID, $childId);
                        } else {
                            // Check if the childId has been mapped to a persisted id in an earlier request.
                            $persistedChildId = $temporaryIdMap->getPersistedId($childEntity, $childId, FALSE);
                            if ($persistedChildId != NULL) {
                                // If so, then replace the temporary id with its persisted version.
                                $childId = $persistedChildId;
                                $childNode->setAttribute(XmlConstants::ID, $persistedChildId);
                            }
                        }
                    }
                }
                if ($parseRecursively) {
                    // Add the node to the list of nodes that must be parsed recursively.
                    $childEntityNodes[] = $childNode;
                }
            }
        }

        // Add a ParsedObject to the list if any action must be performed on it.
        $parsedObject = NULL;
        if ($entity != NULL) {
            $parsedObject = new ParsedObject($entity, $id, $content, $modifyingAction,
                    $xmlElement->getAttribute(XmlConstants::SCOPE));
            if ($modifyingAction != NULL) {
                $this->parsedObjects[] = $parsedObject;
            }
            // Mark the object if it must be published.
            if ($xmlElement->hasAttribute(XmlConstants::PUBLISHED)) {
                $this->publishedObjects[] = $parsedObject;
            }
        }

        // Recursively parse the child node(s) and pass the currens ParsedObject as the parent.
        foreach ($childEntityNodes as $childEntityNode) {
            $parsedChildObject = $this->parseElementTree($schema, $childEntityNode, $temporaryIdMap);
            if (($parsedObject != NULL) and ($parsedChildObject != NULL)) {
                // Record the relationship in both directions.
                $parsedObject->addRelatedObject($parsedChildObject);
                $parsedChildObject->addRelatedObject($parsedObject);
            }
        }
        
        return $parsedObject;
    }

    private function determineModifyingAction(DOMElement $xmlElement, ObjectEntity $entity, &$id,
            TemporaryIdMap $temporaryIdMap) {
        $modifyingAction = NULL;
        $persistedId = $temporaryIdMap->getPersistedId($entity, $id, FALSE);
        // If a temporary id was specified that has a 'known' persisted version...
        if (($persistedId != NULL) and (strcasecmp($persistedId, $id) != 0)) {
            // ...then replace the temporary id with that persisted version.
            $id = $persistedId;
            $xmlElement->setAttribute(XmlConstants::ID, $id);
        }

        // Check if it's a reference-only node.
        if (($xmlElement->childNodes->length == 0) and ($id == NULL)) {
            throw new Exception("Reference to '$xmlElement->nodeName' contains no @id attribute.",
                    RestResponse::CLIENT_ERROR);
        }

        if ($xmlElement->hasAttribute(XmlConstants::DELETED) === TRUE) {
            $modifyingAction = ParsedObject::DELETED;
            // Ensure that objects that are to be deleted have a persisted id.
            if ($id == NULL) {
                throw new Exception("Cannot delete '" . $entity->getName() . "' if no @id attribute is specified.",
                        RestResponse::CLIENT_ERROR);
            } else if ($persistedId == NULL) {
                // A temporary, non-persisted object must be deleted.
                throw new Exception("Cannot delete '" . $entity->getName() . "' with non-persisted id '$id'.",
                        RestResponse::CLIENT_ERROR);
            }
        } else if ($xmlElement->childNodes->length > 0) {
            // Check if the id has been mapped to a persisted id in an earlier request.
            if ($persistedId != NULL) {
                $modifyingAction = ParsedObject::CHANGED;
            } else {
                $modifyingAction = ParsedObject::CREATED;
                // Generate a temporary id for new objects if necessary.
                if ($id == NULL) {
                    // If so, then generate a temporary id.
                    $id = TemporaryIdMap::generateTemporaryId();
                    $xmlElement->setAttribute(XmlConstants::ID, $id);
                }
            }
        } else if ($id == NULL) {
            // It's a reference-only node.
            throw new Exception("Reference to '$xmlElement->nodeName' contains no @id attribute.",
                    RestResponse::CLIENT_ERROR);
        }
        
        return $modifyingAction;
    }

    public function applyParsedRelationships() {
        // Configure all relationships: set foreign key properties.
        // Note: all foreign keys that are added to the ParsedObjects are removed from $parsedRelatedObjects.
        // This means that the remaining content of $parsedRelatedObjects will represent only link-relationships
        // (i.e. many-to-many relationships).
        foreach ($this->parsedObjects as $parsedObject) {
            $parsedObject->adjustForeignIdProperties();
        }
    }

    public function groupParsedObjects(Schema $schema, ObjectFetcher $objectFetcher) {
        // Divide the parsed objects in three groups: 'created', 'changed' and 'deleted'.
        foreach ($this->parsedObjects as $parsedObject) {
            if ($parsedObject->getAction() == ParsedObject::CREATED) {
                $this->createdObjects[] = $parsedObject;
            } else if ($parsedObject->getAction() == ParsedObject::CHANGED) {
                $isTouched = FALSE;
                // Check for each CHANGED object if the new data differs from the persisted data. Do this only for
                // objects with a state; objects without state will always be updated, even if they are unchanged.
                if ($parsedObject->getEntity()->getStateIdColumnName() != NULL) {
                    $persistedPropertyValues = $objectFetcher->getPropertyValues($parsedObject->getEntity(),
                            $parsedObject->getId());
                    // If all values are the same, then mark this ParsedObject as 'touched'. Its properties won't be
                    // stored, but any modified relationship will be processed.
                    if ($parsedObject->hasEqualProperties($persistedPropertyValues)) {
                        $this->touchedObjects[] = $parsedObject;
                        $isTouched = TRUE;
                    }
                }
                if (!$isTouched) {
                    $this->changedObjects[] = $parsedObject;
                }
            } else if ($parsedObject->getAction() == ParsedObject::DELETED) {
                $this->deletedObjects[] = $parsedObject;
            }
        }

        // Put all CREATED and DELETED objects in the right order.
        $schema->sortObjects($this->createdObjects, FALSE);
        $schema->sortObjects($this->deletedObjects, TRUE);
    }

    public function detectObsoleteConnections(Schema $schema, ObjectFetcher $objectFetcher) {
        // CHANGED objects can have persisted relationships that don't exist in the parsed dataset.
        // Such persisted relationships must be removed. This implies that either
        //   a. nothing special needs to be done, or
        //   b. persisted connected objects must be 'patched', i.e. their foreign key must be set to NULL, or
        //   c. persisted connected objects must be deleted, or
        //   d. a persisted link (mant-to-many relationship) must be deleted.
        // Option a. is the case when the CHANGED object holds the foreign key and if is NOT an owner of the
        // connected object, or when the connected object is present in the set of ParsedObjects.
        // Option b. applies to connected objects that are not in the set of ParsedObjects. Such connected objects
        // must be added as CHANGED ParsedObjects and filled with all existing property values, except for the
        // foreign key.
        // Option c. applies to situations where the CHANGED object is the owning entity. In this case the existing
        // connected object must be added to the set of ParsedObjects and marked as DELETED.
        // Option d. is similar to option c, but the connected object is a LinkEntity. LinkEntities cannot be referred
        // to by a single id and therefore cannot be dealt with via ParsedObjects. Creating or deleting LinkEntities is
        // done in ParsedObject::establishLinks().
        // 
        // Note that ParsedObjects marked as 'touched' are not modified themselves, but they may have modified
        // relationships. These ParsedObjects must be treadet the same as CHANGED objects here.
        // 
        // So these are the steps to be taken:
        //   1. loop over the CHANGED and 'touched' ParsedObjects
        //   2. per object, loop over the relationships, ignoring many-to-many (link) relationships
        //   3. check if the entity of the object is NOT the foreign key entity in the relationship
        //      and check if the object is owner of the connected object
        //   4. if either is the case, then see if the parsed data specifies a connection for this relationship
        //   5. if NOT, then add the connected object to the set of ParsedObjects
        //   6. if the connected object is NOT an owner, then fetch its id and mark the newly created ParsedObject
        //      as DELETED
        //   7. if the connected object is the foreign key entity, then fetch and set its id and all its properties
        //      (except for the foreign key) and mark the newly created ParsedObject as CHANGED
        foreach ($this->getChangedAndTouchedObjects() as $changedObject) {
            if ($changedObject->getScope()->includes(Scope::TAG_COMPONENTS) == Scope::INCLUDES_NONE) {
                continue;
            }
            $changedEntity = $changedObject->getEntity();
            foreach ($changedEntity->getRelationships() as $relationship) {
                // Ignore LinkEntities, see ParsedObject::establishLinks().
                if ($relationship->getFkEntity()->isObjectEntity()) {
                    $connectedEntity = $relationship->getOppositeEntity($changedEntity);
                    $isFkEntity = ($changedEntity == $relationship->getFkEntity());
                    $isOwnerEntity = ($changedEntity == $relationship->getOwnerEntity());
                    if (($isOwnerEntity) or (!$isFkEntity)) {
                        // Check if the parsed data specifies a (new) connection for this relationship.
                        $parsedConnectedObject = NULL;
                        foreach ($changedObject->getRelatedObjects() as $relatedObject) {
                            if (($relatedObject->getEntity() == $connectedEntity)) {
                                $parsedConnectedObject = $relatedObject;
                                break;
                            }
                        }
                        // Also check the other direction.
                        foreach ($this->parsedObjects as $parsedObject) {
                            if ($parsedConnectedObject != NULL) {
                                break;
                            }
                            if (($parsedObject->getEntity() == $connectedEntity)) {
                                foreach ($parsedObject->getRelatedObjects() as $relatedObject) {
                                    if (($relatedObject->getEntity() == $changedEntity)) {
                                        $parsedConnectedObject = $parsedObject;
                                        break;
                                    }
                                }
                            }
                        }
                        // Only do something if there is no (new) connection for this relationship, so any existing
                        // connection must be deleted.
                        if ($parsedConnectedObject == NULL) {
                            $persistedConnectionIds = NULL;
                            if ($isFkEntity) {
                                $persistedConnectionIds = array();
                                $fkColumnName = $relationship->getFkColumnName($connectedEntity);
                                // Fetch the foreign key.
                                $persistedConnectionId = $objectFetcher->getObjectProperty($changedEntity,
                                        $changedObject->getId(), $fkColumnName, array());
                                if ($persistedConnectionId != NULL) {
                                    $persistedConnectionIds[] = $persistedConnectionId;
                                }
                            } else {
                                // The existing connected object must be patched or deleted.
                                // Fetch the ids of the connected objects.
                                $changedObjectRef = new ObjectRef($changedEntity, $changedObject->getId());
                                $persistedConnectionIds = $changedObjectRef->fetchRelatedObjectIdsOfEntity(
                                        $schema->getMySQLi(), $connectedEntity);
                            }
                            foreach ($persistedConnectionIds as $persistedConnectionId) {
                                // Check if the existing connected object is already specified in the current
                                // transaction.
                                $alreadyParsedObject = NULL;
                                foreach ($this->parsedObjects as $parsedObject) {
                                    if (($parsedObject->getEntity() == $connectedEntity)
                                            and ($parsedObject->getId() == $persistedConnectionId)) {
                                        $alreadyParsedObject = $parsedObject;
                                        break;
                                    }
                                }
                                // Do nothing if the existing connected object is already present in the set of
                                // ParsedObjects.
                                if ($alreadyParsedObject != NULL) {
                                    continue;
                                }
                                if ($isOwnerEntity) {
                                    $deletedObject = new ParsedObject($connectedEntity, $persistedConnectionId,
                                            array(), ParsedObject::DELETED);
                                    $this->deletedObjects[] = $deletedObject;
                                } else {
                                    $propertyValues = $objectFetcher->getPropertyValues($connectedEntity,
                                            $persistedConnectionId);
                                    $propertyValues[$relationship->getFkColumnName($changedEntity)] = NULL;
                                    $additionalChangedObject = new ParsedObject($connectedEntity,
                                            $persistedConnectionId, $propertyValues, ParsedObject::CHANGED);
                                    $additionalChangedObject->adjustForeignIdProperties();
                                    $this->changedObjects[] = $additionalChangedObject;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

?>