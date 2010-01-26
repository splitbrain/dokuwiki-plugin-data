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
        $class = str_replace('datatable','',$class);
        $class = trim($class,'- ');

        // parse info
        $data = array();
        $meta = array();
        foreach ( $lines as $line ) {
            // ignore comments
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            $line = preg_split('/\s*:\s*/',$line,2);

            $column = $this->dthlp->_column($line[0]);
            if($column['multi']){
                if(!is_array($data[$column['key']])) $data[$column['key']] = array(); // init with empty array
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
        return array('data'=>$data, 'cols'=>$columns, 'classes'=>$class);
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;

        switch ($format){
            case 'xhtml':
                $renderer->doc .= $this->_showData($data,$renderer);
                return true;
            case 'metadata':
                $this->_saveData($data,$ID,$renderer->meta['title']);
                return true;
            default:
                return false;
        }
    }

    /**
     * Output the data in a table
     */
    function _showData($data,&$R){
        $ret = '';

        $ret .= '<div class="inline dataplugin_entry '.$data['classes'].'"><dl>';
        foreach($data['data'] as $key => $val){
            if($val == '' || !count($val)) continue;

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
        return $ret;
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id,$title){
        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $error = '';
        if(!$title) $title = $id;

        // begin transaction
        $sqlite->query("BEGIN TRANSACTION");

        // store page info
        $sqlite->query("INSERT OR IGNORE INTO pages (page,title) VALUES (?,?)",
                       $id,$title);

        // Update title if insert failed (record already saved before)
        $sqlite->query("UPDATE pages SET title = ? WHERE page = ?",
                       $id,$title);

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

}
