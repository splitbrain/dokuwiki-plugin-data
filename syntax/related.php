<?php
/**
 * List related pages based on similar data in the given column(s)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_data_related extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_data_related(){
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
        $this->Lexer->addSpecialPattern('----+ *datarelated(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',$mode,'plugin_data_related');
    }


    /**
     * Handle the match - parse the data
     */
    function handle($match, $state, $pos, &$handler){
        // get lines and additional class
        $lines = explode("\n",$match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = str_replace('datatable','',$class);
        $class = trim($class,'- ');

        $data = array();
        $data['classes'] = $class;
        $data['title']   = $this->getLang('related');

        // parse info
        foreach ( $lines as $line ) {
            // ignore comments
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            $line = preg_split('/\s*:\s*/',$line,2);
            $line[0] = strtolower($line[0]);

            $logic = 'OR';
            // handle line commands (we allow various aliases here)
            switch($line[0]){
                case 'title':
                        $data['title'] = $line[1];
                        break;
                case 'select':
                case 'cols':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim($col);
                            if(!$col) continue;
                            list($key,$type) = $this->dthlp->_column($col);
                            $data['cols'][$key] = $type;
                        }
                    break;
                case 'limit':
                case 'max':
                        $data['limit'] = abs((int) $line[1]);
                    break;
                case 'order':
                case 'sort':
                        list($sort) = $this->dthlp->_column($line[1]);
                        if(substr($sort,0,1) == '^'){
                            $data['sort'] = array(substr($sort,1),'DESC');
                        }else{
                            $data['sort'] = array($sort,'ASC');
                        }
                    break;
                case 'where':
                case 'filter':
                case 'filterand':
                case 'and':
                    $logic = 'AND';
                case 'filteror':
                case 'or':
                        if(preg_match('/^(.*?)(=|<|>|<=|>=|<>|!=|=~|~)(.*)$/',$line[1],$matches)){
                            list($key) = $this->dthlp->_column(trim($matches[1]));
                            $val = trim($matches[3]);
                            $val = sqlite_escape_string($val); //pre escape
                            $com = $matches[2];
                            if($com == '<>'){
                                $com = '!=';
                            }elseif($com == '=~' || $com == '~'){
                                $com = 'LIKE';
                                $val = str_replace('*','%',$val);
                            }

                            $data['filter'][] = array('key'     => $key,
                                                      'value'   => $val,
                                                      'compare' => $com,
                                                      'logic'   => $logic
                                                     );
                        }
                    break;
                default:
                    msg("data plugin: unknown option '".hsc($line[0])."'",-1);
            }
        }

        return $data;
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        global $ID;

        if($format != 'xhtml') return false;
        if(!$this->dthlp->_dbconnect()) return false;

        $sql = $this->_buildSQL($data,$ID);
        if(!$sql) return true; // sql build

        $res = sqlite_query($this->dthlp->db,$sql);
        if(!sqlite_num_rows($res)) return true; // no rows matched

        $renderer->doc .= '<dl class="'.$data['classes'].'">';
        $renderer->doc .= '<dt>'.htmlspecialchars($data['title']).'</dt>';
        $renderer->doc .= '<dd>';
        $renderer->listu_open();
        while ($row = sqlite_fetch_array($res, SQLITE_ASSOC)) {
            $renderer->listitem_open(1);
            $renderer->internallink($row['page']);
            $renderer->listitem_close();
        }
        $renderer->listu_close();
        $renderer->doc .= '</dd>';
        $renderer->doc .= '</dl>';


        return true;
    }

    /**
     * Builds the SQL query from the given data
     */
    function _buildSQL(&$data,$id){
        $cnt    = 1;
        $tables = array();
        $cond   = array();
        $from   = '';
        $where  = '';
        $order  = '';


        // prepare the columns to match against
        $found = false;
        foreach (array_keys($data['cols']) as $col){
            // get values for current page:
            $values = array();
            $sql = "SELECT A.value
                      FROM data A, pages B
                     WHERE key = '".sqlite_escape_string($col)."'
                       AND A.pid = B.pid
                       AND B.page = '".sqlite_escape_string($id)."'";
            $res = sqlite_query($this->dthlp->db,$sql);
            while ($row = sqlite_fetch_array($res, SQLITE_NUM)) {
                if($row[0]) $values[] = $row[0];
            }
            if(!count($values)) continue; // no values? ignore the column.
            $found = true;
            $values = array_map('sqlite_escape_string',$values);

            $cond[] = " ( T1.key = '".sqlite_escape_string($col)."'".
                      " AND T1.value IN ('".join("','",$values)."') )\n";
        }
        $where .= ' AND ('.join(' OR ',$cond).') ';

        // any tags to compare?
        if(!$found) return false;

        // prepare sorting
        if($data['sort'][0]){
            $col = $data['sort'][0];

            if($col == '%pageid%'){
                $order = ', pages.page '.$data['sort'][1];
            }elseif($col == '%title%'){
                $order = ', pages.title '.$data['sort'][1];
            }else{
                // sort by hidden column?
                if(!$tables[$col]){
                    $tables[$col] = 'T'.(++$cnt);
                    $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = pages.pid';
                    $from  .= ' AND '.$tables[$col].".key = '".sqlite_escape_string($col)."'";
                }

                $order = ', '.$tables[$col].'.value '.$data['sort'][1];
            }
        }else{
            $order = ', pages.page';
        }

        // add filters
        if(is_array($data['filter']) && count($data['filter'])){
            $where .= ' AND ( 1=1 ';

            foreach($data['filter'] as $filter){
                $col = $filter['key'];

                if($col == '%pageid%'){
                    $where .= " ".$filter['logic']." pages.page ".$filter['compare']." '".$filter['value']."'";
                }elseif($col == '%title%'){
                    $where .= " ".$filter['logic']." pages.title ".$filter['compare']." '".$filter['value']."'";
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

            $where .= ' ) ';
        }

        // build the query
        $sql = "SELECT pages.pid, pages.page as page, pages.title as title, COUNT(*) as rel
                  FROM pages, data as T1 $from
                 WHERE pages.pid = T1.pid
                   AND pages.page != '".sqlite_escape_string($id)."'
                       $where
              GROUP BY pages.pid
              ORDER BY rel DESC$order";

        // limit
        if($data['limit']){
            $sql .= ' LIMIT '.($data['limit']);
        }

        return $sql;
    }

}

