<?php
/*
DROP DATABASE openq;
CREATE DATABASE openq DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;



DROP TABLE IF EXISTS oq_questions;
CREATE TABLE IF NOT EXISTS oq_questions (
 iID BIGINT AUTO_INCREMENT PRIMARY KEY,
 sQuestion VARCHAR(100) NULL DEFAULT NULL,
 sDescription VARCHAR(2000) NULL DEFAULT NULL,
 dAdded TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

DROP TABLE IF EXISTS oq_answers;
CREATE TABLE IF NOT EXISTS oq_answers (
 iID BIGINT AUTO_INCREMENT PRIMARY KEY,
 iQuestionID BIGINT NOT NULL,
 sAnswer VARCHAR(200) NULL DEFAULT NULL,
 sSession VARCHAR(50) NULL DEFAULT NULL,
 sIP VARCHAR(50) NULL DEFAULT NULL,
 sCountry VARCHAR(50) NULL DEFAULT NULL,
 sLocality VARCHAR(100) NULL DEFAULT NULL,
 sCity VARCHAR(100) NULL DEFAULT NULL,
 sReferer VARCHAR(255) NULL DEFAULT NULL,
 aUtmCampaign VARCHAR(255) NULL DEFAULT NULL,
 sOsBrowser VARCHAR(255) NULL DEFAULT NULL,
 sLanguage VARCHAR(100) NULL DEFAULT NULL,
 dAdded TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE INDEX oq_index_questions_answers ON oq_answers (iQuestionID);


DROP TABLE IF EXISTS oq_votes;
CREATE TABLE IF NOT EXISTS oq_votes (
 iID BIGINT AUTO_INCREMENT PRIMARY KEY,
 iAnswerID BIGINT NOT NULL,
 sSession VARCHAR(50) NULL DEFAULT NULL,
 sIP VARCHAR(50) NULL DEFAULT NULL,
 sCountry VARCHAR(50) NULL DEFAULT NULL,
 sLocality VARCHAR(100) NULL DEFAULT NULL,
 sCity VARCHAR(100) NULL DEFAULT NULL,
 sReferer VARCHAR(255) NULL DEFAULT NULL,
 aUtmCampaign VARCHAR(255) NULL DEFAULT NULL,
 sOsBrowser VARCHAR(255) NULL DEFAULT NULL,
 sLanguage VARCHAR(100) NULL DEFAULT NULL,
 dAdded TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE INDEX oq_index_answers_votes ON oq_votes (iAnswerID);


*/

include_once("config.php");



//Set encodings
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

//SETUP Timezone
date_default_timezone_set("UTC");

//SETUP DB connection
$sErrorMessage = null;
if(is_null($sOpenqDbPort)) {
	$oConnection = new mysqli($sOpenqDbHost, $sOpenqDbUser, $sOpenqDbPass, $sOpenqDbName);
} else {
	$oConnection = new mysqli($sOpenqDbHost, $sOpenqDbUser, $sOpenqDbPass, $sOpenqDbName, (int)$sOpenqDbPort);
}

//Set the encoding
if (!$oConnection->set_charset("utf8")) {
	$sErrorMessage = "Connection could not set UTF8 encoding: " . $oConnection->connect_error;
}

// Check connection
if ($oConnection->connect_errno) {
	$sErrorMessage = "Connection failed: " . $oConnection->connect_error;
}



//Start the session
session_start();

//Check if this is the admin
if(isset($_GET["admin"])) {
	$_SESSION["bIsAdmin"] = $_GET["admin"] == $sOpenqAdminPass;
}
$bIsAdmin = isset($_SESSION["bIsAdmin"]) ? $_SESSION["bIsAdmin"] : false;

$sErrorMessage = null;

//Get the referer url if available, store them for future use
$_SESSION["referer"] = isset($_SESSION["referer"]) ? $_SESSION["referer"] : null;
if(isset($_SERVER['HTTP_REFERER']) && ($aUrlParse = parse_url($_SERVER['HTTP_REFERER'])) && 
	strpos($aUrlParse['host'], $_SERVER['SERVER_NAME']) === false && strpos($aUrlParse['host'], "localhost") === false) {
	$_SESSION["referer"] = $_SERVER['HTTP_REFERER'];
}
$sReferer = $_SESSION["referer"];

//Get the UTM variables if available, store them for future use
$aUtmCampaign = array();
if(isset($_GET['utm_source']) && is_string($_GET['utm_source']) && trim($_GET['utm_source']) != "") {
	$aUtmCampaign["utm_source"] = $_GET["utm_source"];
}
if(isset($_GET['utm_medium']) && is_string($_GET['utm_medium']) && trim($_GET['utm_medium']) != "") {
	$aUtmCampaign["utm_medium"] = $_GET["utm_medium"];
}
if(isset($_GET['utm_campaign']) && is_string($_GET['utm_campaign']) && trim($_GET['utm_campaign']) != "") {
	$aUtmCampaign["utm_campaign"] = $_GET["utm_campaign"];
}
if(isset($_GET['utm_content']) && is_string($_GET['utm_content']) && trim($_GET['utm_content']) != "") {
	$aUtmCampaign["utm_content"] = $_GET["utm_content"];
}
if(isset($_GET['utm_term']) && is_string($_GET['utm_term']) && trim($_GET['utm_term']) != "") {
	$aUtmCampaign["utm_term"] = $_GET["utm_term"];
}
$_SESSION["utm"] = isset($_SESSION["utm"]) ? $_SESSION["utm"] : null;
if(count($aUtmCampaign) > 0) {
	$_SESSION["utm"] = serialize($aUtmCampaign);
}


function aGetVisitorDetails() {
		
	$deep_detect = true;
	$sIP = $_SERVER["REMOTE_ADDR"];
	if ($deep_detect) {
		if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
			$sIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
			if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
				$sIP = $_SERVER['HTTP_CLIENT_IP'];
	}
	$bIsExternal = filter_var($sIP, FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) != false;
	
	$_SESSION["ip_info"] = isset($_SESSION["ip_info"]) ? $_SESSION["ip_info"] : null;
	if($bIsExternal && (!isset($_SESSION["ip_info"]) || !is_array($_SESSION["ip_info"]))) {
		$_SESSION["ip_info"] = ip_info("Visitor", "location", $deep_detect);
	}
	$aIpInfo = $_SESSION["ip_info"];
	
	$sReferer = $_SESSION["referer"];
	$sUtmCampaign = $_SESSION["utm"];
	
	$sLanguage = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
	$sOsBrowser = $_SERVER["HTTP_USER_AGENT"];
	
	return array(
		"sIP" => $sIP,
		"sLanguage" => $sLanguage,
		"sOsBrowser" => $sOsBrowser,
		"sReferer" => $sReferer,
		"sUtmCampaign" => $sUtmCampaign,
		"sCountry" => is_string($aIpInfo["country"]) && strlen(trim($aIpInfo["country"])) > 0 ? $aIpInfo["country"] : null,
		"sLocality" => is_string($aIpInfo["state"]) && strlen(trim($aIpInfo["state"])) > 0 ? $aIpInfo["state"] : null,
		"sCity" => is_string($aIpInfo["city"]) && strlen(trim($aIpInfo["city"])) > 0 ? $aIpInfo["city"] : null,
	);
}

function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
	$output = NULL;
	if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
		$ip = $_SERVER["REMOTE_ADDR"];
		if ($deep_detect) {
			if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
					$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
	}
	$purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
	$support    = array("country", "countrycode", "state", "region", "city", "location", "address");
	$continents = array(
		"AF" => "Africa",
		"AN" => "Antarctica",
		"AS" => "Asia",
		"EU" => "Europe",
		"OC" => "Australia (Oceania)",
		"NA" => "North America",
		"SA" => "South America"
	);
	if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
		$ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
		if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
			switch ($purpose) {
				case "location":
					$output = array(
					"city"           => @$ipdat->geoplugin_city,
					"state"          => @$ipdat->geoplugin_regionName,
					"country"        => @$ipdat->geoplugin_countryName,
					"country_code"   => @$ipdat->geoplugin_countryCode,
					"continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
					"continent_code" => @$ipdat->geoplugin_continentCode
					);
					break;
				case "address":
					$address = array($ipdat->geoplugin_countryName);
					if (@strlen($ipdat->geoplugin_regionName) >= 1)
						$address[] = $ipdat->geoplugin_regionName;
						if (@strlen($ipdat->geoplugin_city) >= 1)
							$address[] = $ipdat->geoplugin_city;
							$output = implode(", ", array_reverse($address));
							break;
				case "city":
					$output = @$ipdat->geoplugin_city;
					break;
				case "state":
					$output = @$ipdat->geoplugin_regionName;
					break;
				case "region":
					$output = @$ipdat->geoplugin_regionName;
					break;
				case "country":
					$output = @$ipdat->geoplugin_countryName;
					break;
				case "countrycode":
					$output = @$ipdat->geoplugin_countryCode;
					break;
			}
		}
	}
	return $output;
}

function aGetData($sSql = null) {
	global $oConnection, $sErrorMessage;
	
	if (!is_null($sSql) && ($oResult = $oConnection->query($sSql)) !== false) {
		$aData = array();
		while($aRow = $oResult->fetch_assoc()) {
			$aData[] = $aRow;
		}
		return $aData;
	} else {
		$sErrorMessage = "DB Error whilst trying to retrieve Data: ".$oConnection->error;
		return false;
	}
}
function bRunQuery($sSql = null) {
	global $oConnection, $sErrorMessage;
	
	if (!is_null($sSql) && ($oResult = $oConnection->query($sSql)) !== false) {
		return true;
	} else {
		$sErrorMessage = "DB Error whilst trying to run a query: ".$oConnection->error;
		return false;
	}
}
function iGetLastQueryKey() {
	global $oConnection, $sErrorMessage;
	
	if (($iID = $oConnection->insert_id) !== false) {
		return $iID;
	} else {
		$sErrorMessage = "DB Error whilst trying to get the last query key: ".$oConnection->error;
		return false;
	}
}

function sEscQuery($sString) {
	global $oConnection;
	return $oConnection->escape_string($sString);
}
function sEscHtml($sString) {
	return htmlspecialchars($sString, ENT_QUOTES);
}
function sEscAttr($sString) {
	return htmlEntities($sString, ENT_QUOTES);
}
function sEscUrlPathName($sString) {
	return rawurlencode($sString);
}
function sRndAlNum($iLen = 10, $sCharacters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') {
	$sResult = '';
	for ($i = 0; $i < $iLen; $i++) {
		$sResult .= $sCharacters[mt_rand(0, strlen($sCharacters) - 1)];
	}
	return $sResult;
}




function aAddQuestion($sQuestion = null, $sDescription = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$sErrorMessage = null;
	if(strlen(trim((string)$sQuestion)) > 100) {
		$sErrorMessage = "Website error: The question is too large. Please shorten and try again?";
	} elseif(strlen(trim((string)$sDescription)) > 2000) {
		$sErrorMessage = "Website error: The description is too large. Please shorten and try again?";
	} elseif(!bRunQuery("INSERT INTO {$sOpenqTable}questions SET sQuestion='".sEscQuery($sQuestion)."', sDescription='".sEscQuery($sDescription)."'")) {
		$sErrorMessage = "Website error: The question could not be added. Please try again?";
	} elseif(!($iQuestionID = iGetLastQueryKey())) {
		$sErrorMessage = "Website error: The new question could not be found. Please try again?";
	} elseif(!($aQuestion = aGetData("SELECT * FROM {$sOpenqTable}questions WHERE iID={$iQuestionID}"))) {
		$sErrorMessage = "Website error: The question record could not be retrieved. Please try again?";
	} elseif(count($aQuestion) != 1) {
		$sErrorMessage = "Website error: The question record could not be retrieved successfully. Please try again?";
	} else {
		return $aQuestion[0];
	}
	
	return false;
}

function bRemoveQuestion($iQuestionID = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iQuestionID)) || trim((string)$iQuestionID) == "") {
		$sErrorMessage = "Website error: The question identifier is not valid. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}questions WHERE iID={$iQuestionID}")) {
		$sErrorMessage = "Website error: The question could not be deleted. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}answers WHERE iQuestionID={$iQuestionID}")) {
		$sErrorMessage = "Website error: The question's answers could not be deleted. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}votes WHERE iAnswerID IN ( SELECT iID FROM {$sOpenqTable}answers WHERE iQuestionID={$iQuestionID} )")) {
		$sErrorMessage = "Website error: The question's votes could not be deleted. Please try again?";
	} else {
		return true;
	}
	
	return false;
}

function aAddAnswer($iQuestionID = null, $sAnswer = null) {
	global $sErrorMessage, $sOpenqTable;
	
	//echo "INSERT INTO {$sOpenqTable}answers SET iQuestionID='".sEscQuery(trim((string)$iQuestionID)))."', sAnswer='".sEscQuery(trim((string)$sAnswer))."'";
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iQuestionID)) || trim((string)$iQuestionID) == "") {
		$sErrorMessage = "Website error: The question could not be identified when creating an answer. Please try again?";
	} elseif(strlen(trim((string)$sAnswer)) > 200) {
		$sErrorMessage = "Website error: The answer is too large. Please shorten and try again?";

//TODO: Need more answer values

	} else {
		$aDetails = aGetVisitorDetails();
		
		$strSQL = "
INSERT INTO {$sOpenqTable}answers SET 
 iQuestionID='".sEscQuery(trim((string)$iQuestionID))."', 
 sAnswer='".sEscQuery(trim((string)$sAnswer))."', 
 sSession='".session_id()."',
 sIP=".(!empty($aDetails["sIP"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sIP"]), 0, 50))."'" : "NULL").", 
 sCountry=".(!empty($aDetails["sCountry"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sCountry"]), 0, 50))."'" : "NULL").", 
 sLocality=".(!empty($aDetails["sLocality"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sLocality"]), 0, 100))."'" : "NULL").", 
 sCity=".(!empty($aDetails["sCity"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sCity"]), 0, 100))."'" : "NULL").", 
 sReferer=".(!empty($aDetails["sReferer"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sReferer"]), 0, 255))."'" : "NULL").", 
 aUtmCampaign=".(!empty($aDetails["sUtmCampaign"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sUtmCampaign"]), 0, 255))."'" : "NULL").", 
 sOsBrowser=".(!empty($aDetails["sOsBrowser"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sOsBrowser"]), 0, 255))."'" : "NULL").", 
 sLanguage=".(!empty($aDetails["sLanguage"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sLanguage"]), 0, 100))."'" : "NULL")."";
		
		if(!bRunQuery($strSQL)) {
			$sErrorMessage = "Website error: The answer could not be added. Please try again?";
		} elseif(!($iAnswerID = iGetLastQueryKey())) {
			$sErrorMessage = "Website error: The new answer could not be found. Please try again?";
		} elseif(($aAnswers = aGetData("SELECT * FROM {$sOpenqTable}answers WHERE iID={$iAnswerID}")) === false) {
			$sErrorMessage = "Website error: The answer record could not be retrieved. Please try again?";
		} elseif(count($aAnswers) != 1) {
			$sErrorMessage = "Website error: The answer record could not be retrieved successfully. Please try again?";
		} else {
			return $aAnswers[0];
		}
	}
	
	return false;
}

function bRemoveAnswer($iAnswerID = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iAnswerID)) || trim((string)$iAnswerID) == "") {
		$sErrorMessage = "Website error: The answer could not be identified. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}answers WHERE iID={$iAnswerID}")) {
		$sErrorMessage = "Website error: The answer could not be deleted. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}votes WHERE iAnswerID={$iAnswerID}")) {
		$sErrorMessage = "Website error: The answer's votes could not be deleted. Please try again?";
	} else {
		return true;
	}
	
	return false;
}

function aVoteAnswer($iAnswerID = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iAnswerID)) || trim((string)$iAnswerID) == "") {
		$sErrorMessage = "Website error: The answer could not be identified when casting a vote. Please try again?";
	} else {
		$aDetails = aGetVisitorDetails();
		
		$strSQL = "
INSERT INTO {$sOpenqTable}votes SET 
 iAnswerID='".sEscQuery(trim((string)$iAnswerID))."', 
 sSession='".session_id()."',
 sIP=".(!empty($aDetails["sIP"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sIP"]), 0, 50))."'" : "NULL").", 
 sCountry=".(!empty($aDetails["sCountry"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sCountry"]), 0, 50))."'" : "NULL").", 
 sLocality=".(!empty($aDetails["sLocality"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sLocality"]), 0, 100))."'" : "NULL").", 
 sCity=".(!empty($aDetails["sCity"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sCity"]), 0, 100))."'" : "NULL").", 
 sReferer=".(!empty($aDetails["sReferer"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sReferer"]), 0, 255))."'" : "NULL").", 
 aUtmCampaign=".(!empty($aDetails["sUtmCampaign"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sUtmCampaign"]), 0, 255))."'" : "NULL").", 
 sOsBrowser=".(!empty($aDetails["sOsBrowser"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sOsBrowser"]), 0, 255))."'" : "NULL").", 
 sLanguage=".(!empty($aDetails["sLanguage"]) ? "'".sEscQuery(substr(trim((string)$aDetails["sLanguage"]), 0, 100))."'" : "NULL")."";
		
		//echo $strSQL;
		
		if(!bRunQuery($strSQL)) {
			$sErrorMessage = "Website error: The vote could not be casted. Please try again?";
		} elseif(!($iVoteID = iGetLastQueryKey())) {
			$sErrorMessage = "Website error: The new vote record could not be found. Please try again?";
		} elseif(($aVotes = aGetData("SELECT * FROM {$sOpenqTable}votes WHERE iID={$iVoteID}")) === false) {
			$sErrorMessage = "Website error: The vote record could not be retrieved. Please try again?";
		} elseif(count($aVotes) != 1) {
			$sErrorMessage = "Website error: The vote record could not be retrieved successfully. Please try again?";
		} else {
			return $aVotes[0];
		}
	}
	
	return false;
}

function bRemoveVote($iAnswerID = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iAnswerID)) || trim((string)$iAnswerID) == "") {
		$sErrorMessage = "Website error: The answer could not be identified. Please try again?";
	} elseif(!bRunQuery("DELETE FROM {$sOpenqTable}votes WHERE iAnswerID={$iAnswerID} AND sSession='".session_id()."'")) {
		$sErrorMessage = "Website error: The vote could not be deleted. Please try again?";
	} else {
		return true;
	}
	
	return false;
}

function aGetAnswer($iAnswerID) {
	global $sErrorMessage, $sOpenqTable;
	
	$sSql = "
SELECT
 {$sOpenqTable}answers.iID, 
 {$sOpenqTable}answers.iQuestionID, 
 {$sOpenqTable}answers.sAnswer, 
 {$sOpenqTable}answers.sSession, 
 IF({$sOpenqTable}answers.sSession='".session_id()."', 1, 0) AS bOwner, 
 BIT_OR({$sOpenqTable}votes.sSession='".session_id()."') AS bAnswered, 
 COUNT({$sOpenqTable}votes.iID) AS iVoteCount 
FROM {$sOpenqTable}answers 
LEFT JOIN {$sOpenqTable}votes 
ON {$sOpenqTable}votes.iAnswerID={$sOpenqTable}answers.iID 
WHERE {$sOpenqTable}answers.iID={$iAnswerID} 
GROUP BY {$sOpenqTable}answers.iID 
ORDER BY {$sOpenqTable}answers.iID ASC ";

	//echo($sSql);
	
	$sErrorMessage = null;
	if(!ctype_digit(trim((string)$iAnswerID))) {
		$sErrorMessage = "Website error: The specified Answer identifier is badly formatted. Please try again?";
	} elseif(($aDbAnswer = aGetData($sSql)) === false) {
		$sErrorMessage = "Website error: The requested Answer could not be retrieved. Please try again?";
	} else {
		return $aDbAnswer[0];
	}
	
	return false;
}

function aGetAllAnswers($iQuestionID = null) {
	global $sErrorMessage, $sOpenqTable;
	
	$bSpecificQuestion = ctype_digit(trim((string)$iQuestionID));
	$sSqlQuestion = $bSpecificQuestion ? "WHERE {$sOpenqTable}questions.iID={$iQuestionID}" : "";
	
	$sSql = "
SELECT 
 {$sOpenqTable}questions.iID AS iQuestionID, 
 {$sOpenqTable}questions.sQuestion, 
 {$sOpenqTable}questions.sDescription, 
 {$sOpenqTable}questions.dAdded AS dQuestionAdded, 
 {$sOpenqTable}answers.iID AS iAnswerID, 
 {$sOpenqTable}answers.sAnswer, 
 {$sOpenqTable}answers.dAdded AS dAnswerAdded, 
 IF({$sOpenqTable}answers.sSession='".session_id()."', 1, 0) AS bOwner, 
 BIT_OR({$sOpenqTable}votes.sSession='".session_id()."') AS bAnswered, 
 COUNT({$sOpenqTable}votes.iID) AS iVoteCount 
FROM {$sOpenqTable}questions 
LEFT JOIN {$sOpenqTable}answers 
ON {$sOpenqTable}answers.iQuestionID={$sOpenqTable}questions.iID 
LEFT JOIN {$sOpenqTable}votes 
ON {$sOpenqTable}votes.iAnswerID={$sOpenqTable}answers.iID 
GROUP BY {$sOpenqTable}questions.iID, {$sOpenqTable}answers.iID 
ORDER BY {$sOpenqTable}questions.iID ASC, RAND() 
{$sSqlQuestion} ";

	//echo($sSql);
	
	$sErrorMessage = null;
	if(($aDbAllAnswers = aGetData($sSql)) === false) {
		$sErrorMessage = "Website error: The Answers could not be retrieved. Please try again?";
	} else {
		
		//Rebuild the tree
		$aAllQuestions = array();
		$iCurrentQuestionID = null;
		foreach($aDbAllAnswers as $aAnswer) {
			if($iCurrentQuestionID != $aAnswer["iQuestionID"]) {
				$iCurrentQuestionID = $aAnswer["iQuestionID"];
				$iCurrentQuestionKey = count($aAllQuestions);
				$aAllQuestions[$iCurrentQuestionKey] = array(
					"iID" => $aAnswer["iQuestionID"],
					"sQuestion" => $aAnswer["sQuestion"],
					"sDescription" => $aAnswer["sDescription"],
					"dAdded" => $aAnswer["dQuestionAdded"],
					"bAnswered" => false,
					"bAnswersCreated" => 0,
					"aAnswers" => array(),
				);
			}
			if(!empty($aAnswer["iAnswerID"])) {
				$aAllQuestions[$iCurrentQuestionKey]["aAnswers"][] = array(
					"iID" => $aAnswer["iAnswerID"],
					"sAnswer" => $aAnswer["sAnswer"],
					"dAdded" => $aAnswer["dAnswerAdded"],
					"iVoteCount" => $aAnswer["iVoteCount"],
					"bAnswered" => $aAnswer["bAnswered"],
					"bOwner" => $aAnswer["bOwner"] == "1",
				);
				$aAllQuestions[$iCurrentQuestionKey]["bAnswered"] = $aAllQuestions[$iCurrentQuestionKey]["bAnswered"] ? true : $aAnswer["bAnswered"] == "1";
				$aAllQuestions[$iCurrentQuestionKey]["bAnswersCreated"] += $aAnswer["bOwner"] == "1" ? 1 : 0;
			}
		}
		unset($aDbAllAnswers);
		return $bSpecificQuestion ? $aAllQuestions[0] : $aAllQuestions;
	}
	
	return false;
}



//Editor's note: using this code has security implications. The client can set HTTP_HOST and REQUEST_URI to any arbitrary value it wants.
$sActualPageLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//var_dump($_POST);
switch (true) {
	case isset($_POST["cmdCreateQuestion"]) && isset($_POST["sQuestion"]) && isset($_POST["sDescription"]):
		if($bIsAdmin) {
			aAddQuestion($_POST["sQuestion"], $_POST["sDescription"]);
		}
		break;
	case isset($_POST["cmdDeleteQuestion"]) && isset($_POST["iQuestionIDToDelete"]):
		if($bIsAdmin) {
			bRemoveQuestion($_POST["iQuestionIDToDelete"]);
		}
		break;
	case isset($_POST["cmdCreateBetterAnswer"]) && isset($_POST["sAnswer"]) && isset($_POST["iQuestionIDForBetterAnswer"]) && trim($_POST["iQuestionIDForBetterAnswer"]) != "":
		$aDbQuestion = aGetAllAnswers($_POST["iQuestionIDForBetterAnswer"]);
		if($bIsAdmin || $aDbQuestion["bAnswersCreated"] < $iOpenqMaxCreateAnswers) {
			aAddAnswer($_POST["iQuestionIDForBetterAnswer"], $_POST["sAnswer"]);
		}
		break;
	case isset($_POST["cmdDeleteAnswer"]) && isset($_POST["iAnswerID"]):
		$aDbAnswer = aGetAnswer($_POST["iAnswerID"]);
		if($bIsAdmin || $aDbAnswer["bOwner"] == "1") {
			bRemoveAnswer($_POST["iAnswerID"]);
		}
		break;
	case isset($_POST["cmdCastVote"]) && isset($_POST["iAnswerID"]):
		aVoteAnswer($_POST["iAnswerID"]);
		break;
	case isset($_POST["cmdRemoveVote"]) && isset($_POST["iAnswerID"]):
		if($iOpenqCorrectVote) {
			bRemoveVote($_POST["iAnswerID"]);
		}
		break;
}

$aAllQuestions = aGetAllAnswers();

$iCurrentQuestionID = null;
//$bIsAdmin = false;
//session_regenerate_id(true);
?>


<?php if($sErrorMessage) { ?>
	<p><?php echo sEscHtml($sErrorMessage); ?></p>
<?php } else { ?>
	<?php if($bIsAdmin) { ?>
		<form action="<?php echo $sActualPageLink; ?>" method="POST" accept-charset="UTF-8">
			<input type="text" class="form-control" name="sQuestion" 
				placeholder="New Question" value="<?php echo !empty($_POST["sQuestion"]) ? sEscAttr($_POST["sQuestion"]) : ''; ?>">
			<textarea rows="4" cols="" class="form-control" name="sDescription" 
				placeholder="Description"><?php echo !empty($_POST["sDescription"]) ? sEscHtml($_POST["sDescription"]) : ''; ?></textarea>
			<button type="submit" class="btn btn-primary" name="cmdCreateQuestion">Create Question</button>
		</form>
	<?php } ?>
	
	<?php if(count($aAllQuestions)) { foreach($aAllQuestions as $aQuestion) { ?>
			<h3 class="mt-5"><?php echo sEscHtml($aQuestion["sQuestion"]); ?></h3>
			<p><?php echo sEscHtml($aQuestion["sDescription"]); ?></p>
			<?php if($bIsAdmin) { ?>
				<form action="<?php echo $sActualPageLink; ?>" method="POST" accept-charset="UTF-8">
					<input type="hidden" name="iQuestionIDToDelete" value="<?php echo $aQuestion["iID"]; ?>">
					<button type="submit" class="btn btn-danger" name="cmdDeleteQuestion">Delete Question</button>
				</form>
			<?php } ?>
		<?php if(count($aQuestion["aAnswers"])) { foreach($aQuestion["aAnswers"] as $aAnswer) { ?>
			<form class="form-inline " action="<?php echo $sActualPageLink; ?>" method="POST" accept-charset="UTF-8">
				<input type="hidden" class="form-control" name="iAnswerID" value="<?php echo sEscAttr($aAnswer["iID"]); ?>">
				<?php if($bIsAdmin || !$aQuestion["bAnswered"]) { ?>
					<button type="submit" class="btn btn-primary col-10 mb-2 text-left" name="cmdCastVote"><?php echo sEscHtml($aAnswer["sAnswer"]); ?></button>
				<?php } else { ?>
					<div class="col-2 text-right"><?php echo sEscHtml($aAnswer["iVoteCount"]); ?></div>
					<div class="col-8 text-left"><?php echo sEscHtml($aAnswer["sAnswer"]); ?>
					
					<?php if($iOpenqCorrectVote && $aAnswer["bAnswered"] == "1") { ?>
						<button type="submit" class="btn btn-danger btn-sm" name="cmdRemoveVote">Unvote</button>
					<?php } ?>
					</div>
				<?php } ?>
			<?php if($bIsAdmin || $aAnswer["bOwner"] == "1") { ?>
				<button type="submit" class="btn btn-danger col-2 mb-2" name="cmdDeleteAnswer">Delete</button>
			<?php } ?>
			</form>
		<?php } ?>
		<?php } else { ?>
			<p>No Answers yet.</p>
		<?php }?>
		<?php if($bIsAdmin || $aQuestion["bAnswersCreated"] < $iOpenqMaxCreateAnswers) { ?>
		<form class="form-inline" action="<?php echo $sActualPageLink; ?>" method="POST" accept-charset="UTF-8">
			<input type="hidden" name="iQuestionIDForBetterAnswer" value="<?php echo sEscAttr($aQuestion["iID"]); ?>">
			<input type="text" class="form-control form-control-sm col-10" name="sAnswer" placeholder="Add a better Answer" value="">
			<button type="submit" class="btn btn-default col-2 btn-sm" name="cmdCreateBetterAnswer">Create</button>
		</form>
		<?php } ?>
	<?php } ?>
	<?php } else { ?>
		<p>No Questions yet.</p>
	<?php }?>
<?php } ?>
