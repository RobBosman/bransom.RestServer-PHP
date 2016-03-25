<?php

Bootstrap::import('nl.bransom.http.HttpResponder');
Bootstrap::import('nl.bransom.http.HttpResponseCodes');
Bootstrap::import('nl.bransom.http.InternetMediaTypes');
Bootstrap::import('nl.bransom.rest.RestController');
Bootstrap::import('nl.bransom.rest.RestResponse');

/**
 * Description of HttpRequestHandler
 *
 * @author Rob Bosman
 */
class HttpRequestHandler {

    private static $METHOD_PARAM = '$method';

    /**
     * @param $method
     * @param $restTarget - [webitems, site.xml]
     * @param $queryString - name=xyz&blobs=false
     * @param $httpAccept
     * @param $cacheControl
     * @param $requestParams
     * @param $requestContent
     * @param $requestContentType
     */
    public function dispatchRequest($method, $restTarget, $queryString, $httpAccept, $cacheControl, $requestParams,
            &$requestContent = NULL, $requestContentType = NULL) {
        try {
            // Get the request parameters and content.
            $params = NULL;
            $content = NULL;
            if (($requestContent != NULL) and ($requestContentType != NULL)
                    and (strcasecmp(InternetMediaTypes::getTagOfType($requestContentType), 'xml') == 0)) {
                $params = $requestParams;
                $content = new DOMDocument();
                $content->loadXML($requestContent);
                if ($content->documentElement == NULL) {
                    throw new Exception("Error loading XML payload of request.");
                }
            } else {
                // Split the request parameters: if a parameter appears in the QueryString then put it in 'params',
                // otherwise put it in 'content'.
                $params = array();
                $content = array();
                // Get an array with the names of all queryString parameters.
                $queryParamNames = explode('&', preg_replace('/=[^&^=]*/', '', urldecode($queryString)));
                // Parameternames in $requestParams may have been modified: 'abc.def=pqr' => 'abc_def=pqr', see
                // http://php.net/manual/en/language.variables.external.php.
                // Patch $queryParamNames to compensate for this.
                $queryParamNamesMap = array();
                foreach ($queryParamNames as $queryParamName) {
                    $patchedName = preg_replace('/[\.\ ]/', '_', $queryParamName);
                    $queryParamNamesMap[$patchedName] = $queryParamName;
                }
                foreach ($requestParams as $name => $value) {
                    $patchedName = preg_replace('/[\.\ ]/', '_', $name);
                    if (array_key_exists($patchedName, $queryParamNamesMap)) {
                        $name = $queryParamNamesMap[$patchedName];
                        $params[$name] = $value;
                    } else {
                        $content[$name] = $value;
                    }
                }
            }
            
            $params['spam'] = 'boe!';
            
            // Filter QueryString parameters that are to be ignored. Also correct the method (POST/GET) here.
            foreach ($params as $name => $value) {
                if (strcasecmp($name, self::$METHOD_PARAM) == 0) {
                    $method = $value;
                    unset($params[$name]);
                }
            }

            // Determine the response media type.
            $expectedResponseTypeTag = $this->getRequestedMediaTypeTag($restTarget);

            // Process the request.
            $restController = new RestController($params);
            $restResponse = $restController->process($method, $restTarget, $content);

            $restResponse->respond($expectedResponseTypeTag, $httpAccept, $cacheControl);
        } catch (Exception $e) {
            Bootstrap::logException($e);
            HttpResponder::respond(HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage() . "\n<!--\n"
                    . htmlspecialchars($e->getTraceAsString()) . "\n-->");
        }
    }

    /*
     * e.g. /webitems/REST/index.php and /webitems/REST/dir1/dir2/test.json gives dir1/dir2/test.json
     */
    public static function getRestTarget($targetUri, $phpSelfUri) {
        // Remove all parameters from the URI.
        $i = strpos($targetUri, '?');
        if ($i !== FALSE) {
            $targetUri = substr($targetUri, 0, $i);
        }

        // Remove parent-dir navigation from the URL, e.g. /abc/def/../pqr => /abc/pqr.
        $targetUri = preg_replace('|[^/\\\\]*[/\\\\]\.\.[/\\\\]|', '', $targetUri);

        // Strip-off any starting slash.
        if ((strlen($targetUri) > 0) and ($targetUri[0] == '/')) {
            $targetUri = substr($targetUri, 1);
        }
        if ((strlen($phpSelfUri) > 0) and ($phpSelfUri[0] == '/')) {
            $phpSelfUri = substr($phpSelfUri, 1);
        }

        // Strip-off the web path part of the request to isolate the REST target path.
        $i = 0;
        while (($i < min(strlen($phpSelfUri), strlen($targetUri))) and ($phpSelfUri[$i] == $targetUri[$i])) {
            $i++;
        }
        if ($i > 0) {
            $targetUri = substr($targetUri, $i);
        }

        // Strip-off any terminating slash.
        $i = strlen($targetUri) - 1;
        if ($targetUri[$i] == '/') {
            $targetUri = substr($targetUri, 0, $i);
        }

        if (strlen($targetUri) > 0) {
            return explode('/', $targetUri);
        } else {
            return array();
        }
    }

    private function getRequestedMediaTypeTag(&$restTarget) {
        $mediaTypeTag = NULL;
        // Check if the last part of the REST target ends with an extension indicating a known response media type.
        if (count($restTarget) != 0) {
            // Check any extension of the URL path.
            $lastIndex = count($restTarget) - 1;
            $lastDotPos = strrpos($restTarget[$lastIndex], '.');
            if ($lastDotPos !== FALSE) {
                $mediaTypeTag = substr($restTarget[$lastIndex], $lastDotPos + 1);
                // Check if the tag is known.
                if (InternetMediaTypes::getTypeOfTag($mediaTypeTag) == NULL) {
                    throw new Exception("Requested media type is not supported: '$mediaTypeTag'.",
                            RestResponse::CLIENT_ERROR);
                }
                // Strip-off the extension from the last REST target.
                $restTarget[$lastIndex] = substr($restTarget[$lastIndex], 0, $lastDotPos);
            }
        }
        return $mediaTypeTag;
    }

}