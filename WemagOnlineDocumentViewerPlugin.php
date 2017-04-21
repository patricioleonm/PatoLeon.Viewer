<?php
/**
 * @author Aart-Jan Boor <aart-jan@wemag.nl>
 * @copyright Aart-Jan Boor <aart-jan@wemag.nl>
 *
 * This file is part of Wemag Online Document Viewer.
 *
 * Wemag Online Document Viewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Wemag Online Document Viewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Wemag Online Document Viewer.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(KT_LIB_DIR . '/plugins/plugin.inc.php');
require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');
//require_once(KT_DIR.'/plugins/PatoLeon/PdfToSwfConverter.php');


/**
 * Document action that allows the actual viewing of a document
 *
 */
class WemagOnlineDocumentViewerDocumentActionPL extends KTDocumentAction{
	// The namespace for this action
	var $sName = 'wemag.OnlineDocumentViewerPluginPL.actions.document';

	// The permission required to see this action on the menu
	//var $sPermissionName = 'ktcore.permissions.read';

	var $aAcceptedMimeTypes = array('doc', 'ods', 'odt', 'ott', 'txt', 'rtf', 'sxw', 'stw',
            'xml' , 'pdb', 'psw', 'ods', 'ots', 'sxc',
            'stc', 'dif', 'dbf', 'xls', 'xlt', 'slk', 'csv', 'pxl',
            'odp', 'otp', 'sxi', 'sti', 'ppt', 'pot', 'sxd', 'odg',
            'otg', 'std', 'asc','pdf', 'tif');
	
	//var $aImageMimeTypes = array('png','jpg','gif','bmp','jpeg','jpe');
	var $aImageMimeTypes = array();
	
	//var $aImageMimeTypes2 = array('image/png','image/jpeg','image/bmp', 'image/pjpeg','image/x-windows-bmp',	'image/gif');
	var $aImageMimeTypes2 = array();

	private function useNewPdfGenerator(){
		global $default;
		
		if((substr($default->systemVersion,0,1) == 3 && substr($default->systemVersion,2,1) >= 6) || substr($default->systemVersion,0,1)>3){
			return true;
		}
		return false;
	}
	
	/**
	 * Method for getting the MIME type extension for the current document.
	 * Source: KnowledgeTree Community Edition v3.5.2c
	 *
	 * @return string mime time extension
	 */
	private function getMimeExtension() {
		global $default;
		if($this->oDocument == null || $this->oDocument == "" || PEAR::isError($this->oDocument) ) return _kt('Unknown Type');

		$oDocument = $this->oDocument;
		$iMimeTypeId = $oDocument->getMimeTypeID();
		$mimetypename = KTMime::getMimeTypeName($iMimeTypeId); // mime type name
		
		
		
		if(WemagOnlineDocumentViewerDocumentActionPL::useNewPdfGenerator()){
			return $mimetypename;
		}
		$sTable = KTUtil::getTableName('mimetypes');
		$sQuery = "SELECT filetypes FROM " . $sTable . " WHERE mimetypes = ?";
		$aQuery = array($sQuery, array($mimetypename));
		$res = DBUtil::getResultArray($aQuery);
		if (PEAR::isError($res)) {
			return $res;
		} else if (count($res) != 0){
			return $res[0]['filetypes'];
		}

		return _kt('Unknown Type');
	}

	function getName(){
		return _kt("View Online");
	}


	private function displayImage(){
		return "<img src=\"plugins/WemagOnlineDocumentViewer/getDoc.php?doc=".$this->oDocument->getId()."\" />";
	}

	/**
	 * Converts document into an acceptable format for the viewer, and loads the document into the viewer.
	 *
	 * @return unknown
	 */
	function do_main() {
		
		global $default;
		$props = array();
		$oConfig =& KTConfig::getSingleton();
		
		//check if we're dealing with an image
		$sDocType = WemagOnlineDocumentViewerDocumentActionPL::getMimeExtension();
		if(WemagOnlineDocumentViewerDocumentActionPL::useNewPdfGenerator()){
			if(in_array($sDocType, $this->aImageMimeTypes2)){
				return WemagOnlineDocumentViewerDocumentActionPL::displayImage();
			}
		}else{			
			foreach($this->aImageMimeTypes as $acceptType){
				if($acceptType == $sDocType){
					return WemagOnlineDocumentViewerDocumentActionPL::displayImage();
				}
			}
		}

		//check if file type is supported
		$accept = false;
		if(WemagOnlineDocumentViewerDocumentActionPL::useNewPdfGenerator() ){
			require_once("PdfToSwfConverter.php");
			$converter = new pdfConverter();
            $mimeTypes = $converter->getSupportedMimeTypes();
            $docType = WemagOnlineDocumentViewerDocumentActionPL::getMimeExtension();

            if($mimeTypes === true || in_array($docType, $mimeTypes) || $docType == 'application/pdf' ){
                $accept=true;
            }
		}else{
			foreach($this->aAcceptedMimeTypes as $acceptType){
				if($acceptType == $sDocType){
					$accept = true;
				}
			}
		}
		
		if($accept == false){
			//WemagOnlineDocumentViewerDocumentActionPL::addErrorMessage(_kt('An error occurred viewing the document - document type not supported'));
			return "";
			//redirect(generateControllerLink('viewDocument',sprintf('fDocumentId=%d',$this->oDocument->getId())));
			//exit(0);
		}

		//Retrieve properties of converted document.
		if(file_exists($oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf")){			
			$props = unserialize(file_get_contents($oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf.prop"));
		}

		//check if the document already has been converted and if the converted document is still the most recent version.
		if(!file_exists($oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf") || (isset($props['content_version'])
		&& $props['content_version'] != $this->oDocument->getContentVersionId())){
			
			$props['content_version'] = $this->oDocument->getContentVersionId();

			$oDocument = $this->oDocument;
			$oStorage =& KTStorageManagerUtil::getSingleton();
			
			$cmdpath = KTUtil::findCommand('externalBinary/python');

			//path to document on FS
			$sPath = sprintf("%s/%s", $oConfig->get('urls/documentRoot'), $oStorage->getPath($oDocument));

			//We don't have to convert pdfs to pdf since.... they're a pdf already ;-)
			//TODO: add check for 3.6.1
			if($sDocType != 'pdf' && (!WemagOnlineDocumentViewerDocumentActionPL::useNewPdfGenerator() || $sDocType != 'application/pdf')){
				
				$oDocument = $this->oDocument;
				$oStorage =& KTStorageManagerUtil::getSingleton();
				$oConfig =& KTConfig::getSingleton();
							
				
				

				//get the actual path to the document on the server
				$sPath = sprintf("%s/%s", $oConfig->get('urls/documentRoot'), $oStorage->getPath($oDocument));
				
				if (file_exists($sPath)) {
					
					if(WemagOnlineDocumentViewerDocumentActionPL::useNewPdfGenerator()){
						 // Check if pdf has already been created
						$dir = $default->pdfDirectory;
						$file = $dir .'/'. $oDocument->getId() . '.pdf';
						$mimetype = 'application/pdf';
						$size = filesize($file);

						// Set the filename
						$name = $this->oDocument->getFileName();
						$aName = explode('.', $name);
						array_pop($aName);
						$name = implode('.', $aName) . '.pdf';

						//create one
						$converter = new pdfConverter();
						$converter->setDocument($this->oDocument);
						$res = $converter->processDocument();

						if(!$res){
							$default->log->error('PDF Generator: PDF file could not be generated');
							WemagOnlineDocumentViewerDocumentActionPL::errorRedirectToMain(_kt('PDF file could not be generated, the file may be of an unsupported mime type or the PDF Generator could not connect.'));
							exit();
						}

						if(file_exists($file)){
							PdfToSwfConverterPL::convertPdfToSwf($file ,$oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf",$props);
						}else{
							$default->log->error('KTplugins.com Online Document Viewer PDF Generator: PDF file could not be converted because it doesn\'t exist');
							return "";
						}
					
					}else{
						$defaultPath = realpath(str_replace('\\','/',KT_DIR . '/../openoffice/program'));
						putenv('ooProgramPath=' . $oConfig->get('openoffice/programPath', $defaultPath));
						$cmdpath = KTUtil::findCommand('externalBinary/python');

					
						// Check if openoffice and python are available
						if($cmdpath == false || !file_exists($cmdpath) || empty($cmdpath)) {
							// Set the error messsage and redirect to view document
							WemagOnlineDocumentViewerDocumentActionPL::addErrorMessage(_kt('An error occurred generating the PDF - please contact the system administrator. Python binary not found.'));
							return "";
						}
					
						// Get a tmp file
						$sTempFilename = tempnam('/tmp', 'ktpdf');

						// We need to handle Windows differently - as usual ;)
						if (substr( PHP_OS, 0, 3) == 'WIN') {	
							$cmd = "\"" . $cmdpath . "\" \"". KT_DIR . "/bin/openoffice/pdfgen.py\" \"" . $sPath . "\" \"" . $sTempFilename . "\"";
							$cmd = str_replace( '/','\\',$cmd);
							PdfToSwfConverterPL::writeLog("Executing WIN command:".$cmd);
							$res = `"$cmd" 2>&1`;
							PdfToSwfConverterPL::writeLog("Result:".$res);
						} else {
							$cmd = $cmdpath . ' ' . KT_DIR . '/bin/openoffice/pdfgen.py ' . escapeshellcmd($sPath) . ' ' . escapeshellcmd($sTempFilename);
							PdfToSwfConverterPL::writeLog("Executing LIN command:".$cmd);
							$res = shell_exec($cmd." 2>&1");
							PdfToSwfConverterPL::writeLog("Result:".$res);				
						}
						
						// Check the tempfile exists and the python script did not return anything (which would indicate an error)
						if (file_exists($sTempFilename) && $res == '') {				
							//now lets turn the PDF into a swf movieclip
							PdfToSwfConverterPL::convertPdfToSwf($sTempFilename ,PatoLeonViewerPlugin::GetParentFolder($oConfig->get("documentRoot"))."/wemagonlinedocuments/".$this->oDocument->getId().".swf",$props);
							echo $oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf";
							unlink($sTempFilename);
						} else {
							// Set the error messsage and redirect to view document
							WemagOnlineDocumentViewerDocumentActionPL::addErrorMessage(_kt('An error occurred converting the document to PDF - please contact the system administrator. ' . $res));

							return "";
						}
					}
				}else{
						// Set the error messsage and redirect to view document
						WemagOnlineDocumentViewerDocumentActionPL::addErrorMessage(_kt('An error occurred converting the document to PDF - source file not found: ' . $sPath));
						return "";
				}
			}else{
				//Document is PDF already, convert it to SWF.
				PdfToSwfConverterPL::convertPdfToSwf($sPath ,$oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf",$props);
			}
		}

		//Get the properties of the current document.
		$props = unserialize(file_get_contents($oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf.prop"));
		//show the viewer and load the document.
		return '
		<script src="plugins/PatoLeon.Viewer/swfobject/swfobject.js"></script>
		<div id="website" style="height: 500px">
						<p align="center" class="style1">In order to view this page you need Flash Player 9+ support!</p>
						<p align="center">
							<a href="http://www.adobe.com/go/getflashplayer">
								<img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" />                </a>            </p>
						</div>
						<script type="text/javascript">
				var flashvars = {
				 doc_url: "plugins/PatoLeon/getDoc.php?swf='.$this->oDocument->getId().'"
				};
				var params = {
				 menu: "false",
				 bgcolor: \'#efefef\',
				 allowFullScreen: \'true\'
				};
				var attributes = {
				 id: \'website\'
				};
				 swfobject.embedSWF(\'plugins/PatoLeon/pdf2swf/swfs/zviewer.swf\',
					\'website\', \'100%\', \'800\', \'9.0.45\',
					\'plugins/PatoLeon/swfobject/expressinstall.swf\',
					flashvars,
					params,
					attributes
				);
		</script>';
	}
}
?>
