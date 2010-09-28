<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(dirname(__FILE__).'/table.php');

class syntax_plugin_data_cloud extends syntax_plugin_data_table {

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

    function _buildSQL(&$data){
        $ckey = array_keys($data['cols']);
        $ckey = $ckey[0];

        $from   = ' ';
        $where  = ' ';
        $pagesjoin = '';
        $tables = array();

        $fields = array('pageid' => 'page', 'class' => 'class',
                       'title' => 'title');
        // prepare filters (no request filters - we set them ourselves)
        if(is_array($data['filter']) && count($data['filter'])){

            foreach($data['filter'] as $filter){
                $col = $filter['key'];

                if (preg_match('/^%(\w+)%$/', $col, $m) && isset($fields[$m[1]])) {
                    $where .= " ".$filter['logic']." pages." . $fields[$m[1]] .
                              " " . $filter['compare']." '".$filter['value']."'";
                    $pagesjoin = ' LEFT JOIN pages ON pages.pid = data.pid';
                }else{
                    // filter by hidden column?
                    if(!$tables[$col]){
                        $tables[$col] = 'T'.(++$cnt);
                        $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = pages.pid';
                        $from  .= ' AND '.$tables[$col].".key = '".sqlite_escape_string($col)."'";
                    }

                    $where .= ' '.$filter['logic'].' '.$tables[$col].'.value '.$filter['compare'].
                              " '".$filter['value']."'"; //value is already escaped
                }
            }
        }

        // build query
        $sql = "SELECT data.value, COUNT(data.pid) as cnt
                  FROM data $from $pagesjoin
                 WHERE data.key = '".sqlite_escape_string($ckey)."'
                 $where
              GROUP BY data.value";
        if(isset($data['min']))   $sql .= ' HAVING cnt >= '.$data['min'];
        $sql .= ' ORDER BY cnt DESC';
        if($data['limit']) $sql .= ' LIMIT '.$data['limit'];

        return $sql;
    }

    /**
     * Create output or save the data
     */
    function render($format, &$renderer, $data) {
        global $ID;

        if($format != 'xhtml') return false;
        $renderer->info['cache'] = false;
        if(is_null($data)) return;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        $ckey = array_keys($data['cols']);
        $ckey = $ckey[0];

        if(!isset($data['page'])) $data['page'] = $ID;

        // build cloud data
        $tags = array();
        $res = $sqlite->query($data['sql']);
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
            $renderer->doc .= '<a href="'.wl($data['page'],array('datasrt'=>$_REQUEST['datasrt'],
                                                                 'dataflt[]'=>"$ckey=$tag" )).
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
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min - 1;
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

