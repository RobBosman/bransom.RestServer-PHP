<?php

Bootstrap::import('nl.bransom.http.HttpResponseCodes');
Bootstrap::import('nl.bransom.http.InternetMediaTypes');

/**
 * Description of HttpResponder
 *
 * @author Rob Bosman
 */
class HttpResponder {

    private static $isShutdownHandlerEnabled = FALSE;

    public static function handleFatalErrorsOnShutdown() {
        // Intercept any fatal error and start buffering all output to prevent fatal errors form being echoed
        // prematurely.
        register_shutdown_function('HttpResponder::handleFatalError');
        ob_start();
        self::$isShutdownHandlerEnabled = TRUE;
    }

    public static function handleFatalError() {
        if (self::$isShutdownHandlerEnabled === TRUE) {
            $errorMessage = ob_get_clean();
            error_log("FATAL ERROR - $errorMessage");
            self::respond(HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR, $errorMessage, 'text/html');
        }
    }

    public static function respond($httpResponseCode, $errorDetails = NULL, $contentType = NULL) {
        self::$isShutdownHandlerEnabled = FALSE;

        if ($errorDetails == NULL) {
            $errorDetails = '';
        }

        // Check if any technical error has occurred (ignore certain errors).
        $lastError = error_get_last();
        if ((isset($lastError['message'])) and (stripos($lastError['message'], 'magic_quotes_gpc') === FALSE)) {
            // If so, then log it.
            $logMsg = $lastError['message'] . " in " . $lastError['file'] . '(' . $lastError['line'] . ')';
            error_log("LAST ERROR - $logMsg $errorDetails");

            // ...and adjust the response to make sure it replects an error.
            if ($httpResponseCode < HttpResponseCodes::errorCodesBeginAt) {
                $httpResponseCode = HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            }
            $errorDetails .= "\n<!-- " . $lastError['message'] . "\n<br/>## " . $lastError['file']
                    . '(' . $lastError['line'] . ") -->";
            // Append any available data.
            $bufferedOutput = ob_get_clean();
            if (strlen($bufferedOutput) > 0) {
                $errorDetails .= "<br/>$bufferedOutput";
            }
            $contentType = 'text/html';
        }

        $responseMessage = HttpResponseCodes::getMessage($httpResponseCode);
        header("HTTP/1.1 $httpResponseCode $responseMessage", true, $httpResponseCode);
        if (!HttpResponseCodes::isSuccessCode($httpResponseCode)) {
            if (HttpResponseCodes::canHaveBody($httpResponseCode)) {
                $contentTypeTag = InternetMediaTypes::getTagOfType($contentType);
                if ($contentTypeTag == 'xml') {
                    $response = '<?xml version="1.0"?>'
                            . "\n<error>"
                            . "<code>$httpResponseCode</code>"
                            . "<message>$responseMessage</message>";
                    if (strlen($errorDetails) > 0) {
                        $response .= "<details>$errorDetails</details>";
                    }
                    $response .= '</error>';
                } else {
                    $contentType = 'text/html';
                    $response = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">'
                            . "\n<html><head>"
                            . "<title>$httpResponseCode - $responseMessage</title>"
                            . '</head><body>'
                            . "<h1>$responseMessage</h1>";
                    if (strlen($errorDetails) > 0) {
                        if (stripos($errorDetails, '</html>') !== FALSE) {
                            $errorDetails = html_entity_decode($errorDetails);
                        }
                        $response .= "<p>$errorDetails</p>";
                    }
                    $response .= '</body></html>';
                }

                header('Content-type: ' . $contentType);
                echo $response;
            }
            exit;
        }
    }

}

?>