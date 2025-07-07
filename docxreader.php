<?php
/*
	Original author: Jason Crider https://github.com/xylude
	https://github.com/xylude/Docx-to-HTML/
	Modified by: Marcin Borkowicz 2025
*/

class DocxReader {

	private $fileData = false;
	private $errors = array();
	private $styles = array();
	private $docHTML = "";
	private $docText = "";
	private $docProperties = array();


public function __construct($path) {
	return $this->fileData = $this->load($path);
}

final private function getProperties() {
	return $this->docProperties;
}

final public function showLog($outputHTML = true) {
	# list all error during the load or processing of the DOCX file
	//TO CONSIDER: to change this method to show debugging data (Chronolog from my ZIP tools!)
	$errorText = "";
	if(count($this->errors)===0) {
		$errorText = "No errors during processing the file";
	}
	else {
		if($outputHTML) {
			//HTML output
			$errorText = "<h4>Error list</h4>\n<ul>\n";
			foreach($this->errors as $i => $errMess) {
				$errorText .= '<li>${errMess}</li>\n';
			}
			$errorText .= '</ul>\n';
		}
		else {
			//plain text output
			foreach($this->errors as $i => $errMess) {
				$errorText .= '$errMess\n';
			}
		}
	}
	
	return $errorText; //jedna mała wtopa: w przypadku braku błędów, nie tworzy wsadu w HTML-u
}

final private function readProperties($zipResource) {
/*
	Reads basic document's metadata (e.g. title, editor)
	This method must be invoked from the inside of load() method An object of ZipArchive class must be present and valid.
*/
	if(($coreIndex = $zipResource->locateName('docProps/core.xml')) !== false) {
		$coreXML = $zipResource->getFromIndex($coreIndex);
		$xml = simplexml_load_string($coreXML);
		$namespaces = $xml->getNamespaces(true);

		$children = $xml->children($namespaces['dc']);
		$this->docProperties['creator'] = htmlspecialchars($children->creator);
		$this->docProperties['title'] = htmlspecialchars($children->title);
		return $this->docProperties;
	}
	else {
		$this->errors[] = "Can't read document's metadata.";
	}
	return false;
}

final private function load($file) {
	//TODO: zrefaktoryzować to na metody czytające style, media, właściwości etc.
	if (file_exists($file)) {
		$zip = new ZipArchive();
		$openedZip = $zip->open($file);
		if($openedZip === true) {
			//attempt to read document's metadata
			$this->readProperties($zip);
			
			//attempt to load styles. TODO: refaktoryzować do odrębnej metody!
			if(($styleIndex = $zip->locateName('word/styles.xml')) !== false) {
				$stylesXml = $zip->getFromIndex($styleIndex);
				$xml = simplexml_load_string($stylesXml);
				$namespaces = $xml->getNamespaces(true);

				$children = $xml->children($namespaces['w']);

				foreach($children->style as $s) {
					$attr = $s->attributes('w', true);
					if (isset($attr['styleId'])) {
						$tags = array();
						$attrs = array();
						foreach(get_object_vars($s->rPr) as $tag => $style) {
							$att = $style->attributes('w', true);
							switch ($tag) {
								case "b":
									$tags[] = 'strong';
									break;
								case "i":
									$tags[] = 'em';
									break;
								case "color":
									//echo (String) $att['val'];
									$attrs[] = 'color:#' . $att['val'];
									break;
								case "sz":
									$attrs[] = 'font-size:' . $att['val'] . 'px';
									break;
							}
						}
						$styles[(String)$attr['styleId']] = array('tags' => $tags, 'attrs' => $attrs);
					}
				}
				$this->styles = $styles;
			}

			if (($index = $zip->locateName('word/document.xml')) !== false) {
				// If found, read it to the string
				$data = $zip->getFromIndex($index);
			}
			$zip->close();
			
			return $data;
		}
		else {
			switch($openedZip) {
				case ZipArchive::ER_EXISTS:
					$this->errors[] = 'File exists.';
					break;
				case ZipArchive::ER_INCONS:
					$this->errors[] = 'Inconsistent ZIP file.';
					break;
				case ZipArchive::ER_MEMORY:
					$this->errors[] = 'Memory allocation failure.';
					break;
				case ZipArchive::ER_NOENT:
					$this->errors[] = 'No such file.';
					break;
				case ZipArchive::ER_NOZIP:
					$this->errors[] = 'File is not a ZIP archive.';
					break;
				case ZipArchive::ER_OPEN:
					$this->errors[] = 'Could not open file.';
					break;
				case ZipArchive::ER_READ:
					$this->errors[] = 'Read error.';
					break;
				case ZipArchive::ER_SEEK:
					$this->errors[] = 'Seek error.';
					break;
			}
		}
	}
	else {
		$this->errors[] = 'File does not exist.';
	}
	
	return false;
} //END load()


final public function __toString() {
	if ($this->fileData) {
		return strip_tags($this->fileData);
	}
	return false;
}

final public function to_html() {
	if($this->fileData) {
		$xml = simplexml_load_string($this->fileData);
		$namespaces = $xml->getNamespaces(true);

		$children = $xml->children($namespaces['w']);

$html = <<<HTML
<!doctype html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		
		<meta name="author" content="{$this->docProperties['creator']}">
		<meta name="designer" content="Marcin Borkowicz/ZIP toolkit">
		<meta name="copyright" content="" />
		<meta name="topic" content="{$this->docProperties['title']}" />
		<title>{$this->docProperties['title']}</title>
		<style>p.block { display: block; }
		</style>
	</head>
	<body>
HTML;

		foreach($children->body->p as $p) {
			$style = '';
			
			$startTags = array();
			$startAttrs = array();
			
			if($p->pPr->pStyle) {					
				$objectAttrs = $p->pPr->pStyle->attributes('w',true);
				$objectStyle = (String) $objectAttrs['val'];
				if(isset($this->styles[$objectStyle])) {
					$startTags = $this->styles[$objectStyle]['tags'];
					$startAttrs = $this->styles[$objectStyle]['attrs'];
				}
			}
			
			if ($p->pPr->spacing) {
				$att = $p->pPr->spacing->attributes('w', true);
				if (isset($att['before'])) {
					$style.='padding-top:' . ($att['before'] / 10) . 'px;';
				}
				if (isset($att['after'])) {
					$style.='padding-bottom:' . ($att['after'] / 10) . 'px;';
				}
			}

			$html.='<p class="block" style="' . $style . '">';
			$li = false;
			if ($p->pPr->numPr) {
				$li = true;
				$html.='<li>';
			}
			
			foreach($p->r as $part) {
				//echo $part->t;
				$tags = $startTags;
				$attrs = $startAttrs;

				foreach(get_object_vars($part->pPr) as $k => $v) {
					if ($k = 'numPr') {
						$tags[] = 'li';
					}
				}

				foreach(get_object_vars($part->rPr) as $tag => $style) {
					//print_r($style->attributes());
					$att = $style->attributes('w', true);
					switch ($tag) {
						case "b":
							$tags[] = 'strong';
							break;
						case "i":
							$tags[] = 'em';
							break;
						case "color":
							//echo (String) $att['val'];
							$attrs[] = 'color:#' . $att['val'];
							break;
						case "sz":
							$attrs[] = 'font-size:' . $att['val'] . 'px';
							break;
					}
				}
				$openTags = '';
				$closeTags = '';
				foreach($tags as $tag) {
					$openTags.='<' . $tag . '>';
					$closeTags.='</' . $tag . '>';
				}
				$html.='<span style="' . implode(';', $attrs) . '">' . $openTags . $part->t . $closeTags . '</span>';
			}
			if ($li) {
				$html.='</li>';
			}
			$html.="</p>\n";
		}

		//Trying to weed out non-utf8 stuff from the file:
		$regex = <<<'END'
/
(
(?: [\x00-\x7F]				 # single-byte sequences   0xxxxxxx
|   [\xC0-\xDF][\x80-\xBF]	  # double-byte sequences   110xxxxx 10xxxxxx
|   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
|   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
){1,100}						# ...one or more times
)
| .								 # anything else
/x
END;
		preg_replace($regex, '$1', $html);

		return $this->docHTML = $html.'</body>
		</html>';
	} //END if()
	return false;
}

final private function getStyles() {
}

}
?>
