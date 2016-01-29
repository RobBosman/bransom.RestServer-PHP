<?php

Bootstrap::import('nl.bransom.persistency.QueryEntity');
Bootstrap::import('nl.bransom.persistency.meta.Relationship');

/**
 * Description of QueryRelatedEntity
 *
 * @author Rob Bosman
 */
class QueryRelatedEntity extends QueryEntity {

    private $givenEntity;
    private $givenObjectId;
    private $relationship;

    public function __construct(Entity $givenEntity, $givenObjectId, Relationship $relationship,
            QueryContext $queryContext, Scope $scope) {
        parent::__construct($relationship->getOppositeEntity($givenEntity), $queryContext, $scope);
        // Check input consistency.
        if ($givenObjectId == null) {
            throw new Exception("The reference-id is not specified for relationship '" . $givenEntity->getName()
                    . "' to '" . $this->getSearchEntity()->getName() . "'.");
        }
        if (($relationship->getOneEntity() != $givenEntity) && ($relationship->getOtherEntity() != $givenEntity)) {
            throw new Exception("Given entity '" . $givenEntity->getName()
                    . "' is not part of the given relationship between '" . $relationship->getOneEntity()->getName()
                    . "' and '" . $relationship->getOtherEntity()->getName() . "'.");
        }
        $this->givenEntity = $givenEntity;
        $this->givenObjectId = $givenObjectId;
        $this->relationship = $relationship;
    }

    /**
     * Compose the query to fetch ObjectRefs of all related entities.
     * The query depends on how the datase is being retrieved: 'published', 'actual' (unpublished) or 'historic' (at a
     * given date).
     * 
     * When fetching the actual dataset, then terminated objectRefs are fetched too. This allows for determining the
     * published state of "bereaved parent" objects. These terminated objectRefs are not included in the resulting XML.
     * 
     * @return queryString
     */
    public function getQueryPartsJoinAndWhere() {
        $givenEntityName = $this->givenEntity->getName();
        $givenObjectIdColumnName = $this->givenEntity->getObjectIdColumnName();
        $givenStateIdColumnName = $this->givenEntity->getStateIdColumnName();
        $searchObjectIdColumnName = $this->getSearchEntity()->getObjectIdColumnName();
        $searchStateIdColumnName = $this->getSearchEntity()->getStateIdColumnName();
        $linkStateIdColumnName = NULL;
        $s = $this->getAlias(0);
        $ss = $this->getAlias(1);
        $g = $this->createAlias();
        $gs = $this->createAlias();
        $l = $this->createAlias();
        $ls = $this->createAlias();
        $joins = "";
        $whereClauses = "";
        if ($this->relationship->getFkEntity() == $this->givenEntity) {
            // One-to-one relationship where the given entity holds the foreign key that refers to the search-entity.
            //
            // Examples:
            // 
            // -- item (given) => image (search)
            // -- whith states for item and image
            // -- at timestamp '2011-08-31 23:49:52'
            // SELECT s.*
            //   FROM image s
            //   JOIN item g ON g.id_image = s.id_object
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   JOIN _audit ssac ON ssac.id = ss.id_created
            //   LEFT JOIN _audit ssat ON ssat.id = ss.id_terminated
            //   JOIN _audit gsac ON gsac.id = gs.id_created
            //   LEFT JOIN _audit gsat ON gsat.id = gs.id_terminated
            //   WHERE TRUE
            //     AND ssac.at <= TIMESTAMP('2011-08-31 23:49:52')
            //     AND (ss.id_terminated IS NULL OR ssat.at > TIMESTAMP('2011-08-31 23:49:52'))
            //     AND gsac.at <= TIMESTAMP('2011-08-31 23:49:52')
            //     AND (gs.id_terminated IS NULL OR gsat.at > TIMESTAMP('2011-08-31 23:49:52'))
            //
            // -- published
            // SELECT s.*
            //   FROM image s
            //   JOIN item g ON g.id_image = s.id_object
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   WHERE TRUE
            //     AND ss.id_published IS NOT NULL
            //     AND gs.id_published IS NOT NULL
            //
            // -- actual, not published
            // SELECT s.*
            //   FROM image s
            //   JOIN item g ON g.id_image = s.id_object
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   WHERE TRUE
            //     AND ss.id_terminated IS NULL
            //     AND gs.id_terminated IS NULL
            //
            $fkColumnNameToSearchEntity = $this->relationship->getFkColumnName($this->getSearchEntity());
            $joins .= " JOIN $givenEntityName $g ON $g.$fkColumnNameToSearchEntity = $s.$searchObjectIdColumnName";
            $whereClauses .= " AND $g.$givenObjectIdColumnName = $this->givenObjectId";
        } else if ($this->relationship->getFkEntity() == $this->getSearchEntity()) {
            // One-to-many relationship where the search-entity holds the foreign key that refers back to the given
            // entity.
            //
            // Examples:
            // 
            // -- cv (given) <= branchekennis (search)
            // -- whith states for item and image
            // -- at timestamp '2011-08-31 23:49:52'
            // SELECT s.*
            //   FROM branchekennis s
            //   JOIN cv g ON g.id_object = s.id_cv
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   JOIN _audit ssac ON ssac.id = ss.id_created
            //   LEFT JOIN _audit ssat ON ssat.id = ss.id_terminated
            //   JOIN _audit gsac ON gsac.id = gs.id_created
            //   LEFT JOIN _audit gsat ON gsat.id = gs.id_terminated
            //   WHERE TRUE
            //     AND ssac.at <= TIMESTAMP('2011-08-31 23:49:52')
            //     AND (ss.id_terminated IS NULL OR ssat.at > TIMESTAMP('2011-08-31 23:49:52'))
            //     AND gsac.at <= TIMESTAMP('2011-08-31 23:49:52')
            //     AND (gs.id_terminated IS NULL OR gsat.at > TIMESTAMP('2011-08-31 23:49:52'))
            //
            // -- published
            // SELECT s.*
            //   FROM branchekennis s
            //   JOIN cv g ON g.id_object = s.id_cv
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   WHERE TRUE
            //     AND ss.id_published IS NOT NULL
            //     AND gs.id_published IS NOT NULL
            //
            // -- actual, not published
            // SELECT s.*, ss.id_terminated
            //   FROM branchekennis s
            //   JOIN cv g ON g.id_object = s.id_cv
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state gs ON gs.id = g.id_state
            //   WHERE TRUE
            //     AND gs.id_terminated IS NULL
            //
            $fkColumnNameToGivenEntity = $this->relationship->getFkColumnName($this->givenEntity);
            $g = $this->createAlias();
            $gs = $this->createAlias();
            $joins .= " JOIN $givenEntityName $g ON $g.$givenObjectIdColumnName = $s.$fkColumnNameToGivenEntity";
            $whereClauses .= " AND $g.$givenObjectIdColumnName = $this->givenObjectId";
        } else {
            // Many-to-many relationship where a third entity (neither the given, nor the serach-entity) holds two
            // foreign keys, one to the given entity and one to the search-entity.
            //
            // Examples:
            //
            // -- _account <= link_account_rol => rol
            // -- whith states for link_account_rol and rol
            // -- at timestamp '2011-11-29 23:00:38'
            // SELECT s.*
            //   FROM rol s
            //   JOIN link_account_rol l ON l.id_rol = t.id
            //   JOIN _account g ON g.id = l.id_account
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _audit ssac ON ssac.id = ss.id_created
            //   LEFT JOIN _audit ssat ON ssat.id = ss.id_terminated
            //   JOIN _state ls ON ls.id = l.id_state
            //   JOIN _audit lsac ON lsac.id = ls.id_created
            //   LEFT JOIN _audit lsat ON lsat.id = ls.id_terminated
            //   WHERE TRUE
            //     AND ssac.at <= TIMESTAMP('2011-11-29 23:00:38')
            //     AND (ss.id_terminated IS NULL OR ssat.at > TIMESTAMP('2011-11-29 23:00:38'))
            //     AND lsac.at <= TIMESTAMP('2011-11-29 23:00:38')
            //     AND (ls.id_terminated IS NULL OR lsat.at > TIMESTAMP('2011-11-29 23:00:38'))
            //
            // -- _account <= link_account_rol => rol
            // -- whith states for link_account_rol and rol
            // -- published
            // SELECT s.*
            //   FROM rol s
            //   JOIN link_account_rol l ON l.id_rol = t.id
            //   JOIN _account g ON g.id = l.id_account
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state ls ON ls.id = l.id_state
            //   WHERE TRUE
            //     AND ss.id_published IS NOT NULL
            //     AND ls.id_published IS NOT NULL
            //
            // -- _account <= link_account_rol => rol
            // -- whith states for link_account_rol and rol
            // -- not published
            // SELECT s.*
            //   FROM rol s
            //   JOIN link_account_rol l ON l.id_rol = s.id
            //   JOIN _account g ON g.id = l.id_account
            //   JOIN _state ss ON ss.id = s.id_state
            //   JOIN _state ls ON ls.id = l.id_state
            //   WHERE TRUE
            //     AND ss.id_terminated IS NULL
            //     AND ls.id_terminated IS NULL
            //
            $linkEntity = $this->relationship->getFkEntity();
            $linkEntityName = $linkEntity->getName();
            $linkStateIdColumnName = $linkEntity->getStateIdColumnName();
            $fkColumnNameToSearchEntity = $this->relationship->getFkColumnName($this->getSearchEntity());
            $fkColumnNameToGivenEntity = $this->relationship->getFkColumnName($this->givenEntity);
            $joins .= " JOIN $linkEntityName $l ON $l.$fkColumnNameToSearchEntity = $s.$searchObjectIdColumnName";
            $joins .= " JOIN $givenEntityName $g ON $g.$givenObjectIdColumnName = $l.$fkColumnNameToGivenEntity";
            $whereClauses .= " AND $g.$givenObjectIdColumnName = $this->givenObjectId";
        }
        if ($searchStateIdColumnName != NULL) {
            $joins .= " JOIN " . DbConstants::TABLE_STATE . " $ss ON $ss.id = $s.$searchStateIdColumnName";
        }
        if ($givenStateIdColumnName != NULL) {
            $joins .= " JOIN " . DbConstants::TABLE_STATE . " $gs ON $gs.id = $g.$givenStateIdColumnName";
        }
        if ($linkStateIdColumnName != NULL) {
            $joins .= " JOIN " . DbConstants::TABLE_STATE . " $ls ON $ls.id = $l.$linkStateIdColumnName";
        }
        
        $paramAt = $this->getQueryContext()->getParameterAt();
        if ($paramAt != NULL) {
            if ($searchStateIdColumnName != NULL) {
                $ssac = $this->createAlias();
                $ssat = $this->createAlias();
                $joins .= " JOIN _a" . DbConstants::TABLE_AUDIT . "udit $ssac ON $ssac.id = $ss.id_created";
                $joins .= " LEFT JOIN " . DbConstants::TABLE_AUDIT . " $ssat ON $ssat.id = $ss.id_terminated";
                $whereClauses .= " AND $ssac.at <= TIMESTAMP('$paramAt')";
                $whereClauses .= " AND ($ss.id_terminated IS NULL OR $ssat.at > TIMESTAMP('$paramAt'))";
            }
            if ($givenStateIdColumnName != NULL) {
                $gsac = $this->createAlias();
                $gsat = $this->createAlias();
                $joins .= " JOIN " . DbConstants::TABLE_AUDIT . " $gsac ON $gsac.id = $gs.id_created";
                $joins .= " LEFT JOIN " . DbConstants::TABLE_AUDIT . " $gsat ON $gsat.id = $gs.id_terminated";
                $whereClauses .= " AND $gsac.at <= TIMESTAMP('$paramAt')";
                $whereClauses .= " AND ($gs.id_terminated IS NULL OR $gsat.at > TIMESTAMP('$paramAt'))";
            }
            if ($linkStateIdColumnName != NULL) {
                $lsac = $this->createAlias();
                $lsat = $this->createAlias();
                $joins .= " JOIN " . DbConstants::TABLE_AUDIT . " $lsac ON $lsac.id = $ls.id_created";
                $joins .= " LEFT JOIN " . DbConstants::TABLE_AUDIT . " $lsat ON $lsat.id = $ls.id_terminated";
                $whereClauses .= " AND $lsac.at <= TIMESTAMP('$paramAt')";
                $whereClauses .= " AND ($ls.id_terminated IS NULL OR $lsat.at > TIMESTAMP('$paramAt'))";
            }
        } else if ($this->getQueryContext()->getParameterPublished()) {
            if ($searchStateIdColumnName != NULL) {
                $whereClauses .= " AND $ss.id_published IS NOT NULL";
            }
            if ($givenStateIdColumnName != NULL) {
                $whereClauses .= " AND $gs.id_published IS NOT NULL";
            }
            if ($linkStateIdColumnName != NULL) {
                $whereClauses .= " AND $ls.id_published IS NOT NULL";
            }
        } else if (!$this->getQueryContext()->isAtAllTimes()) {
            // If we're querying for an unpublished searchEntity that has a one-to-many relationship to the givenEntity,
            // whereby the foreign key is on the searchEntity, then we need to check if it hasn't been terminated.
            // Because if it is, the published state of our givenEntity must be set to FALSE.
            // To check this, we added the id_terminated flag to the select part and we must NOT filter terminated
            // objects here. So only add the following where-clause if that specific situation is not the case!
            if ($searchStateIdColumnName != NULL && $this->relationship->getFkEntity() != $this->getSearchEntity()) {
                $whereClauses .= " AND $ss.id_terminated IS NULL";
            }
            if ($givenStateIdColumnName != NULL) {
                $whereClauses .= " AND $gs.id_terminated IS NULL";
            }
            if ($linkStateIdColumnName != NULL) {
                $whereClauses .= " AND $ls.id_terminated IS NULL";
            }
        }
        return "$joins WHERE TRUE$whereClauses";
    }

}

?>
