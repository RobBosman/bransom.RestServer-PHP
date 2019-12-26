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
  const OPENID_CONFIG_URL = 'https://login.microsoftonline.com/' . self::TENANT_ID . '/v2.0/.well-known/openid-configuration';
  const OPENID_CONFIG_AUTH_ENDPOINT_KEY = 'authorization_endpoint';
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

  public static function authenticate($redirectUrl, $scope, $hostedDomain) {
    $jwt = HttpUtil::getJWTFromHeader();
    $jwtPayload = self::getValidatedJWTPayload($jwt);
    if ($jwtPayload === NULL) {
      // This method is called twice in the authentication process:
      // 1) to send a (GET) token request to the OpenID provider
      // 2) to process the (POST) response of that request.
      
      // If no POST data was received, then the token has not yet been requested.
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        self::requestAccessToken($redirectUrl, $scope, $hostedDomain);
      }
      
      // OK, so now we are processing the token response.
      $receivedData = (object) filter_input_array(INPUT_POST);
      if (isset($receivedData->error)) {
        self::logErrorAndClearCache($receivedData->error);
        HttpUtil::replyError(HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR, $receivedData->error);
      } else if (!isset($receivedData->state)) {
        $errorMsg = "State not present.";
        self::logErrorAndClearCache($errorMsg);
        HttpUtil::replyError(HttpResponseCodes::HTTP_UNAUTHORIZED, $errorMsg);
      } else if ($receivedData->state != "state-" . self::getAntiForgeryStateToken()) {
        $errorMsg = "Invalid state: expected 'state-"
            . self::getAntiForgeryStateToken()
            . "' but got '$receivedData->state'.";
        self::logErrorAndClearCache($errorMsg);
        HttpUtil::replyError(HttpResponseCodes::HTTP_UNAUTHORIZED, $errorMsg);
      } else if (!isset($receivedData->access_token)) {
        $errorMsg = 'An error occurred while requesting a token from the OpenID provider.';
        self::logErrorAndClearCache($errorMsg);
        HttpUtil::replyError(HttpResponseCodes::HTTP_UNAUTHORIZED, $errorMsg);
      } else{
        // Temporarilly store the JWT in the session.
        SessionCache::set(self::$PARKED_JWT_CACHE_KEY, $receivedData->access_token);
      }
    }
  }

  static function getValidatedJWTPayload($jwt) {
    if ($jwt === NULL) {
      return NULL;
    }
    
    try {
      $nonce = "nonce-" . self::getAntiForgeryStateToken();
      return OpenIDTokenVerifier::validateJWT($jwt, $nonce);
    } catch (Exception $e) {
      self::logErrorAndClearCache($e->getMessage() . "\r\n" . $e->getTraceAsString());
    }
  }

  // Set the client ID, token state, and application name in the HTML while serving it.
  private static function requestAccessToken($redirectUri, $scope, $hostedDomain) {
    $nonce = self::getAntiForgeryStateToken(TRUE);
    $requestParams = array();
    $requestParams['client_id'] = self::CLIENT_ID;
    $requestParams['redirect_uri'] = $redirectUri;
    $requestParams['domain_hint'] = $hostedDomain;
    $requestParams['response_type'] = 'token';
    $requestParams['scope'] = $scope;
    $requestParams['nonce'] = "nonce-$nonce";
    $requestParams['state'] = "state-$nonce";
    $requestParams['response_mode'] = 'form_post';
    $openIdAuthEndpoint = self::getOpenIDConfig(self::OPENID_CONFIG_AUTH_ENDPOINT_KEY);
    $targetUrl = $openIdAuthEndpoint . (strpos($openIdAuthEndpoint, '?') === FALSE ? '?' : '&')
            . HttpUtil::toQueryString($requestParams);

    // Redirect to OpenID provider.
    header("Location: $targetUrl");
    exit;
  }

  /**
   * Create a state token to prevent request forgery.
   * Store it in the session for later validation.
   */
  private static function getAntiForgeryStateToken($recreate = FALSE) {
    $stateToken = SessionCache::get(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY);
    if ($stateToken === FALSE or $recreate) {
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
    if (!isset($openIDConfig->$key)) {
      $errorMsg = "Error fecthing '$key' from OpenID config:\n$openIDConfigJSON";
      self::logErrorAndClearCache($errorMsg);
      HttpUtil::replyError(HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR, $errorMsg);
      return NULL;
    }
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
