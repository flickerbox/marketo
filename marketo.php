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
	public function __construct($user_id, $encryption_key, $soap_host)
	{
		$this->user_id = $user_id;
		$this->encryption_key = $encryption_key;
		$this->soap_host = $soap_host;

		$soap_end_point = "https://{$this->soap_host}/soap/mktows/1_8";

		$options = array("connection_timeout" => 20, "location" => $soap_end_point);

		$wsdl_url = $soap_end_point . '?WSDL';

		$this->soap_client = new soapClient($wsdl_url, $options);
	}

	// Public: Get a lead record
	//
	// $type - The type of ID you would like to look up the lead by. This can be one
	// of the following:
	//
	// - idnum - The Marketo lead ID
	// - cookie - The entire _mkto_trk cookie
	// - email - The email address of the lead
	// - sdfccontantid - The Salesforce Contact ID
	// - sfdcleadid - The Salesforce Lead ID
	//
	// $value - The value for the key. For example if the $type is email the $value
	// should be and email address
	//
	// Examples
	//
	//  `$client->get_lead_by('email', 'ben@benubois.com');`
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
	// $lead - Associative array of lead attributes
	//
	// $lead_key - Optional, The key being used to identify the lead, this can be
	// either an email or Marketo ID
	//
	// $cookie - Optional, The entire _mkto_trk cookie the lead will be associated with
	//
	// Examples
	//
	// When no $lead_key or $cookie is given a new lead will be created
	//
	// `$client->sync_lead(array('Email' => 'ben@benubois.com'));`
	//
	// When a $lead_key or $cookie is specified, Marketo will attempt to identify the
	// lead and update it
	//
	// `$client->sync_lead(array('Unsubscribed' => FALSE), 'ben@benubois.com', $_COOKIE['_mkto_trk']);`
	//
	// Returns an object containing the lead info
	public function sync_lead($lead, $lead_key = NULL, $cookie = NULL)
	{
		$params = new stdClass;
		$params->marketoCookie = $cookie;
		$params->returnLead = TRUE;
		$params->leadRecord = $this->lead_record($lead, $lead_key);

		$result = $this->request('syncLead', $params);

		$result = $result->result;
		$result->leadRecord->attributes = $this->flatten_attributes($result->leadRecord->leadAttributeList->attribute);
		unset($result->leadRecord->leadAttributeList);

		return $result;
	}

	// Public: Get all available campaigns
	//
	// $name - Optional, the exact name of the campaign to get
	//
	// You would usually use this to figure out what campaigns are available when
	// calling add_to_campaign
	//
	// Returns an object containing all the campaigns that are available from the API
	public function get_campaigns($name = NULL)
	{
		$params = new stdClass;
		$params->source = 'MKTOWS';

		if ($name)
		{
			$params->name = '';
			$params->exactName = TRUE;
		}

		return $this->request('getCampaignsForSource', $params);
	}

	// Public: Add leads to a campaign
	//
	// $campaign_key - Either the campaign id or the campaign name. You can get these
	// from get_campaigns().
	//
	// $leads - An associative array with a key of lead id type the lead id value. This
	// can also be an array of associative arrays. The available id types are:
	//
	// - idnum - The Marketo lead ID
	// - sdfccontantid - The Salesforce Contact ID
	// - sfdcleadid - The Salesforce Lead ID
	//
	// $program_name - Optional, the Program Name
	//
	// $tokens - Optional, an associative array with a key of token name (including {{}}) and value
	// of token value.
	//
	// Examples
	//
	// Add one lead to a campaign
	//
	//     $client->add_to_campaign(321, array('idnum' => '123456'));
	//
	// Add multiple leads to a campaign with mixed id types
	//
	//     $leads = array(
	//        array('idnum' => '123456'),
	//        array('sfdcleadid' => '001d000000FXkBt')
	//     );
	//     $client->add_to_campaign(321, $leads);
	//
	// Returns true if successful false if not
	public function add_to_campaign($campaign_key, $leads, $program_name = NULL, $tokens = NULL)
	{
		$lead_keys = array();
		foreach ($leads as $type => $value)
		{
			if (is_array($value))
			{
				// Just getting the type and value into the right place
				foreach ($value as $type => $value){}
			}

			$lead_key = new stdClass;
			$lead_key->keyType  = strtoupper($type);
			$lead_key->keyValue = $value;

			array_push($lead_keys, $lead_key);
		}

		$params  = new stdClass;
		$params->leadList = $lead_keys;
		$params->source = 'MKTOWS';

		if (is_numeric($campaign_key))
		{
			$params->campaignId = $campaign_key;
		}
		else
		{
			$params->campaignName = $campaign_key;
		}

		// Get tokens into the right format:
		if ($tokens && is_array($tokens) && $program_name)
		{
			$token_list = array();
			foreach ($tokens as $token_key => $token_value) {
				$token = new stdClass;
				$token->name = $token_key;
				$token->value = $token_value;

				array_push($token_list, $token);
			}

			$params->programName = $program_name;
			$params->programTokenList = array("attrib" => $token_list);
		}

		return $this->request('requestCampaign', $params);
	}

	// Build a lead object for syncing
	//
	// $lead - Associative array of lead attributes
	// $lead_key - Optional, The key being used to identify the lead, this can be
	// either an email or Marketo ID
	//
	// Returns an object with the prepared lead
	protected function lead_record($lead_attributes, $lead_key = NULL)
	{
		$record = new stdClass;

		// Identify the lead if it is known
		if ($lead_key)
		{
			if (is_numeric($lead_key))
			{
				$record->Id = $lead_key;
			}
			else
			{
				$record->Email = $lead_key;
			}
		}

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

		// One record comes back as an object but two comes as an array of objects, just
		// make them both arrays of objects
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
			if (is_object($attribute))
			{
				if (in_array($attribute->attrType, $php_types))
				{
					// Cast marketo type to supported php types
					settype($attribute->attrValue, $attribute->attrType);
				}
				$attributes_array[$attribute->attrName] = $attribute->attrValue;
			}
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
