<?php

Bootstrap::import('nl.bransom.persistency.ObjectRef');
Bootstrap::import('nl.bransom.persistency.QueryContext');
Bootstrap::import('nl.bransom.persistency.QueryEntity');
Bootstrap::import('nl.bransom.persistency.QueryRelatedEntity');
Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.Property');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.persistency.Scope');
Bootstrap::import('nl.bransom.persistency.XmlConstants');

/**
 * Description of ObjectFetcher
 *
 * @author Rob Bosman
 */
class ObjectFetcher {

    private $schema;
    private $namespaceUri;

    function __construct(Schema $schema, $namespaceUri) {
        $this->schema = $schema;
        $this->namespaceUri = $namespaceUri;
    }

    /**
     * Fetches a set of zero or more objects of the given entity, applying the query parameters to limit the resultset.
     * A root element <set_of_###> is added to the given XML document. For each fetched object a child element is added
     * to this root element.
     * 
     * @param Entity $entity
     * @param array $params
     * @params DOMDocument $domDoc
     * @return DOMElement containing all data that matched the given criteria
     */
    public function getObjectSet(Entity $entity, array $params, DOMDocument $domDoc) {
        $scope = Scope::parseValue(RestUrlParams::extractValue($params, RestUrlParams::SCOPE));
        $skipBinaries = RestUrlParams::parseBoolean(RestUrlParams::extractValue($params, RestUrlParams::SKIP_BINARIES));
        $queryContext = new QueryContext($params, $entity);
        $xmlSet = $domDoc->createElementNS($this->namespaceUri, XmlConstants::SET_OF_ . $entity->getName());
        $xmlElementsWithState = array();
        $this->fetchObjects($entity, NULL, $queryContext, $scope, $skipBinaries, $domDoc, $xmlSet, $xmlElementsWithState);
        $this->setLifecycleTimestamps($xmlElementsWithState);
        $this->setTimestamp($xmlSet, XmlConstants::AT, $queryContext->getParameterAt());
        return $xmlSet;
    }

    /**
     * Fetches a single object of the given entity and id, applying the query parameters to limit the resultset.
     * The fetched data is added as the root element of the given XML document.
     * 
     * @param Entity $entity
     * @param type $id
     * @param array $params
     * @params Scope $scope
     * @params DOMDocument $domDoc
     * @params $skipBinaries
     * @return DOMElement with requested data or NULL if not found
     */
    public function getObjectTree(Entity $entity, $id, array $params, DOMDocument $domDoc) {
        $scope = Scope::parseValue(RestUrlParams::extractValue($params, RestUrlParams::SCOPE));
        $skipBinaries = RestUrlParams::parseBoolean(RestUrlParams::extractValue($params, RestUrlParams::SKIP_BINARIES));
        $queryContext = new QueryContext($params, $entity);
        $xmlElementsWithState = array();
        $this->fetchObjects($entity, $id, $queryContext, $scope, $skipBinaries, $domDoc, $domDoc, $xmlElementsWithState);
        $xmlResult = $domDoc->documentElement;
        if ($xmlResult != NULL) {
            $this->setLifecycleTimestamps($xmlElementsWithState);
            $this->setTimestamp($xmlResult, XmlConstants::AT, $queryContext->getParameterAt());
        }
        return $xmlResult;
    }

    /**
     * Fetches a single property of the given object and returns its plain, unformatted value.
     * 
     * @param Entity $entity
     * @param type $id
     * @param type $propertyName
     * @param array $params
     * @return property value or NULL if not present
     */
    public function getObjectProperty(Entity $entity, $id, $propertyName, array $params) {
        $queryContext = new QueryContext($params, $entity);
        // Always include blobs
        $query = new QueryEntity($entity, $queryContext, Scope::parseValue(Scope::VALUE_P_ALL), $id);
        $query->setPropertyNames(array($propertyName));

        $mySQLi = $this->schema->getMySQLi();
        $queryString = $query->getQueryString();
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching property '$propertyName' of '" . $entity->getName() . "' - "
                    . $mySQLi->error . "\n<!--\n$queryString\n-->");
        }
        $queryData = $queryResult->fetch_assoc();
        $output = NULL;
        if ($queryData) {
            $output = $queryData[$propertyName];
        }
        $queryResult->close();
        return $output;
    }

    public function getPropertyValues(ObjectEntity $entity, $id) {
        // Compose and execute the query.
        $query = new QueryEntity($entity, new QueryContext(array(), $entity), Scope::parseValue('Pcao'), $id);
        $mySQLi = $this->schema->getMySQLi();
        $queryString = $query->getQueryString();
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching all properties of entity '" . $entity->getName() . "'[$id] - "
                    . $mySQLi->error . "\n<!--\n$queryString\n-->");
        }
        // Copy the query result into $properties.
        $propertyValues = array();
        while ($dbObject = $queryResult->fetch_assoc()) {
            foreach ($dbObject as $columnName => $value) {
                if ((strcasecmp($columnName, $entity->getObjectIdColumnName()) == 0)
                        or (strcasecmp($columnName, QueryEntity::ID_PUBLISHED) == 0)
                        or (strcasecmp($columnName, QueryEntity::ID_TERMINATED) == 0)) {
                    continue;
                }
                $property = $entity->getProperty($columnName);
                $propertyValues[$property->getName()] = $this->adjustValueFormat($value, $property->getTypeIndicator());
            }
        }
        $queryResult->close();
        return $propertyValues;
    }

    private function fetchObjects(ObjectEntity $entity, $queryId, QueryContext $queryContext, Scope $defaultScope,
            $skipBinaries, DOMDocument $domDoc, DOMNode $xmlParent, array &$xmlElementsWithState,
            array &$allFetchedObjects = array()) {
        // Compose and execute the query.
        $mySQLi = $this->schema->getMySQLi();
        $query = new QueryEntity($entity, $queryContext, $defaultScope, $queryId, $skipBinaries);
        $queryString = $query->getQueryString();
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching objects of entity '" . $entity->getName() . "' - " . $mySQLi->error
                    . "\n<!--\n$queryString\n-->");
        }

        // Convert the query result into XML.
        while ($dbObject = $queryResult->fetch_assoc()) {
            $id = $dbObject[$entity->getObjectIdColumnName()];
            $objectRef = new ObjectRef($entity, $id);

            // Beware of endless recursion!
            $scope = $this->detectEndlessRecursion($objectRef, $query->getScope(), $allFetchedObjects);

            // Create an XML node with the object id as an attribute and insert the node sorted by entity and id.
            $xmlElement = $domDoc->createElementNS($this->namespaceUri, $entity->getName());
            $xmlElement->setAttribute(XmlConstants::ID, $id);
            $this->sortedInsertNode($xmlParent, $xmlElement);

            // Now determine what else to fetch, depending on the given scope.
            $hasTerminatedObjects = $this->fetchRelatedObjects($objectRef, $queryContext, $scope, $skipBinaries,
                    $domDoc, $xmlElement, $xmlElementsWithState, $allFetchedObjects);

            // Add any object properties to the XML.
            $skipBinaryProperties = $skipBinaries
                    || ($scope->includes(Scope::TAG_PROPERTIES) == Scope::INCLUDES_REFS_ONLY);
            $publishedState = $this->addPropertiesToXML($dbObject, $entity, $skipBinaryProperties, $domDoc, $xmlElement,
                    $xmlElementsWithState);
            if ($hasTerminatedObjects) {
                $publishedState = FALSE;
            }

            // Add a scope-attribute if it is not a reference, if the value is not empty and if it is not updatable.
            if (($xmlElement->childNodes->length > 0) and (strlen($scope->getScopeValue()) > 0)
                    and (!$scope->isUpdatable())) {
                $xmlElement->setAttribute(XmlConstants::SCOPE, $scope->getScopeValue());
            }

            // Notify the published-state to the parent node and its ancestors.
            $this->setPublishedStateAndPropagateToAncestors($xmlElement, $publishedState);
        }
        $queryResult->close();
    }
    
    private function detectEndlessRecursion(ObjectRef $objectRef, Scope $scope, array &$allFetchedObjects) {
        $fetchHash = $objectRef->getEntity()->getName() . "[" . $objectRef->getId() . "]";
        $fetchedScopeValue = NULL;
        if (array_key_exists($fetchHash, $allFetchedObjects)) {
            $fetchedScopeValue = $allFetchedObjects[$fetchHash];
        }
        $allFetchedObjects[$fetchHash] = $scope->removeRecursingScopeValues($fetchedScopeValue);
        return $scope;
    }

    private function fetchRelatedObjects(ObjectRef $givenObjectRef, QueryContext $queryContext, Scope $scope,
            $skipBinaries, DOMDocument $domDoc, DOMNode $xmlElement, array &$xmlElementsWithState,
            array &$allFetchedObjects) {
        $hasTerminatedObjects = FALSE;
        $mySQLi = $this->schema->getMySQLi();
        $givenEntity = $givenObjectRef->getEntity();
        foreach ($givenEntity->getRelationships() as $relationship) {
            $otherEntity = $relationship->getOppositeEntity($givenEntity);
            // Ignore relationships with link-entities.
            if ($otherEntity->isObjectEntity()) {
                // Determine the scope of the related object to be fetched.
                $scopeUnitTag = NULL;
                if ($relationship->getOwnerEntity() == $givenEntity) {
                    $scopeUnitTag = Scope::TAG_COMPONENTS;
                } else if ($relationship->getOwnerEntity() == $otherEntity) {
                    $scopeUnitTag = Scope::TAG_OWNERS;
                } else {
                    $scopeUnitTag = Scope::TAG_ASSOCIATES;
                }
                if ($scope->includes($scopeUnitTag) != Scope::INCLUDES_NONE) {
                    $subScope = $scope->getSubScope($scopeUnitTag);
                    // Fetch ObjectRefs to each related entity...
                    $fetchedObjectRefs = array();
                    $hasTerminatedObjects |= $givenObjectRef->fetchRelatedObjectRefsOfEntity($mySQLi, $otherEntity,
                            $queryContext, $subScope, $fetchedObjectRefs);
                    // ...and then fetch each non-terminated entity.
                    foreach ($fetchedObjectRefs as $fetchedObjectRef) {
                        $this->fetchObjects($fetchedObjectRef->getEntity(), $fetchedObjectRef->getId(), $queryContext,
                                $subScope, $skipBinaries, $domDoc, $xmlElement, $xmlElementsWithState, $allFetchedObjects);
                    }
                }
            }
        }
        return $hasTerminatedObjects;
    }
    
    /**
     * Inserts the node sorted by entity and id to enable easier unit testing.
     *
     * @param DOMNode $xmlParent
     * @param DOMNode $xmlElement 
     */
    private function sortedInsertNode(DOMNode $xmlParent, DOMNode $xmlElement) {
        $xmlElementName = $xmlElement->nodeName;
        $xmlElementId = (int) $xmlElement->getAttribute('id');
        $xmlNextSibling = NULL;
        $siblingIndex = 0;
        while (($siblingIndex < $xmlParent->childNodes->length) && ($xmlNextSibling == NULL)) {
            $xmlSibling = $xmlParent->childNodes->item($siblingIndex);
            if ($xmlSibling->nodeType == XML_ELEMENT_NODE) {
                if ($xmlSibling->nodeName > $xmlElementName) {
                    $xmlNextSibling = $xmlSibling;
                } else if ($xmlSibling->nodeName == $xmlElementName) {
                    $siblingId = $xmlSibling->getAttribute('id');
                    if ((int) $siblingId > $xmlElementId) {
                        $xmlNextSibling = $xmlSibling;
                    }
                }
            }
            $siblingIndex++;
        }
        if ($xmlNextSibling != NULL) {
            $xmlParent->insertBefore($xmlElement, $xmlNextSibling);
        } else {
            $xmlParent->appendChild($xmlElement);
        }
    }

    /**
     *
     * @param type $dbObject
     * @param Entity $entity
     * @param type $skipBinaries
     * @param DOMDocument $domDoc
     * @param DOMElement $xmlElement
     * @param array $xmlElementsWithState 
     * @return Boolean $isPublished = NULL, FALSE or TRUE
     */
    private function addPropertiesToXML($dbObject, Entity $entity, $skipBinaries, DOMDocument $domDoc,
            DOMElement $xmlElement, array &$xmlElementsWithState) {
        $insertBeforeXmlSibling = $xmlElement->childNodes->item(0);
        $stateIdCreated = NULL;
        $stateIdTerminated = NULL;
        $publishedState = NULL;
        foreach ($dbObject as $columnName => $value) {
            if (strcasecmp($columnName, QueryEntity::ID_CREATED) == 0) {
                // Remember the 'created' timestamp for the current object.
                $stateIdCreated = $value;
            } else if (strcasecmp($columnName, QueryEntity::ID_TERMINATED) == 0) {
                // Remember the 'terminated' state for the current object.
                $stateIdTerminated = $value;
            } else if (strcasecmp($columnName, QueryEntity::ID_PUBLISHED) == 0) {
                // Remember the 'published' state of the current object.
                $publishedState = ($value != NULL);
            } else {
                $property = $entity->getProperty($columnName);
                $typeIndicator = $property->getTypeIndicator();
                if ($value != NULL) {
                    // Check if it's a primary or foreign key.
                    $keyIndicator = $property->getKeyIndicator();
                    // Output all properties, except primary and foreign keys.
                    if (($keyIndicator != Property::KEY_PRIMARY) && ($keyIndicator != Property::KEY_FOREIGN)) {
                        $xmlChild = $domDoc->createElementNS($this->namespaceUri, $property->getName());
                        $xmlElement->insertBefore($xmlChild, $insertBeforeXmlSibling);

                        // Adjust the String-format for some types.
                        $value = $this->adjustValueFormat($value, $typeIndicator);
                        // Mark specific data types.
                        if (($typeIndicator != Property::TYPE_TEXT) and ($typeIndicator != Property::TYPE_DOUBLE)
                                 and ($typeIndicator != Property::TYPE_INTEGER)) {
                            $xmlChild->setAttribute(XmlConstants::TYPE, $typeIndicator);
                        }
                        // Add the value.
                        if ($typeIndicator == Property::TYPE_TEXT) {
                            // Text-data must always be wrapped in a CDATA section.
                            $xmlChild->appendChild($domDoc->createCDATASection($value));
                        } else {
                            $xmlChild->appendChild($domDoc->createTextNode($value));
                        }
                    }
                } else if ($typeIndicator === Property::TYPE_BINARY and $skipBinaries) {
                    $xmlChild = $domDoc->createElementNS($this->namespaceUri, $property->getName());
                    $xmlElement->insertBefore($xmlChild, $insertBeforeXmlSibling);
                    $xmlChild->setAttribute(XmlConstants::TYPE, $typeIndicator);
                    // This is a 'suppressed LOB-property', i.e. with scope VALUE_P_REF.
                    $xmlChild->setAttribute(XmlConstants::SCOPE, Scope::VALUE_P_REF);
                }
            }
        }
        // Remember the state ids per xmlElement, to add the 'created' and 'terminated' attributes lateron.
        if ($stateIdCreated != NULL) {
            $xmlElementsWithState[] = array($xmlElement, $stateIdCreated, $stateIdTerminated);
            if ($publishedState == NULL) {
                $publishedState = FALSE;
            }
        }
        return $publishedState;
    }

    private function adjustValueFormat($value, $typeIndicator) {
        // Adjust the String-format for some types.
        if ($typeIndicator == Property::TYPE_TIMESTAMP) {
            // Convert DB-format to XML.
            $value = str_replace(' ', 'T', $value);
        } else if ($typeIndicator == Property::TYPE_BINARY) {
            // Encode LOB-values before adding them to the XML.
            $value = base64_encode($value);
        }
        return $value;
    }

    private function setPublishedStateAndPropagateToAncestors(DOMElement $xmlElement, $publishedState) {
        // Combine the given publishedState of the object with the aggregated state of all 'owned' components, which
        // is stored in the 'published' flag.
        if (($publishedState !== FALSE) and (!$xmlElement->hasAttribute(XmlConstants::PUBLISHED))) {
            $xmlElement->setAttribute(XmlConstants::PUBLISHED, 'true');
            return;
        }
        $publishedFlag = 'false';
        if ($publishedState === FALSE) {
            $xmlElement->setAttribute(XmlConstants::PUBLISHED, $publishedFlag);
        } else {
            $publishedFlag = $xmlElement->getAttribute(XmlConstants::PUBLISHED);
        }
        // If the current element is NOT published, then propagate up the node hierarchy and set all 'published' flags
        // to false. Stop if an existing flag with value 'false' is found.
        if (strcmp($publishedFlag, 'false') == 0) {
            $ancestorXml = $xmlElement->parentNode;
            while ($ancestorXml instanceof DOMElement) {
                if (strcmp($ancestorXml->getAttribute(XmlConstants::PUBLISHED), 'false') == 0) {
                    return;
                }
                $ancestorXml->setAttribute(XmlConstants::PUBLISHED, 'false');
                $ancestorXml = $ancestorXml->parentNode;
            }
        }
    }

    private function setTimestamp(DOMElement $xmlElement, $attributeName, $at, $fetchTimestampIfNull = TRUE) {
        // If no timestamp parameter was specified...
        if (($at == NULL) and ($fetchTimestampIfNull)) {
            // ...then get a timestamp from the database...
            $mySQLi = $this->schema->getMySQLi();
            $queryResult = $mySQLi->query("SELECT NOW() AS at");
            if (!$queryResult) {
                throw new Exception("Error fetching timestamp - " . $mySQLi->error);
            }
            $queryData = $queryResult->fetch_assoc();
            $at = $queryData['at'];
            $queryResult->close();
            // ...and format the DB-data to XML xs:dateTime.
            $at = str_replace(' ', 'T', $at);
        }
        if ($at != NULL) {
            $xmlElement->setAttribute($attributeName, $at);
        }
    }

    private function setLifecycleTimestamps(array &$xmlElementsWithState) {
        if (count($xmlElementsWithState) == 0) {
            return;
        }

        // Gather all state ids. Use the key-field of the array to automatically undouble the set.
        $stateIdAuditMap = array();
        foreach ($xmlElementsWithState as $xmlElementWithState) {
            $stateIdAuditMap[$xmlElementWithState[1]] = NULL;
            if ($xmlElementWithState[2] != NULL) {
                $stateIdAuditMap[$xmlElementWithState[2]] = NULL;
            }
        }

        // Fetch the timestamps of all gathered state objects.
        $queryString = "SELECT au.id, au.at, ac.name"
                . " FROM " . DbConstants::TABLE_AUDIT . " au"
                . " JOIN " . DbConstants::TABLE_ACCOUNT . " ac ON ac.id = au.id_account"
                . " WHERE au.id IN('" . implode("','", array_keys($stateIdAuditMap)) . "')";
        $mySQLi = $this->schema->getMySQLi();
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching timestamps - " . $mySQLi->error . "\n<!--\n$queryString\n-->");
        }
        while ($dbObject = $queryResult->fetch_assoc()) {
            $stateIdAuditMap[$dbObject['id']] = array($dbObject['at'], $dbObject['name']);
        }
        $queryResult->close();

        // Now apply all fetched timestamps.
        foreach ($xmlElementsWithState as $xmlElementWithState) {
            $xmlElement = $xmlElementWithState[0];
            $xmlElement->setAttribute(XmlConstants::CREATED_AT, $stateIdAuditMap[$xmlElementWithState[1]][0]);
            $stateIdTerminated = $xmlElementWithState[2];
            if ($stateIdTerminated != NULL) {
                $xmlElement->setAttribute(XmlConstants::TERMINATED_AT, $stateIdAuditMap[$stateIdTerminated][0]);
            }
        }
    }

}

?>
