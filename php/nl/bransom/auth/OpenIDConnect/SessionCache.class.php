<?php

Bootstrap::startSession();

/**
 * Description of SessionCache
 *
 * @author RobB
 */
class SessionCache {

  const ID = 'id';
  const EXP = 'exp';

  private static $NUM_TIME_DIGITS = 12;

  public static function get($cacheKey) {
    $key = self::getSessionKey($cacheKey);
    if (!isset($_SESSION) || !array_key_exists($key, $_SESSION)) {
      return FALSE;
    }
    if (isset($cacheKey[self::EXP])) {
      $createTimestamp = substr($_SESSION[$key], 0, self::$NUM_TIME_DIGITS);
      $elapsedTime = time() - $createTimestamp;
      if ($elapsedTime > $cacheKey[self::EXP]) {
        return FALSE;
      }
    }
    return substr($_SESSION[$key], self::$NUM_TIME_DIGITS);
  }

  public static function set($cacheKey, $value) {
    $key = $cacheKey[self::ID];
    $_SESSION[$key] = sprintf("%'.0" . self::$NUM_TIME_DIGITS . "d", time()) . $value;
  }

  public static function clear($cacheKey) {
    $key = self::getSessionKey($cacheKey);
    if (isset($_SESSION) && array_key_exists($key, $_SESSION)) {
      unset($_SESSION[$key]);
    }
  }
  
  private static function getSessionKey($cacheKey) {
    return $cacheKey[self::ID];
  }
}
