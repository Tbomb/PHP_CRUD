<?php
    class databaseConnect{
        
        public static function returnResults($sql, $wbInsert=0){
            $wnRowNum = (int) $wnRowNum;
            $wsRow = (string) $wsRow;
            $wcaReturn = array();
            try{
            	$wcqLink = databaseConnect::dboConnect();
            }
            catch (Exception $e){
                throw new Exception("Error Processing Request", 1, $e);
                error_log($e);
            }
            
            $wcqResult = $wcqLink->query($sql);
            
            if($wbInsert){
                $wcaReturn["items"] = $wcqLink->insert_id;
            }
            else{
                if($wcqResult && $wcqResult->num_rows > 0){
                    $wnRowNum = mysqli_num_rows($wcqResult);
                    if($wnRowNum){
                        while($wsRow = $wcqResult->fetch_assoc()){
                            $wcaReturn["items"][] = $wsRow;
                        }
                    }
                    $wcqResult->free();
                }
            }
            $wcqLink->close();
            
            return $wcaReturn;
        }

        public static function dboConnect(){
            $wsHostname = (string) 'localhost';
            $wsUsername = (string) 'dbUserName';
            $wsPassword = (string) 'dbPassword!';
            $wsDbName = (string) 'dbName';
            try{
                $wcoLink = new mysqli($wsHostname, $wsUsername, $wsPassword, $wsDbName);
            }
            catch(Exception $e){
                throw new Exception("Error Processing Request", 1, $e);
                error_log($e);
            }
            return $wcoLink;
        }
    }
    
    class stringFunctions{
        function SanatizeString($wsString){
            $sSearchStr = array("'","=","%","&","--");
            $sReplaceStr = array("","","","","");
            $wsSanatized = str_replace($sSearchStr,$sReplaceStr,$wsString);
            
            return $wsSanatized;
        }

        function initalizeArray(){
            $wcaStandardArray = array(
                "bErr" => 0,
                "sErrDesc" => "",
                "cqData" => "",
                "sHtml" => ""
            );

            return $wcaStandardArray;
        }
    }
?>