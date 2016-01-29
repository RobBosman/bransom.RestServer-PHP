<?php

require_once Bootstrap::phpRootDir() . '/Firebase/PHP-JWT/Authentication/JWT.php';
Bootstrap::import('nl.bransom.auth.OpenIDConnect.HttpUtil');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.OpenIDConnect');
Bootstrap::import('nl.bransom.auth.OpenIDConnect.SessionCache');

/**
 * Description of OpenIDTokenVerifier
 *
 * @author RobB
 */
class OpenIDTokenVerifier {
    
    private static $JWT_CERTS_CACHE_KEY = array('id' => 'JWT_CERTS', 'exp' => 60);
    
    public static function getRawJWTPayload($jwt) {
        $jwtParts = explode('.', $jwt);
        if (count($jwtParts) === 3) {
            return base64_decode($jwtParts[1]);
        }
        return NULL;
    }
    
    public static function getValidatedJWTPayload($jwt, $jwks) {
        if ($jwt == NULL) {
            return NULL;
        }

        $payload = NULL;
        if ($payload == NULL) {
            $payload = self::verifySignature($jwt, $jwks);
        }
        if ($payload == NULL) {
            $payload = self::verifySignatureFirebase($jwt);
        }
        if ($payload == NULL) {
            $payload = self::verifySignatureAtGoogle($jwt);
        }
        return $payload;
    }
    
    private static function verifySignature($jwt, $jwks) {
        $jwtParts = explode('.', $jwt);
        if (count($jwtParts) !== 3) {
            throw new UnexpectedValueException('Incorrect JWT format.');
        }
        $jwtPayload = base64_decode($jwtParts[1]);
        $payload = json_decode($jwtPayload, TRUE);
        $email = $payload['email'];
        
        // Check if the token is still valid.
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new UnexpectedValueException("The JWT ($email) has expired.");
        }
        
        $jwtHeader = base64_decode($jwtParts[0]);
        $header = json_decode($jwtHeader, TRUE);
        if ($header['alg'] != "RS256") {
            throw new UnexpectedValueException("Unsupported JWT ($email) signature algorithm: " . $header['alg'] . '.');
        }
        $theKey = FALSE;
        foreach ($jwks['keys'] as $key) {
            if (strcasecmp ($key['kid'], $header['kid']) == 0) {
                $theKey = $key;
                break;
            }
        }
        if ($theKey === FALSE) {
            // throw new UnexpectedValueException("Cannot find JWT ($email) signature key.\n$jwt");
            error_log("Cannot find JWT ($email) signature key.\n$jwt");
            return NULL;
        }

//        // TODO - implement!
//        $jwtSignature = base64_decode($jwtParts[2]);
//        throw new Exception('TO BE IMPLEMENTED: verify JWT signature');

        // Delegate signature verification to Firebase.
        return self::verifySignatureFirebase($jwt);
    }
    
    private static function verifySignatureFirebase($jwt) {
        $jwtCertsJSON = SessionCache::get(self::$JWT_CERTS_CACHE_KEY);
        if ($jwtCertsJSON === FALSE) {
            $jwtCertsJSON = HttpUtil::processRequest('https://www.googleapis.com/oauth2/v1/certs');
            SessionCache::set(self::$JWT_CERTS_CACHE_KEY, $jwtCertsJSON);
        }
        $jwtCerts = json_decode($jwtCertsJSON, TRUE);
        return JWT::decode($jwt, $jwtCerts);
    }
    
    private static function verifySignatureAtGoogle($jwt) {
        $response = HttpUtil::processRequest('https://www.googleapis.com/oauth2/v1/tokeninfo',
                array('id_token' => $jwt));
        return json_decode($response, TRUE);
    }
}