<?php
/*
	poc.php //codename
	Project: Outokumpu DOCX to HTML converter
	
	Proof of concept of an indirect translating DOCX files to HTML format without even using MS Office COM objects:
	The principle of work:
	1. Open a DOCX file as a ZIP file (which it is indeed).
	2. Find a './word/document.xml' file inside (the main document content).
	3. Convert Word XML to somehow valid HTML5 document.
	4. Save as HTML file.
	*5 Extra points for finding and binding all document-related image files.
	*6 Go extra mile and convert a batch of files inside a selected directory.
	
	Author: Marcin Borkowicz 2025
*/

session_start();

# ### Constants & config

# define("INDEX", true); //might be usuful later on
define('INPUT', "./input/"); //path of the dir containing DOCX files
define('OUTPUT', "./output/"); //path of the dir for HTML files
define('EOL',"<br/>\n");


# ### Functions ###

//might be useful
function removeslashes($string) {
	//https://www.php.net/manual/en/function.stripslashes.php#114533
    $string = implode("", explode("\\", $string));
    return stripslashes(trim($string));
}

//scan and find DOCX files in the directory
function scan4docx() {
	# ### TODO: a stub only; later for point *6
/* foreach($_FILES as $name => $properties) {
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
} */
}

//open a DOCX file as a ZIP
function openZIP($file) {
/*
	Reads DOCX $file as an input.
	Returns XML string if success; false otherwise.
*/
	$zip = new ZipArchive;
	$res = $zip->open($file, ZipArchive::RDONLY);
	if ($res === TRUE) {
		 //find 'document.xml'
		 $document = $zip->getFromName('word\document.xml'); // backslashes for Windows OS only
		 $xml = simplexml_load_string($document);
	} else {
		 return false;
	}
	$zip->close();
	return $xml;
}


# ### main loop of the script ###
try {
	$filename = "okk.docx";
	$filepath = INPUT.$filename;
	
	$docx = openZIP($filepath);
	var_dump($docx);
}
catch(Exception $docx) {
	echo $docx->getMessage(), EOL;
}



//dev
	/*$fileName = "files-upload-log.txt";
	$fp = fopen($fileName, "a");  */


	


// git commit -m"ADDED: first version of openZIP() function. Basic testbed included."
?>