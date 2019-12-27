<?php

Bootstrap::import('nl.bransom.Config');

/**
 * Description of DbConnection
 *
 * @author Rob Bosman
 */
class DbConnection {

    private $mysqli;

    function __construct($schemaName) {
        $config = Config::getInstance();
        $configDb = $config->getSection('db');
        if ((!isset($configDb['hostName'])) or (!isset($configDb['userName'])) or (!isset($configDb['password']))) {
            throw new Exception("Configuratiefout: specificeer properties 'hostName', 'userName' en 'password'"
                    . " in sectie [db].");
        }
        $hostName = $configDb['hostName'];
        $userName = $configDb['userName'];
        $password = $configDb['password'];
        $mysqli = new mysqli($hostName, $userName, $password, $schemaName);
        // check if connection has been made successfully
        if (mysqli_connect_errno()) {
            throw new Exception("Error connecting to database '$userName@$hostName' - " . mysqli_connect_error());
        }
        $mysqli->query('SET NAMES \'UTF8\'');
        $this->mysqli = $mysqli;
    }

    function __destruct() {
        $this->close();
    }

    public function getMySQLi() {
        return $this->mysqli;
    }

    public function close() {
        if ($this->mysqli != NULL) {
            $this->mysqli->close();
            $this->mysqli = NULL;
        }
    }
}

?>
