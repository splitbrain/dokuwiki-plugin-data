<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(dirname(__FILE__).'/../syntaxbase.php');

/**
 * We extend our own base class here
 */
class syntax_plugin_data_entry extends syntaxbase_plugin_data {

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

            list($key,$type,$multi,$title) = $this->_column($line[0]);
            if($multi){
                if(!is_array($data[$key])) $data[$key] = array(); // init with empty array
                $vals = explode(',',$line[1]);
                foreach($vals as $val){
                    $val = trim($this->_cleanData($val,$type));
                    if($val == '') continue;
                    if(!in_array($val,$data[$key])) $data[$key][] = $val;
                }
            }else{
                $data[$key] = $this->_cleanData($line[1],$type);
            }
            $meta[$key]['multi'] = $multi;
            $meta[$key]['type']  = $multi;
            $meta[$key]['title'] = $title;
        }
        return array('data'=>$data, 'meta'=>$meta, 'classes'=>$class);
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
                $this->_saveData($data,noNS($ID),$renderer->meta['title']);
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
            if($val == '') continue;

            $ret .= '<dt>'.hsc($data['meta'][$key]['title']).'<span class="sep">: </span></dt>';
            if(is_array($val)){
                $cnt = count($val);
                for ($i=0; $i<$cnt; $i++){
                    $ret .= '<dd>';
                    $ret .= $this->_formatData($val[$i], $data['meta'][$key]['type'], $R);
                    if($i < $cnt - 1) $ret .= '<span class="sep">, </span>';
                    $ret .= '</dd>';
                }
            }else{
                $ret .= '<dd>'.$this->_formatData($val, $data['meta'][$key]['type'], $R).'</dd>';
            }
        }
        $ret .= '</dl><div class="clearer"></div></div>';
        return $ret;
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id){
        if(!$this->_dbconnect()) return false;

        $error = '';
        $id    = sqlite_escape_string($id);

        // begin transaction
        $sql = "BEGIN TRANSACTION";
        sqlite_query($this->db,$sql);

        // store page info
        $sql = "INSERT OR IGNORE INTO pages (page) VALUES ('$id')";
        sqlite_query($this->db,$sql,SQLITE_NUM);

        // fetch page id
        $sql = "SELECT pid FROM pages WHERE page = '$id'";
        $res = sqlite_query($this->db, $sql);
        $pid = (int) sqlite_fetch_single($res);

        if(!$pid){
            msg("data plugin: failed saving data",-1);
            return false;
        }

        // update meta info
        foreach ($data['meta'] as $key => $info){
            $key   = sqlite_escape_string($key);
            $type  = sqlite_escape_string($info['type']);
            $multi = (int) $info['multi'];

            $sql = "REPLACE INTO meta (key, type, multi) VALUES ('$key', '$type', '$multi')";
            sqlite_query($this->db,$sql);
        }

        // remove old data
        $sql = "DELETE FROM data WHERE pid = $pid";
        sqlite_query($this->db,$sql);

        // insert new data
        foreach ($data['data'] as $key => $val){
            $k = sqlite_escape_string($key);
            if(is_array($val)) foreach($val as $v){
                $v   = sqlite_escape_string($v);
                $sql = "INSERT INTO data (pid, key, value) VALUES ($pid, '$k', '$v')";
                sqlite_query($this->db,$sql);
            }else {
                $v   = sqlite_escape_string($val);
                $sql = "INSERT INTO data (pid, key, value) VALUES ($pid, '$k', '$v')";
                sqlite_query($this->db,$sql);
            }
        }

        // finish transaction
        $sql = "COMMIT TRANSACTION";
        sqlite_query($this->db,$sql);

        sqlite_close($this->db);
        return true;
    }

}
