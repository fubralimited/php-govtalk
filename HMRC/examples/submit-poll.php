<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../VAT.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['endpoint']) && isset($_GET['correlation'])) {

	$hmrcVat = new HmrcVat($hmrcUserId, $hmrcPassword);
	if ($pollResponse = $hmrcVat->declarationResponsePoll($_GET['correlation'], $_GET['endpoint'])) {
	
		if (isset($pollResponse['endpoint'])) {
			echo 'Response pending.  Please wait '.$pollResponse['interval'].' seconds and then refresh this page to try again.';
		} else {
			echo 'Response received, delete command sent.  See below:';
			var_dump($pollResponse); exit;
			if ($hmrcVat->sendDeleteRequest()) {
				echo 'Delete request successful. Resource no longer exists on Gateway.';
			} else {
				echo 'Delete request failed. Resource may still exist on Gateway.';
			}
		}
		
	} else {
	
		echo 'Government Gateway returned errors in response to poll request:';
		var_dump($hmrcVat->getResponseErrors());
		
	}

} else {
	echo 'Unable to poll Government Gateway: missing arguments.';
}