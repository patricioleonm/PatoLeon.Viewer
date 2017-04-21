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



/**
 * Class to convert PDFs to SWF movieclips
 *
 */

class PdfToSwfConverterPL{
	/**
	 * Writes log to a log file
	 *
	 * @param string $txt log text
	 * @param file $logfile log file
	 */
	public static function writeLog($txt,$logfile = null){
		if($logfile == null){
			$oConfig =& KTConfig::getSingleton();
			$logfile = $oConfig->get("urls/logDirectory")."/wemag-online-documents.txt";
		}
		if(@filesize($logfile) > 10000){
			@unlink($logfile);
		}
		file_put_contents($logfile,date('r').":".$txt."\n",FILE_APPEND);
	}
	
	/**
	 * Converts a PDF to an SWF
	 *
	 * @param file $src src file
	 * @param file $destination destination file
	 * @param array $extra_props extra properties for companying property file
	 */
	public static function convertPdfToSwf($src, $destination, $extra_props = array()){
		$tempname = time();
		if(!is_file($src) || filesize($src) == 0){
			die("An error occured: Invalid source file (PDF conversion error - corrupt source file) ".$src);
		}
		PdfToSwfConverterPL::writeLog("Starting PDF to SWF conversion");
		 if (substr( PHP_OS, 0, 3) == 'WIN') {
			$cmd = '"'.KT_DIR.'/plugins/PatoLeon.Viewer/pdf2swf/pdf2swf.exe" "'.$src.'" -o "'.$destination.'"';
			//$cmd = str_replace('/','\\', $cmd);
			PdfToSwfConverterPL::writeLog("Executing WIN command ".$cmd); 
			PdfToSwfConverterPL::writeLog("Command output: ");	
			$output = `"$cmd" 2>&1`;
			PdfToSwfConverterPL::writeLog($output);
		 }else{
			$cmd = "pdf2swf \"$src\" -o \"$destination\"";
			PdfToSwfConverterPL::writeLog("Executing LIN command ".$cmd);
			PdfToSwfConverterPL::writeLog("Command output: ");	
		 	PdfToSwfConverterPL::writeLog(exec($cmd));
		 }
		
		PdfToSwfConverterPL::writeLog("\n========================================\n\n");

		//delete old property file
		@unlink($destination.".prop");
		
		//write property file
		file_put_contents($destination.".prop",serialize($extra_props));
	}
	
	
}
?>
