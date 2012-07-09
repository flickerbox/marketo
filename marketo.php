<?php

// This is the annotated source for [marketo](http://github.com/flickerbox/marketo), a simple Marketo SOAP client.
class Marketo
{
	// Internal: Marketo API Access Key
	protected $user_id;

	// Internal: Marketo API Secret Key
	protected $encryption_key;
	
	// Internal: The host name of the soap endpoint, i.e. na-c.marketo.com
	protected $soap_host;
	
	// Public: Initialize a new Marketo API instance.
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
	
	// Public: Get a lead record
	// 
	// $type  - The type of ID you would like to look up the lead by. This can 
	//          be one of the following:
	//  
	// - idnum - The Marketo lead ID
	// - cookie - The entire _mkto_trk cookie
	// - email - The email address of the lead
	// - sdfccontantid - The Salesforce Contact ID
	// - sfdcleadid - The Salesforce Lead ID
	//
	// $value - The value for the key. For example if the $type is email the 
	//          $value should be and email address
	// 
	// Examples
	// 
	//    `$client->get_lead_by('email', 'ben@benubois.com');`
	// 
	// Returns an object containing lead data or FALSE if no lead was found
	public function get_lead_by($type, $value)
	{
		$lead = new stdClass;
		$lead->leadKey = new stdClass;
		$lead->leadKey->keyType  = strtoupper($type);
		$lead->leadKey->keyValue = $value;

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
	
	// Public: Create or update lead information
	// 
	// $email  - The email address of the lead
	// $cookie - The entire _mkto_trk cookie
	// $lead   - Associative array of lead attributes
	// 
	// Examples
	// 
	// `$client->sync_lead('ben@benubois.com', $_COOKIE['_mkto_trk'], array('Unsubscribe' -> FALSE));`
	// 
	// Returns an object containing the updated lead info
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
	
	// Build a lead object for syncing
	// 
	// $email - The email address of the lead
	// $lead  - Associative array of lead attributes
	// 
	// Returns an object with the prepared
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
	
	// Format Marketo lead object into something easier to work with
	// 
	// $marketo_result - The result of a get_lead call
	// 
	// Returns an array of formatted lead objects
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
	
	// Helper for format_leads. Formats attribute objects to a simple 
	// associative array
	// 
	// $attributes - An array of attribute objects from a get_lead call
	// 
	// Returns and array flattened array of attributes
	// 
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
	
	// Creates a SOAP authentication header to be used in the SOAP request
	// 
	// Returns a SoapHeader object
	protected function authentication_header()
	{
		$timestamp      = date("c");
		$encrypt_string = $timestamp . $this->user_id;
		$signature      = hash_hmac('sha1', $encrypt_string, $this->encryption_key);
		
		$data = new stdClass;
		$data->mktowsUserId     = $this->user_id;
		$data->requestSignature = $signature;
		$data->requestTimestamp = $timestamp;
		
		$header = new SoapHeader('http://www.marketo.com/mktows/', 'AuthenticationHeader', $data);
		
		return $header;
	}

	// Make a SOAP request to the Marketo API
	// 
	// $operation - The name of the soap method being called
	// $params    - The object to send with the request
	// 
	// Returns the SOAP request result
	protected function request($operation, $params)
	{
		return $this->soap_client->__soapCall($operation, array($params), array(), $this->authentication_header());
	}
	
}
