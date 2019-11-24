<?php

Bootstrap::import('nl.bransom.auth.OpenIDConnect.HttpUtil');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.OpenIDConnect');
Bootstrap::import('nl.bransom.persistency.meta.DbConstants');
Bootstrap::import('nl.bransom.persistency.meta.MetaData');
Bootstrap::import('nl.bransom.persistency.meta.Schema');

/**
 * Description of OpenIDConnectHandler
 *
 * @author Rob
 */
class OpenIDConnectHandler {

  private $appName;

  public function __construct($appName) {
    $this->appName = $appName;
  }

  public function getSignedInAccountId(&$jwt) {
    if ($jwt == NULL) {
      $jwt = HttpUtil::getJWTFromHeader();
    }
    $jwtPayload = OpenIDConnect::getValidatedJWTPayload($jwt);
    if ($jwtPayload !== NULL) {
      return $this->getAccountIdByName($jwtPayload->name);
    }
    return NULL;
  }

  private function getAccountIdByName($accountName) {
    $accountIdCacheKey = array(
        'id' => 'ACCOUND_ID_FOR_' . strtolower($accountName),
        'exp' => 3600); // 1 hour
    $accountId = SessionCache::get($accountIdCacheKey);
    if ($accountId == NULL) {
      $accountId = $this->getOrCreateAccountIdByNameFromDB($accountName);
      SessionCache::set($accountIdCacheKey, $accountId);
    }
    return $accountId;
  }

  private function getOrCreateAccountIdByNameFromDB($accountName) {
    try {
      $id = $this->getAccountIdByNameFromDB($accountName);
      if ($id == NULL) {
        $this->createAccount($accountName);
        $id = $this->getAccountIdByNameFromDB($accountName);
      }
      return $id;
    } catch (Exception $e) {
      Bootstrap::logException($e);
    }
  }

  private function getAccountIdByNameFromDB($accountName) {
    $id = NULL;
    $schema = MetaData::getInstance()->getSchema($this->appName);
    $mySQLi = $schema->getMySQLi();
    $queryString = "SELECT id FROM " . DbConstants::TABLE_ACCOUNT
            // ignore '.', '-' and ' ' while matching
            . " WHERE REPLACE(REPLACE(REPLACE(name, '.' , ''), '-', ''), ' ', '') = REPLACE(REPLACE('$accountName', '-', ''), ' ', '')";
    $queryResult = $mySQLi->query($queryString);
    if (!$queryResult) {
      throw new Exception("Error fetching account ID for '$accountName' - $mySQLi->error\n<!--\n$queryString\n-->");
    }
    $queryData = $queryResult->fetch_assoc();
    if (isset($queryData['id'])) {
      $id = $queryData['id'];
    }
    $queryResult->close();
    return $id;
  }

  private function createAccount($accountName) {
    $schema = MetaData::getInstance()->getSchema($this->appName);
    $mySQLi = $schema->getMySQLi();
    $queryString = "INSERT INTO " . DbConstants::TABLE_ACCOUNT . " (name) VALUES('$accountName')";
    $queryResult = $mySQLi->query($queryString);
    if (!$queryResult || !$mySQLi->commit()) {
      throw new Exception("Error creating account ID for '$accountName' - $mySQLi->error");
    }
  }
}
