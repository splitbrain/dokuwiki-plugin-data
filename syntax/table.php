<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_data_table extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     */
    var $dthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_data_table(){
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
        $this->Lexer->addSpecialPattern('----+ *datatable(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',$mode,'plugin_data_table');
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
                case 'select':
                case 'cols':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim($col);
                            if(!$col) continue;
                            list($key,$type) = $this->dthlp->_column($col);
                            $data['cols'][$key] = $type;

                            // fix type for special type
                            if($key == '%pageid%') $data['cols'][$key] = 'page';
                            if($key == '%title%') $data['cols'][$key] = 'title';
                        }
                    break;
                case 'title':
                case 'titles':
                case 'head':
                case 'header':
                case 'headers':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim($col);
                            $data['headers'][] = $col;
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

        // if no header titles were given, use column names
        if(!is_array($data['headers'])){
            foreach(array_keys($data['cols']) as $col){
                if($col == '%pageid%'){
                    $data['headers'][] = 'pagename'; #FIXME add lang string
                }elseif($col == '%title%'){
                    $data['headers'][] = 'page'; #FIXME add lang string
                }else{
                    $data['headers'][] = $col;
                }
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

        #dbg($data);
        $sql = $this->_buildSQL($data); // handles GET params, too
        #dbg($sql);

        // register our custom aggregate function
        sqlite_create_aggregate($this->dthlp->db,'group_concat',
                                array($this,'_sqlite_group_concat_step'),
                                array($this,'_sqlite_group_concat_finalize'), 2);


        // run query
        $types = array_values($data['cols']);
        $res = sqlite_query($this->dthlp->db,$sql);

        // build table
        $renderer->doc .= '<table class="inline dataplugin_table '.$data['classes'].'">';

        // build column headers
        $renderer->doc .= '<tr>';
        $cols = array_keys($data['cols']);
        foreach($data['headers'] as $num => $head){
            $col = $cols[$num];

            $renderer->doc .= '<th>';
            if($col == $data['sort'][0]){
                if($data['sort'][1] == 'ASC'){
                    $renderer->doc .= '<span>&darr;</span> ';
                    $col = '^'.$col;
                }else{
                    $renderer->doc .= '<span>&uarr;</span> ';
                }
            }
            $renderer->doc .= '<a href="'.wl($ID,array('datasrt'=>$col, 'dataofs'=>$_GET['dataofs'], 'dataflt'=>$_GET['dataflt'])).
                              '" title="'.$this->getLang('sort').'">'.hsc($head).'</a>';

            $renderer->doc .= '</th>';
        }
        $renderer->doc .= '</tr>';

        // build data rows
        $cnt = 0;
        while ($row = sqlite_fetch_array($res, SQLITE_NUM)) {
            $renderer->doc .= '<tr>';
            foreach($row as $num => $col){
                $renderer->doc .= '<td>'.$this->dthlp->_formatData($cols[$num],$col,$types[$num],$renderer).'</td>';
            }
            $renderer->doc .= '</tr>';
            $cnt++;
            if($data['limit'] && ($cnt == $data['limit'])) break; // keep an eye on the limit
        }

        // if limit was set, add control
        if($data['limit']){
            $renderer->doc .= '<tr><th colspan="'.count($data['cols']).'">';
            $offset = (int) $_GET['dataofs'];
            if($offset){
                $prev = $offset - $data['limit'];
                if($prev < 0) $prev = 0;

                $renderer->doc .= '<a href="'.wl($ID,array('datasrt'=>$_GET['datasrt'], 'dataofs'=>$prev, 'dataflt'=>$_GET['dataflt'] )).
                              '" title="'.$this->getLang('prev').'" class="prev">'.$this->getLang('prev').'</a>';
            }

            $renderer->doc .= '&nbsp;';

            if(sqlite_num_rows($res) > $data['limit']){
                $next = $offset + $data['limit'];
                $renderer->doc .= '<a href="'.wl($ID,array('datasrt'=>$_GET['datasrt'], 'dataofs'=>$next, 'dataflt'=>$_GET['dataflt'] )).
                              '" title="'.$this->getLang('next').'" class="next">'.$this->getLang('next').'</a>';
            }
            $renderer->doc .= '</th></tr>';
        }

        $renderer->doc .= '</table>';

        return true;
    }

    /**
     * Builds the SQL query from the given data
     */
    function _buildSQL(&$data){
        $cnt    = 0;
        $tables = array();
        $select = array();
        $from   = '';
        $where  = '';
        $order  = '';


        // take overrides from HTTP GET params into account
        if($_GET['datasrt']){
            if($_GET['datasrt']{0} == '^'){
                $data['sort'] = array(substr($_GET['datasrt'],1),'DESC');
            }else{
                $data['sort'] = array($_GET['datasrt'],'ASC');
            }
        }


        // prepare the columns to show
        foreach (array_keys($data['cols']) as $col){
            if($col == '%pageid%'){
                $select[] = 'pages.page';
            }elseif($col == '%title%'){
                $select[] = "pages.page || '|' || pages.title";
            }else{
                if(!$tables[$col]){
                    $tables[$col] = 'T'.(++$cnt);
                    $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = pages.pid';
                    $from  .= ' AND '.$tables[$col].".key = '".sqlite_escape_string($col)."'";
                }
                $select[] = 'group_concat('.$tables[$col].".value,'\n')";
            }
        }

        // prepare sorting
        if($data['sort'][0]){
            $col = $data['sort'][0];

            if($col == '%pageid%'){
                $order = 'ORDER BY pages.page '.$data['sort'][1];
            }elseif($col == '%title%'){
                $order = 'ORDER BY pages.title '.$data['sort'][1];
            }else{
                // sort by hidden column?
                if(!$tables[$col]){
                    $tables[$col] = 'T'.(++$cnt);
                    $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = pages.pid';
                    $from  .= ' AND '.$tables[$col].".key = '".sqlite_escape_string($col)."'";
                }

                $order = 'ORDER BY '.$tables[$col].'.value '.$data['sort'][1];
            }
        }else{
            $order = 'ORDER BY 1 ASC';
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

        // add GET filter
        if($_GET['dataflt']){
            list($col,$val) = split(':',$_GET['dataflt'],2);
            if(!$tables[$col]){
                $tables[$col] = 'T'.(++$cnt);
                $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = pages.pid';
                $from  .= ' AND '.$tables[$col].".key = '".sqlite_escape_string($col)."'";
            }

            $where .= ' AND '.$tables[$col].".value = '".sqlite_escape_string($val)."'";
        }

        // were any data tables used?
        if(count($tables)){
            $where = 'pages.pid = T1.pid '.$where;
        }else{
            $where = '1 = 1 '.$where;
        }

        // build the query
        $sql = "SELECT ".join(', ',$select)."
                  FROM pages $from
                 WHERE $where
              GROUP BY pages.page
                $order";

        // offset and limit
        if($data['limit']){
            $sql .= ' LIMIT '.($data['limit'] + 1);

            if((int) $_GET['dataofs']){
                $sql .= ' OFFSET '.((int) $_GET['dataofs']);
            }
        }


        return $sql;
    }

    /**
     * Aggregation function for SQLite
     *
     * @link http://devzone.zend.com/article/863-SQLite-Lean-Mean-DB-Machine
     */
    function _sqlite_group_concat_step(&$context, $string, $separator = ',') {
         $context['sep']    = $separator;
         $context['data'][] = $string;
    }

    /**
     * Aggregation function for SQLite
     *
     * @link http://devzone.zend.com/article/863-SQLite-Lean-Mean-DB-Machine
     */
    function _sqlite_group_concat_finalize(&$context) {
         $context['data'] = array_unique($context['data']);
         return join($context['sep'],$context['data']);
    }
}

