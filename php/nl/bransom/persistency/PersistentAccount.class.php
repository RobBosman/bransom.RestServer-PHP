<?php

Bootstrap::import('nl.bransom.Account');
Bootstrap::import('nl.bransom.persistency.DbConnection');
Bootstrap::import('nl.bransom.persistency.meta.Schema');

/**
 * Description of PersistentAccount
 *
 * @author Rob Bosman
 */
class PersistentAccount implements Account {

    private static $accounts = array();

    public static function getAccount(Schema $schema, $accountId) {
        if (!array_key_exists($accountId, self::$accounts)) {
            $queryString = "SELECT a.id,a.name FROM " . DbConstants::TABLE_ACCOUNT . " a WHERE a.id = '$accountId'";
            $queryResult = $schema->getMySQLi()->query($queryString);
            if (!$queryResult) {
                throw new Exception("Error fetching account for [$accountId] - " . $schema->getMySQLi()->error
                        . "\n<-- $queryString -->");
            }
            $account = NULL;
            $queryData = $queryResult->fetch_assoc();
            if ($queryData) {
                $account = new PersistentAccount($queryData['id'], $queryData['name']);
            }
            $queryResult->close();
            if ($account == NULL) {
                throw new Exception("Cannot find account for '$accountId'.");
            }
            self::$accounts[$accountId] = $account;
        }
        return self::$accounts[$accountId];
    }

    private $accountId;
    private $accountName;

    function __construct($accountId, $accountName) {
        $this->accountId = $accountId;
        $this->accountName = $accountName;
    }

    public function getId() {
        return $this->accountId;
    }

    public function getName() {
        return $this->accountName;
    }
}

?>
