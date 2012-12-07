<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_data_table extends DokuWiki_Syntax_Plugin {

    /**
     * will hold the data helper plugin
     * @var $dthlp helper_plugin_data
     */
    var $dthlp = null;

    var $sums = array();

    /**
     * Constructor. Load helper plugin
     */
    function syntax_plugin_data_table(){
        $this->dthlp =& plugin_load('helper', 'data');
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
     *
     * This parsing is shared between the multiple different output/control
     * syntaxes
     */
    function handle($match, $state, $pos, &$handler){
        // get lines and additional class
        $lines = explode("\n",$match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = preg_replace('/^----+ *data[a-z]+/','',$class);
        $class = trim($class,'- ');

        $data = array('classes'    => $class,
                      'limit'      => 0,
                      'dynfilters' => false,
                      'summarize'  => false,
                      'rownumbers' => (bool)$this->getConf('rownumbers'),
                      'sepbyheaders' => false,
                      'headers'    => array(),
                      'widths'     => array(),
                      'filter'     => array());

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
                case 'field':
                case 'col':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim($col);
                            if(!$col) continue;
                            $column = $this->dthlp->_column($col);
                            $data['cols'][$column['key']] = $column;
                        }
                    break;
                case 'title':
                        $data['title'] = $line[1];
                    break;
                case 'head':
                case 'header':
                case 'headers':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim($col);
                            if($col[0]=='"' AND substr($col, -1)=='"'){
                                $col=substr($col, 1, -1);
                            }
                            $data['headers'][] = $col;
                        }
                    break;
                case 'align':
                        $cols = explode(',',$line[1]);
                        foreach($cols as $col){
                            $col = trim(strtolower($col));
                            if($col[0] == 'c'){
                                $col = 'center';
                            }elseif($col[0] == 'r'){
                                $col = 'right';
                            }else{
                                $col = 'left';
                            }
                            $data['align'][] = $col;
                        }
                case 'widths':
                    $cols = explode(',',$line[1]);
                    foreach($cols as $col){
                        $col = trim($col);
                        $data['widths'][] = $col;
                    }
                    break;
                case 'min':
                        $data['min']   = abs((int) $line[1]);
                    break;
                case 'limit':
                case 'max':
                        $data['limit'] = abs((int) $line[1]);
                    break;
                case 'order':
                case 'sort':
                        $column = $this->dthlp->_column($line[1]);
                        $sort = $column['key'];
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
                        if(!$logic) $logic = 'OR';
                        $flt = $this->dthlp->_parse_filter($line[1]);
                        if(is_array($flt)){
                            $flt['logic'] = $logic;
                            $data['filter'][] = $flt;
                        }
                    break;
                case 'page':
                case 'target':
                        $data['page'] = cleanID($line[1]);
                    break;
                case 'dynfilters':
                        $data['dynfilters'] = (bool) $line[1];
                    break;
                case 'rownumbers':
                    $data['rownumbers'] = (bool) $line[1];
                    break;
                case 'summarize':
                        $data['summarize'] = (bool) $line[1];
                    break;
                case 'sepbyheaders':
                        $data['sepbyheaders'] = (bool) $line[1];
                    break;
                default:
                    msg("data plugin: unknown option '".hsc($line[0])."'",-1);
            }
        }

        // we need at least one column to display
        if(!is_array($data['cols']) || !count($data['cols'])){
            msg('data plugin: no columns selected',-1);
            return null;
        }

        // fill up headers with field names if necessary
        $data['headers'] = (array) $data['headers'];
        $cnth = count($data['headers']);
        $cntf = count($data['cols']);
        for($i=$cnth; $i<$cntf; $i++){
            $item = array_pop(array_slice($data['cols'],$i,1));
            $data['headers'][] = $item['title'];
        }

        $data['sql'] = $this->_buildSQL($data);

        // Save current request params for comparison in updateSQL
        $data['cur_param']=$this->dthlp->_get_current_param(false);
        return $data;
    }

    protected $before_item = '<tr>';
    protected $after_item  = '</tr>';
    protected $before_val  = '<td %s>';
    protected $after_val   = '</td>';

    /**
     * Create output
     */
    function render($format, &$R, $data) {
        if($format != 'xhtml') return false;
        if(is_null($data)) return false;
        $R->info['cache'] = false;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        if ($this->hasRequestFilter() OR isset($_REQUEST['dataofs'])) {
            $this->updateSQLwithQuery($data); // handles request params
        }
        $this->dthlp->_replacePlaceholdersInSQL($data);

        // run query
        $clist = array_keys($data['cols']);
        $res = $sqlite->query($data['sql']);

        $rows = $sqlite->res2arr($res);
        $cnt = count($rows);

        if ($cnt === 0) {
            $this->nullList($data, $clist, $R);
            return true;
        }

        if ($data['limit'] && $cnt > $data['limit']) {
            $rows = array_slice($rows, 0, $data['limit']);
        }

        $R->doc .= $this->preList($clist, $data);
        foreach ($rows as $rownum => $row) {
            // build data rows
            $R->doc .= $this->before_item;
            if($data['rownumbers']){
                $R->doc .= sprintf($this->before_val,'class="'.$data['align'][0].'align"');
                $R->doc .= $rownum+1;
                $R->doc .= $this->after_val;
            }
            
            foreach(array_values($row) as $num => $cval){
                $num_rn = ($data['rownumbers'] ? $num+1 : $num);

                $R->doc .= sprintf($this->beforeVal($data,$num_rn),'class="'.$data['align'][$num_rn].'align"');
                $R->doc .= $this->dthlp->_formatData(
                                $data['cols'][$clist[$num]],
                                $cval,$R);
                $R->doc .= $this->afterVal($data,$num_rn);

                // clean currency symbols
                $nval = str_replace('$€₤','',$cval);
                $nval = str_replace('/ [A-Z]{0,3}$/','',$nval);
                $nval = str_replace(',','.',$nval);
                $nval = trim($nval);

                // summarize
                if($data['summarize'] && is_numeric($nval)){
                    if(!isset($this->sums[$num])) $this->sums[$num] = 0;
                    $this->sums[$num] += $nval;
                }

            }
            $R->doc .= $this->after_item;
        }
        $R->doc .= $this->postList($data, $cnt);

        return true;
    }

    protected function beforeVal(&$data, $colno) {
        return $this->before_val;
    }
    protected function afterVal(&$data, $colno) {
        return $this->after_val;
    }

    function preList($clist, $data) {
        global $ID;
        global $conf;

        // Save current request params to not loose them
        $cur_params = $this->dthlp->_get_current_param();

        //show active filters
        $text = '<div class="table dataaggregation">';
        if(isset($_REQUEST['dataflt'])){
            $filters=$this->dthlp->_get_filters();
            $fltrs = array();
            foreach($filters as $filter){
                if(strpos($filter['compare'],'LIKE')!==false){
                    if(strpos($filter['compare'],'NOT')!==false){
                        $comparator_value = '!~'.str_replace('%','*',$filter['value']);
                    }else{
                        $comparator_value = '*~'.str_replace('%','',$filter['value']);
                    }
                    $fltrs[]=$filter['key'].$comparator_value;
                }else{
                    $fltrs[]=$filter['key'].$filter['compare'].$filter['value'];
                }
            }

            $text .= '<div class="filter">';
            $text .=    '<h4>'.sprintf($this->getLang('tablefilteredby'),hsc(implode(' & ', $fltrs))).'</h4>';
            $text .=    '<div class="resetfilter">'.
                            '<a href="'.wl($ID).'">'.$this->getLang('tableresetfilter').'</a>'.
                        '</div>';
            $text .= '</div>';
        }
        // build table
        $text .= '<table class="inline dataplugin_table '.$data['classes'].'">';
        // build column headers
        $text .= '<tr>';

        if($data['rownumbers']) $text .= '<th>#</th>';

        foreach($data['headers'] as $num => $head){
            $ckey = $clist[$num];

            $width = '';
            if(isset($data['widths'][$num]) AND $data['widths'][$num] != '-') {
                $width = ' style="width: '.$data['widths'][$num].';"';
            }
            $text .= '<th'.$width.'>';

            // add sort arrow
            if(isset($data['sort']) && $ckey == $data['sort'][0]){
                if($data['sort'][1] == 'ASC'){
                    $text .= '<span>&darr;</span> ';
                    $ckey = '^'.$ckey;
                }else{
                    $text .= '<span>&uarr;</span> ';
                }
            }

            // Clickable header for dynamic sorting
            $text .= '<a href="'.wl($ID,array('datasrt' => $ckey)+$cur_params).
                       '" title="'.$this->getLang('sort').'">'.hsc($head).'</a>';
            $text .= '</th>';
        }
        $text .= '</tr>';

        // Dynamic filters
        if ($data['dynfilters']) {
            $text .= '<tr class="dataflt">';

            if($data['rownumbers']) $text .= '<th></th>';

            foreach($data['headers'] as $num => $head){
                $text .= '<th>';
                $form = new Doku_Form(array('method' => 'GET'));
                $form->_hidden = array();
                if(!$conf['userewrite']) $form->addHidden('id',$ID);

                $key = 'dataflt[' . $data['cols'][$clist[$num]]['colname'] . '*~' . ']';
                $val = isset($cur_params[$key]) ? $cur_params[$key] : '';

                // Add current request params
                foreach($cur_params as $c_key => $c_val) {
                    if ($c_val !== '' && $c_key !== $key) {
                        $form->addHidden($c_key, $c_val);
                    }
                }

                $form->addElement(form_makeField('text', $key, $val, ''));
                $text .= $form->getForm();
                $text .= '</th>';
            }
            $text .= '</tr>';
        }

        return $text;
    }

    function nullList($data, $clist, &$R) {
        $R->doc .= $this->preList($clist, $data);
        $R->tablerow_open();
        $R->tablecell_open(count($clist), 'center');
        $R->cdata($this->getLang('none'));
        $R->tablecell_close();
        $R->tablerow_close();
        $R->doc .= '</table></div>';
    }

    function postList($data, $rowcnt) {
        global $ID;
        $text = '';
        // if summarize was set, add sums
        if($data['summarize']){
            $text .= '<tr>';
            $len = count($data['cols']);

            if($data['rownumbers']) $text .= '<td></td>';

            for($i=0; $i<$len; $i++){
                $text .= '<td class="'.$data['align'][$i].'align">';
                if(!empty($this->sums[$i])){
                    $text .= '∑ '.$this->sums[$i];
                }else{
                    $text .= '&nbsp;';
                }
                $text .= '</td>';
            }
            $text .= '<tr>';
        }

        // if limit was set, add control
        if($data['limit']){
            $text .= '<tr><th colspan="'.(count($data['cols'])+($data['rownumbers'] ? 1 : 0)).'">';
            $offset = (int) $_REQUEST['dataofs'];
            if($offset){
                $prev = $offset - $data['limit'];
                if($prev < 0) $prev = 0;

                // keep url params
                $params = $this->dthlp->_a2ua('dataflt',$_REQUEST['dataflt']);
                $params['datasrt'] = $_REQUEST['datasrt'];
                $params['dataofs'] = $prev;

                $text .= '<a href="'.wl($ID,$params).
                              '" title="'.$this->getLang('prev').
                              '" class="prev">'.$this->getLang('prev').'</a>';
            }

            $text .= '&nbsp;';

            if($rowcnt > $data['limit']){
                $next = $offset + $data['limit'];

                // keep url params
                $params = $this->dthlp->_a2ua('dataflt',$_REQUEST['dataflt']);
                $params['datasrt'] = $_REQUEST['datasrt'];
                $params['dataofs'] = $next;

                $text .= '<a href="'.wl($ID,$params).
                              '" title="'.$this->getLang('next').
                              '" class="next">'.$this->getLang('next').'</a>';
            }
            $text .= '</th></tr>';
        }

        $text .= '</table></div>';
        return $text;
    }

    /**
     * Builds the SQL query from the given data
     */
    function _buildSQL(&$data){
        $cnt    = 0;
        $tables = array();
        $select = array();
        $from   = '';
        $order  = '';

        $from2   = '';
        $where2  = '1 = 1';

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        // prepare the columns to show
        foreach ($data['cols'] as &$col){
            $key = $col['key'];
            if($key == '%pageid%'){
                // Prevent stripping of trailing zeros by forcing a CAST
                $select[] = '" " || pages.page';
            }elseif($key == '%class%'){
                // Prevent stripping of trailing zeros by forcing a CAST
                $select[] = '" " || pages.class';
            }elseif($key == '%lastmod%'){
                $select[] = 'pages.lastmod';
            }elseif($key == '%title%'){
                $select[] = "pages.page || '|' || pages.title";
            }else{
                if(!isset($tables[$key])){
                    $tables[$key] = 'T'.(++$cnt);
                    $from  .= ' LEFT JOIN data AS '.$tables[$key].' ON '.$tables[$key].'.pid = W1.pid';
                    $from  .= ' AND '.$tables[$key].".key = ".$sqlite->quote_string($key);
                }
                $type = $col['type'];
                if (is_array($type)) $type = $type['type'];
                switch ($type) {
                case 'pageid':
                    $select[] = "pages.page || '|' || group_concat(".$tables[$key].".value,'\n')";
                    break;
                case 'wiki':
                    $select[] = "pages.page || '|' || group_concat(".$tables[$key].".value,'\n')";
                    break;
                default:
                    // Prevent stripping of trailing zeros by forcing a CAST
                    $select[] = 'group_concat(" " || '.$tables[$key].".value,'\n')";
                }
            }
        }
        unset($col);

        // prepare sorting
        if(isset($data['sort'])){
            $col = $data['sort'][0];

            if($col == '%pageid%'){
                $order = 'ORDER BY pages.page '.$data['sort'][1];
            }elseif($col == '%class%'){
                $order = 'ORDER BY pages.class '.$data['sort'][1];
            }elseif($col == '%title%'){
                $order = 'ORDER BY pages.title '.$data['sort'][1];
            }elseif($col == '%lastmod%'){
                $order = 'ORDER BY pages.lastmod '.$data['sort'][1];
            }else{
                // sort by hidden column?
                if(!$tables[$col]){
                    $tables[$col] = 'T'.(++$cnt);
                    $from  .= ' LEFT JOIN data AS '.$tables[$col].' ON '.$tables[$col].'.pid = W1.pid';
                    $from  .= ' AND '.$tables[$col].".key = " . $sqlite->quote_string($col);
                }

                $order = 'ORDER BY '.$tables[$col].'.value '.$data['sort'][1];
            }
        }else{
            $order = 'ORDER BY 1 ASC';
        }

        // may be disabled from config. as it decreases performance a lot
        $use_dataresolve = $this->getConf('use_dataresolve');

        // prepare filters
        $cnt = 0;
        if(is_array($data['filter']) && count($data['filter'])){

            foreach($data['filter'] as $filter){
                $col = $filter['key'];

                if($col == '%pageid%'){
                    $where2 .= " ".$filter['logic']." pages.page ".$filter['compare']." '".$filter['value']."'";
                }elseif($col == '%class%'){
                    $where2 .= " ".$filter['logic']." pages.class ".$filter['compare']." '".$filter['value']."'";
                }elseif($col == '%title%'){
                    $where2 .= " ".$filter['logic']." pages.title ".$filter['compare']." '".$filter['value']."'";
                }elseif($col == '%lastmod%'){
                    # parse value to int?
                    $filter['value'] = (int) strtotime($filter['value']);
                    $where2 .= " ".$filter['logic']." pages.lastmod ".$filter['compare']." ".$filter['value'];
                }else{
                    // filter by hidden column?
                    $table= 'T'.(++$cnt);
                    $from2  .= ' LEFT JOIN data AS '.$table.' ON '.$table.'.pid = pages.pid';
                    $from2  .= ' AND '.$table.".key = " . $sqlite->quote_string($col);

                    // apply data resolving?
                    if($use_dataresolve && $filter['colname'] && (substr($filter['compare'],-4) == 'LIKE')){
                        $where2 .= ' '.$filter['logic'].' DATARESOLVE('.$table.'.value,\''.$sqlite->escape_string($filter['colname']).'\') '.$filter['compare'].
                                  " '".$filter['value']."'"; //value is already escaped
                    } else {
                        $where2 .= ' '.$filter['logic'].' '.$table.'.value '.$filter['compare'].
                                  " '".$filter['value']."'"; //value is already escaped
                    }
                }
            }
        }

        // build the query
        $sql = "SELECT ".join(', ',$select)."
                FROM (
                    SELECT DISTINCT pages.pid 
                    FROM pages $from2
                    WHERE $where2
                ) AS W1 
                $from
                LEFT JOIN pages ON W1.pid=pages.pid
                GROUP BY W1.pid
                $order";

        // offset and limit
        if($data['limit']){
            $sql .= ' LIMIT '.($data['limit'] + 1);
            // offset is added from REQUEST params in updateSQLwithQuery
        }

        return $sql;
    }

    /**
     * Handle request paramaters, rebuild sql when needed
     * @param array $data
     */
    function updateSQLwithQuery(&$data) {
        if($this->hasRequestFilter()){
            if (isset($_REQUEST['datasrt'])) {
                if($_REQUEST['datasrt']{0} == '^'){
                    $data['sort'] = array(substr($_REQUEST['datasrt'],1),'DESC');
                }else{
                    $data['sort'] = array($_REQUEST['datasrt'],'ASC');
                }
            }

            // add request filters
            $data['filter'] = array_merge($data['filter'], $this->dthlp->_get_filters());

            // Rebuild SQL FIXME do this smarter & faster
            $data['sql'] = $this->_buildSQL($data);
        }

        if($data['limit'] && (int) $_REQUEST['dataofs']){
            $data['sql'] .= ' OFFSET '.((int) $_REQUEST['dataofs']);
        }
    }

    function hasRequestFilter() {
        return isset($_REQUEST['datasrt']) || isset($_REQUEST['dataflt']);
    }
}

