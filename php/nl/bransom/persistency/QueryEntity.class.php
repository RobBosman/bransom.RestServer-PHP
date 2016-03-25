<?php

Bootstrap::import('nl.bransom.persistency.Query');
Bootstrap::import('nl.bransom.persistency.Scope');
Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.Property');

/**
 * Description of QueryEntity
 *
 * @author Rob Bosman
 */
class QueryEntity extends Query {

    const ID_CREATED = 'ID_CREATED';
    const ID_TERMINATED = 'ID_TERMINATED';
    const ID_PUBLISHED = 'ID_PUBLISHED';

    private $searchEntity;
    private $queryParams;
    private $propertyNames;
    private $scope;
    private $skipBinaries;

    public function __construct(Entity $searchEntity, QueryContext $queryContext, Scope $defaultScope,
            $id = NULL, $skipBinaries = FALSE) {
        parent::__construct($queryContext);
        $this->searchEntity = $searchEntity;
        $this->queryParams = $queryContext->getQueryParameters($searchEntity, $id);
        $this->propertyNames = array();
        $this->scope = $queryContext->getScope($searchEntity, $defaultScope);
        $this->skipBinaries = $skipBinaries;

        // If the scope doesn't include property values...
        if ($this->scope->includes(Scope::TAG_PROPERTIES) == Scope::INCLUDES_NONE) {
            // ...then only fetch the object id and any foreign keys.
            if ($this->searchEntity->isObjectEntity()) {
                $this->propertyNames[] = $this->searchEntity->getObjectIdColumnName();
            }
            foreach ($this->searchEntity->getRelationships() as $relationship) {
                if ($relationship->getFkEntity() != $this->searchEntity) {
                    continue;
                }
                $toEntity = $relationship->getOppositeEntity($this->searchEntity);
                $this->propertyNames[] = $relationship->getFkColumnName($toEntity);
            }
        }

        // Create the first two aliases for the table and its state.
        $this->createAlias();
        $this->createAlias();

        // Add the query parameters to the where-clause.
        foreach ($this->queryParams as $paramName => $paramValue) {
            $property = $this->searchEntity->getProperty($paramName, FALSE);
            if ($property == NULL) {
                error_log("Ignoring unknown query parameter: '$paramName=$paramValue'.");
            } else {
                $this->addWhereClause($paramName, $paramValue);
            }
        }
    }

    public function setPropertyNames(array $propertyNames) {
        // Check if the input is consistent.
        foreach ($propertyNames as $propertyName) {
            $this->searchEntity->getProperty($propertyName);
        }
        $this->propertyNames = $propertyNames;
    }

    public function getSearchEntity() {
        return $this->searchEntity;
    }

    public function getScope() {
        return $this->scope;
    }

    protected function getQueryPartSelect() {
        $query = "";
        $s = $this->getAlias(0);
        if (count($this->propertyNames) == 0) {
            $this->propertyNames = array_keys($this->searchEntity->getProperties());
        }
        foreach ($this->propertyNames as $propertyName) {
            if (strlen($query) == 0) {
                $query .= "SELECT ";
            } else {
                $query .= ",";
            }
            $property = $this->searchEntity->getProperty($propertyName);
            if ($property->getTypeIndicator() == Property::TYPE_BINARY
                    and ($this->skipBinaries
                        or $this->scope->includes(Scope::TAG_PROPERTIES) == Scope::INCLUDES_REFS_ONLY)) {
                $query .= "'' AS $propertyName";
            } else {
                $query .= "$s.$propertyName";
            }
        }
        if ($this->searchEntity->getStateIdColumnName() != NULL) {
            $ss = $this->getAlias(1);
            if ($this->getQueryContext()->isAtAllTimes()) {
                $query .= ",$ss.id_created AS " . self::ID_CREATED;
            }
            $query .= ",$ss.id_published AS " . self::ID_PUBLISHED;
            $query .= ",$ss.id_terminated AS " . self::ID_TERMINATED;
        }
        $query .= " FROM " . $this->searchEntity->getName() . " $s";
        return $query;
    }

    protected function getQueryPartsJoinAndWhere() {
        $query = NULL;
        $stateIdColumnName = $this->searchEntity->getStateIdColumnName();
        $joins = "";
        $whereClauses = "";
        if ($stateIdColumnName != NULL) {
            $paramAt = $this->getQueryContext()->getParameterAt();
            $s = $this->getAlias(0);
            $ss = $this->getAlias(1);
            $joins .= " JOIN " . DbConstants::TABLE_STATE . " $ss ON $ss.id = $s.$stateIdColumnName";
            if ($paramAt != NULL) {
                $ssac = $this->createAlias();
                $ssat = $this->createAlias();
                $joins .= " JOIN " . DbConstants::TABLE_AUDIT . " $ssac ON $ssac.id = $ss.id_created";
                $joins .= " LEFT JOIN " . DbConstants::TABLE_AUDIT . " $ssat ON $ssat.id = $ss.id_terminated";
                $whereClauses .= " AND $ssac.at <= TIMESTAMP('$paramAt')";
                $whereClauses .= " AND ($ss.id_terminated IS NULL OR $ssat.at > TIMESTAMP('$paramAt'))";
            } else if ($this->getQueryContext()->getParameterPublished()) {
                $whereClauses .= " AND $ss.id_published IS NOT NULL";
            } else if (!$this->getQueryContext()->isAtAllTimes()) {
                $whereClauses .= " AND $ss.id_terminated IS NULL";
            }
        }
        return "$joins WHERE TRUE$whereClauses";
    }
}