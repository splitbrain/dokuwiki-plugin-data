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

class admin_plugin_data extends DokuWiki_Admin_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function admin_plugin_data(){
        $this->dthlp =& plugin_load('helper', 'data');
    }

    function getMenuSort() { return 501; }
    function forAdminOnly() { return true; }

    function handle() {
        if(!is_array($_REQUEST['d']) || !checkSecurityToken()) return;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $sqlite->query("BEGIN TRANSACTION");
        $sqlite->query("DELETE FROM aliases");
        foreach($_REQUEST['d'] as $row){
            $row = array_map('trim',$row);
            if(!$row['name']) continue;
            $sqlite->query("INSERT INTO aliases (name, type, prefix, postfix, comment)
                                 VALUES (?,?,?,?,?)",$row);
        }
        $sqlite->query("COMMIT TRANSACTION");
    }

    function html() {
        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        echo $this->locale_xhtml('intro');


        $sql = "SELECT * FROM aliases ORDER BY name";
        $res = $sqlite->query($sql);
        $rows = $sqlite->res2arr($res);

        #FIXME localize
        echo '<form method="post" action="">';
        formSecurityToken();
        echo '<table class="inline">';
        echo '<tr>';
        echo '<th>'.'name'.'</th>';
        echo '<th>'.'type'.'</th>';
        echo '<th>'.'prefix'.'</th>';
        echo '<th>'.'postfix'.'</th>';
        echo '<th>'.'comment'.'</th>';
        echo '</tr>';

        // add empty row for adding a new entry
        $rows[] = array('name'=>'','type'=>'','prefix'=>'','postfix'=>'','comment'=>'');

        $cur = 0;
        foreach($rows as $row){
            echo '<tr>';
            echo '<td><input type="text" name="d['.$cur.'][name]" class="edit" value="'.hsc($row['name']).'" /></td>';
            echo '<td><input type="text" name="d['.$cur.'][type]" class="edit" value="'.hsc($row['type']).'" /></td>'; #FIXME make dropdown
            echo '<td><input type="text" name="d['.$cur.'][prefix]" class="edit" value="'.hsc($row['prefix']).'" /></td>';
            echo '<td><input type="text" name="d['.$cur.'][postfix]" class="edit" value="'.hsc($row['postfix']).'" /></td>';
            echo '<td><input type="text" name="d['.$cur.'][comment]" class="edit" value="'.hsc($row['comment']).'" /></td>';
            echo '</tr>';

            $cur++;
        }
        echo '</table>';
        echo '<input type="submit" class="button" />';
        echo '</form>';
    }

}

// vim:ts=4:sw=4:et:enc=utf-8:
