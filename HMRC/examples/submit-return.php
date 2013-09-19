<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../VAT.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['formdata'])) {

	$hmrcVat = new HmrcVat($hmrcUserId, $hmrcPassword, 'vsips');
	if ($pollRequest = $hmrcVat->declarationRequest($_GET['vatnumber'], $_GET['periodid'], $_GET['capacity'], $_GET['formdata'][1], $_GET['formdata'][2], $_GET['formdata'][4], $_GET['formdata'][6], $_GET['formdata'][7], $_GET['formdata'][8], $_GET['formdata'][9])) {
	
		echo 'Return successfully submitted.<br /><br />';
		echo 'Endpoint: '.$pollRequest['endpoint'].'<br />';
		echo 'Interval: '.$pollRequest['interval'].' seconds<br />';
		echo 'Correlation: '.$pollRequest['correlationid'].'<br />';
		echo '<br /><a href="submit-poll.php?endpoint='.urlencode($pollRequest['endpoint']).'&correlation='.urlencode($pollRequest['correlationid']).'">Poll for HMRC response.</a><br />';
		
	} else {
	
		echo 'Return was rejected by the Government Gateway: ';
		var_dump($hmrcVat->getResponseErrors());
		
	}

} else {

?>

<form action="" method="get">
	VAT number: <input name="vatnumber" type="text" value="999900001" /><br />
	Period ID: <input name="periodid" type="text" value="2009-01" /><br />
	Capacity:
	<select name="capacity">
		<option>Individual</option>
		<option>Company</option>
		<option>Agent</option>
		<option>Bureau</option>
		<option>Partnership</option>
		<option>Trust</option>
		<option>Employer</option>
		<option>Government</option>
		<option>Acting in Capacity</option>
		<option>Other</option>
	</select><br /><br />
	Box 1: <input name="formdata[1]" type="text" value="6035.33" /><br />
	Box 2: <input name="formdata[2]" type="text" value="0.00" /><br />
	Box 3: <input name="formdata[3]" type="text" disabled="disabled" /><br />
	Box 4: <input name="formdata[4]" type="text" value="84.75" /><br />
	Box 5: <input name="formdata[5]" type="text" disabled="disabled" /><br />
	Box 6: <input name="formdata[6]" type="text" value="40235.35" /><br />
	Box 7: <input name="formdata[7]" type="text" value="993.54" /><br />
	Box 8: <input name="formdata[8]" type="text" value="0.00" /><br />
	Box 9: <input name="formdata[9]" type="text" value="0.00" /><br />
	<input type="submit" value="Submit return" />
</form>

<?

}

?>