<?php

Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.Relationship');

/**
 * Description of ManyToManyRelationship
 *
 * @author Rob Bosman
 */
class ManyToManyRelationship extends Relationship {

    public function __construct(Entity $fkEntity, array $fkColumnNameToEntityMap) {
        parent::__construct($fkEntity, $fkColumnNameToEntityMap, NULL);
    }

}

?>
