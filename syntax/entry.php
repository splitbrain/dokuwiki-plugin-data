<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_data_entry extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_data_entry(){
        $this->dthlp =& plugin_load('helper', 'data');
        if(!$this->dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.',-1);
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *dataentry(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',$mode,'plugin_data_entry');
    }

    /**
     * Handle the match - parse the data
     */
    function handle($match, $state, $pos, &$handler){
        // get lines
        $lines = explode("\n",$match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = str_replace('dataentry','',$class);
        $class = trim($class,'- ');

        // parse info
        $data = array();
        $meta = array();
        foreach ( $lines as $line ) {
            // ignore comments
            preg_match('/^(.*?(?<![&\\\\]))(?:#(.*))?$/',$line, $matches);
            $line = $matches[1];
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            $line = preg_split('/\s*:\s*/',$line,2);

            $column = $this->dthlp->_column($line[0]);
            if (isset($matches[2])) $column['comment'] = $matches[2];
            if($column['multi']){
                if(!isset($data[$column['key']])) {
                    // init with empty array
                    // Note that multiple occurrences of the field are
                    // practically merged
                    $data[$column['key']] = array();
                }
                $vals = explode(',',$line[1]);
                foreach($vals as $val){
                    $val = trim($this->dthlp->_cleanData($val,$column['type']));
                    if($val == '') continue;
                    if(!in_array($val,$data[$column['key']])) $data[$column['key']][] = $val;
                }
            }else{
                $data[$column['key']] = $this->dthlp->_cleanData($line[1],$column['type']);
            }
            $columns[$column['key']]  = $column;
        }
        return array('data'=>$data, 'cols'=>$columns, 'classes'=>$class,
                     'pos' => $pos, 'len' => strlen($match)); // not utf8_strlen
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;
        switch ($format){
            case 'xhtml':
                $this->_showData($data,$renderer);
                return true;
            case 'metadata':
                $this->_saveData($data,$ID,$renderer->meta['title']);
                return true;
            case 'plugin_data_edit':
                $this->_editData($data, $renderer);
                return true;
            default:
                return false;
        }
    }

    /**
     * Output the data in a table
     */
    function _showData($data,&$R){
        global $ID;
        $ret = '';

        if (method_exists($R, 'startSectionEdit')) {
            $data['classes'] .= ' ' . $R->startSectionEdit($data['pos'], 'plugin_data');
        }
        $ret .= '<div class="inline dataplugin_entry '.$data['classes'].'"><dl>';
        foreach($data['data'] as $key => $val){
            if($val == '' || !count($val)) continue;
            $type = $data['cols'][$key]['type'];
            if (is_array($type)) $type = $type['type'];
            switch ($type) {
            case 'pageid':
                $type = 'title';
            case 'wiki':
                $val = $ID . '|' . $val;
                break;
            }

            $ret .= '<dt class="' . hsc($key) . '">'.hsc($data['cols'][$key]['title']).'<span class="sep">: </span></dt>';
            if(is_array($val)){
                $cnt = count($val);
                for ($i=0; $i<$cnt; $i++){
                    $ret .= '<dd class="' . hsc($key) . '">';
                    $ret .= $this->dthlp->_formatData($data['cols'][$key], $val[$i],$R);
                    if($i < $cnt - 1) $ret .= '<span class="sep">, </span>';
                    $ret .= '</dd>';
                }
            }else{
                $ret .= '<dd class="' . hsc($key) . '">'.
                        $this->dthlp->_formatData($data['cols'][$key], $val, $R).'</dd>';
            }
        }
        $ret .= '</dl></div>';
        $R->doc .= $ret;
        if (method_exists($R, 'finishSectionEdit')) {
            $R->finishSectionEdit($data['len'] + $data['pos']);
        }
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id,$title){
        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $error = '';
        if(!$title) $title = $id;

        $class = $data['classes'];

        // begin transaction
        $sqlite->query("BEGIN TRANSACTION");

        // store page info
        $sqlite->query("INSERT OR IGNORE INTO pages (page,title,class) VALUES (?,?,?)",
                       $id,$title,$class);

        // Update title if insert failed (record already saved before)
        $sqlite->query("UPDATE pages SET title = ?, class = ? WHERE page = ?",
                       $title,$class,$id);

        // fetch page id
        $res = $sqlite->query("SELECT pid FROM pages WHERE page = ?",$id);
        $pid = (int) sqlite_fetch_single($res);

        if(!$pid){
            msg("data plugin: failed saving data",-1);
            return false;
        }

        // remove old data
        $sqlite->query("DELETE FROM data WHERE pid = ?",$pid);

        // insert new data
        foreach ($data['data'] as $key => $val){
            if(is_array($val)) foreach($val as $v){
                $sqlite->query("INSERT INTO data (pid, key, value) VALUES (?, ?, ?)",
                               $pid,$key,$v);
            }else {
                $sqlite->query("INSERT INTO data (pid, key, value) VALUES (?, ?, ?)",
                               $pid,$key,$val);
            }
        }

        // finish transaction
        $sqlite->query("COMMIT TRANSACTION");

        return true;
    }

    function _editData($data, &$renderer) {
        $renderer->form->startFieldset($this->getLang('dataentry'));
        $renderer->form->_content[count($renderer->form->_content) - 1]['class'] = 'plugin__data';

        if ($this->getConf('edit_content_only')) {
            $renderer->form->addHidden('data_edit[classes]', $data['classes']);
            $renderer->form->addElement('<table>');
        } else {
            $renderer->form->addElement(form_makeField('text', 'data_edit[classes]', $data['classes'], $this->getLang('class'), 'data__classes'));
            $renderer->form->addElement('<table>');

            $text = '<tr>';
            foreach(array('title', 'type', 'multi', 'value', 'comment') as $val) {
                $text .= '<th>' . $this->getLang($val) . '</th>';
            }
            $renderer->form->addElement($text . '</tr>');

            // New line
            $data['data'][''] = '';
            $data['cols'][''] = array('type' => '', 'multi' => false);
        }

        $n = 0;
        foreach($data['cols'] as $key => $vals) {
            $fieldid = 'data_edit[data][' . $n++ . ']';
            $content = $vals['multi'] ? implode(', ', $data['data'][$key]) : $data['data'][$key];
            if (is_array($vals['type'])) {
                $vals['basetype'] = $vals['type']['type'];
                if (isset($vals['type']['enum'])) {
                    $vals['enum'] = $vals['type']['enum'];
                }
                $vals['type'] = $vals['origtype'];
            } else {
                $vals['basetype'] = $vals['type'];
            }
            $renderer->form->addElement('<tr>');
            if ($this->getConf('edit_content_only')) {
                if (isset($vals['enum'])) {
                    $values = preg_split('/\s*,\s*/', $vals['enum']);
                    if (!$vals['multi']) array_unshift($values, '');
                    $content = form_makeListboxField($fieldid . '[value][]', $values,
                                                     $data['data'][$key], $vals['title'], '', '', ($vals['multi'] ? array('multiple' => 'multiple'): array()));
                } else {
                    $classes = 'data_type_' . $vals['type'] . ($vals['multi'] ? 's' : '') .  ' ' .
                               'data_type_' . $vals['basetype'] . ($vals['multi'] ? 's' : '');
                    $content = form_makeField('text', $fieldid . '[value]', $content, $vals['title'], '', $classes);

                }
                $cells = array($vals['title'] . ':',
                               $content,
                               $vals['comment']);
                foreach(array('title', 'multi', 'comment', 'type') as $field) {
                    $renderer->form->addHidden($fieldid . "[$field]", $vals[$field]);
                }
            } else {
                $check_data = $vals['multi'] ? array('checked' => 'checked') : array();
                $cells = array(form_makeField('text', $fieldid . '[title]', $vals['title'], $this->getLang('title')),
                               form_makeMenuField($fieldid . '[type]',
                                                  array_merge(array('', 'page', 'nspage', 'title',
                                                                    'img', 'mail', 'url', 'tag', 'wiki', 'dt'),
                                                              array_keys($this->dthlp->_aliases())),
                                                  $vals['type'],
                                                  $this->getLang('type')),
                               form_makeCheckboxField($fieldid . '[multi]', array('1', ''), $this->getLang('multi'), '', '', $check_data),
                               form_makeField('text', $fieldid . '[value]', $content, $this->getLang('value')),
                               form_makeField('text', $fieldid . '[comment]', $vals['comment'], $this->getLang('comment'), '', 'data_comment', array('readonly' => 1)));
            }
            foreach($cells as $cell) {
                $renderer->form->addElement('<td>');
                $renderer->form->addElement($cell);
                $renderer->form->addElement('</td>');
            }
            $renderer->form->addElement('</tr>');
        }

        $renderer->form->addElement('</table>');
        $renderer->form->endFieldset();
    }

    function _normalize($txt) {
        return str_replace('#', '\#', trim($txt));
    }

    public static function editToWiki($data) {
        $nudata = array();
        $len = 0;
        foreach ($data['data'] as $field) {
            if ($field['title'] === '') continue;
            $s = syntax_plugin_data_entry::_normalize($field['title']);
            if (trim($field['type']) !== '' ||
                (substr($s, -1, 1) === 's' && $field['multi'] === '')) {
                $s .= '_' . syntax_plugin_data_entry::_normalize($field['type']);
            }
            if ($field['multi'] === '1') {
                $s .= 's';
            }
            if (is_array($field['value'])) {
                $field['value'] = join(', ', $field['value']);
            }

            $nudata[] = array($s, syntax_plugin_data_entry::_normalize($field['value']),
                              isset($field['comment']) ? trim($field['comment']) : '');
            $len = max($len, utf8_strlen($nudata[count($nudata) - 1][0]));
        }

        $ret = '---- dataentry ' . trim($data['classes']) . ' ----' . DOKU_LF;
        foreach ($nudata as $field) {
            $ret .= $field[0] . str_repeat(' ', $len + 1 - utf8_strlen($field[0])) . ': ' .
                    $field[1];
            if ($field[2] !== '') {
                $ret .= ' #' . $field[2];
            }
            $ret .= DOKU_LF;
        }
        $ret .= '----';
        return $ret;
    }
}
