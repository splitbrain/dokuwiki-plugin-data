<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_data_cloud extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_data_cloud(){
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
        $this->Lexer->addSpecialPattern('----+ *datacloud(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',$mode,'plugin_data_cloud');
    }


    /**
     * Handle the match - parse the data
     */
    function handle($match, $state, $pos, &$handler){
        // get lines and additional class
        $lines = explode("\n",$match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = str_replace('datatcloud','',$class);
        $class = trim($class,'- ');

        $data = array();
        $data['classes'] = $class;

        // parse info
        foreach ( $lines as $line ) {
            // ignore comments
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            $line = preg_split('/\s*:\s*/',$line,2);
            $line[0] = strtolower($line[0]);

            // handle line commands (we allow various aliases here)
            switch($line[0]){
                case 'field':
                case 'select':
                case 'col':
                        list($key)     = $this->dthlp->_column($line[1]);
                        $data['field'] = $key;
                    break;
                case 'limit':
                case 'max':
                        $data['limit'] = abs((int) $line[1]);
                    break;
                case 'min':
                        $data['min'] = abs((int) $line[1]);
                    break;
                case 'page':
                case 'target':
                        $data['page'] = cleanID($line[1]);
                    break;
                default:
                    msg("data plugin: unknown option '".hsc($line[0])."'",-1);
            }
        }

        return $data;
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;

        if($format != 'xhtml') return false;
        if(!$this->dthlp->_dbconnect()) return false;
        $renderer->info['cache'] = false;

        if(!$data['page']) $data['page'] = $ID;

        // build query
        $sql = "SELECT value, COUNT(pid) as cnt
                  FROM data
                 WHERE key = '".sqlite_escape_string($data['field'])."'
              GROUP BY value";
        if($data['min'])   $sql .= ' HAVING cnt >= '.$data['min'];
        $sql .= ' ORDER BY cnt DESC';
        if($data['limit']) $sql .= ' LIMIT '.$data['limit'];

        // build cloud data
        $tags = array();
        $res = sqlite_query($this->dthlp->db,$sql);
        $min = 0;
        $max = 0;
        while ($row = sqlite_fetch_array($res, SQLITE_NUM)) {
            if(!$max) $max  = $row[1];
            $min  = $row[1];
            $tags[$row[0]] = $row[1];
        }
        $this->_cloud_weight($tags,$min,$max,5);

        // output cloud
        $renderer->doc .= '<ul class="dataplugin_cloud '.hsc($data['classes']).'">';
        foreach($tags as $tag => $lvl){
            $renderer->doc .= '<li class="cl'.$lvl.'">';
            $renderer->doc .= '<a href="'.wl($data['page'],array('datasrt'=>$_GET['datasrt'],
                                                                 'dataflt'=>$data['field'].':'.$tag )).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($tag)).'" class="wikilink1">'.hsc($tag).'</a>';
            $renderer->doc .= '</li>';
        }
        $renderer->doc .= '</ul>';
        return true;
    }


    /**
     * Create a weighted tag distribution
     *
     * @param $tag arrayref The tags to weight ( tag => count)
     * @param $min int      The lowest count of a single tag
     * @param $max int      The highest count of a single tag
     * @param $levels int   The number of levels you want. A 5 gives levels 0 to 4.
     */
    function _cloud_weight(&$tags,$min,$max,$levels){
        $levels--;

        // calculate tresholds
        $tresholds = array();
        for($i=0; $i<=$levels; $i++){
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $mini - 1;
        }

        // assign weights
        foreach($tags as $tag => $cnt){
            foreach($tresholds as $tresh => $val){
                if($cnt <= $val){
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }

        // sort
        ksort($tags);
    }

}

