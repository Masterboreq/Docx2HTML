<?php
/*
	poc.php //codename
	Project: Outokumpu DOCX to HTML converter
	
	Proof of concept of an indirect translating DOCX files to HTML format without even using MS Office COM objects:
	The principle of work:
	1. Open a DOCX file as a ZIP file (which it is indeed).
	2. Find a 'doc.xml' file inside (the main document content).
	3. Convert Word XML to somehow valid HTML5 document.
	4. Save as HTML file.
	*5 Extra points for finding and binding all document-related image files.
	*6 Go extra mile and convert a batch of files inside a selected directory.
	
	Author: Marcin Borkowicz 2025
*/

session_start();

# define("INDEX", true); //might be usuful later on
define('EOL',"<br/>\n");
# define('config', (require_once "./include/globalconfig.php"));

# require_once config['utilitiesDir']."usedb.php"; //obsługa bazy danych MySQL


function removeslashes($string) {
	//https://www.php.net/manual/en/function.stripslashes.php#114533
    $string = implode("", explode("\\", $string));
    return stripslashes(trim($string));
}

# ### main loop of the script ###
try {
	
}
catch(Exception $dbe) {
	echo $dbe->getMessage(), EOL;
}


$request = "";
$explodedRequest = [];
$errorMessage = [];
$output = [];
$campaignId = "";
$proceed = 0;
$contentTypeHeader = "Content-Type: application/json";

//dev
	/*$fileName = "files-upload-log.txt";
	$fp = fopen($fileName, "a");  */


	
// ### próba zapisu przesłanych plików do katalogu kampanii
foreach($_FILES as $name => $properties) {
	$uploadfile = $campaignDir."/".basename($_FILES[$name]['name']);
	
	if(move_uploaded_file($_FILES[$name]['tmp_name'], $uploadfile)) {
		$fileProperties['name'] = $properties['name'];
		$fileProperties['type'] = $properties['type'];
		$fileProperties['size'] = $properties['size'];
		$output['file'][] = $fileProperties;
	}
	else {
		$errorMessage['fileUpload'][] =  "Niepoprawna próba uploadu pliku!";
	}		
}


?>