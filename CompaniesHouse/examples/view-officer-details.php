<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../CompaniesHouse.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['id'])) {

	 // Deal with form submission, do a CH request and print out information...
	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($officerDetails = $companiesHouse->officerDetails($_GET['id'], 'Testing')) {

		echo 'Officer name: '.$officerDetails['forename'].' '.$officerDetails['surname'].'<br />';
		echo 'Officer DOB: '.date('d-m-y', $officerDetails['dob']).'<br />';
		foreach ($officerDetails['company'] AS $company) {
			echo '<hr />';
			echo 'Company: '.$company['name'].' (<a href="view-company-details.php?companynumber='.$company['number'].'">'.$company['number'].'</a>)<br />';
			foreach ($company['appointment'] AS $appointment) {
				echo 'Officer type: '.$appointment['type'].'<br />';
			}
		}
		# etc.

	} else {
	 // No officer found / error occured...
		echo 'No officer found for \''.$_GET['id'].'\'.';
	}

} else {

	 // First page visit, display the search box...
?>

<form action="" method="get">
	Lookup officer: <input name="id" type="text" /> <input type="submit" value="Lookup" />
</form>

<?php

}

?>