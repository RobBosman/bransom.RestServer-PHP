<?php

/**
 * Description of RestUrlParams
 *
 * @author Rob Bosman
 */
class RestUrlParams {

    const SESSION_ID = '$clientID';
    const ENTITY_SEPARATOR = '/';
    /**
     * Name of query-string parameter (URL request) to indicate a specific timestamp.
     * e.g. http://.../REST/some-app/site.xml?$at=2011-09-01T11:20
     */
    const AT = '$at';
    const TIMESTAMP_FORMAT = '%04d-%02d-%02dT%02d:%02d:%02d';
    const ALL_TIMES = '*';
    /**
     * Name of query-string parameter (URL request) to indicate that only data that has been published (or not)
     * must be includedn in de response. Valid values are 'true' and 'false'.
     * e.g. http://.../REST/some-app/site.xml?$published=true
     */
    const PUBLISHED = '$published';
    /**
     * Name of query-string parameter (URL request) to indicate that the content (details) of certain nodes
     * must not be returned in the response, see class Scope. Some examples:
     *   http://.../REST/some-app/_account/69.xml?rol/$scope=PCao
     *   http://.../REST/some-app/_account/69.xml?$scope=PCAo(P)
     *   http://.../REST/some-app/_account/69.xml?$scope=PC(PC(PC(P)))A(P)o
     *   http://.../REST/some-app/businessunit.xml?$scope=PA()
     */
    const SCOPE = '$scope';
    /**
     * Name of query-string parameter (URL request) to indicate wether or not any binary data must be included.
     * e.g. http://.../REST/some-app/site.xml?item/$skipBinaries=true
     * Default is 'false'
     */
    const SKIP_BINARIES = '$skipBinaries';
    /**
     * Name of query-string parameter (URL request) that can be used to request a specific set of objects.
     * e.g. http://.../REST/some-app/site.xml?item/$id=12,15,23
     */
    const ID = '$id';
    const ID_SEPARATOR = ',';

    private static $FETCH_PARAM_NAMES;

    public static function isFetchParam($paramName) {
        if (self::$FETCH_PARAM_NAMES == NULL) {
            self::$FETCH_PARAM_NAMES = array(self::AT, self::PUBLISHED, self::SCOPE, self::SKIP_BINARIES, self::ID);
        }
        foreach (self::$FETCH_PARAM_NAMES as $validParamName) {
            if (strcasecmp($paramName, $validParamName) == 0) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public static function extractValue(array &$params, $key, $defaultValue = NULL) {
        foreach ($params as $paramName => $value) {
            if (strcasecmp($paramName, $key) == 0) {
                unset($params[$paramName]);
                return $value;
            }
        }
        return $defaultValue;
    }

    public static function parseBoolean($value) {
        if (($value === NULL) or (strlen($value) == 0) or (strcasecmp($value, 'false') == 0)) {
            return FALSE;
        } else if (strcasecmp($value, 'true') == 0) {
            return TRUE;
        } else {
            throw new Exception("Invalid boolean value '$value'; expected 'true' or 'false'.",
                    RestResponse::CLIENT_ERROR);
        }
    }

    public static function parseTimestamp($value) {
        if (($value == NULL) || (strlen($value) == 0)) {
            return NULL;
        } else if (strcasecmp($value, self::ALL_TIMES) == 0) {
            return self::ALL_TIMES;
        } else {
            $ts = sscanf($value, self::TIMESTAMP_FORMAT);
            if ($ts[0] == NULL) {
                throw new Exception("Invalid timestamp value '$value'; expected format 'yyyy-MM-ddTHH:mm:ss'.",
                        RestResponse::CLIENT_ERROR);
            }
            if ($ts[1] == NULL) {
                $ts[1] = 1;
            }
            if ($ts[2] == NULL) {
                $ts[2] = 1;
            }
            if ($ts[3] == NULL) {
                $ts[3] = 0;
            }
            if ($ts[4] == NULL) {
                $ts[4] = 0;
            }
            if ($ts[5] == NULL) {
                $ts[5] = 0;
            }
            return sprintf(self::TIMESTAMP_FORMAT, $ts[0], $ts[1], $ts[2], $ts[3], $ts[4], $ts[5]);
        }
    }

}
?>
