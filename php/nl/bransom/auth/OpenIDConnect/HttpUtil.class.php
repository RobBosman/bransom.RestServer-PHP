<?php

/**
 * Description of HttpUtil
 *
 * @author RobB
 */
class HttpUtil {

    public static function getJWTFromHeader() {
        // Get JWT from the HTTP Authorization header.
        $httpHeaders = getallheaders();
        if (isset($httpHeaders['Authorization']) &&strpos($httpHeaders['Authorization'], 'Bearer ') === 0) {
            return substr($httpHeaders['Authorization'], strlen('Bearer '));
        }
        return NULL;
    }

    public static function toQueryString($requestParams) {
        $queryParams = array();
        foreach ($requestParams as $key => $value) {
            $queryParams[] = "$key=" . urlencode($value);
        }
        return implode('&', $queryParams);
    }

    public static function processRequest($url, $postParams = NULL) {
        error_reporting(E_ERROR);

        $curl = curl_init($url);
        if ($postParams != NULL) {
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, self::toQueryString($postParams));
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        $response = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $responseHeaderSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeader = substr($response, 0, $responseHeaderSize);
        $responseBody = substr($response, $responseHeaderSize);
        curl_close($curl);

        error_reporting(E_ALL | E_NOTICE | E_WARNING | E_STRICT);

        $lastError = error_get_last();
        if (is_array($lastError)) {
            self::replyError($responseCode, $lastError['message']);
        }

        return $responseBody;
    }

    /**
     * @param type $responseCode
     * @param type $errorMessage
     */
    public static function replyError($responseCode, $errorMessage) {
        header("HTTP/1.1 $responseCode $errorMessage", TRUE, $responseCode);
        header("Content-type: text/html");
        echo <<<EOT
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html>
<head><title>$responseCode - $errorMessage</title></head>
<body><h1>$errorMessage</h1></body>
</html>
EOT;
        exit;
    }
}