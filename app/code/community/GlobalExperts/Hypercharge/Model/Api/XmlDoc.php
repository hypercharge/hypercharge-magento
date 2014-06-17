<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE_AFL.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     GlobalExperts_Hypercharge
 * @copyright   Copyright (c) 2014 Global Experts GmbH (http://www.globalexperts.ch/)
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class GlobalExperts_Hypercharge_Model_Api_XmlDoc {

    /**
     * An xml parser
     * @var resource 
     */
    var $_parser = null;

    /**
     * The root node of the document, added by default
     * @var XMLDocElement 
     */
    var $document = null;

    /**
     * The parsing stack
     * @var array 
     */
    var $_stack = array();

    /**
     * Error list
     * @var array 
     */
    var $_errors = array();

    /**
     * Class constructor, Php5 style
     * @param type $options Options for the xml_parser
     */
    function __construct($options = null) {
        $this->_parser = xml_parser_create('');

        xml_set_object($this->_parser, $this);
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
        if (is_array($options))
            foreach ($options as $option => $value)
                xml_parser_set_option($this->_parser, $option, $value);

        xml_set_element_handler($this->_parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->_parser, '_characterData');
    }

    /**
     * Returns the errors array
     * @return array 
     */
    function getErrors() {
        return $this->_errors;
    }

    /**
     * Get the last error
     * @return string 
     */
    function getError() {
        return array_pop($this->_errors);
    }

    /**
     * Sets an error
     * @param string $error 
     */
    function setError($error) {
        if (is_string($error))
            $this->_errors[] = htmlentities($error);
    }

    /**
     * Loads and parses an XML string
     * @param string $string The xml string to parse
     * @return bool
     */
    function loadString($string) {
        return $this->_parse($string);
    }

    /**
     * Getter for the xml_parser
     * @return object 
     */
    function getParser() {
        return $this->_parser;
    }

    /**
     * Parser setter
     * @param object $parser 
     */
    function setParser($parser) {
        $this->_parser = $parser;
    }

    /**
     * Wrapper for the native parsing function with error handling
     * @param string $data The xml string to parse
     * @return bool
     */
    function _parse($data = '') {
        if (!xml_parse($this->_parser, $data)) {
            $this->setError('XML Parsing Error at '
                    . xml_get_current_line_number($this->_parser) . ':'
                    . xml_get_current_column_number($this->_parser) . ' - '
                    . xml_error_string(xml_get_error_code($this->_parser))
            );
            xml_parser_free($this->_parser);
            return false;
        }

        xml_parser_free($this->_parser);
        return true;
    }

    /**
     * Returns a string with the current stack value, as set by the 
     * _startElement function
     * @see _startElement()
     * @return string 
     */
    function _getStackLocation() {
        $return = '';
        foreach ($this->_stack as $stack)
            $return .= $stack . '->';

        return rtrim($return, '->');
    }

    /**
     * Start element handler for the native xml parser. 
     * It handles the opening of an xml tag
     * @param object $parser
     * @param string $name The name of the opened tag
     * @param array $attrs An array with the attributes to pass to 
     * the XMLDocElement object
     */
    function _startElement($parser, $name, $attrs = array()) {
        $count = count($this->_stack);
        if ($count == 0) { // No element added yet
            $classname = get_class($this) . 'Element';
            $this->document = new $classname($name, $attrs);

            $this->_stack = array('document');
        } else {
            $parent = $this->_getStackLocation();

            // Child addition
            eval('$this->' . $parent . '->addChild($name, $attrs, '
                    . $count . ');');
            //Stack update
            eval('$this->_stack[] = $name.\'[\'.(count($this->'
                    . $parent . '->' . $name . ') - 1).\']\';');
        }
    }

    /**
     * End element handler for the native xml parser
     * @param object $parser The xml parser resource
     * @param string $name The name of the closing xml tag
     */
    function _endElement($parser, $name) {
        array_pop($this->_stack);
    }

    /**
     * Data section handler for the xml parser
     * @param object $parser The xml parser resource
     * @param string $data The data within the xml tag 
     */
    function _characterData($parser, $data) {
        $tag = $this->_getStackLocation();

        eval('$this->' . $tag . '->_data .= $data;');
    }

}

