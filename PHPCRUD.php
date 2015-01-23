<?php
    require 'erFunctions.php';
    //Make a function call and return the value from the functiob
    $wcsAJAX = doPostHandler($_POST);
    echo json_encode($wcsAJAX);

    //Function for the post handler
    function doPostHandler($wcsForm){

        // Declare the varibles from the post
        $sAction = $wcsForm['sAction'];
        $wcaResult = array(
            "bErr" => 0,
            "sErrDesc" => '',
            "sHtml" => ""
        );

        // used as a post handler to call the correct function
        if($sAction != ""){
            switch ($sAction) {
                case 'LookUpUser':
                    $wcaResult = checkDB($wcsForm);
                    break;
                case 'InsertUser':
                    $wcaResult = checkDB($wcsForm);
                    break;
                case 'Search':
                    $wcaResult = searchUsers($wcsForm['sSearchTerm']);
                    break;
                case 'CheckIn':
                    $wcaResult = CheckInUsers($wcsForm['aCheckIDs']);
                    break;
                case 'LookupAll':
                    $wcaResult = getCheckedin($wcsForm['nLimiter']);
                    break;
                case 'markPaid':
                    $wcaResult = updateCheckin($wcsForm);
                    break;
                case 'UpdateUser':
                    $wcaResult = updateWaiver($wcsForm);
                    break;
                case 'HideUser':
                    $wcaResult = HideCheckin($wcsForm);
                    break;
                
                default:
                    $wcaResult["sHtml"] = "No known action was provided";
                    break;
            }
        }
        return $wcaResult;
    }

	// I believe this is unused in production at the moment
	// Check the database for a user based off of their name and email address
	function checkDB($wcsForm){

		$wsSql = (string) $wsSql;
        $wsFormName = (string) $wcsForm['sName'];
        $wsFormEmail = (string) $wcsForm['sEmail'];

		$wcsReturn = stringFunctions::initalizeArray();

        $wcsReturn["items"] = "";

		// Check if required fields are blank
		if($wsFormName != "" && $wsFormEmail != ""){
			$wsSql = "SELECT ID FROM Waiver WHERE Name = '{$wsFormName}' AND Email = '{$wsFormEmail}'";

			// Attempt the insert
			try{
				$wcsReturn = databaseConnect::returnResults($wsSql);
			}
			catch(Exception $e){
				throw new Exception("Error Processing Request", 1, $e);
                $wcsReturn["bErr"] = 1;
				$wcsReturn["sErrDesc"] = $e;
				error_log($e);
			}
			// Log result
			//error_log(json_encode($wcsReturn));
			// If a user is found, check them in
			if(array_key_exists("items", $wcsReturn)){
                foreach ($wcsReturn["items"] as $wsTemp) {
                    foreach($wsTemp as $wsTemp2){
                        //error_log($wsTemp2);
                        insertCheckIn($wsTemp2);
                    }
                }
			}
            else{
                $wcsReturn = insertDB($wcsForm);
            }
		}

		return $wcsReturn;
	}

	// Insert a new user into the database returns a 1 or 0 (success or fail)
	function insertDB($wcsForm){

            $wcsReturn = stringFunctions::initalizeArray();

            $wcsReturn["nInsertRec"] = 0;
            $wcsReturn["nCheckInRec"] = 0;
            $wcsReturn["sHtml"] = "There was an error with the waiver!";

            $sErr = (string) $sErr;
            $wbErr = (boolean) 0;
            $wsSql = (string) $wsSql;
            $wsFormName = (string) $wcsForm['sName'];
            $wsFormEmail = (string) $wcsForm['sEmail'];
            $wnFormZip = $wcsForm['sZip'];
            $wnFormBmonth = $wcsForm['sBmonth'];	
            $wnFormBday = $wcsForm['sBday'];	
            $wnFormByear = $wcsForm['sByear'];
            $wsFormParent = (string) $wcsForm['sParent'];
            $wbFromTablet = 0;

            if(array_key_exists("bTablet",$wcsForm)){
                $wbFromTablet = 1;
            }


            if((is_string($wsFormName) && $wsFormName != "") && (is_string($wsFormEmail) && $wsFormEmail != "")){
//                $wsSql = "INSERT INTO Waiver(Name,Email,Zip,Bmonth,Bday,Byear,ParentName,`Last Visit`,`Number of Visits`) Value('{$wsFormName}','{$wsFormEmail}','{$wnFormZip}','{$wnFormBmonth}','{$wnFormBday}','{$wnFormByear}','{$wsFormParent}',CURDATE(),'1')";
                $wsSql = "INSERT INTO Waiver(Name,Email,Zip,Bmonth,Bday,Byear,ParentName,birthDate) Value('{$wsFormName}','{$wsFormEmail}','{$wnFormZip}','{$wnFormBmonth}','{$wnFormBday}','{$wnFormByear}','{$wsFormParent}','{$wnFormByear}-{$wnFormBmonth}-{$wnFormBday}')";

                try{
                   $wcsReturn = databaseConnect::returnResults($wsSql,1);
                }
                catch(Exception $e){
                    throw new Exception("Error Processing Request", 1, $sErr);
                    error_log($sErr);
                    $wcsReturn["sErrDesc"] = $sErr;
                    $wcsReturn["bErr"] = 1;
                }

                if(!$wcsReturn["bErr"] AND $wbFromTablet == 1){
                    $wcsReturn["nCheckInRec"] = insertCheckIn($wcsReturn["items"]);
                    if($wcsReturn["nCheckInRec"] != 0){
                        $wcsReturn["sHtml"] = "Waiver signed and checked in!";
                    }
                    else{
                        $wcsReturn["sHtml"] = "Waiver signed. Please checked in!";
                    }    
                }
            }
            // error_log(json_encode($wcsReturn));
            return $wcsReturn;
	}

	// Insert a checked in user returns a 1 or 0 (success or fail)
	function insertCheckIn($sWaiverID){
            $wcsReturn = stringFunctions::initalizeArray();

            $wbErr = (boolean) 0;
            $wnReturn = 0;
            $wsSql = (string) "INSERT INTO er_checkins(WaiverID,DateCheckedIn) Value ('{$sWaiverID}',NOW())";

            try{	
                $wcsReturn = databaseConnect::returnResults($wsSql,1);
            }
            catch(Exception $e){
                throw new Exception("Error Processing Request", 1, $e);
                error_log($e);
                $wcsReturn["sErrDesc"] = "Error Processing Request";
                $wcsReturn["bErr"] = 1;
            }
            
            if(!$wcsReturn["bErr"]){
                $wnReturn = $wcsReturn["items"];
            }

            return $wnReturn;
	}

	//Searches user in the database and returns a json structure (array with keys)
	function searchUsers($sSearchTerm){
            $wcsReturn = stringFunctions::initalizeArray();
            $wcsReturn["nResults"] = 0;

            $sSanitized = stringFunctions::SanatizeString($sSearchTerm);
            $wsSql = "SELECT ID, Name, Team, Bmonth, Bday, Byear, error FROM Waiver WHERE ((Name LIKE '%{$sSanitized}%') OR (Email = '{$sSanitized}') OR (Team LIKE '%{$sSanitized}%')) AND (DATE(IFNULL(`Last Visit`,'0000-00-00')) < CURDATE())";

            // Check to make sure the var is a string
            if(is_string($sSanitized) && $sSanitized != ''){
                //Query and return the database
                try{
                    $wcsReturn["cqData"] = databaseConnect::returnResults($wsSql,0);
                    if(array_key_exists("items", $wcsReturn["cqData"])){

                        $wcsReturn["nResults"] = 1;
                        $wcsReturn["sHtml"] .= '<div class="title col-xs-8 col-md-8" style="font-weight: bold;">';
                        $wcsReturn["sHtml"] .= '<div class="col-xs-5 col-md-5">Name</div><div class="col-xs-4 col-md-4">Team</div><div class="col-xs-3 col-md-3">Birthdate</div>';
                        $wcsReturn["sHtml"] .= "</div>";

                        foreach ($wcsReturn["cqData"]["items"] as $wsTemp) {
                            $wcsReturn["sHtml"] .= '<div class="checkIn col-xs-8 col-md-8">';
                            $wcsReturn["sHtml"] .= '<div class="col-xs-5 col-md-5">'.$wsTemp['Name'].'</div>';
                            $wcsReturn["sHtml"] .= '<div class="col-xs-4 col-md-4">'.$wsTemp['Team'].'</div>';
                            $wcsReturn["sHtml"] .= '<div class="col-xs-3 col-md-3">'.$wsTemp['Bmonth'].'/'.$wsTemp['Bday'].'/'.$wsTemp['Byear'];
                            $wcsReturn["sHtml"] .= '<input type="checkbox" class="checkID" id="'.$wsTemp['ID'].'" email="'.$wsTemp['error'].'"></div>';
                            $wcsReturn["sHtml"] .= "</div>";
                        }
                    }
                    
                }
                catch(Exception $e){
                        throw new Exception("Error Processing Request", 1, $e);
                        $wcsReturn["bErr"] = 1;
                        $wcsReturn["sErrDesc"] = $e;
                        error_log($e);
                }
                if(!$wcsReturn["bErr"] && $wcsReturn["sHtml"]!=""){
                        $wcsReturn["sHtml"] .= '<div class="submitBtn col-xs-12 col-md-12"><button class="btn btn-custom btn-lg" type="button" onClick="CheckemIn();">Check In</button></div>';
                }
                else{
                    $wcsReturn["sHtml"] = '<div class="col-xs-8 col-md-8" style="font-weight: bold;">There was a problem with the name, team, or email address that you searched for. Please try a different one!</div>';
                }
            }

            return $wcsReturn;
	}

	// Checks the selected user into the system returns a json structure (array with keys)
	function CheckInUsers($aCheckIn){

		$wcsReturn = stringFunctions::initalizeArray();

    	$wcsReturn["sHtml"] = '<div id="insertResults"> An error occured</div>';

		$wcaTemp = array();

		// loop over all of the values passed
		foreach ($aCheckIn as $sWIDs){
                    insertCheckIn($sWIDs);
		}

		// If an error occured in the SQL, set the HTML accordingly
		if(!$wcsReturn["bErr"]){
			$wcsReturn["sHtml"] ='<div id="insertResults">Success</div>';
		}

		// echo json_encode($wcaResult);
		return $wcsReturn;
	}

    // Function to return checked in players. wnLimit values can be (following database values): 0 = non rentals, 1 = morning rentals, 2 = afternoon rentals, 3 = full day rentals, 4 = show all checked in players.
	function getCheckedin($wnSessionLimit = "4"){
        $wcsReturn = stringFunctions::initalizeArray();

        // Changing the birthdate field to number of visits
        // $wsSql = (string) "SELECT DISTINCT Waiver.ID, Waiver.Name, Waiver.Team, Waiver.Bmonth, Waiver.Bday, Waiver.Byear, er_checkins.paid,er_checkins.session FROM `Waiver` INNER JOIN `er_checkins` ON er_checkins.WaiverID = Waiver.ID WHERE er_checkins.DateCheckedIn = CURDATE()";
        $wsSql = (string) "SELECT DISTINCT Waiver.ID, Waiver.Name, Waiver.Team, IFNULL(Waiver.`Number of Visits`,0) AS NumOfVisits, er_checkins.paid,er_checkins.session, er_checkins.GearReturned FROM `Waiver` INNER JOIN `er_checkins` ON er_checkins.WaiverID = Waiver.ID WHERE (DATE(er_checkins.DateCheckedIn) = CURDATE()) AND hideFromScreen = 0";
        if($wnSessionLimit != "4"){
            $wsSql .= " AND er_checkins.Session = '{$wnSessionLimit}'";
        }
        $wsSql .= " ORDER BY er_checkins.paid, er_checkins.session, er_checkins.GearReturned, Waiver.Name";
        try{
            $wcqResult = databaseConnect::returnResults($wsSql,0);
            if(array_key_exists("items", $wcqResult)){
                $wcsReturn["sHtml"] .= '<div class="checkedtitle col-xs-12 col-md-12" style="font-weight: bold;">';
                // Changing the birthdate field to number of visits
                // $wcaResult["sHtml"] .= '<div class="col-xs-1 col-md-1 checkinTitle">Paid</div><div class="col-xs-5 col-md-5">Name</div><div class="col-xs-2 col-md-2">Team</div><div class="col-xs-2 col-md-2">Birthdate</div><div class="col-xs-1 col-md-1 checkinTitle">Morning</div><div class="col-xs-1 col-md-1 checkinTitle">Afternoon</div>';
                $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1 checkinTitle">Paid</div><div class="col-xs-4 col-md-4">Name</div><div class="col-xs-2 col-md-2">Team</div><div class="col-xs-1 col-md-1">No. of Visits</div><div class="col-xs-1 col-md-1 checkinTitle">Morning</div><div class="col-xs-1 col-md-1 checkinTitle">Afternoon</div><div class="col-xs-1 col-md-1 checkinTitle">Gear Returned</div>';
                $wcsReturn["sHtml"] .= "</div>";
                foreach ($wcqResult["items"] as $wsTemp) {
                    $wcsReturn["sHtml"] .= '<div class="checkIn col-xs-12 col-md-12">';
                    if($wsTemp["paid"]==1){
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="checkID" id="'.$wsTemp['ID'].'" onclick="togglePaid('.$wsTemp['ID'].');" checked></div>';
                    }
                    else{
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="checkID" id="'.$wsTemp['ID'].'" onclick="togglePaid('.$wsTemp['ID'].');"></div>';
                    }
                    $wcsReturn["sHtml"] .= '<div id="playerName-'.$wsTemp['ID'].'" class="col-xs-4 col-md-4">'.$wsTemp['Name'].'</div>';
                    $wcsReturn["sHtml"] .= '<div id="playerTeam-'.$wsTemp['ID'].'" class="col-xs-2 col-md-2">'.$wsTemp['Team'].'</div>';
                    // $wcsReturn["sHtml"] .= '<div class="col-xs-2 col-md-2">'.$wsTemp['Bmonth'].'/'.$wsTemp['Bday'].'/'.$wsTemp['Byear'].'</div>';
                    $wcsReturn["sHtml"] .= '<div id="visits-'.$wsTemp['ID'].'" class="col-xs-1 col-md-1">'.$wsTemp['NumOfVisits'].'</div>';

                    //determine if the rental session
                    if($wsTemp["paid"]==1 && $wsTemp["session"]==1){
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="MornRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Morn-'.$wsTemp['ID'].'");" checked></div>';
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="noonRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Noon-'.$wsTemp['ID'].'");"></div>';
                    }
                    else if($wsTemp["paid"]==1 && $wsTemp["session"]==2){
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="MornRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Morn-'.$wsTemp['ID'].'");"></div>';
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="noonRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Noon-'.$wsTemp['ID'].'");" checked></div>';
                    }
                    else if($wsTemp["paid"]==1 && $wsTemp["session"]==3){
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="MornRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Morn-'.$wsTemp['ID'].'");" checked></div>';
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="noonRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Noon-'.$wsTemp['ID'].'");" checked></div>';
                    }
                    else{
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="MornRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Morn-'.$wsTemp['ID'].'");"></div>';
                        $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="noonRental" onclick="toggleSessPaid('.$wsTemp['ID'].');" id="Noon-'.$wsTemp['ID'].'");"></div>';
                    }
                    //if they are a rental customer show the gear returned checkbox, otherwise return an empty div.
                    if($wsTemp["session"]>0){
                        if($wsTemp["GearReturned"]==1){
                            $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="ReturnGear" onclick="togglePaid('.$wsTemp['ID'].');" id="gear-'.$wsTemp['ID'].'");" checked></div>';
                        }
                        else{
                            $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><input type="checkbox" class="ReturnGear" onclick="togglePaid('.$wsTemp['ID'].');" id="gear-'.$wsTemp['ID'].'");"></div>';
                        }
                    }
                    else{
                         $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"></div>';
                    }
                    $wcsReturn["sHtml"] .= '<div class="col-xs-1 col-md-1"><a class="btn btn-mini btn-danger" title="Hide entry from the check in screen" href="#" onclick="removeCheckin('.$wsTemp['ID'].')">Hide</a></div>';
                    $wcsReturn["sHtml"] .= "</div>";
                }
            }
        }
        catch(Exception $e){
            throw new Exception("Error Processing Request", 1, $e);
            error_log($e);
            $wcsReturn["bErr"] = 1;
        }

        return $wcsReturn;
	}

    function updateCheckin($wcsForm){
            $wcsReturn = stringFunctions::initalizeArray();

            $wbPaid = $wcsForm["bPaid"];
            $wnSession = $wcsForm["nSession"];
            $wbGear = $wcsForm["bGear"];
            $wnID = $wcsForm["nID"];
            $wsSql = (string) "UPDATE er_checkins SET Paid = {$wbPaid}, session = {$wnSession}, GearReturned = {$wbGear}";
            //if the gear is returned, mark a timestamp.
            if($wbGear){
                $wsSql .= ", TimeReturned = NOW()";
            }
            else{
                $wsSql .= ", TimeReturned = '0000-00-00 00:00:00'";
            }

            $wsSql .= " WHERE WaiverID = {$wnID} AND (DATE(`DateCheckedIn`) = CURDATE())";
            try{
                    $wcqResult = databaseConnect::returnResults($wsSql,0);
            }
            catch(Exception $e){
                throw new Exception("Error Processing Request", 1, $e);
                error_log($e);
                $wcsReturn["bErr"] = 1;
                $wcsReturn["sErrDesc"] = $e;
            }

            if(!$wcsReturn["bErr"]){
                $wcsReturn["sHtml"] = 1;

                checkinPaid($wnID,$wbPaid);
            }

        return $wcsReturn;
    }
	function HideCheckin($wcsForm){
        $wcsReturn = stringFunctions::initalizeArray();

        $wnID = $wcsForm["nID"];
        $wsSql = (string) "UPDATE er_checkins SET hideFromScreen = 1 WHERE WaiverID = {$wnID} AND (DATE(`DateCheckedIn`) = CURDATE())";

        try{
            $wcsReturn = databaseConnect::returnResults($wsSql,0);
        }
        catch(Exception $e){
            throw new Exception("Error Processing Request", 1, $e);
            error_log($e);
            $wcsReturn["bErr"] = 1;
            $wcsReturn["sErrDesc"] = $e;
        }

        return $wcsReturn;
	}
        
    // Update Checked in status using the waiver ID and add boolean
    function checkinPaid($wnWavID, $bAdd){
        $wcsReturn = stringFunctions::initalizeArray();

        if($bAdd){
            $wsSql = "UPDATE Waiver SET `Last Visit` = (SELECT DateCheckedIn FROM er_checkins WHERE WaiverID = '{$wnWavID}' ORDER BY DateCheckedIn DESC LIMIT 1), `Number of Visits` = (IFNULL(`Number of Visits`,0) + 1) WHERE ID IN ('{$wnWavID}') AND IFNULL(DATE(`Last Visit`),'0000-00-00 00:00:00') < CURDATE()";
        }
        else{
            $wsSql = "UPDATE Waiver SET `Last Visit` = (SELECT DateCheckedIn FROM er_checkins WHERE WaiverID = '{$wnWavID}' ORDER BY DateCheckedIn DESC LIMIT 1,1), `Number of Visits` = CASE WHEN (IFNULL(`Number of Visits`,0) = 0) THEN 0 ELSE IFNULL(`Number of Visits`,0) - 1 END WHERE ID IN ('{$wnWavID}')";
        }
        // insert the values into the databse
        try{
            $wcqResult = databaseConnect::returnResults($wsSql,1);
        }
        catch(Exception $e){
            throw new Exception("Error Processing Request", 1, $e);
            error_log($e);
            $wcsReturn["bErr"] = 1;
            $wcsReturn["sErrDesc"] = $e;
        }
    }
    
    // Update the waiver in the database and return that status to the page
    function updateWaiver($wcsForm){

        $wcsReturn = stringFunctions::initalizeArray();

        $wcsReturn["nInsertRec"] = 0;
        $wcsReturn["nCheckInRec"] = 0;
        $wcsReturn["sHtml"] = "There was an error with the waiver!";

        $wbErr = (boolean) 0;
        $wsSql = (string) $wsSql;
        $wsFormName = (string) $wcsForm['sName'];
        $wsFormEmail = (string) $wcsForm['sEmail'];
        $wnFormZip = $wcsForm['sZip'];
        $wnFormBmonth = $wcsForm['sBmonth'];	
        $wnFormBday = $wcsForm['sBday'];	
        $wnFormByear = $wcsForm['sByear'];
        $wsFormID = (string) $wcsForm['nUserID'];
        
        if((is_string($wsFormName) && $wsFormName != "") && (is_string($wsFormEmail) && $wsFormEmail != "")){
            $wsSql = "UPDATE Waiver SET Name = '{$wsFormName}', Email = '{$wsFormEmail}', Bmonth = {$wnFormBmonth}, Bday = {$wnFormBday}, Byear = {$wnFormByear}, Zip = {$wnFormZip}, error = 0 WHERE ID = {$wsFormID}";
            
            try{
               $wcsReturn = databaseConnect::returnResults($wsSql,1);
            }
            catch(Exception $e){
                throw new Exception("Error Processing Request", 1, $e);
                error_log($e);
                $wcsReturn["bErr"] = 1;
                $wcsReturn["sErrDesc"] = $e;
            }

            if(!$wbErr){
                $wcsReturn["nCheckInRec"] = insertCheckIn($wsFormID);
                if($wcsReturn["nCheckInRec"] != 0){
                    $wcsReturn["sHtml"] = "Waiver signed and checked in!";
                }
                else{
                    $wcsReturn["sHtml"] = "Waiver signed. Please checked in!";
                }    
            }
        }
        return $wcsReturn;
    }
?>