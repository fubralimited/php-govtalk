<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../CompaniesHouse.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['companyname'])) {

	 // Deal with form submission, do a CH search and print out a list...
	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($companyList = $companiesHouse->companyNameSearch($_GET['companyname'])) {

	 // Exact match...
		if (is_array($companyList['exact'])) {
			echo 'Exact name match: '.$companyList['exact']['name'].' (<a href="view-company-details.php?companynumber='.$companyList['exact']['number'].'">'.$companyList['exact']['number'].'</a>)';
		}
		
	 // Similar (including exact match)...
		echo '<ul>';
		foreach ($companyList['match'] AS $company) {
			echo '<li>'.$company['name'].' (<a href="view-company-details.php?companynumber='.$company['number'].'">'.$company['number'].'</a>)</li>';
		}
		echo '</ul>';
		
	} else {
	 // No companies found / error occured...
		echo 'No companies found for \''.$_GET['companyname'].'\'.';
		var_dump($companiesHouse->getFullXMLResponse());
	}
	
} else {

	 // First page visit, display the search box...
?>

<form action="" method="get">
	Search for company: <input name="companyname" type="text" /> <input type="submit" value="Search" />
</form>

<?php

}

?>