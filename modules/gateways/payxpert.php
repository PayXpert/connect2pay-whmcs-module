<?php
/*
 Copyright 2013 PayXpert

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License. 
*/   

require_once(dirname(__FILE__) . "/payxpert/Connect2PayClient.php");
require_once(dirname(__FILE__) . "/payxpert/GatewayClient.php");


function payxpert_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value" => "PayXpert"),
     "originator" => array("FriendlyName" => "Originator ID", "Type" => "text", "Size" => "20", ),
     "password" => array("FriendlyName" => "Password", "Type" => "text", "Size" => "20", ),
     "url" => array("FriendlyName" => "Gateway URL", "Type" => "text", "Size" => "64", "Description" => "Leave this field empty unless, PayXpert provides you an URL"),
     "apiurl" => array("FriendlyName" => "API URL", "Type" => "text", "Size" => "64", "Description" => "Leave this field empty unless, PayXpert provides you an URL"),
     "3dsecure" => array("FriendlyName" => "3D Secure", "Type" => "yesno", "Description" => "Enable 3D Secure transaction", ),
     "iframe" => array("FriendlyName" => "Iframe Mode", "Type" => "yesno", "Description" => "Turns on iframe checkout mode", ),
    );
	return $configarray;
}

function payxpert_getGatewayUrl($params) {
	
	if (isset($params['url']) && !empty($params['url'])) {
		return trim($params['url']);
	}
	return "https://connect2.payxpert.com";
}

function payxpert_getAPIUrl($params) {
	
	if (isset($params['apiurl']) && !empty($params['apiurl'])) {
		return trim($params['apiurl']);
	}
	return "https://api.payxpert.com/";
}

function payxpert_escapeHTML($string) {
    return htmlentities($string, ENT_QUOTES, 'UTF-8');
} 

function payxpert_link($params) {

	global $remote_ip;
	
	# Gateway Specific Variables
	$originator = $params['originator'];
	$password = $params['password'];

    $c2pClient = new Connect2PayClient(payxpert_getGatewayUrl($params), $originator, $password);
	
	$c2pClient->setOrderID( $params['invoiceid'] );
	$c2pClient->setCustomerIP( $remote_ip );
	$c2pClient->setPaymentType( Connect2PayClient::_PAYMENT_TYPE_CREDITCARD );
	$c2pClient->setPaymentMode( Connect2PayClient::_PAYMENT_MODE_SINGLE );
	$c2pClient->setShopperID( $params['clientdetails']['userid'] );
	$c2pClient->setShippingType( Connect2PayClient::_SHIPPING_TYPE_VIRTUAL );
	$c2pClient->setAmount( ceil($params['amount'] * 100));
  // In case we are using a conversion
  if (isset($params['basecurrencyamount'])) {
    $baseCurrencyAmount = $params['basecurrencyamount'];
  } else {
    $baseCurrencyAmount = $params['amount'];
  }
  
	$c2pClient->setOrderDescription( $params["description"] );
	$c2pClient->setCurrency( $params['currency'] );
	
	$c2pClient->setShopperFirstName( html_entity_decode($params['clientdetails']['firstname'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperLastName( html_entity_decode($params['clientdetails']['lastname'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperAddress( trim( html_entity_decode($params['clientdetails']['address1'] . " ". $params['clientdetails']['address2'], ENT_QUOTES, 'UTF-8') ) );
	$c2pClient->setShopperZipcode( html_entity_decode($params['clientdetails']['postcode'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperCity( html_entity_decode($params['clientdetails']['city'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperState( html_entity_decode($params['clientdetails']['state'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperCountryCode( $params['clientdetails']['countrycode'] );
	$c2pClient->setShopperPhone( html_entity_decode($params['clientdetails']['phonenumber'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setShopperEmail("heitor.dolinski@payxpert.com"); //(html_entity_decode($params['clientdetails']['email'], ENT_QUOTES, 'UTF-8') );
	$c2pClient->setCtrlRedirectURL( $params['systemurl'] . '/modules/gateways/callback/payxpert.php' );
	$c2pClient->setCtrlCallbackURL( "https://developers.payxpert.com/" );
	if (!empty($params['3dsecure'])) {
		$c2pClient->setSecure3d(true);
	}
  
  $md5 = md5($params['invoiceid'] . $params['amount'] . $password);
  
  $c2pClient->setCtrlCustomData ($baseCurrencyAmount . "|" . $md5);
  
	
	if ($c2pClient->validate()) {
	  if ($c2pClient->prepareTransaction()) {
    
		$url = $c2pClient->getCustomerRedirectURL();

    $method = '';

    if (!empty($params['iframe'])) {
      $url = $params['systemurl'] . 'pxp_iframe.php';
      $method = 'method=POST';
    }

		$code = '<form ' . $method . ' action="'. $url .'"><input type="submit" value="'. $params['langpaynow']. '" /><input type="hidden" name="data" value="' . encrypt($c2pClient->getCustomerRedirectURL()) . '" /></form>';
	  } else {
		$message = "<b>PayXpert</b> payment module: Error in prepareTransaction: <br />";
		$message .= "Order id: " . $params['invoiceid'] . "<br />";
		$message .= "Result code: " . payxpert_escapeHTML($c2pClient->getReturnCode()) . "<br />";
		$message .= "Preparation error occured: " . payxpert_escapeHTML($c2pClient->getClientErrorMessage()) . "<br />";
		$code = '<div class="alert alert-block alert-error"><p>' . $message . '</p></div>';
	  }
	} else {
		$message = "<b>PayXpert</b> payment module: Error in validate function: <br />";
		$message .= "Order id: " . $params['invoiceid'] . "<br />";
		$message .= "Validation error occured: " . payxpert_escapeHTML($c2pClient->getClientErrorMessage()) . "<br />";
		$code = '<div class="alert alert-block alert-error"><p>' . $message . '</p></div>';
	}

  $_SESSION['merchantToken'] = $c2pClient->getMerchantToken();
  
	return $code;
}


function payxpert_refund($params) {

	# Gateway Specific Variables
	$originator = $params['originator'];
	$password = $params['password'];

    $client = new GatewayClient(payxpert_getAPIUrl($params), $originator, $password);
	$transaction = $client->newTransaction('Refund');
	$transaction->setReferralInformation($params['transid'], (int)($params['amount']) * 100);
	
	
	$response    = $transaction->send();
	
	# Perform Refund Here & Generate $results Array, eg:
	$results = array();
	if ($response->errorCode === '000') {
		$results["status"] = "success";
		$results["transid"] = $response->transactionID;
	} else if ($response->errorCode === '001') {
		$results["status"] = "declined";
		$results["transid"] = $response->transactionID;
		$results["errorMessage"] = $response->errorMessage;
	} else {
		$results["status"] = "error";
		$results["transid"] = $response->transactionID;
		$results["errorMessage"] = $response->errorMessage;
	}
	

	# Return Results
	if ($results["status"] == "success") {
		return array("status" => "success", "transid" => $results["transid"], "rawdata" => $results);
	} elseif ($gatewayresult == "declined") {
        return array("status" => "declined", "rawdata" => $results);
    } else {
		return array("status" => "error", "rawdata" => $results);
	}

}

?>