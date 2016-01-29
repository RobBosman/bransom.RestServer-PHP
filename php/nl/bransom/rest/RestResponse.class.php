<?php

Bootstrap::import('nl.bransom.http.HttpResponder');
Bootstrap::import('nl.bransom.http.HttpResponseCodes');
Bootstrap::import('nl.bransom.rest.TemporaryIdMap');

/**
 * Description of RestResponse
 *
 * @author Rob Bosman
 */
class RestResponse {

    const SERVER_ERROR = 0;
    const CLIENT_ERROR = 1;
    const AUTH_ERROR = 2;
    const CREATED = 3;
    const UPDATED = 4;
    const DELETED = 5;
    const NOT_FOUND = 6;
    const OK = 7;

    private $restContent;
    private $restContentXml;
    private $restCode;
    private $expectedResponseTypeTag;
    private $characterEncoding;

    function __construct($restCode, $restContent = NULL, $responseTypeTag = NULL) {
        if ($restContent instanceof Exception) {
            // error response
            $this->restCode = $restContent->getCode();
            $this->restContent = $restContent;
        } else if ($restContent instanceof DOMDocument) {
            // response of read request
            $this->restCode = $restCode;
            $this->restContent = $restContent->saveXML();
            $this->restContentXml = $restContent->documentElement;
            $this->expectedResponseTypeTag = 'xml';
        } else if ($restContent instanceof DOMElement) {
            // response of read request
            $this->restCode = $restCode;
            $this->restContent = $restContent->ownerDocument->saveXML($restContent);
            $this->restContentXml = $restContent;
            $this->expectedResponseTypeTag = 'xml';
        } else if ($restContent instanceof TemporaryIdMap) {
            // response of update request: TemporaryIdMap
            $this->restCode = $restCode;
            $this->restContent = $restContent->toXmlString();
            $this->expectedResponseTypeTag = 'xml';
        } else {
            $this->restCode = $restCode;
            $this->restContent = $restContent;
            $this->expectedResponseTypeTag = $responseTypeTag;
        }

        if (($this->restCode == self::NOT_FOUND) and ($this->restContent == NULL)) {
            $this->restContent = 'The requested resource was not found on this server.';
        }
    }

    public function getRestCode() {
        return $this->restCode;
    }

    public function getResponseContentXml() {
        return $this->restContentXml;
    }

    public function getResponseContentHttp() {
        if ($this->restContent instanceof Exception) {
            return $this->restContent->getMessage() . "\n<!--\n" . $this->restContent->getTraceAsString() . "\n-->";
        } else {
            return $this->restContent;
        }
    }

    public function respond($expectedResponseTypeTag = NULL, $httpAccept = NULL, $cacheControl = NULL) {
        if ($this->expectedResponseTypeTag == NULL) {
            $this->expectedResponseTypeTag = $expectedResponseTypeTag;
        }
        $contentType = $this->getHttpMediaType($httpAccept);

        // Reply HTTP response code and headers.
        $httpResponseCode = $this->getHttpResponseCode();
        HttpResponder::respond($httpResponseCode, $this->getResponseContentHttp(), $contentType);
        if (InternetMediaTypes::requiresEncoding($contentType)) {
            $this->characterEncoding = 'utf-8';
            header("Content-type: $contentType; charset=" . $this->characterEncoding);
        } else {
            header("Content-type: $contentType");
        }
        // Set cache-control header.
        if ($cacheControl != NULL) {
            header("Cache-Control: $cacheControl");
        } else {
            // No caching!
            header("Cache-Control: no-cache, no-store, max-age=0");
            header("Pragma: no-cache");
        }

        // Reply the content.
        echo $this->getResponseContentHttp();
    }

    private function getHttpMediaType($httpAccept) {
        // if the response media type was requested via the URI...
        $mediaType = NULL;
        if ($this->expectedResponseTypeTag != NULL) {
            // ...then get the associated InternetMediaType
            $mediaType = InternetMediaTypes::getTypeOfTag($this->expectedResponseTypeTag);
        } else if ($httpAccept != NULL) {
            // ...else find a suitable media type in the 'Accept' HTTP-header of the request
            $mediaType = InternetMediaTypes::findSuitableType($httpAccept);
        }
        if ($mediaType == NULL) {
            $mediaType = 'text/html';
        }
        return $mediaType;
    }

    private function getHttpResponseCode() {
        switch ($this->restCode) {
            case self::OK:
                return HttpResponseCodes::HTTP_OK;
            case self::NOT_FOUND:
                return HttpResponseCodes::HTTP_NOT_FOUND;
            case self::CREATED:
                return HttpResponseCodes::HTTP_CREATED;
            case self::UPDATED:
                return HttpResponseCodes::HTTP_ACCEPTED;
            case self::DELETED:
                return HttpResponseCodes::HTTP_ACCEPTED;
            case self::CLIENT_ERROR:
                return HttpResponseCodes::HTTP_BAD_REQUEST;
            case self::AUTH_ERROR:
                return HttpResponseCodes::HTTP_FORBIDDEN;
            case self::SERVER_ERROR:
                return HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            default:
                throw new Exception("Unsupported response code: $this->restCode.");
        }
    }
}
?>
