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


# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

require_once(dirname(__FILE__) . "/../payxpert.php");


	$gatewaymodule = "payxpert"; # Enter your gateway module name here replacing template

	$GATEWAY = getGatewayVariables($gatewaymodule);
	
	if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

	# Gateway Specific Variables
	$originator = $GATEWAY['originator'];
	$password = $GATEWAY['password'];

	$c2pClient = new Connect2PayClient(payxpert_getGatewayUrl($GATEWAY), $originator, $password);


	if ($c2pClient->handleCallbackStatus()) {
	
		$status = $c2pClient->getStatus();

		// get the Error code
		$errorCode = $status->getErrorCode();
		$errorMessage = $status->getErrorMessage();
		$transactionId = $status->getTransactionID();
		$invoiceId = $status->getOrderID();
		$convertedAmount = number_format($status->getAmount() / 100, 2, '.', '');

    $array = explode('|', $c2pClient->getCtrlCustomData());
    $baseCurrencyAmount = $array[0];
    $md5 = $array[1];
    
    if (md5($invoiceId . $convertedAmount . $password) != $md5) {
    //if (0) {
    
      logTransaction($GATEWAY["name"], "Callback received an incorrect checksum for invoiceID " . $invoiceId . " from " . $_SERVER["REMOTE_ADDR"], "Error"); # Save to Gateway Log: name, data array, status
      
      $data = compact ("errorCode", "errorMessage", "transactionId", "invoiceId", "convertedAmount");
      logTransaction($GATEWAY["name"], $data, "Unsuccessful"); # Save to Gateway Log: name, data array, status
      
    } else {
    
      $fee = 0;
      $data = compact ("errorCode", "errorMessage", "transactionId", "invoiceId", "convertedAmount");

      $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

      checkCbTransID($transactionId); # Checks transaction number isn't already in the database and ends processing if it does
      
      // errorCode = 000 transaction is successfull
      if ($errorCode == '000') {
        // If we reach this part of the code the transaction successed
        // Do some SQL query to add the transaction
      
        addInvoicePayment($invoiceId, $transactionId, $baseCurrencyAmount, $fee, $gatewaymodule); # Apply Payment to Invoice: invoiceId, transactionid, amount paid, fees, modulename
        logTransaction($GATEWAY["name"], $data, "Successful"); # Save to Gateway Log: name, data array, status

      } else {
        // add you code in case the transaction is denied
      
        logTransaction($GATEWAY["name"], $data, "Unsuccessful"); # Save to Gateway Log: name, data array, status
      
      }
    }
    
    // Send a response to mark this transaction as notified
    $response = array("status" => "OK", "message" => "Status recorded");
    header("Content-type: application/json");
    echo json_encode($response);
		
	} else {
	
		logTransaction($GATEWAY["name"], "Callback received an incorrect status from " . $_SERVER["REMOTE_ADDR"], "Error"); # Save to Gateway Log: name, data array, status
	}

?> 