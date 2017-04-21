<?php

/**
 * $Id$
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * -------------------------------------------------------------------------
 *
 * You can contact the copyright owner regarding licensing via the contact
 * details that can be found on the KnowledgeTree web site:
 *
 *         http://www.ktdms.com/
 */

require_once(KT_LIB_DIR . '/plugins/plugin.inc.php');
require_once(KT_LIB_DIR . '/plugins/pluginregistry.inc.php');
require_once(KT_LIB_DIR . '/metadata/fieldset.inc.php');
require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');
require_once(KT_LIB_DIR . '/documentmanagement/DocumentTransactionType.inc.php');
require_once(KT_DIR . '/plugins/PatoLeon.Viewer/WemagOnlineDocumentViewerPlugin.php');

class PatoLeonViewerPlugin extends KTPlugin {
    var $sNamespace = "PatoLeon.viewer.plugin";	
	var $iVersion = 0;
    var $autoRegister = true;
    var $createSQL = true;
	
    function PatoLeonViewerPlugin($sFilename = null) {
        $res = parent::KTPlugin($sFilename);
        $this->sFriendlyName = _kt('Pato Leon - Viewer Plugin');
		$this->dir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		$this->sSQLDir = $this->dir . 'sql' . DIRECTORY_SEPARATOR;
        return $res;
    }            
    
    function setup() {
		$oConfig =& KTConfig::getSingleton();
		//set up swf dir
		/*if(!is_dir($this->GetParentFolder($oConfig->get("documentRoot"))."/wemagonlinedocuments")){
			die("Please create the directory ".$this->GetParentFolder($oConfig->get("documentRoot"))."/wemagonlinedocuments"." first in the way described in the installation instructions of this plugin.");
		}*/

		//create log file
		if(!is_file($oConfig->get("urls/logDirectory")."/wemag-online-documents.txt")){
			if($fh = @fopen($oConfig->get("urls/logDirectory")."/wemag-online-documents.txt","a+b")){
				fclose($fh);
			}
			if(!is_file($oConfig->get("urls/logDirectory")."/wemag-online-documents.txt")){
				die("Can't create log file: " . $oConfig->get("urls/logDirectory")."/wemag-online-documents.txt".". Please verify if the containing directory exists and permissions are set correctly");
			}
		}
	
	//register templates
        require_once(KT_LIB_DIR . "/templating/templating.inc.php");
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplating->addLocation('PatoLeon.Viewer', '/plugins/PatoLeon.Viewer/templates/');
	
	//register actions
		$this->registerAction('documentaction', 'WemagOnlineDocumentViewerDocumentActionPL', 'wemag.OnlineDocumentViewerPluginPL.actions.document');
	
	//register triggers
		$this->registerTrigger('delete', 'postValidate', 'PatoLeonViewerDeleteTrigger', 'PatoLeon.Viewer.Delete', __FILE__);
    }

	function getPrevNext(){		
		$results=unserialize($_SESSION['search2_results']);
		$Documents = array();
		if($results){			
			foreach($results['docs'] as $val){
				$Documents[] = $val->id;
			}			
		}else{
			$Documents = explode(',',Folder::getDocumentIDs($this->oDocument->getFolderID()));
			//var_dump($Documents);
			/*$PrevNext = array();
			$PrevNext['Prev'] = $Documents[$index-1];
			$PrevNext['Next'] = $Documents[$index+1];
			return PrevNext;*/
		}
		$index = array_search($this->oDocument->getId(), $Documents);
		return array($Documents[$index-1],$Documents[$index+1], $index, count($Documents));
	}
	
}

class PatoLeonViewerDeleteTrigger{
	var $sNamespace = "PatoLeon.Viewer.Delete";
		
     var $aInfo = null;

    function setInfo($aInfo) {
        $this->aInfo = $aInfo;
    }

    /**
     * On deleting a document, delete also swf and swf.prop files
     */
    function postValidate() {
        $oDoc = $this->aInfo['document'];
        $docId = $oDoc->getId();
        //$docInfo = array('id' => $docId, 'name' => $oDoc->getName());

        // Delete the pdf document
        global $default;
        $documentRoot = $default->documentRoot;
        
		$file=$oConfig->get("swfDirectory").DIRECTORY_SEPARATOR.$this->oDocument->getId().".swf";

        if(file_exists($file)){
            @unlink($file);
        }
		
        if(file_exists($file.'.prop')){
            @unlink($file.'.prop');
        }
		
    }
}


$oPluginRegistry =& KTPluginRegistry::getSingleton();
$oPluginRegistry->registerPlugin('PatoLeonViewerPlugin', 'PatoLeon.viewer.plugin', __FILE__);
?>
