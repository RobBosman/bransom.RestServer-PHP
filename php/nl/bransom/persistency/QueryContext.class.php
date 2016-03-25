<?php

Bootstrap::import('nl.bransom.rest.RestUrlParams');
Bootstrap::import('nl.bransom.persistency.meta.Entity');

/**
 * Description of QueryContext
 *
 * @author Rob Bosman
 */
class QueryContext {

    const ALL_TIMES = 'All_TIMES';

    private $at;
    private $published;
    private $queryParamsMap;
    private $scopeMap;

    public function __construct(array $params, $mainEntity) {
        $this->at = RestUrlParams::parseTimestamp(RestUrlParams::extractValue($params, RestUrlParams::AT));
        if ($this->at == RestUrlParams::ALL_TIMES) {
            $this->at = self::ALL_TIMES;
        }
        $this->published = RestUrlParams::parseBoolean(RestUrlParams::extractValue($params, RestUrlParams::PUBLISHED));
        $this->queryParamsMap = array();
        $this->scopeMap = array();
        if ($mainEntity != NULL) {
            foreach ($params as $paramName => $paramValue) {
                // Parameters can be specified with or without entity-prefix, e.g. 'entity/property=value' or just
                // 'property=value'. Check if an entity-prefix is provided.
                $entityName = NULL;
                $propertyName = NULL;
                $paramNameParts = explode(RestUrlParams::ENTITY_SEPARATOR, $paramName);
                if (count($paramNameParts) == 1) {
                    // It's like 'property=value'.
                    $entityName = $mainEntity->getName();
                    $propertyName = $paramName;
                } else if (count($paramNameParts) == 2) {
                    // It's like 'entity/property=value'.
                    $entityName = $paramNameParts[0];
                    $propertyName = $paramNameParts[1];
                } else {
                    throw new Exception("Illegal query parameter '$paramName'.");
                }
                // If it's a scope parameter...
                if (strcasecmp($propertyName, RestUrlParams::SCOPE) == 0) {
                    // ...then parse it.
                    $this->scopeMap[$entityName] = Scope::parseValue($paramValue);
                } else {
                    // ...else add the value to the map at the correct entity-entry.
                    if (!array_key_exists($entityName, $this->queryParamsMap)) {
                        $this->queryParamsMap[$entityName] = array();
                    }
                    // If $paramName is 'id'...
                    if (strcasecmp($propertyName, RestUrlParams::ID) == 0) {
                        // ...then check if this is allowed for the given entity...
                        if (!$mainEntity->isObjectEntity()) {
                            throw new Exception("Cannot fetch entity '" . $mainEntity->getName() . "' by id.");
                        }
                        // ...and apply the correct property name and value.
                        $propertyName = $mainEntity->getObjectIdColumnName();
                        $paramValue = explode(RestUrlParams::ID_SEPARATOR, $paramValue);
                    }
                    $this->queryParamsMap[$entityName][$propertyName] = $paramValue;
                }
            }
        }
    }

    public function getParameterAt($nullIfAnyTime = TRUE) {
        if (($nullIfAnyTime) and ($this->at == self::ALL_TIMES)) {
            return NULL;
        } else {
            return $this->at;
        }
    }

    public function isAtAllTimes() {
        return ($this->at == self::ALL_TIMES);
    }

    public function getParameterPublished() {
        return $this->published;
    }

    public function getQueryParameters(Entity $entity, $id) {
        $queryParams = array();
        if (array_key_exists($entity->getName(), $this->queryParamsMap)) {
            $queryParams = array_merge($queryParams, $this->queryParamsMap[$entity->getName()]);
        }
        if ($id != NULL) {
            $queryParams[$entity->getObjectIdColumnName()] = $id;
        }
        $entity->removeUnknownParameters($queryParams);
        return $queryParams;
    }

    public function getScope(Entity $entity, Scope $defaultScope) {
        if (array_key_exists($entity->getName(), $this->scopeMap)) {
            return $this->scopeMap[$entity->getName()];
        } else {
            return $defaultScope;
        }
    }
}
