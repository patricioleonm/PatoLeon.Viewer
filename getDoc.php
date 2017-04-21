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
 * This file serves the document SWF's and does the permission checks etc.
 */
require_once('../../config/dmsDefaults.php');

//authenticate
$session = new Session();
$status = $session->verify();
if(PEAR::isError($status) || $status == false){
	die("Please log in.");
}
$oUser = User::get($_SESSION['userID']);
if(Pear::isError($oUser)){
	die("Invalid user");
}

//check input
$mode = 'doc';
$doc = $_GET['doc'];
$swf = $_GET['swf'];
if(!is_numeric($doc)){
	$mode = 'swf';
	$doc = $swf;
}

$doc = Document::get($doc);
if(PEAR::isError($doc)){
	die("Invalid document");
}

//check permission
if(KTPermissionUtil::userHasPermissionOnItem($oUser,'ktcore.permissions.read',$doc) == true){
	$oConfig =& KTConfig::getSingleton();	
	$path = $oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$doc->getId().".swf";
	
	if($mode == 'doc'){
		$oStorage =& KTStorageManagerUtil::getSingleton();
		$oConfig =& KTConfig::getSingleton();
		$path = sprintf("%s/%s", $oConfig->get('urls/documentRoot'), $oStorage->getPath($doc));
	}
	
	if($fh=fopen($path,"r")){
		if($mode == 'doc'){
			$mid = $doc->getMimeTypeID();
			header("Content-Type: ".KTMime::getMimeTypeName($mid));
		}else{
			header("Content-Type: application/x-shockwave-flash");
		}
		header("Content-Length: ".filesize($path));
		fpassthru($fh);
		fclose($fh);
	}else{
		die("Can't open file: ".$path);
	}
}else{
	die("You don't have permission to view this document.");
}

?>