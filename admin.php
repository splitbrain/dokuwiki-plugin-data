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
        $form = new Doku_Form(array('method'=>'post'));

        $form->addHidden('page','data');
        $form->addElement(
            '<table class="inline">'.
            '<tr>'.
            '<th>'.'name'.'</th>'.
            '<th>'.'type'.'</th>'.
            '<th>'.'prefix'.'</th>'.
            '<th>'.'postfix'.'</th>'.
            '<th>'.'comment'.'</th>'.
            '</tr>'
        );

        // add empty row for adding a new entry
        $rows[] = array('name'=>'','type'=>'','prefix'=>'','postfix'=>'','comment'=>'');

        $cur = 0;
        foreach($rows as $row){
            $form->addElement('<tr>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][name]',$row['name'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeMenuField('d['.$cur.'][type]',
                                array('','page','title','mail','url'),$row['type'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][prefix]',$row['prefix'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][postfix]',$row['postfix'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][comment]',$row['comment'],''));
            $form->addElement('</td>');

            $form->addElement('</tr>');

            $cur++;
        }

        $form->addElement('</table>');
        $form->addElement(form_makeButton('submit','admin','FIXME local'));
        $form->printForm();
    }

}

// vim:ts=4:sw=4:et:enc=utf-8:
