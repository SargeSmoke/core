<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

//New PDO DB connection
try {
  	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

@session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_POST["address"]) . "/alarm.php" ;

if (isActionAccessible($guid, $connection2, "/modules/System Admin/alarm.php")==FALSE) {
	//Fail 0
	$URL.="&updateReturn=fail0" ;
	header("Location: {$URL}");
}
else {
	//Proceed!
	$alarm=$_POST["alarm"] ;
	
	//Validate Inputs
	if ($alarm!="None" AND $alarm!="General" AND $alarm!="Lockdown") {
		//Fail 3
		$URL.="&updateReturn=fail3" ;
		header("Location: {$URL}");
	}
	else {	
		$fail=FALSE ;
		
		//Write setting to database
		try {
			$data=array("alarm"=>$alarm); 
			$sql="UPDATE gibbonSetting SET value=:alarm WHERE scope='System' AND name='alarm'" ;
			$result=$connection2->prepare($sql);
			$result->execute($data);
		}
		catch(PDOException $e) { 
			$fail=TRUE ;
		}	
		
		//Check for existing alarm
		$checkFail=FALSE ;
		try {
			$data=array(); 
			$sql="SELECT * FROM gibbonAlarm WHERE status='Current'" ;
			$result=$connection2->prepare($sql);
			$result->execute($data);
		}
		catch(PDOException $e) { 
			$checkFail=TRUE ;
		}
			
		//Alarm is being turned on, so insert new record
		if ($alarm=="General" OR $alarm=="Lockdown") {
			if ($checkFail==TRUE) {
				$fail=TRUE ;
			}
			else {
				if ($result->rowCount()==0) {
					//Write alarm to database
					try {
						$data=array("type"=>$alarm, "gibbonPersonID"=>$_SESSION[$guid]["gibbonPersonID"], "timestampStart"=>date("Y-m-d H:i:s")); 
						$sql="INSERT INTO gibbonAlarm SET type=:type, status='Current', gibbonPersonID=:gibbonPersonID, timestampStart=:timestampStart" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						$fail=TRUE ;
					}	 
				}
				else {
					$row=$result->fetch() ;
					try {
						$data=array("type"=>$alarm, "gibbonAlarmID"=>$row["gibbonAlarmID"]); 
						$sql="UPDATE gibbonAlarm SET type=:type WHERE gibbonAlarmID=:gibbonAlarmID" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						$fail=TRUE ;
					}	 
				}
			}
		}
		else {
			if ($result->rowCount()==1) {
				$row=$result->fetch() ;
				try {
					$data=array("timestampEnd"=>date("Y-m-d H:i:s"), "gibbonAlarmID"=>$row["gibbonAlarmID"]); 
					$sql="UPDATE gibbonAlarm SET status='Past', timestampEnd=:timestampEnd WHERE gibbonAlarmID=:gibbonAlarmID" ;
					$result=$connection2->prepare($sql);
					$result->execute($data);
				}
				catch(PDOException $e) { 
					$fail=TRUE ;
				}	 
			}
			else {
				$fail=TRUE ;
			}
		}
			
		if ($fail==TRUE) {
			//Fail 2
			$URL.="&updateReturn=fail2" ;
			header("Location: {$URL}");
		}
		else {
			//Success 0
			getSystemSettings($guid, $connection2) ;
			$URL.="&updateReturn=success0" ;
			header("Location: {$URL}");
		}
	}
}
?>