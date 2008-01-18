<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(dirname(__FILE__).'/syntaxbase.php');

/**
 * Action plugin to delete data from DB on page deletion
 *
 * We extend our own base class here
 *
 * Yes, this base class inherits from syntax plugin instead from action plugin.
 * This is nothing to be proud of, but it works around missing multi
 * inheritance in PHP, it does not depend on PHP5 only interfaces, does not fall
 * back to procedural programming style and avoids double coding. Oh and it just
 * works ;-).
 */
class action_plugin_data extends syntaxbase_plugin_data {

    /**
     * Registers a callback function for a given event
     */
    function register($controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, '_handle');
    }

    /**
     * Handles the page write event and removes the database info on
     * page deletions
     */
    function _handle(&$event, $param){
        $data = $event->data;
        if(!empty($data[0][1])) return; // no page deletion - do nothing
        if(!$this->_dbconnect()) return;
        $id = $data[2];

        // get page id
        $sql = "SELECT pid FROM pages WHERE page ='".sqlite_escape_string($id)."'";
        $res = sqlite_query($this->db, $sql);
        $pid = (int) sqlite_fetch_single($res);
        if(!$pid) return; // we have no data for this page

        $sql = "DELETE FROM data WHERE pid = $pid";
        sqlite_query($this->db, $sql);

        $sql = "DELETE FROM pages WHERE pid = $pid";
        sqlite_query($this->db, $sql);
    }
}

