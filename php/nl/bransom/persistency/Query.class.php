<?php

Bootstrap::import('nl.bransom.persistency.QueryContext');

/**
 * Description of Query
 *
 * @author Rob Bosman
 */
abstract class Query {

    private $queryContext;
    private $aliases;
    private $whereClauses;

    public function __construct(QueryContext $queryContext) {
        $this->queryContext = $queryContext;
        $this->aliases = array();
        $this->whereClauses = array();
    }

    public function getQueryContext() {
        return $this->queryContext;
    }

    public function getAlias($index = 0) {
        return $this->aliases[$index];
    }

    public function addWhereClause($paramName, $paramValue) {
        if (is_array($paramValue)) {
            $this->whereClauses[] = " AND " . $this->getAlias() . ".$paramName IN('". implode("','", $paramValue)
                    . "')";
        } else {
            $this->whereClauses[] = " AND " . $this->getAlias() . ".$paramName = '$paramValue'";
        }
    }

    public function getQueryString() {
        return $this->getQueryPartSelect() . $this->getQueryPartsJoinAndWhere() . implode(' ', $this->whereClauses);
    }

    protected function createAlias() {
        $alias = chr(ord('a') + count($this->aliases));
        $this->aliases[] = $alias;
        return $alias;
    }

    protected abstract function getQueryPartSelect();

    protected abstract function getQueryPartsJoinAndWhere();

    public function __toString() {
        return $this->getQueryString();
    }

}

?>
