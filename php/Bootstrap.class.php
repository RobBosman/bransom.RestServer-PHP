<?php

class Bootstrap {
    
    public static function phpRootDir() {
        return dirname(__FILE__);
    }

    public static function import($className) {
        $classFile = realpath(self::phpRootDir() . DIRECTORY_SEPARATOR
                . str_replace('.', DIRECTORY_SEPARATOR, $className) . '.class.php');
        if ($classFile === FALSE) {
            $e = new Exception("Cannot find file for PHP class '$className'.");
            self::logException($e);
            throw $e;
        }
        require_once $classFile;
    }

    private static $configFile;

    public static function initConfig($configFile) {
        self::$configFile = $configFile;
    }

    public static function getConfigFile() {
        return self::$configFile;
    }

    public static function startSession() {
        if (!isset($_SESSION)) {
            ini_set("session.cookie_httponly", TRUE);
            if (isset($_SERVER['HTTPS'])) {
                ini_set("session.cookie_secure", TRUE);
            }
            session_start();
        }
    }

    public static function logException(Exception $e) {
        error_log($e->getMessage() . "\r\n" . $e->getTraceAsString());
    }
}
