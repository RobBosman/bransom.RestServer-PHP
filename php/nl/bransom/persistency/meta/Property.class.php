<?php

/**
 * Description of Property
 *
 * @author Rob Bosman
 */
class Property {

    const KEY_NONE = '';
    const KEY_PRIMARY = 'primary';
    const KEY_FOREIGN = 'foreign';
    const KEY_UNIQUE = 'unique';
    const TYPE_DOUBLE = 'double';
    const TYPE_INTEGER = 'integer';
    const TYPE_TEXT = 'text';
    const TYPE_BINARY = 'binary';
    const TYPE_TIMESTAMP = 'timestamp';

    private $name;
    private $keyIndicator;
    private $typeIndicator;
    private $defaultValue;

    function __construct($propertyName, $metaDataColumn) {
        $this->name = $propertyName;
        $this->keyIndicator = self::parseKeyIndicator($metaDataColumn['Key']);
        $this->typeIndicator = self::parseTypeIndicator($metaDataColumn['Type']);
        $this->defaultValue = $metaDataColumn['Default'];
    }

    public function getName() {
        return $this->name;
    }

    public function getKeyIndicator() {
        return $this->keyIndicator;
    }

    public function getTypeIndicator() {
        return $this->typeIndicator;
    }

    public function getDefaultValue() {
        return $this->defaultValue;
    }

    private static function parseKeyIndicator($keyDescription) {
        if (strlen($keyDescription) == 0) {
            return self::KEY_NONE;
        } else if (strripos($keyDescription, 'PRI') !== FALSE) {
            return self::KEY_PRIMARY;
        } else if (strripos($keyDescription, 'MUL') !== FALSE) {
            return self::KEY_FOREIGN;
        } else if (strripos($keyDescription, 'UNI') !== FALSE) {
            return self::KEY_UNIQUE;
        }
        throw new Exception("Key indicator '$keyDescription' of property '$this->name' is not supported.");
    }

    private static function parseTypeIndicator($typeDescription) {
        if ((stripos($typeDescription, 'float') !== FALSE)
                || (stripos($typeDescription, 'double') !== FALSE)) {
            return self::TYPE_DOUBLE;
        } else if ((strripos($typeDescription, 'int(') !== FALSE)
                || (stripos($typeDescription, 'decimal') !== FALSE)
                || (stripos($typeDescription, 'year') !== FALSE)) {
            return self::TYPE_INTEGER;
        } else if ((strripos($typeDescription, 'char') !== FALSE)
                || (stripos($typeDescription, 'text') !== FALSE)) {
            return self::TYPE_TEXT;
        } else if ((strripos($typeDescription, 'blob') !== FALSE)
                || (stripos($typeDescription, 'binary') !== FALSE)) {
            return self::TYPE_BINARY;
        } else if ((strripos($typeDescription, 'date') !== FALSE)
                || (strripos($typeDescription, 'timestamp') !== FALSE)) {
            return self::TYPE_TIMESTAMP;
        }
        throw new Exception("Data type '$typeDescription' of property '$this->name' is not supported.");
    }
}

?>
