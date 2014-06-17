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

class GlobalExperts_Hypercharge_Model_Api_XmlDocElement {

    /**
     * Element attributes 
     * @var $_attributes
     */
    public $_attributes = array();

    /**
     * Element name
     * @var $_name
     */
    public $_name = '';

    /**
     * Element CDATA
     * @var $_data
     */
    public $_data = '';

    /**
     * Element children
     * @var $_children
     */
    public $_children = array();

    /**
     * Node level
     * @var $_level
     */
    public $_level = 0;

    /**
     * Php5 style constructor
     * @param string $name
     * @param array $attrs
     * @param int $level 
     */
    function __construct($name, $attrs = array(), $level = 0) {
        $this->_attributes = array_change_key_case($attrs, CASE_LOWER);
        $this->_name = strtolower($name);
        $this->_level = $level;
    }

    /**
     * Get the name of the element
     * @return string 
     */
    function name() {
        return $this->_name;
    }

    /**
     * Get the attributes of the element or a specific attribute
     * @param string $attribute Optional attribute to extract
     * @return mixed Array of attributes or single attribute
     */
    function attributes($attribute = null) {
        if (!isset($attribute))
            return $this->_attributes;

        return isset($this->_attributes[$attribute]) ? $this->_attributes[$attribute] : null;
    }

    /**
     * Get th element's data
     * @return string The element data
     */
    function data() {
        return $this->_data;
    }

    /**
     * Set the element's data
     * @param string $data 
     */
    function setData($data) {
        $this->_data = $data;
    }

    /**
     * Return the children of the element
     * @return array 
     */
    function children() {
        return $this->_children;
    }

    /**
     * Return the depth in the xml tree of the element
     * @return int 
     */
    function level() {
        return $this->_level;
    }

    /**
     * Add an attribute to the element
     * @param string $name
     * @param string $value 
     */
    function addAttribute($name, $value) {
        //add the attribute to the element, replace if it exists
        $this->_attributes[$name] = $value;
    }

    /**
     * Remove an attribute of the element
     * @param string $name The name of the attribute to remove
     */
    function removeAttribute($name) {
        unset($this->_attributes[$name]);
    }

    /**
     * Add a child to the current element
     * @param string $name
     * @param array $attrs
     * @param int $level
     * @return XMLDocElement The element that was added 
     */
    function &addChild($name, $attrs = array(), $level = null) {
        if (!isset($this->$name))
            $this->$name = array();

        if ($level == null)
            $level = ($this->_level + 1);

        $classname = get_class($this);
        $child = new $classname($name, $attrs, $level);

        $this->{$name}[] = & $child;
        $this->_children[] = & $child;

        return $child;
    }

    /**
     * Removes a child of the element
     * @param XMLDocElement $child 
     */
    function removeChild(&$child) {
        $name = $child->name();
        for ($i = 0, $n = count($this->_children); $i < $n; $i++)
            if ($this->_children[$i] == $child)
                unset($this->_children[$i]);

        for ($i = 0, $n = count($this->{$name}); $i < $n; $i++)
            if ($this->{$name}[$i] == $child)
                unset($this->{$name}[$i]);

        $this->_children = array_values($this->_children);
        $this->{$name} = array_values($this->{$name});
        unset($child);
    }

    /**
     * Parse a given path and return the element it refers
     * @param string $path
     * @return mixed The found element or boolean false 
     */
    function &getElementByPath($path) {
        $tmp = & $this;
        $parts = explode('/', trim($path, '/'));

        foreach ($parts as $node) {
            $found = false;
            foreach ($tmp->_children as $child)
                if ($child->_name == $node) {
                    $tmp = & $child;
                    $found = true;
                    break;
                }

            if (!$found)
                break;
        }

        return $found ? $tmp : false;
    }

    /**
     * Transforms the current element into a well-formed xml
     * @param boolean $whitespace Ident the xml with spaces?
     * @return string 
     */
    function toString($whitespace = true) {
        if ($whitespace)
            $out = "\n" . str_repeat("\t", $this->_level) . '<' . $this->_name;
        else
            $out = '<' . $this->_name;

        foreach ($this->_attributes as $attr => $value)
            $out .= ' ' . $attr . '="' . htmlspecialchars($value) . '"';

        if (empty($this->_children) && empty($this->_data))
            $out .= " />";
        else {
            if (!empty($this->_children)) {
                $out .= '>';

                foreach ($this->_children as $child)
                    $out .= $child->toString($whitespace);

                if ($whitespace)
                    $out .= "\n" . str_repeat("\t", $this->_level);
            }
            elseif (!empty($this->_data))
                $out .= '>' . htmlspecialchars($this->_data);

            $out .= '</' . $this->_name . '>';
        }

        return $out;
    }

}