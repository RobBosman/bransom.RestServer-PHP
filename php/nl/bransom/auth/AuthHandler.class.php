<?php

Bootstrap::import('nl.bransom.Config');
Bootstrap::import('nl.bransom.auth.OpenIDConnectHandler');
Bootstrap::import('nl.bransom.auth.HTPasswordHandler');

Bootstrap::startSession();

/**
 * Description of AuthHandler
 *
 * @author Rob Bosman
 */
class AuthHandler {

    public static function getSignedInAccountId($appName, &$jwt = NULL) {
        $authModelPropertyName = 'authentication-model-for-app.' . $appName;
        $authModel = self::getAuthModel($authModelPropertyName);
        if ($authModel === NULL) {
            throw new Exception("Config error: please specify property '$authModelPropertyName' in section [auth].");
        }
        if (strcasecmp($authModel, 'OpenIDConnect') == 0) {
            $handler = new OpenIDConnectHandler($appName);
            return $handler->getSignedInAccountId($jwt);
        } else if (strcasecmp($authModel, 'HTPassword') == 0) {
            return HTPasswordHandler::getSignedInAccountId();
        } else if (strcasecmp($authModel, 'NONE') == 0 || $authModel == '') {
            return 1;
        } else {
            throw new Exception("Config error: unexpected value for property $authModelPropertyName: '$authModel'.");
        }
    }

    private static function getAuthModel($authModelPropertyName) {
        $config = Config::getInstance();
        foreach ($config->getSection('auth') as $propertyName => $value) {
            if (strcasecmp($propertyName, $authModelPropertyName) === 0) {
                return ($value !== NULL ? $value : '');
            }
        }
        return NULL;
    }
}
