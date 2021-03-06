<?php

Bootstrap::import('nl.bransom.Config');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.OpenIDConnectHandler');
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
      throw new Exception("Configuratiefout: specificeer property '$authModelPropertyName' in sectie [auth].");
    }
    if (strcasecmp($authModel, 'OpenIDConnect') === 0) {
      $handler = new OpenIDConnectHandler($appName);
      return $handler->getSignedInAccountId($jwt);
    } else if (strcasecmp($authModel, 'HTPassword') === 0) {
      return HTPasswordHandler::getSignedInAccountId();
    } else if (strcasecmp($authModel, 'NONE') == 0 || $authModel == '') {
      return 1;
    } else {
      throw new Exception("Configuratiefout: onbekende waarde voor property $authModelPropertyName: '$authModel'.");
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
