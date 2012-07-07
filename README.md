Marketo
-------

A simple Marketo SOAP client.

Usage
=====

**Authentication**

You can authenticate using constants:

	define('MARKETO_SOAP_HOST',      'na-*.marketo.com');
	define('MARKETO_USER_ID',        'User Id');
	define('MARKETO_ENCRYPTION_KEY', 'Encryption Key');

or by setting environment variables:

	$_ENV['MARKETO_SOAP_HOST'];
	$_ENV['MARKETO_USER_ID'];
	$_ENV['MARKETO_ENCRYPTION_KEY'];

**Getting a lead**
	
	require('marketo.php');
	$marketo_client - new Marketo;
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
	$marketo_client - new Marketo;
	$marketo_client->sync_lead('ben@benubois.com', $_COOKIE['_mkto_trk'], array('Unsubscribe' -> FALSE));

This will return the updated/created lead object.