<?php

// Client for the Marketo SOAP API
class Marketo
{
	/**
	 * Marketo API Access Key
	 *
	 * @var string
	 */
	protected $user_id;

	/**
	 * Marketo API Secret Key
	 *
	 * @var string
	 */
	protected $encryption_key;

	/**
	 * The host name of the soap endpoint, i.e. na-c.marketo.com
	 *
	 * @var string
	 */
	protected $soap_host;
	
	public function __construct()
	{
		
		if (defined('MARKETO_USER_ID'))
		{
			$this->user_id = MARKETO_USER_ID;
		}
		elseif (isset($_ENV['MARKETO_USER_ID']))
		{
			$this->user_id = $_ENV['MARKETO_USER_ID'];
		}
		else
		{
			throw new Exception("MARKETO_USER_ID missing", 1);
		}
		
		if (defined('MARKETO_ENCRYPTION_KEY'))
		{
			$this->encryption_key = MARKETO_ENCRYPTION_KEY;
		}
		elseif (isset($_ENV['MARKETO_ENCRYPTION_KEY']))
		{
			$this->encryption_key = $_ENV['MARKETO_ENCRYPTION_KEY'];
		}
		else
		{
			throw new Exception("MARKETO_ENCRYPTION_KEY missing", 1);
		}
		
		if (defined('MARKETO_SOAP_HOST'))
		{
			$this->soap_host = MARKETO_SOAP_HOST;
		}
		elseif (isset($_ENV['MARKETO_SOAP_HOST']))
		{
			$this->soap_host = $_ENV['MARKETO_SOAP_HOST'];
		}
		else
		{
			throw new Exception("MARKETO_SOAP_HOST missing", 1);
		}
		
		$soap_end_point = "https://{$this->soap_host}/soap/mktows/1_8";

		$options = array("connection_timeout" => 20, "location" => $soap_end_point);
		
		$wsdl_url = $soap_end_point . '?WSDL';

		$this->soap_client = new soapClient($wsdl_url, $options);
	}
	
	/**
	 * get lead record information
     * 
	 * @param string $type this should be either:
     * IDNUM - The Marketo lead ID
     * COOKIE - The entire _mkto_trk cookie
     * EMAIL - The email address of the lead
     * SDFCCONTANTID - The Salesforce Contact ID
	 * SFDCLEADID - The Salesforce Lead ID
	 *
	 * @param string $value - the lead id value
     * 
	 * @return object The Marketo lead record information
	 **/
	public function get_lead_by($type, $value)
	{
		$lead = array(
			'leadKey' => array(
				'keyType'  => strtoupper($type),
				'keyValue' => $value
			)
		);

		try 
		{
			$result = $this->request('getLead', $lead);
			$leads = $this->format_leads($result);
		}
		catch (Exception $e) 
		{
			if (isset($e->detail->serviceException->code) && $e->detail->serviceException->code == '20103') 
			{
				// No leads were found
				$leads = FALSE;
			}
			else
			{
				throw new Exception($e, 1);
			}
		}
		
		return $leads;
	}
	
	/**
	 * Send lead info to marketo
     * 
	 * @param string $email The email address of the lead
	 * @param string $cookie - The entire _mkto_trk cookie
	 * @param array $lead - Associative array of lead attributes
     * 
	 * @return object The Marketo lead record information
	 **/
	public function sync_lead($email, $cookie, $lead)
	{
		$params = new stdClass;
		$params->marketoCookie = $cookie;
		$params->returnLead = TRUE;
		$params->leadRecord = $this->lead_record($email, $lead);
			
		$result = $this->request('syncLead', $params);
		
		$result = $result->result;
		$result->leadRecord->attributes = $this->flatten_attributes($result->leadRecord->leadAttributeList->attribute);
		unset($result->leadRecord->leadAttributeList);
		
		return $result;
	}
	
	/**
	 * Build a lead object for syncing
     * 
	 * @param string $email The email address of the lead
	 * @param array $lead - Associative array of lead attributes
     * 
	 * @return object The prepared lead object
	 **/
	protected function lead_record($email, $lead_attributes)
	{
		$record = new stdClass;
		$record->Email = $email;
		$record->leadAttributeList = new stdClass;
		$record->leadAttributeList->attribute = array();

		foreach ($lead_attributes as $attribute => $value)
		{
			$type = NULL;

			// Booleans have to be '1' or '0'
			if (is_bool($value))
			{
				$value = strval(intval($value));
				$type = 'boolean';
			}

			$lead_attribute = new stdClass;
			$lead_attribute->attrName  = $attribute;
			$lead_attribute->attrValue = $value;
			$lead_attribute->attrType  = $type;
			
			array_push($record->leadAttributeList->attribute, $lead_attribute);
		}
		
		return $record;
	}
	
	/**
	 * Format Marketo lead object into something easier to work with
     * 
	 * @param object $marketo_result The result of a get_lead call
     * 
	 * @return array of formatted lead objects
	 **/
	protected function format_leads($marketo_result)
	{
		$leads = array();
		
		// One record comes back as an object but two comes as an array of objects, just make them both arrays of objects
		if (is_object($marketo_result->result->leadRecordList->leadRecord))
		{
			$marketo_result->result->leadRecordList->leadRecord = array($marketo_result->result->leadRecordList->leadRecord);
		}
		
		foreach ($marketo_result->result->leadRecordList->leadRecord as $lead)
		{
			$lead->attributes = $this->flatten_attributes($lead->leadAttributeList->attribute);
			unset($lead->leadAttributeList);
				
			array_push($leads, $lead);
		}
		
		return $leads;
	}
	
	/**
	 * Helper for format_leads. Formats attribute objects to simple associative array
     * 
	 * @param array $attributes An array of attribute objects from a get_lead call
     * 
	 * @return array flattened array of attributes
	 **/
	protected function flatten_attributes($attributes)
	{
		$php_types = array('integer', 'string', 'boolean', 'float');
		$attributes_array = array();
		foreach ($attributes as $attribute)
		{
			if (in_array($attribute->attrType, $php_types))
			{
				// Cast marketo type to supported php types
				settype($attribute->attrValue, $attribute->attrType);
			}
			$attributes_array[$attribute->attrName] = $attribute->attrValue;
		}
		
		return $attributes_array;
	}
	
	/**
	 * Creates a SOAP authentication header to be used in the SOAP request
     * 
	 * @return object SoapHeader object
	 **/
	protected function authentication_header()
	{
		$timestamp      = date("c");
		$encrypt_string = $timestamp . $this->user_id;
		$signature      = hash_hmac('sha1', $encrypt_string, $this->encryption_key);
		
		$attrs = array(
			'mktowsUserId'     => $this->user_id,
			'requestSignature' => $signature,
			'requestTimestamp' => $timestamp,
		);
		
		$header = new SoapHeader('http://www.marketo.com/mktows/', 'AuthenticationHeader', $attrs);
		
		return $header;
	}

	/**
	 * Make a SOAP request to the Marketo API
     * 
	 * @param string $operation The name of the soap method being called
	 * @param object|array object or array of objects to send in the request
     * 
	 * @return object SOAP request result
	 **/
	protected function request($operation, $params)
	{
		return $this->soap_client->__soapCall($operation, array($params), array(), $this->authentication_header());
	}
	
}
