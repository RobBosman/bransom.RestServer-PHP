<?php

Bootstrap::import('nl.bransom.auth.OpenIDConnect.HttpUtil');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.OpenIDTokenVerifier');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.SessionCache');

/**
 * Description of OpenIDContext
 *
 * @author RobB
 */
class OpenIDConnect {

    const OPENID_CONFIG_URL_KEY = 'https://accounts.google.com/.well-known/openid-configuration';
    const OPENID_CONFIG_AUTH_ENDPOINT_KEY = 'authorization_endpoint';
    const OPENID_CONFIG_TOKEN_ENDPOINT_KEY = 'token_endpoint';
    const OPENID_CONFIG_JWKS_URI_KEY = 'jwks_uri';
    
    private static $ANTI_FORGERY_STATE_TOKEN_CACHE_KEY = array(
        'id' => 'AntiForgeryStateToken',
        'exp' => 600); // 10 minutes
    private static $OPENID_CONFIG_CACHE_KEY = array(
        'id' => 'OpenIDConfig',
        'exp' => 3600); // 1 hours
    private static $JWKS_CACHE_KEY = array(
        'id' => 'JWKS',
        'exp' => 3600); // 1 hours
    private static $PARKED_JWT_CACHE_KEY = array(
        'id' => 'ParkedJWT',
        'exp' => 10); // 10 seconds
    
    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
    
    public static function getParkedJWT() {
        return SessionCache::get(self::$PARKED_JWT_CACHE_KEY);
    }
    
    public function authenticate($redirectUrl, $hostedDomain, $legacyRealm = NULL) {
        $jwt = HttpUtil::getJWTFromHeader();
        $jwtPayload = self::getValidatedJWTPayload($jwt);
        if ($jwtPayload == NULL) {
            $requestError = filter_input(INPUT_GET, 'error');
            if (isset($requestError)) {
                self::logErrorAndClearCache($requestError);
                HttpUtil::replyError(500, $requestError);
            }

            $requestState = filter_input(INPUT_GET, 'state');
            $requestCode = filter_input(INPUT_GET, 'code');
            if (!isset($requestState)) {
                $this->requestAuthCode($redirectUrl, $hostedDomain, $legacyRealm);
            } else if ($requestState != $this->getAntiForgeryStateToken(FALSE)) {
                self::logErrorAndClearCache("Invalid state parameter: expected\n\t"
                        . $this->getAntiForgeryStateToken(FALSE)
                        . " but got\n\t$requestState.");
                HttpUtil::replyError(401, 'Invalid state parameter');
            } else if (isset($requestCode)) {
                $jwt = $this->exchangeCodeForJWT($requestCode, $redirectUrl);
                // Temporarilly store the JWT in the session.
                SessionCache::set(self::$PARKED_JWT_CACHE_KEY, $jwt);
            }
        }
    }

    public static function getValidatedJWTPayload($jwt) {
        if ($jwt != NULL) {
            try {
                return OpenIDTokenVerifier::getValidatedJWTPayload($jwt, self::getOpenIDJWKS());
            } catch (Exception $e) {
                self::logErrorAndClearCache($e->getMessage() . "\r\n" . $e->getTraceAsString());
            }
        }
        return NULL;
    }

    // Set the client ID, token state, and application name in the HTML while serving it.
    private function requestAuthCode($redirectUrl, $hostedDomain, $legacyRealm) {
        $requestParams = array();
        $requestParams['client_id'] = $this->clientId;
        $requestParams['response_type'] = 'code';
        $requestParams['scope'] = 'openid email'; // openid + email + profile
        $requestParams['redirect_uri'] = $redirectUrl;
        $requestParams['state'] = $this->getAntiForgeryStateToken(TRUE);
        // prompt =[optional] none | consent | select_account
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

    private function exchangeCodeForJWT($receivedCode, $redirectUrl) {
        // Ensure that there is no request forgery going on, and that the user sending us this connect request is
        // the user that was supposed to.
        $requestParams = array();
        $requestParams['code'] = $receivedCode;
        $requestParams['client_id'] = $this->clientId;
        $requestParams['client_secret'] = $this->clientSecret;
        $requestParams['redirect_uri'] = $redirectUrl;
        $requestParams['grant_type'] = 'authorization_code';
        $openIdTokenEndpoint = self::getOpenIDConfig(self::OPENID_CONFIG_TOKEN_ENDPOINT_KEY);
        $responseJSON = HttpUtil::processRequest($openIdTokenEndpoint, $requestParams);
        $response = json_decode($responseJSON, TRUE);
        if (isset($response['id_token'])) {
            return $response['id_token'];
        }
        return NULL;
    }
    
    /**
     * Create a state token to prevent request forgery.
     * Store it in the session for later validation.
     */
    private function getAntiForgeryStateToken($createIfAbsent) {
        $stateToken = SessionCache::get(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY);
        if ($stateToken === FALSE && $createIfAbsent) {
            $stateToken = md5(rand());
            SessionCache::set(self::$ANTI_FORGERY_STATE_TOKEN_CACHE_KEY, $stateToken);
        }
        return $stateToken;
    }
    
    private static function getOpenIDJWKS() {
        $jkwsJSON = SessionCache::get(self::$JWKS_CACHE_KEY);
        if ($jkwsJSON === FALSE) {
            $jwksUrl = self::getOpenIDConfig(self::OPENID_CONFIG_JWKS_URI_KEY);
            $jkwsJSON = HttpUtil::processRequest($jwksUrl);
            SessionCache::set(self::$JWKS_CACHE_KEY, $jkwsJSON);
        }
        return json_decode($jkwsJSON, TRUE);
    }
    
    private static function getOpenIDConfig($key) {
        $openIDConfigJSON = SessionCache::get(self::$OPENID_CONFIG_CACHE_KEY);
        if ($openIDConfigJSON == FALSE) {
            $openIDConfigJSON = HttpUtil::processRequest(self::OPENID_CONFIG_URL_KEY);
            SessionCache::set(self::$OPENID_CONFIG_CACHE_KEY, $openIDConfigJSON);
        }
        $openIDConfig = json_decode($openIDConfigJSON, TRUE);
        return $openIDConfig[$key];
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