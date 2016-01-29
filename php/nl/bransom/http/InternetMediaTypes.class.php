<?php

/**
 * Description of InternetMediaTypes (f.k.a. MIME types)
 *
 * @author Rob Bosman
 */
class InternetMediaTypes {

    private static $MEDIA_TYPES = array(
        // type => (tag, requiresEncoding)
        'application/atom+xml' => array('atom', true),
        'application/octet-stream' => array('bin', false),
        'image/bmp' => array('bmp', false),
        'text/css' => array('css', true),
        'text/csv' => array('csv', true),
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => array('docx', true),
        'multipart/form-data' => array('form', true),
        'image/gif' => array('gif', false),
        'text/html' => array('html', true),
        'image/jpeg' => array('jpg', false),
        'image/pjpeg' => array('jpg', false),
        'text/javascript' => array('js', true),
        'application/javascript' => array('js', true),
        'application/x-javascript' => array('js', true),
        'application/json' => array('json', true),
        'text/x-json' => array('json', true),
        'application/jsonrequest' => array('json', true),
        'image/png' => array('png', false),
        'application/rss+xml' => array('rss', true),
        'text/plain' => array('txt', true),
        'application/x-www-form-urlencoded' => array('url-form', true),
        'application/xhtml+xml' => array('xhtml', true),
        'text/xml' => array('xml', true),
        'application/xml' => array('xml', true),
        'application/x-xml' => array('xml', true)
    );

    public static function getTypeOfTag($givenTag) {
        $givenTag = strtolower($givenTag);
        foreach (self::$MEDIA_TYPES as $type => $info) {
            if (strcasecmp($info[0], $givenTag) == 0) {
                return $type;
            }
        }
        return NULL;
    }

    public static function getTagOfType($givenType) {
        $givenType = strtolower($givenType);
        // Strip-off anything after a ';'.
        $i = strpos($givenType, ';');
        if ($i !== FALSE) {
            $givenType = substr($givenType, 0, $i);
        }
        $givenType = str_replace(' ', '', $givenType);
        if (array_key_exists($givenType, self::$MEDIA_TYPES)) {
            return self::$MEDIA_TYPES[$givenType][0];
        } else {
            return NULL;
        }
    }

    /*
     * find a suitable media type in the 'Accept' HTTP-header of the request
     */
    public static function findSuitableType($httpAccept) {
        $httpAccept = strtolower($httpAccept);
        foreach (self::$MEDIA_TYPES as $type => $info) {
            if (strpos($httpAccept, $type) !== FALSE) {
                return $type;
            }
        }
        return NULL;
    }

    public static function requiresEncoding($givenType) {
        $givenType = strtolower($givenType);
        if (array_key_exists($givenType, self::$MEDIA_TYPES)) {
            return self::$MEDIA_TYPES[$givenType][1];
        }
        return false;
    }
}

?>