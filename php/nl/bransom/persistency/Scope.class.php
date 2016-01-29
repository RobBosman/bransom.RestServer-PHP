<?php

/**
 * Use a 'scope' in data queries to specify teh ammount of details you want to fetch.
 * A scope is defined in four levels:
 * 
 *   PROPERTIES - properties of the target object.
 *   COMPONENTS - child objects 'owned' by the target object.
 *   ASSOCIATES - objects that have a many-to-many relationship of the target object.
 *   OWNERS     - parent objects 'owning' the target object. 
 * 
 * Specifying a scope can be done via its value, which is a combination of these characters:
 *
 *   'P' - include 'Properties'.
 *   'p' - include 'Properties', but skip LOB-properties (BLOB/CLOB).
 *   'C' - include fully expanded nodes of 'Components', using default nested scope.
 *   'c' or 'C()' - include reference nodes of 'Components'.
 *   'C(xxxx)' - include fully expanded nodes of 'Components', where xxxx is the nested scope.
 *   'a' or 'A()' - include reference nodes of 'Associates'.
 *   'A' - include fully expanded nodes of 'Associates', using default nested scope.
 *   'A(xxxx)' - include reference nodes of 'Associates', where xxxx is the nested scope.
 *   'O' - include fully expanded nodes of 'Owner' entity nodes (parents), using default nested scope.
 *   'o' or 'O()' - include reference nodes of 'Owner' entity nodes (parents).
 *   'O(xxxx)' - include fully expanded nodes of 'Owner' entity nodes (parents), where xxxx is the nested scope.
 *
 * @author Rob Bosman
 */
class Scope {

    const INCLUDES_NONE = 'NONE';
    const INCLUDES_REFS_ONLY = 'REFS_ONLY';
    const INCLUDES_ALL = 'ALL';

    const TAG_PROPERTIES = 'PROPERTIES';
    const TAG_COMPONENTS = 'COMPONENTS';
    const TAG_ASSOCIATES = 'ASSOCIATES';
    const TAG_OWNERS = 'OWNERS';

    const VALUE_P_ALL = 'P';
    const VALUE_P_REF = 'p';
    const VALUE_C_ALL = 'C';
    const VALUE_C_REF = 'c';
    const VALUE_A_ALL = 'A';
    const VALUE_A_REF = 'a';
    const VALUE_O_ALL = 'O';
    const VALUE_O_REF = 'o';
        
    private static $VALUE_MAP = array(
        self::VALUE_P_ALL => array(self::TAG_PROPERTIES, self::INCLUDES_ALL),
        self::VALUE_P_REF => array(self::TAG_PROPERTIES, self::INCLUDES_REFS_ONLY),
        self::VALUE_C_ALL => array(self::TAG_COMPONENTS, self::INCLUDES_ALL),
        self::VALUE_C_REF => array(self::TAG_COMPONENTS, self::INCLUDES_REFS_ONLY),
        self::VALUE_A_ALL => array(self::TAG_ASSOCIATES, self::INCLUDES_ALL),
        self::VALUE_A_REF => array(self::TAG_ASSOCIATES, self::INCLUDES_REFS_ONLY),
        self::VALUE_O_ALL => array(self::TAG_OWNERS, self::INCLUDES_ALL),
        self::VALUE_O_REF => array(self::TAG_OWNERS, self::INCLUDES_REFS_ONLY)
    );
    private static $NULL_SCOPE = NULL;
    private static $DEFAULT_SCOPE_VALUES = array(
        NULL => 'PCA',
        self::TAG_PROPERTIES => 'P',
        self::TAG_COMPONENTS => 'PCA',
        self::TAG_ASSOCIATES => 'P',
        self::TAG_OWNERS => ''
    );
    private static $DEFAULT_SCOPES = NULL;

    private static function init() {
        if (self::$NULL_SCOPE === NULL) {
            self::$NULL_SCOPE = new Scope();
            self::$DEFAULT_SCOPES = array();
            foreach (self::$DEFAULT_SCOPE_VALUES as $defaultUnitTag => $defaultValue) {
                if (strlen($defaultValue) > 0) {
                    self::$DEFAULT_SCOPES[$defaultUnitTag] = self::parseValue($defaultValue);
                } else {
                    self::$DEFAULT_SCOPES[$defaultUnitTag] = self::$NULL_SCOPE;
                }
            }
        }
    }

    private static function getNullScope() {
        self::init();
        return self::$NULL_SCOPE;
    }

    private static function getDefaultScope($unitTag) {
        self::init();
        if (!array_key_exists($unitTag, self::$DEFAULT_SCOPES)) {
            throw new Exception("Unsupported scope unit: '$unitTag'.", RestResponse::CLIENT_ERROR);
        }
        return self::$DEFAULT_SCOPES[$unitTag];
    }

    public static function parseValue($value) {
        if ($value === NULL) {
            return self::getDefaultScope(NULL);
        }
        $scope = new Scope();
        $remainingValue = $value;
        while (strlen($remainingValue) > 0) {
            $foundMatch = FALSE;
            foreach (self::$VALUE_MAP as $unitValue => $unitTagIncludesMap) {
                if (strpos($remainingValue, $unitValue) === 0) {
                    $unitTag = $unitTagIncludesMap[0];
                    if ((array_key_exists($unitTag, $scope->includes))
                            or (array_key_exists($unitTag, $scope->subScopes))) {
                        throw new Exception("Scope '$unitTag' is multiply defined in '$value'.",
                                RestResponse::CLIENT_ERROR);
                    }
                    $includes = $unitTagIncludesMap[1];
                    $remainingValue = substr($remainingValue, strlen($unitValue));
                    $subScope = self::parseSubScope($remainingValue);
                    if (($subScope != NULL) and ($includes != self::INCLUDES_ALL)) {
                        throw new Exception("Defining a sub-scope is not allowed for reference-only value.",
                                RestResponse::CLIENT_ERROR);
                    }
                    $scope->scopeValue .= $unitValue;
                    $scope->includes[$unitTag] = $includes;
                    $scope->subScopes[$unitTag] = $subScope;
                    $foundMatch = TRUE;
                    break;
                }
            }
            if (!$foundMatch) {
                throw new Exception("Illegal scope value(s) in '$value': '$remainingValue'.",
                        RestResponse::CLIENT_ERROR);
            }
        }
        return $scope;
    }

    private static function parseSubScope(&$value) {
        $start = strpos($value, '(');
        if (strpos($value, '(') == 0) {
            $nestLevel = 0;
            for ($i = 0; $i < strlen($value); $i++) {
                $c = $value[$i];
                if ($c == '(') {
                    $nestLevel++;
                } else if ($c == ')') {
                    $nestLevel--;
                    if ($nestLevel == 0) {
                        $subValue = substr($value, 1, $i - 1);
                        $value = substr($value, $i + 1);
                        if (strlen($subValue) > 0) {
                            return self::parseValue($subValue);
                        } else {
                            return self::getNullScope();
                        }
                    }
                }
            }
        }
        return NULL;
    }

    private $scopeValue;
    private $includes;
    private $subScopes;

    private function __construct() {
        $this->scopeValue = '';
        $this->includes = array();
        $this->subScopes = array();
    }

    public function getScopeValue() {
        return $this->scopeValue;
    }

    public function includes($unitTag) {
        if (array_key_exists($unitTag, $this->includes)) {
            return $this->includes[$unitTag];
        } else {
            return self::INCLUDES_NONE;
        }
    }

    public function getSubScope($unitTag) {
        $subScope = NULL;
        if (array_key_exists($unitTag, $this->subScopes)) {
            $subScope = $this->subScopes[$unitTag];
        }
        if ($subScope == NULL) {
            if ($this->includes($unitTag) == self::INCLUDES_ALL) {
                $subScope = self::getDefaultScope($unitTag);
            } else {
                $subScope = self::getNullScope();
            }
        }
        return $subScope;
    }

    /**
     * Checks if an object that was fetched with the given scope contains enough information to be updated.
     * If the scope is too restrictive, e.g. so that the object only contains references to its components, then
     * updating such object would delete all property values and its associations to related objects.
     *
     * @return Boolean
     */
    public function isUpdatable() {
        if (($this->includes(self::TAG_PROPERTIES) != self::INCLUDES_NONE)
                and ($this->includes(self::TAG_COMPONENTS) != self::INCLUDES_NONE)
                and ($this->includes(self::TAG_ASSOCIATES) != self::INCLUDES_NONE)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Checks if an object that was fetched with the given scope is a reference, containing only its object id.
     *
     * @return Boolean
     */
    public function isReferenceOnly() {
        if (($this->includes(self::TAG_PROPERTIES) == self::INCLUDES_NONE)
                and ($this->includes(self::TAG_COMPONENTS) == self::INCLUDES_NONE)
                and ($this->includes(self::TAG_ASSOCIATES) == self::INCLUDES_NONE)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Reduces include levels to prevent endless recursion.
     *
     * @param String $fetchedScopeValue
     * @return String new, combined fetchedScopeValue
     */
    public function removeRecursingScopeValues($fetchedScopeValue) {
        $combinedScopeValue = $fetchedScopeValue;
        if ($this->includes(self::TAG_COMPONENTS) == self::INCLUDES_ALL) {
            if (strpos($fetchedScopeValue, self::VALUE_C_ALL) === FALSE) {
                $combinedScopeValue .= self::VALUE_C_ALL;
            } else {
                $this->scopeValue = str_replace(self::VALUE_C_ALL, self::VALUE_C_REF, $this->scopeValue);
                $this->includes[self::TAG_COMPONENTS] = self::INCLUDES_REFS_ONLY;
                $this->subScopes[self::TAG_COMPONENTS] = NULL;
            }
        }
        if ($this->includes(self::TAG_ASSOCIATES) == self::INCLUDES_ALL) {
            if (strpos($fetchedScopeValue, self::VALUE_A_ALL) === FALSE) {
                $combinedScopeValue .= self::VALUE_A_ALL;
            } else {
                $this->scopeValue = str_replace(self::VALUE_A_ALL, self::VALUE_A_REF, $this->scopeValue);
                $this->includes[self::TAG_ASSOCIATES] = self::INCLUDES_REFS_ONLY;
                $this->subScopes[self::TAG_ASSOCIATES] = NULL;
            }
        }
        if ($this->includes(self::TAG_OWNERS) == self::INCLUDES_ALL) {
            if (strpos($fetchedScopeValue, self::VALUE_O_ALL) === FALSE) {
                $combinedScopeValue .= self::VALUE_O_ALL;
            } else {
                $this->scopeValue = str_replace(self::VALUE_O_ALL, self::VALUE_O_REF, $this->scopeValue);
                $this->includes[self::TAG_OWNERS] = self::INCLUDES_REFS_ONLY;
                $this->subScopes[self::TAG_OWNERS] = NULL;
            }
        }
        return $combinedScopeValue;
    }
}

?>
