<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../CompaniesHouse.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['dockey'])) {

	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($documentList = $companiesHouse->documentInfo($_GET['companynumber'], $_GET['companyname'], $_GET['dockey'])) {
	
		if ($documentRequest = $companiesHouse->documentRequest($documentList['key'], 'Document request test')) {
			echo 'Document is available: <a href="'.$documentRequest['endpoint'].'">here</a>.';
		} else {
			echo 'Unable to fetch document: document request failed.';
		}
	
	} else {
		echo 'Unable to fetch document: document info failed.';
	}

} else if (isset($_GET['companyname']) && isset($_GET['companynumber'])) {

	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($documentList = $companiesHouse->filingHistory($_GET['companynumber'])) {
	
?>
<form action="" method="get">
	<input type="hidden" name="companyname" value="<?=$_GET['companyname']?>" />
	<input type="hidden" name="companynumber" value="<?=$_GET['companynumber']?>" />
	Select document: <select name="dockey">
<?php

		foreach ($documentList AS $document) {
			echo '<option value="'.$document['key'].'">'.implode(', ', $document['description']).'</option>';
		}
		
?>
	</select>
	<input type="submit" value="Fetch document" />
</form>
<?php
	
	} else {
		echo 'No documents found for company '.$_GET['companynumber'];
	}

} else {

?>

<form action="" method="get">
	Search for company documents (name &amp; number): <input name="companyname" type="text" /> <input name="companynumber" type="text" /> <input type="submit" value="Search" />
</form>

<?php

}

?>