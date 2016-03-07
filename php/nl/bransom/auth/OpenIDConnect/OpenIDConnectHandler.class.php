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
    if (isset($jwtPayload->email)
            && $jwtPayload->email != NULL
            && isset($jwtPayload->email_verified)
            && $jwtPayload->email_verified === TRUE) {
      $emailParts = explode('@', $jwtPayload->email);
      return $this->getAccountIdByName($emailParts[0]);
    }
    return NULL;
  }

  private function getAccountIdByName($accountName) {
    $accountIdCacheKey = array(
        'id' => 'ACCOUND_ID_FOR_' . strtolower($accountName),
        'exp' => 3600); // 1 hour
    $accountId = SessionCache::get($accountIdCacheKey);
    if ($accountId == NULL) {
      $accountId = $this->getAccountIdByNameFromDB($accountName);
      SessionCache::set($accountIdCacheKey, $accountId);
    }
    return $accountId;
  }

  private function getAccountIdByNameFromDB($accountName) {
    $id = NULL;
    try {
      $schema = MetaData::getInstance()->getSchema($this->appName);
      $mySQLi = $schema->getMySQLi();
      $queryString = "SELECT id FROM " . DbConstants::TABLE_ACCOUNT . " WHERE name LIKE '" . $accountName . "'";
      $queryResult = $mySQLi->query($queryString);
      if (!$queryResult) {
        throw new Exception("Error fetching account ID for '$accountName' - $mySQLi->error\n<!--\n$queryString\n-->");
      }
      $queryData = $queryResult->fetch_assoc();
      if (isset($queryData['id'])) {
        $id = $queryData['id'];
      }
      $queryResult->close();
    } catch (Exception $e) {
      Bootstrap::logException($e);
    }
    return $id;
  }

}
