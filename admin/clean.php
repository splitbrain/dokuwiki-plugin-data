<?php
/**
 * DokuWiki Plugin data (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_data_clean extends DokuWiki_Admin_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function admin_plugin_data_clean(){
        $this->dthlp =& plugin_load('helper', 'data');
    }

    function getMenuSort() { return 502; }
    function forAdminOnly() { return true; }

    function getMenuText($language) {
        return $this->getLang('menu_clean');
    }

    function handle() {
        if(!isset($_REQUEST['data_go']) || !checkSecurityToken()) return;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $res  = $sqlite->query("SELECT pid, page FROM pages");
        $rows = $sqlite->res2arr($res);

        $count = 0;
        foreach($rows as $row){
            if(!page_exists($row['page'])){
                $sqlite->query('DELETE FROM data WHERE pid = ?',$row['pid']);
                $sqlite->query('DELETE FROM pages WHERE pid = ?',$row['pid']);
                $count++;
            }
        }

        msg(sprintf($this->getLang('pages_del'),$count),1);
    }

    function html() {

        echo $this->locale_xhtml('intro_clean');

        $form = new Doku_Form(array('method'=>'post'));
        $form->addHidden('page','data_clean');
        $form->addHidden('data_go','go');

        $form->addElement(form_makeButton('submit','admin',$this->getLang('submit_clean')));
        $form->printForm();
    }

}

// vim:ts=4:sw=4:et:enc=utf-8:
