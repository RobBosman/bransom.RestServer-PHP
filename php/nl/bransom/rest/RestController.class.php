<?php

Bootstrap::import('nl.bransom.auth.AuthHandler');
Bootstrap::import('nl.bransom.persistency.ObjectFetcher');
Bootstrap::import('nl.bransom.persistency.ObjectModifier');
Bootstrap::import('nl.bransom.persistency.ObjectPublisher');
Bootstrap::import('nl.bransom.persistency.PersistentAccount');
Bootstrap::import('nl.bransom.persistency.PersistentAudit');
Bootstrap::import('nl.bransom.persistency.QueryEntity');
Bootstrap::import('nl.bransom.persistency.meta.MetaData');
Bootstrap::import('nl.bransom.persistency.meta.ObjectEntity');
Bootstrap::import('nl.bransom.persistency.meta.Schema');
Bootstrap::import('nl.bransom.rest.ParsedObject');
Bootstrap::import('nl.bransom.rest.RestParser');
Bootstrap::import('nl.bransom.rest.RestResponse');
Bootstrap::import('nl.bransom.rest.RestUrlParams');
Bootstrap::import('nl.bransom.rest.TemporaryIdMap');

Bootstrap::startSession();

/**
 * Description of RestController
 *
 * @author Rob Bosman
 */
class RestController {

    private $metaData;
    private $domImplementation;
    private $appNameObjectFetcherMap;
    private $objectPublisher;
    private $objectModifier;

    private $params;
    private $sessionId;
    private $temporaryIdMap;

    function __construct(array &$params) {
        $this->metaData = MetaData::getInstance();
        $this->domImplementation = new DOMImplementation();
        $this->appNameObjectFetcherMap = array();
        $this->objectPublisher = new ObjectPublisher();
        $this->objectModifier = new ObjectModifier($this->objectPublisher);

        $this->params = $params;
        $this->sessionId = RestUrlParams::extractValue($this->params, RestUrlParams::SESSION_ID);
    }

    public function process($method, array $target, $content = NULL, $jwt = NULL) {
        try {
            // First check if you're signed-in.
            if (count($target) > 0 && AuthHandler::getSignedInAccountId($target[0], $jwt) == NULL) {
                $contextRoot = $this->metaData->getContextRoot($target[0]);
                $msg = ($jwt != NULL) ?
                    "Sorry, your access token has expired. Please <a href='/$contextRoot'>login</a> again." :
                    "You are not signed-in! Authentication is required to access data, please <a href='/$contextRoot'>login</a> first.";
                throw new Exception($msg, RestResponse::AUTH_ERROR);
            }

            // Now go do something!
            $restResponse = $this->dispatch($method, $target, $content);
        } catch (Exception $e) {
            Bootstrap::logException($e);
            $responseCode = RestResponse::SERVER_ERROR;
            if ($e->getCode() != 0) {
                $responseCode = $e->getCode();
            }
            $restResponse = new RestResponse($responseCode, $e);
        }
        return $restResponse;
    }

    private function getObjectFetcher($appName) {
        if (array_key_exists($appName, $this->appNameObjectFetcherMap)) {
            return $this->appNameObjectFetcherMap[$appName];
        } else {
            $objectFetcher = new ObjectFetcher($this->metaData->getSchema($appName),
                    $this->metaData->getNamespaceUri($appName));
            $this->appNameObjectFetcherMap[$appName] = $objectFetcher;
            return $objectFetcher;
        }
    }

    private function dispatch($method, array $target, $content) {
        $numTargetParts = count($target);

        // Now go do something.
        switch ($method) {
            case 'PUT':
                switch ($numTargetParts) {
                    case 2:
                        // .../webitems/itemset/
                        if (is_array($content)) {
                            return $this->createObject($target[0], $target[1], $content);
                        } else if ($content instanceof DOMDocument) {
                            return $this->createXmlObject($target[0], $target[1], $content->documentElement);
                        } else {
                            throw new Exception("Content must be a DOMDocument or an array.",
                                    RestResponse::CLIENT_ERROR);
                        }
                    default:
                        throw new Exception("Unsupported number of REST target parts: $numTargetParts ("
                                . implode('/', $target) . ")", RestResponse::CLIENT_ERROR);
                }
            case 'GET':
                switch ($numTargetParts) {
                    case 0:
                        // .../
                        return $this->readMetaData();
                    case 1:
                        // .../webitems/
                        return $this->readSchema($target[0]);
                    case 2:
                        // .../webitems/itemset/
                        return $this->readObjectSet($target[0], $target[1]);
                    case 3:
                        // .../webitems/itemset/1
                        return $this->readObjectTree($target[0], $target[1], $target[2]);
                    case 4:
                        // .../webitems/itemset/1/name
                        return $this->readObjectProperty($target[0], $target[1], $target[2], $target[3]);
                    default:
                        throw new Exception("Unsupported number of REST target parts: $numTargetParts ("
                                . implode('/', $target) . ")", RestResponse::CLIENT_ERROR);
                }
            case 'POST':
                switch ($numTargetParts) {
                    case 1:
                        // .../webitems/
                        if ($content instanceof DOMDocument) {
                            return $this->processXmlObjects($target[0], array($content->documentElement),
                                    RestResponse::UPDATED);
                        } else {
                            throw new Exception("Content must be a DOMDocument.",
                                    RestResponse::CLIENT_ERROR);
                        }
                    case 3:
                        // .../webitems/itemset/1
                        if (is_array($content)) {
                            return $this->updateObject($target[0], $target[1], $target[2], $content);
                        } else if ($content instanceof DOMDocument) {
                            return $this->updateXmlObject($target[0], $target[1], $target[2],
                                    $content->documentElement);
                        } else {
                            throw new Exception("Content must be a DOMDocument or an array.",
                                    RestResponse::CLIENT_ERROR);
                        }
                    default:
                        throw new Exception("Unsupported number of REST target parts: $numTargetParts ("
                                . implode('/', $target) . ")", RestResponse::CLIENT_ERROR);
                }
            case 'DELETE':
                switch ($numTargetParts) {
                    case 3:
                        // .../webitems/itemset/1
                        return $this->deleteObject($target[0], $target[1], $target[2]);
                    default:
                        throw new Exception("Unsupported number of REST target parts: $numTargetParts ("
                                . implode('/', $target) . ")", RestResponse::CLIENT_ERROR);
                }
            default:
                throw new Exception("Unsupported HTTP-method: '$method'", RestResponse::CLIENT_ERROR);
        }
    }

    /**
     * Delegates to self::processXmlObjects()
     *
     * @param type $appName
     * @param type $entityName
     * @param array $content
     * @return RestResponse 
     */
    protected function createObject($appName, $entityName, array $content) {
        $domDoc = $this->domImplementation->createDocument();
        $xmlElement = $domDoc->createElement($entityName);
        foreach ($content as $propertyName => $value) {
            $xmlElement->appendChild($domDoc->createElement($propertyName, $value));
        }
        return $this->processXmlObjects($appName, array($xmlElement), RestResponse::CREATED);
    }

    /**
     * Delegates to self::processXmlObjects()
     *
     * @param <type> $appName
     * @param <type> $entityName
     * @param DOMElement $xmlElement
     * @return <type>
     */
    private function createXmlObject($appName, $entityName, DOMElement $xmlElement) {
        // Check if the node name corresponds to the object name obtained from the URL.
        if (strtolower($xmlElement->nodeName) != strtolower($entityName)) {
            throw new Exception("Expected node '$entityName', but found '" . $xmlElement->nodeName . "'.",
                    RestResponse::CLIENT_ERROR);
        }
        return $this->processXmlObjects($appName, array($xmlElement), RestResponse::CREATED);
    }

    private function readMetaData() {
        // Read all app names.
        $result = "REST apps:";
        foreach ($this->metaData->getAppNames() as $appName) {
            $result .= " <a href='$appName'>$appName</a>";
        }
        return new RestResponse(RestResponse::OK, $result, 'html');
    }

    private function readSchema($appName) {
        // Read all entities of the given schema.
        $schema = $this->metaData->getSchema($appName);
        $result = "Entities of REST app '$appName':";
        foreach ($schema->getObjectEntities() as $entity) {
            $entityName = $entity->getName();
            $url = $appName . '/' . $entityName . '?$scope=';
            $result .= "<br/><a href='$url'>$entityName</a>";
            foreach ($entity->getProperties() as $property) {
                if (($property->getKeyIndicator() != Property::KEY_PRIMARY)
                        and ($property->getKeyIndicator() != Property::KEY_FOREIGN)) {
                    $result .= "<br/>&nbsp;&nbsp;" . $property->getName() . " [" . $property->getTypeIndicator() . "]";
                }
            }
        }
        return new RestResponse(RestResponse::OK, $result, 'html');
    }

    private function readObjectSet($appName, $entityName) {
        $schema = $this->metaData->getSchema($appName);
        $entity = $schema->getObjectEntity($entityName);
        $this->processQueryParams($schema, $entity, TRUE);
        $objectFetcher = $this->getObjectFetcher($appName);
        // Read all objects of the given entity.
        $domDoc = $this->domImplementation->createDocument();
        $xmlSet = $objectFetcher->getObjectSet($entity, $this->params, $domDoc);
        $domDoc->appendChild($xmlSet);
        return new RestResponse(RestResponse::OK, $domDoc);
    }

    private function readObjectTree($appName, $entityName, $id) {
        $schema = $this->metaData->getSchema($appName);
        $entity = $schema->getObjectEntity($entityName);
        $this->processQueryParams($schema, $entity, TRUE);
        $persistedId = $this->temporaryIdMap->getPersistedId($entity, $id);
        $objectFetcher = $this->getObjectFetcher($appName);
        // Read one object from the given entities.
        $domDoc = $this->domImplementation->createDocument();
        $xmlResult = $objectFetcher->getObjectTree($entity, $persistedId, $this->params, $domDoc);
        if ($xmlResult != NULL) {
            $domDoc->appendChild($xmlResult);
            return new RestResponse(RestResponse::OK, $domDoc);
        } else {
            return new RestResponse(RestResponse::NOT_FOUND);
        }
    }

    private function readObjectProperty($appName, $entityName, $id, $propertyName) {
        $schema = $this->metaData->getSchema($appName);
        $entity = $schema->getObjectEntity($entityName);
        $this->processQueryParams($schema, $entity, TRUE);
        $persistedId = $this->temporaryIdMap->getPersistedId($entity, $id);
        $objectFetcher = $this->getObjectFetcher($appName);
        // Read one property of an object from the given entity.
        $result = $objectFetcher->getObjectProperty($entity, $persistedId, $propertyName, $this->params);
        if ($result != NULL) {
            return new RestResponse(RestResponse::OK, $result);
        } else {
            return new RestResponse(RestResponse::NOT_FOUND);
        }
    }

    /**
     * Delegates to self::processXmlObjects()
     *
     * @param <type> $appName
     * @param <type> $entityName
     * @param <type> $id
     * @param <type> $content
     * @return RestResponse
     */
    private function updateObject($appName, $entityName, $id, $content) {
        $domDoc = $this->domImplementation->createDocument();
        $xmlElement = $domDoc->createElement($entityName);
        $xmlElement->setAttribute(XmlConstants::ID, $id);
        foreach ($content as $propertyName => $value) {
            $xmlElement->appendChild($domDoc->createElement($propertyName, $value));
        }
        return $this->processXmlObjects($appName, array($xmlElement), RestResponse::UPDATED);
    }

    /**
     * Delegates to self::processXmlObjects()
     *
     * @param <type> $appName
     * @param <type> $entityName
     * @param <type> $id
     * @param DOMElement $xmlElement
     * @return <type>
     */
    private function updateXmlObject($appName, $entityName, $id, DOMElement $xmlElement) {
        // Check if the node name corresponds to the object name obtained from the URL.
        if (strtolower($xmlElement->nodeName) != strtolower($entityName)) {
            throw new Exception("Expected node '$entityName', but found '" . $xmlElement->nodeName . "'.",
                    RestResponse::CLIENT_ERROR);
        }
        if ($id != NULL) {
            // Check if the id of the xmlElement corresponds to the object id obtained from the URL.
            $idFromXml = $xmlElement->getAttribute(XmlConstants::ID);
            if (($idFromXml == NULL) and ($id != NULL) and ($idFromXml != $id)) {
                throw new Exception("Expected @id to be absent or @id='$id' on root node, but found @id='$idFromXml'.",
                        RestResponse::CLIENT_ERROR);
            }
        }
        return $this->processXmlObjects($appName, array($xmlElement), RestResponse::UPDATED);
    }

    private function deleteObject($appName, $entityName, $id) {
        $domDoc = $this->domImplementation->createDocument();
        $xmlElement = $domDoc->createElement($entityName);
        $xmlElement->setAttribute(XmlConstants::ID, $id);
        $xmlElement->setAttribute(XmlConstants::DELETED, 'true');
        return $this->processXmlObjects($appName, array($xmlElement), RestResponse::DELETED);
    }

    /**
     * Accepts a list of XML nodes, each representing an object that must be either created, updated or deleted,
     * depending on the presence of respectively a @created or @deleted attribute. The node names must be equal to that
     * of an entity and the names of the child nodes must be equal to the property names applicable to that entity.
     * Node names are compared case-insensitive.
     *
     * Special attributes are
     *     deleted - Change-index indicating that this is an existing (persisted) object that must be deleted.
     *     id - Object identifier (primary key).
     *
     * Every object-node must have an @id, either permanent (numerical value) or temporary (starting with a non-digit).
     * Temporary ID's will be substituted with the permanent (persisted) ID lateron.
     *
     * Child nodes who's name equals that of an entity are NOT recursively parsed and processed. Only their @id
     * attribute is used to add or update foreign keys.
     *
     * RETURN
     * A map of (temporary and) permanent id's per entity is returned. This map enables the caller to substitute
     * temporary id's at the client side.
     *
     * ERROR
     * It is an error if the name of a given node doesn't match that of an entity, or if the names of child nodes
     * don't match that of properties of the entity.
     *
     * HOW IT WORKS
     * The nodes are scanned recursively (so $xmlElements can be a 'flat' list of object nodes or a nested xml tree or
     * a combination of both) and grouped in three arrays, one with nodes of objects that must be created, changed or
     * deleted. The 'created' group is processed first. The nodes in this group are sorted based on their entity, so the
     * order of creation allows for substituting any foreign key of successively created objects. This is to prevent
     * violating foreign key constraints.
     * Then the 'changed' group is processed. Foreign keys are substitued before updating each object in the database.
     * Foreign keys of objects that are in the 'deleted' group will be set to NULL.
     * Finally the objects in the 'deleted' group are deleted.
     */
    private function processXmlObjects($appName, array $xmlElements, $restResponseCode) {
        $schema = $this->metaData->getSchema($appName);
        $this->processQueryParams($schema, NULL, FALSE);
        $mySQLi = $schema->getMySQLi();
        try {
            $account = PersistentAccount::getAccount($schema, AuthHandler::getSignedInAccountId($appName));
            $audit = new PersistentAudit($schema, $account);
            $objectFetcher = $this->getObjectFetcher($appName);
            $restParser = new RestParser();

            // Parse all nodes...
            $restParser->parse($schema, $xmlElements, $this->temporaryIdMap, $objectFetcher);
            // ...set the foreign key id's...
            $restParser->applyParsedRelationships();
            // ...and divide the ParsedObjects in groups (CREATED, CHANGED, DELETED).
            $restParser->groupParsedObjects($schema, $objectFetcher);

            // Any CHANGED object can have existing (persisted) relationships that don't exist in the parsed dataset.
            // Detect these now.
            $restParser->detectObsoleteConnections($schema, $objectFetcher);

            // Create all CREATED objects.
            foreach ($restParser->getCreatedObjects() as $createdObject) {
                $entity = $createdObject->getEntity();
                // Note: the id of a $createdParsedObject is always a temporary id.
                $temporaryId = $createdObject->getId();

                // Substitute any temporary id's in foreign key properties.
                $createdObject->substituteTemporaryIds($this->temporaryIdMap);

                // Create the object and add the newly created object id to the temporaryIdMap.
                $persistedId = $this->objectModifier->createObject($schema, $entity,
                        $createdObject->getPropertyValues(), $audit);
                $createdObject->setId($persistedId);
                $this->temporaryIdMap->setId($entity, $temporaryId, $persistedId);
            }

            // Update all objects in the CHANGED group.
            foreach ($restParser->getChangedObjects() as $changedObject) {
                if ($changedObject->getScope()->includes(Scope::TAG_PROPERTIES) != Scope::INCLUDES_ALL) {
                    continue;
                }
                $changedObject->substituteTemporaryIds($this->temporaryIdMap);
                $isPersisted = $this->objectModifier->modifyObject($schema, $changedObject->getEntity(),
                        $changedObject->getId(), $changedObject->getPropertyValues(), $audit);
                if ($isPersisted) {
                    $this->temporaryIdMap->setId($changedObject->getEntity(), NULL, $changedObject->getId());
                }
            }

            // Create and/or delete all link-relationships.
            foreach ($restParser->getChangedAndTouchedObjects() as $changedObject) {
                $changedObject->establishLinks($schema, $this->objectModifier, $audit);
            }
            foreach ($restParser->getCreatedObjects() as $createdObject) {
                $createdObject->establishLinks($schema, $this->objectModifier, $audit);
            }

            // Delete all objects in the DELETED group.
            foreach ($restParser->getDeletedObjects() as $deletedObject) {
                $this->objectModifier->deleteObjectTree($schema, $deletedObject->getEntity(), $deletedObject->getId(),
                        $audit);
            }

            // Update the PUBLISHED state.
            foreach ($restParser->getPublishedObjects() as $publishedObject) {
                $this->objectPublisher->publish($schema, $publishedObject->getEntity(), $publishedObject->getId(),
                        $audit);
                // Purge - permanently delete - all 'terminated' objects that are not part of the published data.
                $this->objectModifier->purge($schema, $publishedObject->getEntity(), $publishedObject->getId(), $audit);
            }

            // Commit the database transaction.
            $mySQLi->commit();

            // Save temporaryIds in the session.
            $_SESSION[$this->sessionId] = $this->temporaryIdMap->serializeTemporaryIds();
            return new RestResponse($restResponseCode, $this->temporaryIdMap);
        } catch (Exception $e) {
            Bootstrap::logException($e);
            $mySQLi->rollback();
            return new RestResponse(RestResponse::SERVER_ERROR, $e);
        }
    }

    private function processQueryParams(Schema $schema, $mainEntity, $allowFetchParams) {
        // Get rid of any 'PHPSESSION' param.
        RestUrlParams::extractValue($this->params, session_name());

        // Create/restore the temporaryIdMap.
        $storedTemporaryIds = NULL;
        if (isset($_SESSION[$this->sessionId])) {
            $storedTemporaryIds = TemporaryIdMap::deserializeTemporaryIds($schema, $_SESSION[$this->sessionId]);
        }
        $this->temporaryIdMap = new TemporaryIdMap($storedTemporaryIds);

        // Validate that prefixed parameters refer to existing entities.
        foreach ($this->params as $paramName => &$paramValue) {
            if (!$allowFetchParams) {
                throw new Exception("Query parameter '$paramName' is not allowed.", RestResponse::CLIENT_ERROR);
            }
            if (RestUrlParams::isFetchParam($paramName)) {
                continue;
            }

            $paramNameParts = explode(RestUrlParams::ENTITY_SEPARATOR, $paramName);
            $entity = NULL;
            $queryParam = NULL;
            if (count($paramNameParts) == 1) {
                if ($mainEntity == NULL) {
                    throw new Exception("Unknown query parameter '$paramName'.", RestResponse::CLIENT_ERROR);
                }
                $entity = $mainEntity;
                $queryParam = $paramNameParts[0];
            } else {
                // Check if the specified entity exists.
                $entity = $schema->getObjectEntity($paramNameParts[0], false);
                if ($entity == NULL) {
                    throw new Exception("Unknown entity '$paramNameParts[0]' in query parameters.",
                            RestResponse::CLIENT_ERROR);
                }
                $queryParam = $paramNameParts[1];
            }

            // If one or more ID's are specified...
            if (strcasecmp($queryParam, RestUrlParams::ID) == 0) {
                // ...then substitute any temporary ID with its persisted counterpart.
                $persistedIds = array();
                foreach (explode(RestUrlParams::ID_SEPARATOR, $paramValue) as $id) {
                    $persistedIds[] = $this->temporaryIdMap->getPersistedId($entity, $id);
                }
                $paramValue = implode(RestUrlParams::ID_SEPARATOR, $persistedIds);
            }
        }
    }

}

?>
