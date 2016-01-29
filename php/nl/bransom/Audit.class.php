<?php

/**
 * Description of Audit
 *
 * @author Rob Bosman
 */
interface Audit {
    public function getId();

    public function getAt();

    public function getAccountId();
}

?>
