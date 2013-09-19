<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../CompaniesHouse.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['companynumber'])) {

	 // Deal with form submission, do a CH request and print out information...
	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($companyDetails = $companiesHouse->companyDetails($_GET['companynumber'])) {

		echo 'Company name: '.$companyDetails['name'].'<br />';
		echo 'Company type: '.$companyDetails['category'].'<br />';
		echo 'Company status: '.$companyDetails['status'].'<br />';
		echo 'Company incorporation date: '.date('jS F Y', $companyDetails['incorporation_date']).'<br />';
		# etc.

	} else {
	 // No companies found / error occured...
		echo 'No companies found for \''.$_GET['companynumber'].'\'.';
	}

} else {

	 // First page visit, display the search box...
?>

<form action="" method="get">
	Lookup company: <input name="companynumber" type="text" /> <input type="submit" value="Lookup" />
</form>

<?php

}

?>