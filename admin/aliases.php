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

class admin_plugin_data_aliases extends DokuWiki_Admin_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function admin_plugin_data_aliases(){
        $this->dthlp =& plugin_load('helper', 'data');
    }

    function getMenuSort() { return 501; }
    function forAdminOnly() { return true; }

    function getMenuText($language) {
        return $this->getLang('menu_alias');
    }

    function handle() {
        if(!is_array($_REQUEST['d']) || !checkSecurityToken()) return;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $sqlite->query("BEGIN TRANSACTION");
        if (!$sqlite->query("DELETE FROM aliases")) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return;
        }
        foreach($_REQUEST['d'] as $row){
            $row = array_map('trim',$row);
            $row['name'] = utf8_strtolower($row['name']);
            $row['name'] = rtrim($row['name'],'s');
            if(!$row['name']) continue;

            // Clean enum
            $arr = preg_split('/\s*,\s*/', $row['enum']);
            $arr = array_unique($arr);
            asort($arr);
            $row['enum'] = implode(', ', $arr);

            if (!$sqlite->query("INSERT INTO aliases (name, type, prefix, postfix, enum)
                                 VALUES (?,?,?,?,?)",$row)) {
                $sqlite->query('ROLLBACK TRANSACTION');
                return;
            }
        }
        $sqlite->query("COMMIT TRANSACTION");
    }

    function html() {
        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        echo $this->locale_xhtml('admin_intro');

        $sql = "SELECT * FROM aliases ORDER BY name";
        $res = $sqlite->query($sql);
        $rows = $sqlite->res2arr($res);

        $form = new Doku_Form(array('method'=>'post'));
        $form->addHidden('page','data_aliases');
        $form->addElement(
            '<table class="inline">'.
            '<tr>'.
            '<th>'.$this->getLang('name').'</th>'.
            '<th>'.$this->getLang('type').'</th>'.
            '<th>'.$this->getLang('prefix').'</th>'.
            '<th>'.$this->getLang('postfix').'</th>'.
            '<th>'.$this->getLang('enum').'</th>'.
            '</tr>'
        );

        // add empty row for adding a new entry
        $rows[] = array('name'=>'','type'=>'','prefix'=>'','postfix'=>'','enum'=>'');

        $cur = 0;
        foreach($rows as $row){
            $form->addElement('<tr>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][name]',$row['name'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeMenuField('d['.$cur.'][type]',
                                array('','page','title','mail','url', 'dt', 'wiki','tag'),
                              $row['type'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][prefix]',$row['prefix'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][postfix]',$row['postfix'],''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d['.$cur.'][enum]',$row['enum'],''));
            $form->addElement('</td>');

            $form->addElement('</tr>');

            $cur++;
        }

        $form->addElement('</table>');
        $form->addElement(form_makeButton('submit','admin',$this->getLang('submit')));
        $form->printForm();
    }

}

// vim:ts=4:sw=4:et:enc=utf-8:
