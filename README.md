# Marketo

A PHP client for the Marketo SOAP API.

## Usage

### Authentication

The first thing you'll want to do is include the marketo class and create a new instance of the client.

You will need your SOAP user id and SOAP encryption key as well as the hostname for your Marketo API endpoint. These can be found in the Admin -> SOAP section when logged into [app.marketo.com](http://app.marketo.com/).

If you store configuration in the environment you would create a new instance like:

	require('marketo.php');
	$marketo_client = new Marketo($_ENV['MARKETO_USER_ID'], $_ENV['MARKETO_ENCRYPTION_KEY'], $_ENV['MARKETO_SOAP_HOST']);
	
The credentials are passed directly to the class rather than looking for constants or keys stored in superglobals so you can connect to multiple Marketo instances.

### Getting a lead

You can get a lead using the `get_lead_by` method.

**Arguments**

`$type` - The type of ID you would like to look up the lead by. This can be one of the following:

 - `idnum` - The Marketo lead ID
 - `cookie` - The entire _mkto_trk cookie
 - `email` - The email address of the lead
 - `sdfccontantid` - The Salesforce Contact ID
 - `sfdcleadid` - The Salesforce Lead ID

`$value` - The value for the key. For example if the $type is email the $value should be and email address

**Examples**

	$marketo_client->get_lead_by('email', 'ben@benubois.com');

This will return an array of lead objects or `FALSE` if not found. The result will always be an array whether there are one or more leads found.

### Creating or updating a lead

You can create or update a lead using the `sync_lead` method.

**Arguments**

`$lead` - Associative array of lead attributes

`$lead_key` - Optional, The key being used to identify the lead, this can be either an email or Marketo ID

`$cookie` - Optional, The entire _mkto_trk cookie the lead will be associated with

**Examples**

When no $lead_key or $cookie is given a new lead will be created

	$marketo_client->sync_lead(array('Email' => 'ben@benubois.com'));
	
When a $lead_key or $cookie is specified, Marketo will attempt to identify the lead and update it. Sending the `_mkto_trk` cookie is important for associating the lead you're syncing with any information Marketo collected when the lead was anonymous.

	$marketo_client->sync_lead(array('Unsubscribed' => FALSE), 'ben@benubois.com', $_COOKIE['_mkto_trk']);

This will return the updated or created lead object.

### Adding leads to campaigns

You can add leads to a campaign using the `add_to_campaign` method.

**Arguments**

`$campaign_key` - Either the campaign id or the campaign name. You can get these from `get_campaigns()`.

`$leads` - An associative array with a key of lead id type and the corresponding value. This can also be an array of associative arrays. The available id types are:

 - `idnum` - The Marketo lead ID
 - `sdfccontantid` - The Salesforce Contact ID
 - `sfdcleadid` - The Salesforce Lead ID

**Examples**

Add one lead to a campaign

    $client->add_to_campaign(321, array('idnum' => '123456'));

Add multiple leads to a campaign with mixed id types

    $leads = array(
       array('idnum' => '123456'),
       array('sfdcleadid' => '001d000000FXkBt')
    );
	
    $client->add_to_campaign(321, $leads);

Returns `TRUE` if successful `FALSE` if not

### Getting campaigns

You can get available campaigns using the `get_campaigns` method.

**Arguments**

`$name` - Optional, the exact name of the campaign to get

You would usually use this to figure out what campaigns are available when calling `add_to_campaign()`.

Returns an object containing all the campaigns that are available to the API. Campaigns are only available to the API if they have a Campaign is Requested trigger where the Source is Web Service API.

**Examples**

	$marketo_client->get_campaigns();

