<?php

Bootstrap::import('nl.bransom.persistency.meta.Entity');
Bootstrap::import('nl.bransom.persistency.meta.Relationship');

/**
 * Description of OneToManyRelationship
 *
 * @author Rob Bosman
 */
class OneToManyRelationship extends Relationship {

    public function __construct(Entity $fkEntity, $fkColumnName, Entity $otherEntity, Entity $ownerEntity) {
        parent::__construct($fkEntity, array($fkColumnName => $otherEntity), $ownerEntity);
    }

}

?>
