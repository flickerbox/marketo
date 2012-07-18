# Marketo

A simple Marketo PHP SOAP client.

## Usage

**Connecting**

You'll need the hostname for your Marketo API endpoint, your SOAP user id and SOAP encryption key. These can be found in the SOAP section in Admin.

If you store configuration in the environment you would create a new instance like:

	$marketo_client = new Marketo($_ENV['MARKETO_USER_ID'], $_ENV['MARKETO_ENCRYPTION_KEY'], $_ENV['MARKETO_SOAP_HOST']);;

**Getting a lead**
	
	require('marketo.php');
	$marketo_client = new Marketo($_ENV['MARKETO_USER_ID'], $_ENV['MARKETO_ENCRYPTION_KEY'], $_ENV['MARKETO_SOAP_HOST']);;
	$marketo_client->get_lead_by('email', 'ben@benubois.com');

This will return the lead object or `FALSE` if not found.

You can get a lead by

 - idnum - The Marketo lead ID
 - cookie - The entire _mkto_trk cookie
 - email - The email address of the lead
 - sdfccontantid - The Salesforce Contact ID
 - sfdcleadid - The Salesforce Lead ID


**Creating/updating a lead**

	require('marketo.php');
	$marketo_client = new Marketo($_ENV['MARKETO_USER_ID'], $_ENV['MARKETO_ENCRYPTION_KEY'], $_ENV['MARKETO_SOAP_HOST']);;
	
	// When no $lead_key or $cookie is given a new lead will be created
	$marketo_client->sync_lead(array('Email' => 'ben@benubois.com'));
	
	// When a $lead_key or $cookie is specified, Marketo will attempt to identify the lead and update it
	$marketo_client->sync_lead(array('Unsubscribed' => FALSE), 'ben@benubois.com', $_COOKIE['_mkto_trk']);

This will return the updated/created lead object.