<?php
/**
 * Xml parser
 * 
 * @author Adrian Rosian
 */
if(!function_exists('xml_parser_create'))
        die('Xml parser expat extension is required to parse xml');

/**
 * Class representing an XML document, replacement for Php5's SimpleXML
 */
class XMLDoc
{
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
    function __construct($options = null)
    {
        $this->_parser = xml_parser_create('');

        xml_set_object($this->_parser, $this);
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
        if(is_array($options))
                foreach($options as $option => $value)
                    xml_parser_set_option($this->_parser, $option, $value);

        xml_set_element_handler($this->_parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->_parser, '_characterData');
    }
    
    /**
     * Returns the errors array
     * @return array 
     */
    function getErrors()     
    {
        return $this->_errors;
    }
    
    /**
     * Get the last error
     * @return string 
     */
    function getError()
    {
        return array_pop($this->_errors);
    }

    /**
     * Sets an error
     * @param string $error 
     */
    function setError($error)
    {
        if(is_string($error))
            $this->_errors[] = htmlentities($error);
    }
    
    /**
     * Loads and parses an XML string
     * @param string $string The xml string to parse
     * @return bool
     */
    function loadString($string)
    {
        return $this->_parse($string);
    }

    /**
     * Getter for the xml_parser
     * @return object 
     */
    function getParser()
    {
        return $this->_parser;
    }

    /**
     * Parser setter
     * @param object $parser 
     */
    function setParser($parser)
    {
        $this->_parser = $parser;
    }

    /**
     * Wrapper for the native parsing function with error handling
     * @param string $data The xml string to parse
     * @return bool
     */
    function _parse($data = '')
    {
        if(!xml_parse($this->_parser, $data))
        {
            $this->setError('XML Parsing Error at '                 
                . xml_get_current_line_number($this->_parser). ':'
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
    function _getStackLocation()
    {
        $return = '';
        foreach($this->_stack as $stack)
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
    function _startElement($parser, $name, $attrs = array())
    {
        $count = count($this->_stack);
        if($count == 0) // No element added yet
        {
            $classname = get_class($this) . 'Element';
            $this->document = new $classname($name, $attrs);

            $this->_stack = array('document');
        }
        else
        {
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
    function _endElement($parser, $name)
    {
        array_pop($this->_stack);
    }

    /**
     * Data section handler for the xml parser
     * @param object $parser The xml parser resource
     * @param string $data The data within the xml tag 
     */
    function _characterData($parser, $data)
    {
        $tag = $this->_getStackLocation();

        eval('$this->' . $tag . '->_data .= $data;');
    }
}

/**
 * Class representing an XML element
 */
class XMLDocElement
{
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
    function __construct($name, $attrs = array(), $level = 0)
    {
        $this->_attributes = array_change_key_case($attrs, CASE_LOWER);
        $this->_name = strtolower($name);
        $this->_level = $level;
    }

    /**
     * Get the name of the element
     * @return string 
     */
    function name()
    {
        return $this->_name;
    }

    /**
     * Get the attributes of the element or a specific attribute
     * @param string $attribute Optional attribute to extract
     * @return mixed Array of attributes or single attribute
     */
    function attributes($attribute = null)
    {
        if(!isset($attribute))
            return $this->_attributes;

        return isset($this->_attributes[$attribute]) 
            ? $this->_attributes[$attribute] : null;
    }

    /**
     * Get th element's data
     * @return string The element data
     */
    function data()
    {
        return $this->_data;
    }

    /**
     * Set the element's data
     * @param string $data 
     */
    function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Return the children of the element
     * @return array 
     */
    function children()
    {
        return $this->_children;
    }

    /**
     * Return the depth in the xml tree of the element
     * @return int 
     */
    function level()
    {
        return $this->_level;
    }

    /**
     * Add an attribute to the element
     * @param string $name
     * @param string $value 
     */
    function addAttribute($name, $value)
    {
        //add the attribute to the element, replace if it exists
        $this->_attributes[$name] = $value;
    }

    /**
     * Remove an attribute of the element
     * @param string $name The name of the attribute to remove
     */
    function removeAttribute($name)
    {
        unset($this->_attributes[$name]);
    }

    /**
     * Add a child to the current element
     * @param string $name
     * @param array $attrs
     * @param int $level
     * @return XMLDocElement The element that was added 
     */
    function &addChild($name, $attrs = array(), $level = null)
    {
        if(!isset($this->$name))
            $this->$name = array();

        if($level == null)
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
    function removeChild(&$child)
    {
        $name = $child->name();
        for($i = 0, $n = count($this->_children); $i < $n; $i++)
            if($this->_children[$i] == $child)
                unset($this->_children[$i]);
            
        for($i = 0, $n = count($this->{$name}); $i < $n; $i++)
            if($this->{$name}[$i] == $child)
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
    function &getElementByPath($path)
    {
        $tmp =& $this;
        $parts = explode('/', trim($path, '/'));

        foreach($parts as $node)
        {
            $found = false;
            foreach($tmp->_children as $child)
                if($child->_name == $node)
                {
                    $tmp = & $child;
                    $found = true;
                    break;
                }
                
            if(!$found)
                break;
        }
        
        return $found ? $tmp : false;
    }

    /**
     * Transforms the current element into a well-formed xml
     * @param boolean $whitespace Ident the xml with spaces?
     * @return string 
     */
    function toString($whitespace=true)
    {
        if($whitespace)
            $out = "\n" . str_repeat("\t", $this->_level) . '<' . $this->_name;
        else
            $out = '<' . $this->_name;

        foreach($this->_attributes as $attr => $value)
            $out .= ' ' . $attr . '="' . htmlspecialchars($value) . '"';

        if(empty($this->_children) && empty($this->_data))
            $out .= " />";
        else
        {
            if(!empty($this->_children))
            {
                $out .= '>';

                foreach($this->_children as $child)
                        $out .= $child->toString($whitespace);

                if($whitespace)
                    $out .= "\n" . str_repeat("\t", $this->_level);
            }
            elseif(!empty($this->_data))
                    $out .= '>' . htmlspecialchars($this->_data);

            $out .= '</' . $this->_name . '>';
        }

        return $out;
    }
}