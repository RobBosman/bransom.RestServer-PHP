<?php

Bootstrap::import('nl.bransom.Account');
Bootstrap::import('nl.bransom.Audit');
Bootstrap::import('nl.bransom.persistency.meta.Schema');

/**
 * Description of PersistentAudit
 *
 * @author Rob Bosman
 */
class PersistentAudit implements Audit {

    private $id;
    private $at;
    private $accountId;

    function __construct(Schema $schema, Account $account) {
        $this->accountId = $account->getId();

        $mySQLi = $schema->getMySQLi();
        $queryString = "INSERT " . DbConstants::TABLE_AUDIT . " (id_account) VALUES('" . $this->accountId . "')";
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error creating audit object for account[" . $this->accountId . "] - "
                    . $mySQLi->error . "\n<!--\n$queryString\n-->");
        }
        $this->id = $mySQLi->insert_id;

        // Get the timestamp of the created audit object.
        $queryString = "SELECT a.at FROM " . DbConstants::TABLE_AUDIT . " a WHERE a.id = $this->id";
        $queryResult = $mySQLi->query($queryString);
        if (!$queryResult) {
            throw new Exception("Error fetching audit[" . $this->accountId . "] - " . $mySQLi->error
                    . "\n<!--\n$queryString\n-->");
        }
        $audit = $queryResult->fetch_assoc();
        $this->at = $audit['at'];
        $queryResult->close();
    }

    public function getId() {
        return $this->id;
    }

    public function getAt() {
        return $this->at;
    }

    public function getAccountId() {
        return $this->accountId;
    }

}

?>
