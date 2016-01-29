<?php

/**
 * Description of XmlConstants
 *
 * @author Rob Bosman
 */
class XmlConstants {

    /**
     * Prefix of XML-response when requesting a set of objects. E.g. when requesting entities named 'xyz',
     * then the root node of the response will be 'set_of_xyz'.
     * For example, fetching objects of entity 'xyz' gives
     *   <set_of_xyz>
     *     <xyz id="1"/>
     *     <xyz id="2"/>
     *   </set_of_xyz>
     */
    const SET_OF_ = 'set_of_';
    const AT = 'at';
    const ID = 'id';
    const TYPE = 'type';
    const SCOPE = 'scope';

    /**
     * Attribute names of input-XML.
     */
    const DELETED = 'deleted';

    /**
     * Attribute names of output-XML.
     */
    const CREATED_AT = 'created_at';
    const TERMINATED_AT = 'terminated_at';
    const PUBLISHED = 'published';

}
?>
