<?php
	// Declare the database variables
	$psHostname = 'localhost';
	$psUsername = 'declareUName';
	$psPassword = 'delcarePass';
	$psDBName = "declareDBName";
	$pcoLink = new mysqli($psHostname, $psUsername, $psPassword, $psDBName);

	// Declare the varibles from the post
	$psAction = $_POST['sAction'];
	$psFormName = $_POST['sName'];
	$psFormEmail = $_POST['sEmail'];
	$psFormZip = $_POST['sZip'];
	$psFormBirthdate = $_POST['sBirthDate'];	
	$psFormGaurdian = $_POST['sGuardian'];

	// used as a post handler to call the correct function
	if($psAction != ""){

		if($psAction == 'LookUpUser'){
			$wcsReturn = checkDB($FormName,$FormEmail); 
		}
		else if($psAction == 'InsertUser'){
			$wcsReturn = insertDB($psFormName,$psFormEmail,$psFormZip,$psFormBirthdate,$psFormGuardian);
		}
		else if($psAction == 'Search'){
			$wcsReturn = searchUsers($_POST['sSearchTerm']);
		}
		else if($psAction = 'CheckIn'){
			$wcsReturn = CheckInUsers($_POST['aCheckIDs']);
		}

		echo $wcsReturn;
	}

	// Insert a new user into the database returns a 1 or 0 (success or fail)
	function insertDB($tsFormName,$tsFormEmail,$tsFormZip,$tsFormBirthdate,$tsFormGuardian){

		$wbRecFound = 0;
		$wsErrDesc = "";
		$wsSql = '';

		$wcaSearchStr = array("'","=","%","&","--");
		$wcaReplaceStr = array("","","","","");
		// remove characters that may cause problems in form fields
		$wsFormName = str_replace($wcaSearchStr,$wcaReplaceStr,$tsFormName);
		$wsFormEmail = str_replace($wcaSearchStr,$wcaReplaceStr,$tsFormEmail);
		$wsFormZip = str_replace($wcaSearchStr,$wcaReplaceStr,$tsFormZip);
		$wsFormBirthdate = str_replace($wcaSearchStr,$wcaReplaceStr,$tsFormBirthdate);	
		$wsFormGuardian = str_replace($wcaSearchStr,$wcaReplaceStr,$tsFormGuardian);


		if($wsFormName != "" && $wsFormEmail != ""){
			$wsSql = "INSERT INTO Users(Name,Email,Zip,BirthDate,GuardianName) Value('{$wsFormName}','{$wsFormEmail}','{$wsFormZip}','{$wsFormBirthdate}','{$wsFormGuardian}')";
			$db = $pcoLink->select_db($psDBName);

			try{
				$result = $pcoLink->query($wsSql);
				$wnInserted_id = $pcoLink->insert_id;
				$pcoLink->close();
				error_log($wnInserted_id);
			}
			catch(Exception $e){
				throw new Exception("Error Processing Request", 1, $e);
				$wsErrDesc = $e;
				error_log($wsErrDesc);
			}

			error_log($result);
			if(!$wsErrDesc && $wnInserted_id != 0){
				$wbRecFound = insertCheckIn($wnInserted_id);
			}
		}
		error_log($wsFormEmail);
		echo $wbRecFound;
	}

	// Insert a checked in user returns a 1 or 0 (success or fail)
	function insertCheckIn($tsID){

		$wbRecFound = 0;
		$wnInserted_id = 0;
		$wsErrDesc = "";
		$wsSql = "INSERT INTO Visit(ID,DateCheckedIn) Value ('{$tsID}',CURDATE())";

		try{	
			$pcoLink->query($wsSql);
			$wnInserted_id = $pcoLink->insert_id;
			$pcoLink->close();
		}
		catch(Exception $e){
			throw new Exception("Error Processing Request", 1, $e);
			$wsErrDesc = $e;
			error_log($wsErrDesc);
		}

		error_log($result);
		if(!$wsErrDesc && $inserted_id != 0){
			$wbRecFound = 1;
		}
		echo $wbRecFound;
	}

	//Searches user in the database and returns a json structure (array with keys)
	function searchUsers($tsSearchTerm){

		$pcoLink;

		$wcaResult = array(
		"bErr" => 0,
		"sErrDesc" => "",
		"sHtml" => "There was a Problem with the search term. Please try again",
		);

		$wsRow;
		$wcaSearchStr = array("'","=","%","&","--");
		$wcaReplaceStr = array("","","","","");
		$wsSanitized = str_replace($wcaSearchStr,$wcaReplaceStr,$tsSearchTerm);
		$wsSql = "SELECT ID, Name, Group FROM Table WHERE (Name LIKE '%{$wsSanitized}%') OR (Email = '{$wsSanitized}') OR (Group LIKE '%{$wsSanitized}%')";

		// Check to make sure the var is a string
		if(is_string($wsSanitized)){

			//Query and return the database
			try{
				$wcaResult = $pcoLink->query($wsSql);

				$wsRow = $wcaResult->fetch_array(MYSQLI_ASSOC);

				while($wsRow = $wcaResult->fetch_array()){
					$wcaResult["sHtml"] .= '<div class="checkIn">';
					$wcaResult["sHtml"] .= '<input type="checkbox" class="checkID" id="'.$wsRow['ID'].'"> ';
					$wcaResult["sHtml"] .= $wsRow['Name']." ";
					$wcaResult["sHtml"] .= " ".$wsRow['Group'];
					$wcaResult["sHtml"] .= "</div>";
				}

				$wcaResult->free();
				$pcoLink->close();
			}
			catch(Exception $e){
				throw new Exception("Error Processing Request", 1, $e);
				$wcaResult["sErrDesc"] = $e;
				error_log($wcaResult["sErrDesc"]);
			}
			if(!$wcaResult["bErr"]){
				$wcaResult["sHtml"] .= '<button type="button" onClick="CheckemIn();">Check In</button>';
			}
		}

		echo json_encode($wcaResult);
	}

	// Checks the selected user into the system returns a json structure (array with keys)
	function CheckInUsers($aCheckIn){

		$wcaResult = array(
		"bErr" => 0,
		"sErrDesc" => "",
		"sHtml" => '<div id="insertResults"> An error occured</div>',
		);
		$wcaTemp = array();

		// loop over all of the values passed
		foreach ($aCheckIn as $sWIDs){
			$wcaTemp[] = "('{".$sWIDs."}',CURDATE())";
		}

		// insert the values into the databse
		try{
			$wsSql = "INSERT INTO Visit(ID,visitDate) VALUES ".implode(',', $wcaTemp);
			$pcoLink->query($wsSql);
			$pcoLink->close();
		}
		catch(Exception $e){
			throw new Exception("Error Processing Request", 1, $e);
			$wcaResult["bErr"] = 1;
			$wcaResult["sErrDesc"] = $e;
			error_log($wcaResult["sErrDesc"]);
		}

		// If an error occured in the SQL, set the HTML accordingly
		if(!$wcaResult["bErr"]){
			$wcaResult["sHtml"] ='<div id="insertResults">Success</div>';
		}

		echo json_encode($wcaResult);
	}

?>