<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_data extends DokuWiki_Action_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function action_plugin_data(){
        $this->dthlp =& plugin_load('helper', 'data');
        if(!$this->dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.',-1);
    }

    /**
     * Return some info
     */
    function getInfo(){
        return $this->dthlp->getInfo();
    }

    /**
     * Registers a callback function for a given event
     */
    function register($controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, '_handle');
    }

    /**
     * Handles the page write event and removes the database info
     * when the plugin code is no longer in the source
     */
    function _handle(&$event, $param){
        $data = $event->data;
        if(strpos($data[0][1],'dataentry') !== false) return; // plugin seems still to be there

        if(!$this->dthlp->_dbconnect()) return;
        $id = $data[2];

        // get page id
        $sql = "SELECT pid FROM pages WHERE page ='".sqlite_escape_string($id)."'";
        $res = sqlite_query($this->dthlp->db, $sql);
        $pid = (int) sqlite_fetch_single($res);
        if(!$pid) return; // we have no data for this page

        $sql = "DELETE FROM data WHERE pid = $pid";
        sqlite_query($this->dthlp->db, $sql);

        $sql = "DELETE FROM pages WHERE pid = $pid";
        sqlite_query($this->dthlp->db, $sql);
    }
}

