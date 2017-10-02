<?php

	 // Include the Companies House module...
require_once('../../../GovTalk.php');
require_once('../CompaniesHouse.php');

	// Include the Companies House configuration
require_once('config.php');

if (isset($_GET['officersurname']) && isset($_GET['officerforename'])) {

	 // Deal with form submission, do a CH search and print out a list...
	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($officerList = $companiesHouse->companyOfficerSearch($_GET['officersurname'], $_GET['officerforename'])) {

	 // Nearest match...
		if (is_array($officerList['exact'])) {
			echo 'Nearest name match: '.$officerList['exact']['title'].' '.$officerList['exact']['forename'].' '.$officerList['exact']['surname'].' (<a href="view-officer-details.php?id='.urlencode($officerList['exact']['id']).'">View details</a>)';
		}

	 // Similar (including nearest match)...
		echo '<ul>';
		foreach ($officerList['match'] AS $officer) {
			echo '<li>'.$officer['title'].' '.$officer['forename'].' '.$officer['surname'].' (<a href="view-officer-details.php?id='.$officer['id'].'">View</a>)</li>';
		}
		echo '</ul>';

	} else {
	 // No officers found / error occured...
		echo 'No officers found for \''.$_GET['officerforename'].' '.$_GET['officersurname'].'\'.';
	}

} else {

	 // First page visit, display the search box...
?>

<form action="" method="get">
	Search for officer (forename / surname): <input name="officerforename" type="text" /> <input name="officersurname" type="text" /> <input type="submit" value="Search" />
</form>

<?php

}

?>