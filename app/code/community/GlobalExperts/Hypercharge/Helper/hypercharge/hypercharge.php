<?php
/**
 * Hypercharge client library
 *  
 * @author Adrian Rosian
 */
 
if(!function_exists('curl_init'))
    exit('CURL extension is required by the gateway client');
$hyperchargePhpVersion = explode('.', PHP_VERSION);
if ($hyperchargePhpVersion[0] < 5 || $hyperchargePhpVersion[1] < 1)
    exit('This extension requires Php >= 5.1.0');

require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'xmldoc.php';

/**
 * Class for Hypercharge Gateway clients
 */
class HyperchargeGateway
{
    const LIVE_URL = 'https://hypercharge.net';
    const TEST_URL = 'https://test.hypercharge.net';
    const WPF_URL = 'https://payment.hypercharge.net/wpf';
    const TEST_WPF_URL = 'https://testpayment.hypercharge.net/wpf';
    const WPF_REC_URL = 'https://payment.hypercharge.net/wpf/reconcile';
    const TEST_WPF_REC_URL 
        = 'https://testpayment.hypercharge.net/wpf/reconcile';
    const WPF_CANCEL_URL = 'https://payment.hypercharge.net/wpf/cancel';
    const TEST_WPF_CANCEL_URL 
        = 'https://testpayment.hypercharge.net/wpf/cancel';
    
    /**
     * @var array The error list
     */
    private $_errors = array();
    /**
     * @var string The channel username
     */
    private $username = null;
    /**
     * @var string The channel password
     */
    private $password = null;
    /**
     * @var string The channel number
     */
    private $channel = null;
    /**
     * @var int Connection timeout for the secure connection
     */
    private $timeout = 30;
    /**
     * @var string The request type, live or test
     */
    private $mode = 'test';
    /**
     * @var array An array holding regular expression validations for params
     */
    private $validations = null;
    
    /**
     * Php5 style constructor
     * @param array $args Channel, timeout and mode configuration
     */
    public function __construct($args = array())
    {
        if(isset($args['username']))
            $this->username = $args['username'];
        if(isset($args['password']))
            $this->password = $args['password'];
        if(isset($args['channel']))
            $this->channel = $args['channel'];
        if(isset($args['timeout']) && is_int($args['timeout']) 
            && $args['timeout'] > 0)
            $this->timeout = $args['timeout'];
        if(isset($args['mode']) && in_array($args['mode'], 
            array('live', 'test')))
            $this->mode = $args['mode'];
        $this->validations = array(
            'reference_id' => '/^[a-f0-9]{32}$/',
            'transaction_id' => '/.+/',
            'card_number' => '/^[0-9]{13,16}/',
            'cvv' => '/^[0-9]{3}$/',
            'card_holder' => '/^.+\s.+/',
            'currency' => '/^[A-Z]{3}$/',
            'amount' => '/^\d+$/',
            'customer_email' => '/^.+@.+\.{2,}/',
            'remote_ip' => '/^(\d){1,3}\.(\d){1,3}\.(\d){1,3}\.(\d){1,3}+$/',
            'expiration_year' => '/^\d{4}$/',
            'expiration_month' => '/^\d{1,2}$/',
            'notification_url' => '/^http/',
            'return_success_url' => '/^http/',
            'return_failure_url' => '/^http/'
        );
    }

    /**
     * Dynamic getter and setter trough magic method
     * @param string Method name
     * @param array Method arguments
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function __call($name, $arguments)
    {
        if (strpos($name, 'get') != 0 && strpos($name, 'set') != 0)
            throw new BadMethodCallException('Call to undefined method ' 
                . $name);
        $property = strtolower(substr($name, 3));
        $prefix = substr($name, 0, 3);
        if ($prefix == 'get')
            switch ($property) {
                case 'error':
                    return array_pop($this->_errors);
                case 'errors':
                    $errors = $this->_errors;
                    $this->_errors = array();
                    return $errors;
                default:
                    return $this->$property;
            }
        if (count($arguments) > 1)
            throw new InvalidArgumentException('Too many arguments');
        $arg = array_shift($arguments);
        switch ($property) {
            case 'error':
                $this->_errors[] = (string) $arg;    
                break; 
            default:
                $this->$property = (string) $arg;
                break;
        }
    }
    
    /**
     * Validates the parameters from $paramsList specified by their keys in $whichParams 
     * @param array $whichParams
     * @param array $paramsList
     * @return boolean 
     */
    function validateParameters($whichParams, $paramsList)     
    {
        if(!$whichParams || !$paramsList)
            return false;
        if(!is_array($whichParams) || !is_array($paramsList))
            return false;
        if(!array_intersect(array_values($whichParams), 
            array_keys($paramsList)))
            return false;        
        
        $noErrors = true;
        foreach($whichParams as $pName)
            if(isset($this->validations[$pName]) && isset($paramsList[$pName])
                && (!$this->validations[$pName]
                || !preg_match($this->validations[$pName], 
                    $paramsList[$pName])))
            {
                $this->setError('Parameter ' . htmlentities($pName) 
                    . ' is invalid');
                $noErrors = false;
            }
        
        return $noErrors;
    }
    
    /**
     * Returns the test or live url depending on mode 
     * @link mode
     * @param string $method The method part of the url, @see reconcile()
     * @return string 
     */
    function getAPIUrl($method = 'process')
    {
        $url = '';
        switch($method)
        {
            case 'process':
                $url = ($this->mode == 'live' ? self::LIVE_URL : self::TEST_URL) 
                    . '/process/' . $this->channel;
                break;
            case 'reconcile';
                $url = ($this->mode == 'live' ? self::LIVE_URL : self::TEST_URL) 
                    . '/reconcile/' . $this->channel;
                break;
            case 'wpf':
                $url = $this->mode == 'live' ? self::WPF_URL 
                    : self::TEST_WPF_URL;
                break;
            case 'wpf_reconcile':
                $url = $this->mode == 'live' ? self::WPF_REC_URL
                    : self::TEST_WPF_REC_URL;
                break;
            case 'wpf_cancel':
                $url = $this->mode == 'live' ? self::WPF_CANCEL_URL
                    : self::TEST_WPF_CANCEL_URL;
                break;
            default:
                $url = ($this->mode == 'live' ? self::LIVE_URL : self::TEST_URL) 
                    . '/' . $method . '/' . $this->channel;
                break;
        }
        return $url;
    }
    
    /**
     * Returns an xml based on an associative array
     * @param array $params
     * @param string $root
     * @param object $xml
     * @return string 
     */
    function paramsXML($params, $root = 'payment_transaction', $xml = null)
    {
        if(!is_array($params) || !$params || !is_string($root))
            return;
        
        if($xml === null)
        {
                $xml = new XMLDoc();
                if(!$xml->loadString('<?xml version="1.0" encoding="utf-8"?>'
                    . "<$root />"))
                {
                    $this->setError($xml->getError());
                    return;
                }
                
                $xml = $xml->document;
        }
        
        foreach($params as $key => $value)
            if(!is_array($value))
            {
                $oldXml = $xml;
                $xml = $xml->addChild($key);
                $xml->setData(utf8_encode($value));
                $xml = $oldXml;
                unset($oldXml);
            }
            elseif($root == 'wpf_payment' && $key == 'transaction_types')
            {
                // Add the root first
                $topXml = $xml;
                $xml = $xml->addChild($key);
                
                // Allow array notation for transaction types
                foreach($value as $v)
                {
                    $oldXml = $xml;
                    $xml = $xml->addChild('transaction_type');
                    $xml->setData(utf8_encode($v));
                    $xml = $oldXml;
                    unset($oldXml);                
                }
                $xml = $topXml;
                unset($topXml);
            }
            else
                self::paramsXML($value, $root, $xml->addChild($key));
            
        return $xml->toString();       
    }
    
    /**
     * Performs a SSL POST request to the gateway with $xmlStr payload
     * @param string $url The url for the request
     * @param string $xmlStr The xml to transmit
     * @return mixed Boolean false or the response string 
     */
    function curlSSLRequest($url, $xmlStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 0);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlStr);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml'));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD,
            $this->username . ':' . $this->password);
        //default timeout 30 sec
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($this->timeout) 
            ? $this->timeout : 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $response_string = curl_exec($ch);

        //check for transport errors
        if(curl_errno($ch) != 0)
        {
            $this->setError('Could not communicate with the gateway: ' 
                . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        return $response_string;
    }
    
    /**
     * Parses the response xml into an associative array
     * @param string $response_string
     * @return mixed Boolean false or the resulting array 
     */
    function parseGatewayResponse($response_string)
    {
        $xmlObj = new XMLDoc();
        
        if(!$xmlObj->loadString($response_string))
        {
            $this->setError($xmlObj->getError());
            return false;
        }
        
        //API error
        if($xmlObj->document->name() == 'error')
        {
            $this->setError($xmlObj->document->message[0]->data());            
            return false;
        }
        
        //Xml is not what is expected
        if($xmlObj->document->name() != 'payment_response' 
            && $xmlObj->document->name() != 'wpf_payment')
        {
            $this->setError('Unknown root node');            
            return false;
        }
                
        $response = self::xmlToArray($xmlObj->document);
        
        return $response;        
    }
    
    /**
     * Transforms an XMLDoc object to an array
     * @param XMLDocElement $node
     * @return array 
     */
    function xmlToArray(&$node)
    {
        $returnArray = array();
        foreach($node->children() as $n)
            if(!$n->children())
                switch($n->name())
                {
                    case 'timestamp':
                        $tz = date_default_timezone_get();
                        date_default_timezone_set('UTC');
                        $returnArray['timestamp'] = strtotime($n->data());
                        date_default_timezone_set($tz);                        
                        break;
                    default:
                        if(count($node->{$n->name()}) > 1)
                            $returnArray[$n->name()][] = $n->data();
                        else
                            $returnArray[$n->name()] = $n->data();
                        break;
                }                
            else
                if(count($node->{$n->name()}) > 1)
                    $returnArray[$n->name()][] = self::xmlToArray($n);
                else
                    $returnArray[$n->name()] = self::xmlToArray($n);
        return $returnArray;
    }
    
    /**
     * Creates a WPF transaction
     * @param array $params
     * @return mixed The response 
     */
    public function wpf_create($params)
    {
        $this->validateParameters(array('transaction_id', 'amount',
            'currency', 'usage', 'description', 'notification_url', 
            'return_success_url', 'return_failure_url', 'return_cancel_url'), 
            $params);
        
        return $this->parseGatewayResponse($this->curlSSLRequest(
            $this->getAPIUrl('wpf'), '<?xml version="1.0" encoding="utf-8"?>'  
            . $this->paramsXML($params, 'wpf_payment')));
    }
    
    /**
     * Cancel a WPF transaction as long as it hasn't been processed
     * @param array $params
     * @return mixed 
     */
    public function wpf_cancel($params)
    {
        $this->validateParameters(array('unique_id'), $params);
        
        return $this->parseGatewayResponse($this->curlSSLRequest(
            $this->getAPIUrl('wpf_cancel'), 
            '<?xml version="1.0" encoding="utf-8"?>'  
            . $this->paramsXML($params, 'wpf_cancel')));
    }
    
    /**
     * Reconcile a WPF transaction to get information about status
     * @param array $params
     * @return mixed 
     */
    public function wpf_reconcile($params)
    {
        $this->validateParameters(array('unique_id'), $params);
        
        return $this->parseGatewayResponse($this->curlSSLRequest(
            $this->getAPIUrl('wpf_reconcile'), 
            '<?xml version="1.0" encoding="utf-8"?>'  
            . $this->paramsXML($params, 'wpf_reconcile')));
    }
    
    /**
     * Returns the xml for the gateway to stop sending notifications for a trx
     * @param int The unique ID of the transaction
     * @return string
     */
     public function getTrxEndXml($wpf_id)
     {
        return '<?xml version="1.0" encoding="utf-8"?>' 
            . $this->paramsXML(array('wpf_unique_id' => $wpf_id), 
            'notification_echo');
     }
}