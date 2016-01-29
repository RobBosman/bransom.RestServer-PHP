<?php

error_reporting(E_ALL | E_NOTICE | E_WARNING);

//$DEBUG_PROXY = 'localhost:8888';

require_once dirname(__FILE__) . '/../php/Bootstrap.class.php';
Bootstrap::initConfig(dirname(__FILE__) . '/../config/config.ini');
Bootstrap::import('nl.bransom.persistency.DbConnection');

class TestEngine {

    public static function _createDatabaseSchema($schemaName) {
        $dbConnection = new DbConnection(NULL);
        $mySQLi = $dbConnection->getMySQLi();
        $queryResult = $mySQLi->multi_query("DROP DATABASE IF EXISTS `$schemaName`;"
                . "CREATE DATABASE IF NOT EXISTS `$schemaName`;");
        if (!$queryResult) {
            throw new Exception("Error creating database '$schemaName' - " . $mySQLi->error);
        }
        $queryResult->close();
    }

    public static function createDatabaseSchema($schemaName) {
        $dbConnection = new DbConnection(NULL);
        $mySQLi = $dbConnection->getMySQLi();
        $queryResult = $mySQLi->query("DROP DATABASE IF EXISTS `$schemaName`");
        if ($queryResult === FALSE) {
            throw new Exception("Error creating database '$schemaName' - " . $mySQLi->error);
        }
        $queryResult = $mySQLi->query("CREATE DATABASE IF NOT EXISTS `$schemaName`");
        if ($queryResult === FALSE) {
            throw new Exception("Error creating database '$schemaName' - " . $mySQLi->error);
        }
        $queryResult->close();
        $dbConnection->close();
    }

    private $schemaName;
    private $dbConnection;
    private $baseUrl;
    private $urlParams;

    public function __construct($schemaName, $baseUrl, array $urlParams) {
        $this->schemaName = $schemaName;
        $this->baseUrl = $baseUrl;
        $this->urlParams = $urlParams;
        $this->dbConnection = new DbConnection($this->schemaName);
    }

    public function _executeDatabaseScript($queryString) {
        $mySQLi = $this->dbConnection->getMySQLi();
        $queryResult = $mySQLi->multi_query($queryString);
        if ($queryResult === FALSE) {
            throw new Exception("Error executing script for database '$this->schemaName' - " . $mySQLi->error);
        }
        while ($mySQLi->more_results()) {
            if ($mySQLi->next_result() === FALSE) {
                throw new Exception("Error executing script for database '$this->schemaName' - " . $mySQLi->error);
            }
        }
        $queryResult->close();
    }

    public function executeDatabaseScript($queryString) {
        $mySQLi = $this->dbConnection->getMySQLi();
        foreach (explode(';', $queryString) as $statement) {
            $statement = trim($statement);
            if ($statement != '') {
                $queryResult = $mySQLi->query($statement);
                if ($queryResult === FALSE) {
                    throw new Exception("Error executing statement for database '$this->schemaName' - "
                            . $mySQLi->error);
                }
                $queryResult->close();
            }
        }
    }

    public function assertGetXml($testDescr, $url, $expectedXML) {
        $outputContent = $this->invokeUrl($url, 'GET');
		$expectedXML = str_replace('<![CDATA[', '<!\[CDATA\[', $expectedXML);
		$expectedXML = str_replace(']]>', '\]\]>', $expectedXML);
        $expectedRegExp = "|^<\?.*\?>\s*$expectedXML\s*$|";
        if (preg_replace($expectedRegExp, '', $outputContent) == '') {
            return "OK\t$testDescr<br/>\n";
        } else {
            return "ERROR\t$testDescr<br/>\n$outputContent\n<br/>";
        }
    }

    public function assertPostXml($testDescr, $inputXML, $expectedXML) {
        $inputDOM = new DOMDocument();
        $inputDOM->loadXML($inputXML);
        $url = $this->baseUrl;
        if (count($this->urlParams) > 0) {
            $params = array();
            foreach ($this->urlParams as $name => $value) {
                $params[] = "$name=$value";
            }
            $url .= '?' . implode('&', $params);
        }
        $outputContent = $this->invokeUrl($url, 'POST', $inputDOM->saveXML(), 'text/xml');
        $expectedRegExp = "|^<\?.*\?>\s*$expectedXML\s*$|";
        if (preg_replace($expectedRegExp, '', $outputContent) == '') {
            return "OK\t$testDescr<br/>\n";
        } else {
            return "ERROR\t$testDescr<br/>\n$outputContent\n<br/>";
        }
    }

    private function invokeUrl($url, $method, $inputContent = NULL, $inputContentType = NULL) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if ($inputContent != NULL) {
            if ($method == 'POST') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $inputContent);
            } else {
                $fh = tmpfile();
                fwrite($fh, $inputContent);
                fseek($fh, 0);
                curl_setopt($curl, CURLOPT_INFILE, $fh);
                curl_setopt($curl, CURLOPT_INFILESIZE, strlen($inputContent));
            }
            if ($inputContentType != NULL) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: ' . $inputContentType));
            }
        }
        if (isset($DEBUG_PROXY)) {
            curl_setopt($curl, CURLOPT_PROXY, $DEBUG_PROXY);
        }
        $outputContent = curl_exec($curl);
        if ($outputContent == FALSE) {
            throw new Exception("Error invoking URL $url.");
        }
        curl_close($curl);
        return $outputContent;
    }

}

?>