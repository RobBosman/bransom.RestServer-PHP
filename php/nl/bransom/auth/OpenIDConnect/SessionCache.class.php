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
        $key = $cacheKey[self::ID];
        if (!isset($_SESSION[$key])) {
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
        unset($_SESSION[$cacheKey]);
    }
}