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
     * Return some info
     */
    function getInfo(){
        return $this->dthlp->getInfo();
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

            list($key,$type,$multi,$title) = $this->dthlp->_column($line[0]);
            if($multi){
                if(!is_array($data[$key])) $data[$key] = array(); // init with empty array
                $vals = explode(',',$line[1]);
                foreach($vals as $val){
                    $val = trim($this->dthlp->_cleanData($val,$type));
                    if($val == '') continue;
                    if(!in_array($val,$data[$key])) $data[$key][] = $val;
                }
            }else{
                $data[$key] = $this->dthlp->_cleanData($line[1],$type);
            }
            $meta[$key]['multi'] = $multi;
            $meta[$key]['type']  = $type;
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

            $ret .= '<dt>'.hsc($data['meta'][$key]['title']).'<span class="sep">: </span></dt>';
            if(is_array($val)){
                $cnt = count($val);
                for ($i=0; $i<$cnt; $i++){
                    $ret .= '<dd>';
                    $ret .= $this->dthlp->_formatData($key, $val[$i], $data['meta'][$key]['type'], $R);
                    if($i < $cnt - 1) $ret .= '<span class="sep">, </span>';
                    $ret .= '</dd>';
                }
            }else{
                $ret .= '<dd>'.$this->dthlp->_formatData($key, $val, $data['meta'][$key]['type'], $R).'</dd>';
            }
        }
        $ret .= '</dl><div class="clearer"></div></div>';
        return $ret;
    }

    /**
     * Save date to the database
     */
    function _saveData($data,$id,$title){
        if(!$this->dthlp->_dbconnect()) return false;

        $error = '';
        if(!$title) $title = $id;
        $id    = sqlite_escape_string($id);
        $title = sqlite_escape_string($title);

        // begin transaction
        $sql = "BEGIN TRANSACTION";
        sqlite_query($this->dthlp->db,$sql);

        // store page info
        $sql = "INSERT OR IGNORE INTO pages (page,title) VALUES ('$id','$title')";
        sqlite_query($this->dthlp->db,$sql,SQLITE_NUM);

        // fetch page id
        $sql = "SELECT pid FROM pages WHERE page = '$id'";
        $res = sqlite_query($this->dthlp->db, $sql);
        $pid = (int) sqlite_fetch_single($res);

        if(!$pid){
            msg("data plugin: failed saving data",-1);
            return false;
        }

        // remove old data
        $sql = "DELETE FROM data WHERE pid = $pid";
        sqlite_query($this->dthlp->db,$sql);

        // insert new data
        foreach ($data['data'] as $key => $val){
            $k = sqlite_escape_string($key);
            if(is_array($val)) foreach($val as $v){
                $v   = sqlite_escape_string($v);
                $sql = "INSERT INTO data (pid, key, value) VALUES ($pid, '$k', '$v')";
                sqlite_query($this->dthlp->db,$sql);
            }else {
                $v   = sqlite_escape_string($val);
                $sql = "INSERT INTO data (pid, key, value) VALUES ($pid, '$k', '$v')";
                sqlite_query($this->dthlp->db,$sql);
            }
        }

        // finish transaction
        $sql = "COMMIT TRANSACTION";
        sqlite_query($this->dthlp->db,$sql);

        sqlite_close($this->dthlp->db);
        return true;
    }

}
