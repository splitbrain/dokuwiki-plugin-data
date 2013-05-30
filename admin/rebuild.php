<?php
/**
 * DokuWiki Plugin git (Admin Component)
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_data_rebuild extends DokuWiki_Admin_Plugin {

    function getInfo() {
        return confToHash(dirname(__FILE__).'plugin.info.txt');
    }

    function getMenuSort() { return 1; }
    function forAdminOnly() { return true; }

    function getMenuText($language) {
        return $this->getLang('menu_rebuild');
    }
    
    function handle() {
    
    }

    function html() {

       echo '<h2>Short:</h2><p>This page allows you to refreshes all Data-Plugin data. You can refresh as often as you like!</p>';
       
       echo '<form method="post">';
       echo '  <input type="submit" name="cmd[refresh_data]"  value="Refresh Data plugin data" />';
       echo '</form><br/>';
       
  }

}

// vim:ts=4:sw=4:et:
