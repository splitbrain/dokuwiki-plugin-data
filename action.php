<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'action.php');

require_once DOKU_PLUGIN.'data/bureaucracy_field.php';

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
    }

    /**
     * Registers a callback function for a given event
     */
    function register(&$controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, '_handle');
        $controller->register_hook('HTML_SECEDIT_BUTTON', 'BEFORE', $this, '_editbutton');
        $controller->register_hook('HTML_EDIT_FORMSELECTION', 'BEFORE', $this, '_editform');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_edit_post');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_handle_ajax');
    }

    /**
     * Handles the page write event and removes the database info
     * when the plugin code is no longer in the source
     */
    function _handle(&$event, $param){
        $data = $event->data;
        if(strpos($data[0][1],'dataentry') !== false) return; // plugin seems still to be there

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return;
        $id = ltrim($data[1].':'.$data[2],':');

        // get page id
        $res = $sqlite->query('SELECT pid FROM pages WHERE page = ?',$id);
        $pid = (int) sqlite_fetch_single($res);
        if(!$pid) return; // we have no data for this page

        $sqlite->query('DELETE FROM data WHERE pid = ?',$pid);
        $sqlite->query('DELETE FROM pages WHERE pid = ?',$pid);
    }

    function _editbutton(&$event, $param) {
        if ($event->data['target'] !== 'plugin_data') {
            return;
        }

        $event->data['name'] = $this->getLang('dataentry');
    }

    function _editform(&$event, $param) {
        global $TEXT;
        if ($event->data['target'] !== 'plugin_data') {
            // Not a data edit
            return;
        }

        $event->stopPropagation();
        $event->preventDefault();
        unset($event->data['intro_locale']);
        $event->data['media_manager'] = false;

        echo $this->locale_xhtml('edit_intro' . ($this->getConf('edit_content_only') ? '_contentonly' : ''));

        require_once 'renderer_data_edit.php';
        $Renderer = new Doku_Renderer_plugin_data_edit();
        $Renderer->form = $event->data['form'];

        // Loop through the instructions
        $instructions = p_get_instructions($TEXT);
        foreach ( $instructions as $instruction ) {
            // Execute the callback against the Renderer
            call_user_func_array(array(&$Renderer, $instruction[0]),$instruction[1]);
        }
    }

    function _handle_edit_post($event) {
        if (!isset($_POST['data_edit'])) {
            return;
        }
        global $TEXT;

        require_once 'syntax/entry.php';
        $TEXT = syntax_plugin_data_entry::editToWiki($_POST['data_edit']);
    }

    function _handle_ajax($event) {
        if (strpos($event->data, 'data_page_') !== 0) {
            return;
        }
        $event->preventDefault();

        $type = substr($event->data, 10);
        $aliases = $this->dthlp->_aliases();
        if (!isset($aliases[$type])) {
            echo 'Unknown type';
            return;
        }
        if ($aliases[$type]['type'] !== 'page') {
            echo 'AutoCompletion is only supported for page types';
            return;
        }

        if (substr($aliases[$type]['postfix'], -1, 1) === ':') {
            // Resolve namespace start page ID
            global $conf;
            $aliases[$type]['postfix'] .= $conf['start'];
        }

        $search = $_POST['search'];
        $pages = ft_pageLookup($search, false, false);

        $regexp = '/^';
        if ($aliases[$type]['prefix'] !== '') {
            $regexp .= preg_quote($aliases[$type]['prefix'], '/');
        }
        $regexp .= '([^:]+)';
        if ($aliases[$type]['postfix'] !== '') {
            $regexp .= preg_quote($aliases[$type]['postfix'], '/');
        }
        $regexp .= '$/';

        $result = array();
        foreach ($pages as $page => $title) {
            $id = array();
            if (!preg_match($regexp, $page, $id)) {
                // Does not satisfy the postfix and prefix criteria
                continue;
            }

            $id = $id[1];

            if ($search !== '' &&
                stripos($id, cleanID($search)) === false &&
                stripos($title, $search) === false) {
                // Search string is not in id part or title
                continue;
            }

            if ($title === '') {
                $title = utf8_ucwords(str_replace('_', ' ', $id));
            }
            $result[hsc($id)] = hsc($title);
        }

        $json = new JSON();
        echo '(' . $json->encode($result) . ')';
    }
}
