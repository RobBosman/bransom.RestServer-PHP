<?php

Bootstrap::import('nl.bransom.auth.OpenIDConnect.HttpUtil');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.OpenIDTokenVerifier');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.SessionCache');
Bootstrap::import('nl.bransom.http.HttpResponseCodes');

/**
 * Description of OpenIDConnect
 *
 * @author RobB
 */
class OpenIDConnect {

  const TENANT_ID = 'b44ed446-bdd4-46ab-a5b3-95ccdb7d4663';
  const CLIENT_ID = '348af39a-f707-4090-bb0a-9e4dca6e4138';
  const CLIENT_SECRET = '_L2w?hG1ugvVch2i7GVC.Nji_50a64N?';
  const OPENID_CONFIG_URL = 'https://login.microsoftonline.com/valori.nl/.well-known/openid-configuration';
  const OPENID_CONFIG_AUTH_ENDPOINT_KEY = 'authorization_endpoint';
  const OPENID_CONFIG_TOKEN_ENDPOINT_KEY = 'token_endpoint';
  const OPENID_CONFIG_JWKS_URI_KEY = 'jwks_uri';

  private static $OPENID_CONFIG_CACHE_KEY = array(
      'id' => 'OpenIDConfig',
      'exp' => 3600); // 1 hours
  private static $JWKS_CACHE_KEY = array(
      'id' => 'JWKS',
      'exp' => 3600); // 1 hours
  private static $ANTI_FORGERY_STATE_TOKEN_CACHE_KEY = array(
      'id' => 'AntiForgeryStateToken',
      'exp' => 600); // 10 minutes
  private static $PARKED_JWT_CACHE_KEY = array(
      'id' => 'ParkedJWT',
      'exp' => 10); // 10 seconds

  public static function getParkedJWT() {
    return SessionCache::get(self::$PARKED_JWT_CACHE_KEY);
  }

  public static function authenticate($redirectUrl, $hostedDomain, $legacyRealm = NULL) {
    $jwt = HttpUtil::getJWTFromHeader();
    $jwtPayload = self::getValidatedJWTPayload($jwt);
    if ($jwtPayload === NULL) {
      $requestError = filter_input(INPUT_GET, 'error');
      if (isset($requestError)) {
        self::logErrorAndClearCache($requestError);
        HttpUtil::replyError(HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR, $requestError);
      }

      $requestState = filter_input(INPUT_GET, 'state');
      $requestCode = filter_input(INPUT_GET, 'code');
      if (!isset($requestState)) {
        self::requestAuthCode($redirectUrl, $hostedDomain, $legacyRealm);
      } else if ($requestState != self::getAntiForgeryStateToken(FALSE)) {
        self::logErrorAndClearCache("Invalid state parameter: expected '"
                . self::getAntiForgeryStateToken(FALSE)
                . "' but got '$requestState'.\n$_SERVER[REQUEST_URI]");
        HttpUtil::replyError(HttpResponseCodes::HTTP_UNAUTHORIZED, 'Invalid state parameter');
      } else if (isset($requestCode)) {
        $jwt = self::exchangeCodeForJWT($requestCode, $redirectUrl);
        // Temporarilly store the JWT in the session.
        SessionCache::set(self::$PARKED_JWT_CACHE_KEY, $jwt);
      }
    }
  }

  static function getValidatedJWTPayload($jwt) {
    if ($jwt === NULL) {
      return NULL;
    }
    
    try {
      return OpenIDTokenVerifier::validateJWT($jwt);
    } catch (Exception $e) {
      self::logErrorAndClearCache($e->getMessage() . "\r\n" . $e->getTraceAsString());
    }
  }

  // Set the client ID, token state, and application name in the HTML while serving it.
  private static function requestAuthCode($redirectUrl, $hostedDomain, $legacyRealm) {
    $requestParams = array();
    $requestParams['client_id'] = self::CLIENT_ID;
    $requestParams['response_type'] = 'code';
    $requestParams['scope'] = 'openid'; // openid + profile
    $requestParams['redirect_uri'] = $redirectUrl;
    $requestParams['state'] = self::getAntiForgeryStateToken(TRUE);
    // prompt = [optional] none | consent | select_account
    // login_hint = [optional] ...
    // display = [optional] page | popup | touch | wap
    // access_type = [optional] offline | online
    // include_granted_scopes = [optional] true | false
    $requestParams['hd'] = $hostedDomain;
    if ($legacyRealm != NULL && strpos($redirectUrl, $legacyRealm) !== FALSE) {
      $requestParams['openid.realm'] = $legacyRealm;
    }

    $openIdAuthEndpoint = self::getOpenIDConfig(self::OPENID_CONFIG_AUTH_ENDPOINT_KEY);
    $targetUrl = $openIdAuthEndpoint . (strpos($openIdAuthEndpoint, '?') === FALSE ? '?' : '&')
            . HttpUtil::toQueryString($requestParams);

    // Redirect to OpenID provider.
    header("Location: $targetUrl");
    exit;
  }

  private static function exchangeCodeForJWT($receivedCode, $redirectUrl) {
    // Ensure that there is no request forgery going on, and that the user sending us this connect request is
    // the user that was supposed to.
    $requestParams = array();
    $requestParams['code'] = $receivedCode;
    $requestParams['client_id'] = self::CLIENT_ID;
    $requestParams['client_secret'] = self::CLIENT_SECRET;
    $requestParams['redirect_uri'] = $redirectUrl;
    $requestParams['grant_type'] = 'authorization_code';
    $openIdTokenEndpoint = self::getOpenIDConfig(self::OPENID_CONFIG_TOKEN_ENDPOINT_KEY);
    $responseJSON = HttpUtil::processRequest($openIdTokenEndpoint, $requestParams);
    $response = json_decode($responseJSON);
    if (isset($response->id_token)) {
      return $response->id_token;
    }
    return NULL;
  }

  /**
   * Create a state token to prevent request forgery.
   * Store it in the session for later validation.
   */
  private static function getAntiForgeryStateToken($createIfAbsent) {
    $stateToken = SessionCache::get(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY);
    if ($stateToken === FALSE && $createIfAbsent) {
      $stateToken = md5(rand());
      SessionCache::set(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY, $stateToken);
    }
    return $stateToken;
  }

  private static function getOpenIDConfig($key) {
    $openIDConfigJSON = SessionCache::get(self::$OPENID_CONFIG_CACHE_KEY);
    if ($openIDConfigJSON == FALSE) {
      $openIDConfigJSON = HttpUtil::processRequest(self::OPENID_CONFIG_URL);
      SessionCache::set(self::$OPENID_CONFIG_CACHE_KEY, $openIDConfigJSON);
    }
    $openIDConfig = json_decode($openIDConfigJSON);
    return $openIDConfig->$key;
  }

  static function getOpenIDJWKS() {
    $jkwsJSON = SessionCache::get(self::$JWKS_CACHE_KEY);
    if ($jkwsJSON === FALSE) {
      $jwksUrl = self::getOpenIDConfig(self::OPENID_CONFIG_JWKS_URI_KEY);
      $jkws = self::fetchJwks($jwksUrl);
      $jkwsJSON = json_encode($jkws);
      SessionCache::set(self::$JWKS_CACHE_KEY, $jkwsJSON);
    }
    return json_decode($jkwsJSON, TRUE);
  }

  static function fetchJwks($jwksUrl) {
    $jkwsKeysJSON = HttpUtil::processRequest($jwksUrl);
    $jwksKeys = json_decode($jkwsKeysJSON);
    $jwks = array();
    foreach ($jwksKeys->keys as $key) {
      $kid = $key->kid;
      $x5c = $key->x5c[0];
      $jwks[$kid] = "-----BEGIN CERTIFICATE-----\n$x5c\n-----END CERTIFICATE-----";
    }
    return $jwks;
  }

  private static function logErrorAndClearCache($errorMessage) {
    error_log($errorMessage);
    // Just to be sure: clear the cached config and JWKS.
    SessionCache::clear(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY);
    SessionCache::clear(self::$OPENID_CONFIG_CACHE_KEY);
    SessionCache::clear(self::$JWKS_CACHE_KEY);
    SessionCache::clear(self::$PARKED_JWT_CACHE_KEY);
  }
}
