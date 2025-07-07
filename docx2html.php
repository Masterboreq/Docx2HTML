<?php
/*
	The code taken from: https://stackoverflow.com/a/70226941
	Author: https://stackoverflow.com/users/1255289/miken32
*/

class Docx_ws_imglnk {
	public $originalpath = '';
	public $extractedpath = '';
}

class Docx_ws_rel {
	public $Id = '';
	public $Target = '';
}

class Docx_ws_def {
	public $styleId = '';
	public $type = '';
	public $color = '000000';
}

class Docx_p_def {
	public $data = array();
	public $text = "";
}

class Docx_p_item {
	public $name = "";
	public $value = "";
	public $innerstyle = "";
	public $type = "text";
}

class Docx_reader {

	private $fileData = false;
	private $errors = array();
	public $rels = array();
	public $imglnks = array();
	public $styles = array();
	public $document = null;
	public $paragraphs = array();
	public $path = '';
	private $saveimgpath = 'docimages';

	public function __construct() {
	
	}

	private function load($file) {
		if (file_exists($file)) {
			$zip = new ZipArchive();
			$openedZip = $zip->open($file);
			if ($openedZip === true) {
			
				$this->path = $file;
			
				//read and save images
				for ( $i = 0; $i < $zip->numFiles; $i ++ ) {
					$zip_element = $zip->statIndex( $i );
					if ( preg_match( "([^\s]+(\.(?i)(jpg|jpeg|png|gif|bmp))$)", $zip_element['name'] ) ) {
						$imglnk = new Docx_ws_imglnk;
						$imglnk->originalpath = $zip_element['name'];
						$imagename = explode( '/',   $zip_element['name'] );
						$imagename = end( $imagename );
						$imglnk->extractedpath = dirname( __FILE__ ) . '/' . $this->savepath . $imagename;
				
						$putres = file_put_contents( $imglnk->extractedpath, $zip->getFromIndex( $i ));
						$imglnk->extractedpath = str_replace('var/www/', 'https://boreqnet/outokumpu/output', $imglnk->extractedpath);
						$imglnk->extractedpath = substr($imglnk->extractedpath, 1);
					
						array_push($this->imglnks, $imglnk);
					}
				}
			
				//read relationships
				if (($styleIndex = $zip->locateName('word/_rels/document.xml.rels')) !== false) {
					$stylesRels = $zip->getFromIndex($styleIndex);
					$xml = simplexml_load_string($stylesRels);
					$XMLTEXT = $xml->saveXML();
					$doc = new DOMDocument();
					$doc->loadXML($XMLTEXT);
					foreach($doc->documentElement->childNodes as $childnode)
					{
						$nodename = $childnode->nodeName;
				   
						if($childnode->hasAttributes())
						{
							$rel = new Docx_ws_rel;
							for ($a = 0; $a < $childnode->attributes->count(); $a++)
							{
								$attrNode = $childnode->attributes->item($a);
							
								if (strcmp( $attrNode->nodeName, 'Id') == 0)
								{
									$rel->Id = $attrNode->nodeValue;
								}
								if (strcmp( $attrNode->nodeName, 'Target') == 0)
								{
									$rel->Target = $attrNode->nodeValue;
								}
							}
							array_push($this->rels, $rel);
						}
					}
				}
			
				//attempt to load styles:
				if (($styleIndex = $zip->locateName('word/styles.xml')) !== false) {
					$stylesXml = $zip->getFromIndex($styleIndex);
					$xml = simplexml_load_string($stylesXml);
					$XMLTEXT = $xml->saveXML();
					$doc = new DOMDocument();
					$doc->loadXML($XMLTEXT);
			   
					foreach($doc->documentElement->childNodes as $childnode)
					{
						$nodename = $childnode->nodeName;
					
						//get style
						if (strcmp($nodename, "w:style") == 0)
						{
							$ws_def = new Docx_ws_def;
							for ($a=0; $a < $childnode->attributes->count(); $a++ )
							{
								$item = $childnode->attributes->item($a);
								//style id
								if (strcmp($item->nodeName, "w:styleId") == 0)
								{
									$ws_def->styleId = $item->nodeValue;
								}
							
								//style type
								if (strcmp($item->nodeName, "w:type") == 0)
								{
									$ws_def->type = $item->nodeValue;
								}
							}
						}
						//push style to the array of styles
						if (strcmp($ws_def->styleId, "") != 0 && strcmp($ws_def->type, "") != 0)
						{
							array_push($this->styles, $ws_def);
						}
					}
				}

				if (($index = $zip->locateName('word/document.xml')) !== false) {
					$stylesDoc = $zip->getFromIndex($index);
					$xml = simplexml_load_string($stylesDoc);
					$XMLTEXT = $xml->saveXML();
					$this->document = new DOMDocument();
					$this->document->loadXML($XMLTEXT);
				}
				$zip->close();
			} else {
				switch($openedZip) {
					case ZipArchive::ER_EXISTS:
						$this->errors[] = 'File exists.';
						break;
					case ZipArchive::ER_INCONS:
						$this->errors[] = 'Inconsistent zip file.';
						break;
					case ZipArchive::ER_MEMORY:
						$this->errors[] = 'Malloc failure.';
						break;
					case ZipArchive::ER_NOENT:
						$this->errors[] = 'No such file.';
						break;
					case ZipArchive::ER_NOZIP:
						$this->errors[] = 'File is not a zip archive.';
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
		} else {
			$this->errors[] = 'File does not exist.';
		}
	}

	public function setFile($path) {
		$this->fileData = $this->load($path);
	}

	public function to_plain_text() {
		if ($this->fileData) {
			return strip_tags($this->fileData);
		} else {
			return false;
		}
	}

	public function processDocument() {
		$html = '';	
	
		foreach($this->document->documentElement->childNodes as $childnode)
		{
			$nodename = $childnode->nodeName;
		
			//get the body of the document
			if (strcmp($nodename, "w:body") == 0)
			{
				foreach($childnode->childNodes as $subchildnode)
				{
					$pnodename = $subchildnode->nodeName;
				
					//process every paragraph
					if (strcmp($pnodename, "w:p") == 0)
					{
						$pdef = new Docx_p_def;
					
						foreach($subchildnode->childNodes as $pchildnode)
						{
							//process any inner children
							if (strcmp($pchildnode, "w:pPr") == 0)
							{
								foreach($pchildnode->childNodes as $prchildnode)
								{
									//process text alignment
									if (strcmp($prchildnode->nodeName, "w:pStyle") == 0)
									{
										$pitem = new Docx_p_item;
										$pitem->name = 'styleId';
										$pitem->value = $prchildnode->attributes->getNamedItem('val')->nodeValue;
										array_push($pdef->data, $pitem);
									}
								
									//process text alignment
									if (strcmp($prchildnode->nodeName, "w:jc") == 0)
									{
										$pitem = new Docx_p_item;
										$pitem->name = 'align';
										$pitem->value = $prchildnode->attributes->getNamedItem('val')->nodeValue;
									
										if (strcmp($pitem->value, "left") == 0)
										{
											$pitem->innerstyle .= "text-align:" . $pitem->value . ";";
										}
									
										if (strcmp($pitem->value, "center") == 0)
										{
											$pitem->innerstyle .= "text-align:" . $pitem->value . ";";
										}
									
										if (strcmp($pitem->value, "right") == 0)
										{
											$pitem->innerstyle .= "text-align:" . $pitem->value . ";";
										}
									
										if (strcmp($pitem->value, "both") == 0)
										{
											$pitem->innerstyle .= "word-spacing:" . 10 . "px;";
										}
									
										array_push($pdef->data, $pitem);
									}
								
									//process drawing
									if (strcmp($prchildnode->nodeName, "w:drawing") == 0)
									{
										$pitem = new Docx_p_item;
										$pitem->name = 'drawing';
										$pitem->value = '';
										$pitem->type = 'graphic';
									
										$extents = $prchildnode->getElementsByTagName('extent')[0];
										$cx = $extents->attributes->getNamedItem('cx')->nodeValue;
										$cy = $extents->attributes->getNamedItem('cy')->nodeValue;
										$pcx = (int)$cx / 9525;
										$pcy = (int)$cy / 9525;
									
										$pitem->innerstyle .= "width:" . $pcx . "px;";
										$pitem->innerstyle .= "height:" . $pcy . "px;";
									
										$blip = $prchildnode->getElementsByTagName('blip')[0];
										$pitem->value = $blip->attributes->getNamedItem('embed')->nodeValue;
								 
										array_push($pdef->data, $pitem);
									}
								
									//process spacing
									if (strcmp($prchildnode->nodeName, "w:spacing") == 0)
									{
										$pitem = new Docx_p_item;
										$pitem->name = 'paragraphSpacing';
										$bval = $prchildnode->attributes->getNamedItem('before')->nodeValue;
										if (strcmp($bval, '') == 0)
											$bval = 0;
										$pitem->innerstyle .= "padding-top:" . $bval . "px;";
										$aval = $prchildnode->attributes->getNamedItem('after')->nodeValue;
										if (strcmp($aval, '') == 0)
											$aval = 0;
										$pitem->innerstyle .= "padding-bottom:" . $aval . "px;";
									
										array_push($pdef->data, $pitem);
									}
								}
							}
						
						
							if (strcmp($pchildnode, "w:r") == 0)
							{
								foreach($pchildnode->childNodes as $rchildnode)
								{
									//process text
									if (strcmp($rchildnode->nodeName, "w:t") == 0)
									{
										$pdef->text .= $rchildnode->nodeValue;
										if (count($pdef->data) == 0)
										{
											$pitem = new Docx_p_item;
											$pitem->name = 'styleId';
											$pitem->value = '';
											array_push($pdef->data, $pitem);
										}
									}
								
									if (strcmp($rchildnode->nodeName, "w:rPr") == 0)
									{
										foreach($rchildnode->childNodes as $rPrchildnode)
										{
											if (strcmp($rPrchildnode->nodeName, "w:b") == 0 )
											{
												$pitem = new Docx_p_item;
												$pitem->name = 'textBold';
												$pitem->value = '';
												$pitem->innerstyle .= "text-weight: 500;";
												array_push($pdef->data, $pitem);
											}
											if (strcmp($rPrchildnode->nodeName, "w:i") == 0 )
											{
												$pitem = new Docx_p_item;
												$pitem->name = 'textItalic';
												$pitem->value = '';
												$pitem->innerstyle .= "text-style: italic;";
												array_push($pdef->data, $pitem);
											}
											if (strcmp($rPrchildnode->nodeName, "w:u") == 0 )
											{
												$pitem = new Docx_p_item;
												$pitem->name = 'textUnderline';
												$pitem->value = '';
												$pitem->innerstyle .= "text-decoration: underline;";
												array_push($pdef->data, $pitem);
											}
											if (strcmp($rPrchildnode->nodeName, "w:sz") == 0 )
											{
												$pitem = new Docx_p_item;
												$pitem->name = 'textSize';
											
												$sz = $rPrchildnode->attributes->getNamedItem('val')->nodeValue;
												if ($sz == '')
												{
													$sz=0;
												}
												$pitem->value = $sz;
												array_push($pdef->data, $pitem);
											}
										}
									}
								}
							}
						}
				  
					   array_push($this->paragraphs, $pdef);
					}
				}
			}
		} 
	
	}

	public function to_html()
	{
		$html = '';
	
		foreach($this->paragraphs as $para)
		{
			$styleselect = null;
			$type = 'text';
			$content = $para->text;
			$sz = 0;
			$extent = '';
			$embedid = '';
		
			$pinnerstylesid = '';
			$pinnerstylesunderline = '';
			$pinnerstylessz = '';		 
		   
		
			if (count($para->data) > 0)
			{
				foreach($para->data as $node)
				{
					if (strcmp($node->name, "styleId") == 0)
					{
						$type = $node->type;
						$pinnerstylesid = $node->innerstyle;
					   
						foreach($this->styles as $style)
						{
							if (strcmp ($node->value, $style->styleId) == 0)
							{
								$styleselect = $style;
							}
						}
					}
				
					if (strcmp($node->name, "align") == 0)
					{
						$pinnerstylesid .= $node->innerstyle. ";";
					}
				
					if (strcmp($node->name, "drawing") == 0)
					{
						$type = $node->type;
						$extent = $node->innerstyle;
						$embedid = $node->value;
					}
				
					if (strcmp($node->name, "textSize") == 0)
					{
						$sz = $node->value;
					}
				
					if (strcmp($node->name, "textUnderline") == 0)
					{
					   $pinnerstylesunderline = $node->innerstyle;
					}
				}
			}
	 
		   if (strcmp($type, 'text') == 0)
		   {
				//echo "has valid para";
				//echo "<br>";
				if ($styleselect != null)
				{
					//echo "has valid style";
					//echo "<br>";
				
					if (strcmp($styleselect->color, '') != 0)
					{
					   $pinnerstylesid .= "color:#" . $styleselect->color. ";";
					}
				}
			
				if ($sz != 0)
				{
					$pinnerstylesid .= 'font-size:' . $sz . 'px;';
					//echo "sz<br>";
				}
			
				$span =  "<p style='". $pinnerstylesid . $pinnerstylesunderline ."'>";
				$span .= $content;
				$span .= "</p>";
				//echo $span;
				$html .= $span;
			}
		
			if (strcmp($type, 'graphic') == 0)
			{
				$imglnk = '';
			
				foreach($this->rels as $rel)
				{
					if(strcmp($embedid, '') != 0 && strcmp($rel->Id, $embedid) == 0)
					{
						foreach($this->imglnks as $imgpathdef)
						{
							if (strpos($imgpathdef->extractedpath, $rel->Target) >= 0)
							{
								$imglnk = $imgpathdef->extractedpath;
								//echo "has img link<br>";
								//echo $imglnk . "<br>";
							}
						}
					}
				}
			
				if ($styleselect != null)
				{
					//echo "has valid style";
					//echo "<br>";
				
					if (strcmp($styleselect->color, '') != 0)
					{
						$pinnerstylesid .= "color:#" . $styleselect->color. ";";
					}
				}
			
				if ($sz != 0)
				{
					$pinnerstylesid .= 'font-size:' . $sz . 'px;';
					//echo "sz<br>";
				}
			
				$span =  "<p style='". $pinnerstylesid . $pinnerstylesunderline ."'>";
				$span .= "<img style='". $extent ."' alt='image coming soon' src ='". $imglnk ."'/>";
				$span .= "</p>";
				//echo $span;
				$html .= $span;
			}
		   
		}
		return $html;
	}

	public function get_errors() {
		return $this->errors;
	}

	private function getStyles() {
	
	}

 }

function getDocX($path) {
	//echo $path;
	$doc = new Docx_reader();
	$doc->setFile($path);

	if(!$doc->get_errors()) {
		$doc->processDocument();
		$html = $doc->to_html();
		echo $html;
	}
	return "";
}


?>