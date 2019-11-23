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

  private static $JWT_HEADER_FIELDS = array('typ', 'alg', 'kid');
  private static $JWT_PAYLOAD_FIELDS = array('name', 'nbf', 'exp', 'upn');

  static function validateJWT($jwt) {
    if ($jwt === NULL) {
      return NULL;
    }

    $jwtParts = explode('.', $jwt);
    if (count($jwtParts) !== 3) {
      throw new UnexpectedValueException('Incorrect JWT format.');
    }
    $jwtHeader = json_decode(base64_decode($jwtParts[0]));
    $jwtPayload = json_decode(base64_decode($jwtParts[1]));

    // Check if all expected fields are present.
    foreach (self::$JWT_HEADER_FIELDS as $field) {
      if (!isset($jwtHeader->$field)) {
        throw new UnexpectedValueException("The header of JWT ($jwtPayload->upn) does not contain field '$field'.");
      }
    }
    foreach (self::$JWT_PAYLOAD_FIELDS as $field) {
      if (!isset($jwtPayload->$field)) {
        throw new UnexpectedValueException("The payload of JWT ($jwtPayload->upn) does not contain field '$field'.");
      }
    }

    // Check if the JWT header is valid.
    if (strcasecmp($jwtHeader->typ, "JWT") !== 0) {
      throw new UnexpectedValueException("Unsupported JWT ($jwtPayload->upn) type $jwtHeader->typ.");
    }
    if (strcasecmp($jwtHeader->alg, "RS256") !== 0) {
      throw new UnexpectedValueException("Unsupported JWT ($jwtPayload->upn) signature algorithm: $jwtHeader->alg.");
    }

    // Check if the token is valid at this moment.
    if (time() < $jwtPayload->nbf) {
      throw new UnexpectedValueException("The JWT ($jwtPayload->upn) is not yet valid.");
    }
    if (time() >= $jwtPayload->exp) {
      throw new UnexpectedValueException("The JWT ($jwtPayload->upn) has expired.");
    }

    // Check if the issuer, tenant and client IDs are OK.
    if (strpos($jwtPayload->iss, OpenIDConnect::TENANT_ID) === FALSE) {
      throw new UnexpectedValueException("The JWT ($jwtPayload->upn) not valid for this app.");
    }
    if (strcmp($jwtPayload->tid, OpenIDConnect::TENANT_ID) !== 0) {
      throw new UnexpectedValueException("The JWT ($jwtPayload->upn) not valid for this app.");
    }
    if (strcmp($jwtPayload->aud, OpenIDConnect::CLIENT_ID) !== 0) {
      throw new UnexpectedValueException("The JWT ($jwtPayload->upn) not valid for this app.");
    }

    // Delegate signature verification to Firebase.
    return JWT::decode($jwt, OpenIDConnect::getOpenIDJWKS());
  }
}
