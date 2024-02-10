<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
/**
 * Class syntax_plugin_data_table
 */
class syntax_plugin_data_table extends SyntaxPlugin
{
    /**
     * will hold the data helper plugin
     *
     * @var $dthlp helper_plugin_data
     */
    public $dthlp;

    public $sums = [];

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->dthlp = plugin_load('helper', 'data');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 155;
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *datatable(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',
            $mode,
            'plugin_data_table'
        );
    }

    /**
     * Handle the match - parse the data
     *
     * This parsing is shared between the multiple different output/control
     * syntaxes
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        if (!$this->dthlp->ready()) return null;

        // get lines and additional class
        $lines = explode("\n", $match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = preg_replace('/^----+ *data[a-z]+/', '', $class);
        $class = trim($class, '- ');

        $data = [
            'classes' => $class,
            'limit' => 0,
            'dynfilters' => false,
            'summarize' => false,
            'rownumbers' => (bool)$this->getConf('rownumbers'),
            'sepbyheaders' => false,
            'headers' => [],
            'widths' => [],
            'filter' => []
        ];

        // parse info
        foreach ($lines as $line) {
            // ignore comments
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) continue;
            $line = preg_split('/\s*:\s*/', $line, 2);
            $line[0] = strtolower($line[0]);

            $logic = 'OR';
            // handle line commands (we allow various aliases here)
            switch ($line[0]) {
                case 'select':
                case 'cols':
                case 'field':
                case 'col':
                    $cols = explode(',', $line[1]);
                    foreach ($cols as $col) {
                        $col = trim($col);
                        if (!$col) continue;
                        $column = $this->dthlp->column($col);
                        $data['cols'][$column['key']] = $column;
                    }
                    break;
                case 'title':
                    $data['title'] = $line[1];
                    break;
                case 'head':
                case 'header':
                case 'headers':
                    $cols = $this->parseValues($line[1]);
                    $data['headers'] = array_merge($data['headers'], $cols);
                    break;
                case 'align':
                    $cols = explode(',', $line[1]);
                    foreach ($cols as $col) {
                        $col = trim(strtolower($col));
                        if ($col[0] == 'c') {
                            $col = 'center';
                        } elseif ($col[0] == 'r') {
                            $col = 'right';
                        } else {
                            $col = 'left';
                        }
                        $data['align'][] = $col;
                    }
                    break;
                case 'widths':
                    $cols = explode(',', $line[1]);
                    foreach ($cols as $col) {
                        $col = trim($col);
                        $data['widths'][] = $col;
                    }
                    break;
                case 'min':
                    $data['min'] = abs((int)$line[1]);
                    break;
                case 'limit':
                case 'max':
                    $data['limit'] = abs((int)$line[1]);
                    break;
                case 'order':
                case 'sort':
                    $column = $this->dthlp->column($line[1]);
                    $sort = $column['key'];
                    if (substr($sort, 0, 1) == '^') {
                        $data['sort'] = [substr($sort, 1), 'DESC'];
                    } else {
                        $data['sort'] = [$sort, 'ASC'];
                    }
                    break;
                case 'where':
                case 'filter':
                case 'filterand':
                case 'and': // phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                    $logic = 'AND';
                case 'filteror':
                case 'or':
                    if (!$logic) {
                        $logic = 'OR';
                    }
                    $flt = $this->dthlp->parseFilter($line[1]);
                    if (is_array($flt)) {
                        $flt['logic'] = $logic;
                        $data['filter'][] = $flt;
                    }
                    break;
                case 'page':
                case 'target':
                    $data['page'] = cleanID($line[1]);
                    break;
                case 'dynfilters':
                    $data['dynfilters'] = (bool)$line[1];
                    break;
                case 'rownumbers':
                    $data['rownumbers'] = (bool)$line[1];
                    break;
                case 'summarize':
                    $data['summarize'] = (bool)$line[1];
                    break;
                case 'sepbyheaders':
                    $data['sepbyheaders'] = (bool)$line[1];
                    break;
                default:
                    msg("data plugin: unknown option '" . hsc($line[0]) . "'", -1);
            }
        }

        // we need at least one column to display
        if (!is_array($data['cols']) || $data['cols'] === []) {
            msg('data plugin: no columns selected', -1);
            return null;
        }

        // fill up headers with field names if necessary
        $data['headers'] = (array)$data['headers'];
        $cnth = count($data['headers']);
        $cntf = count($data['cols']);
        for ($i = $cnth; $i < $cntf; $i++) {
            $column = array_slice($data['cols'], $i, 1);
            $columnprops = array_pop($column);
            $data['headers'][] = $columnprops['title'];
        }

        $data['sql'] = $this->buildSQL($data);

        // Save current request params for comparison in updateSQL
        $data['cur_param'] = $this->dthlp->getPurrentParam(false);
        return $data;
    }

    protected $before_item = '<tr>';
    protected $after_item = '</tr>';
    protected $before_val = '<td %s>';
    protected $after_val = '</td>';

    /**
     * Handles the actual output creation.
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $data data created by handler()
     * @return  boolean               rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format != 'xhtml') return false;
        if (is_null($data)) return false;
        if (!$this->dthlp->ready()) return false;
        $sqlite = $this->dthlp->getDB();
        if (!$sqlite) return false;

        $renderer->info['cache'] = false;

        //reset counters
        $this->sums = [];

        if ($this->hasRequestFilter() || isset($_REQUEST['dataofs'])) {
            $this->updateSQLwithQuery($data); // handles request params
        }
        $this->dthlp->replacePlaceholdersInSQL($data);

        // run query
        $clist = array_keys($data['cols']);
        $rows = $sqlite->queryAll($data['sql']);
        $cnt = count($rows);

        if ($cnt === 0) {
            $this->nullList($data, $clist, $renderer);
            return true;
        }

        if ($data['limit'] && $cnt > $data['limit']) {
            $rows = array_slice($rows, 0, $data['limit']);
        }

        //build classnames per column
        $classes = [];
        $class_names_cache = [];
        $offset = 0;
        if ($data['rownumbers']) {
            $offset = 1; //rownumbers are in first column
            $classes[] = $data['align'][0] . 'align rownumbers';
        }
        foreach ($clist as $index => $col) {
            $class = '';
            if (isset($data['align'])) {
                $class .= $data['align'][$index + $offset];
            }
            $class .= 'align ' . hsc(sectionID($col, $class_names_cache));
            $classes[] = $class;
        }

        //start table/list
        $renderer->doc .= $this->preList($clist, $data);

        foreach ($rows as $rownum => $row) {
            // build data rows
            $renderer->doc .= $this->before_item;

            if ($data['rownumbers']) {
                $renderer->doc .= sprintf($this->before_val, 'class="' . $classes[0] . '"');
                $renderer->doc .= $rownum + 1;
                $renderer->doc .= $this->after_val;
            }

            foreach (array_values($row) as $num => $cval) {
                $num_rn = $num + $offset;

                $renderer->doc .= sprintf($this->beforeVal($data, $num_rn), 'class="' . $classes[$num_rn] . '"');
                $renderer->doc .= $this->dthlp->formatData(
                    $data['cols'][$clist[$num]],
                    $cval,
                    $renderer
                );
                $renderer->doc .= $this->afterVal($data, $num_rn);

                // clean currency symbols
                $nval = str_replace('$€₤', '', $cval);
                $nval = str_replace('/ [A-Z]{0,3}$/', '', $nval);
                $nval = str_replace(',', '.', $nval);
                $nval = trim($nval);

                // summarize
                if ($data['summarize'] && is_numeric($nval)) {
                    if (!isset($this->sums[$num])) {
                        $this->sums[$num] = 0;
                    }
                    $this->sums[$num] += $nval;
                }
            }
            $renderer->doc .= $this->after_item;
        }
        $renderer->doc .= $this->postList($data, $cnt);

        return true;
    }

    /**
     * Before value in table cell
     *
     * @param array $data instructions by handler
     * @param int $colno column number
     * @return string
     */
    protected function beforeVal(&$data, $colno)
    {
        return $this->before_val;
    }

    /**
     * After value in table cell
     *
     * @param array $data
     * @param int $colno
     * @return string
     */
    protected function afterVal(&$data, $colno)
    {
        return $this->after_val;
    }

    /**
     * Create table header
     *
     * @param array $clist keys of the columns
     * @param array $data instruction by handler
     * @return string html of table header
     */
    public function preList($clist, $data)
    {
        global $ID;
        global $conf;

        // Save current request params to not loose them
        $cur_params = $this->dthlp->getPurrentParam();

        //show active filters
        $text = '<div class="table dataaggregation">';
        if (isset($_REQUEST['dataflt'])) {
            $filters = $this->dthlp->getFilters();
            $fltrs = [];
            foreach ($filters as $filter) {
                if (strpos($filter['compare'], 'LIKE') !== false) {
                    if (strpos($filter['compare'], 'NOT') !== false) {
                        $comparator_value = '!~' . str_replace('%', '*', $filter['value']);
                    } else {
                        $comparator_value = '*~' . str_replace('%', '', $filter['value']);
                    }
                    $fltrs[] = $filter['key'] . $comparator_value;
                } else {
                    $fltrs[] = $filter['key'] . $filter['compare'] . $filter['value'];
                }
            }

            $text .= '<div class="filter">';
            $text .= '<h4>' . sprintf($this->getLang('tablefilteredby'), hsc(implode(' & ', $fltrs))) . '</h4>';
            $text .= '<div class="resetfilter">' .
                '<a href="' . wl($ID) . '">' . $this->getLang('tableresetfilter') . '</a>' .
                '</div>';
            $text .= '</div>';
        }
        
        // fixes for bootstrap tpl 
        if( isset($conf['tpl']['bootstrap3']['tableStyle'])) {
            $ts = explode(',', $conf['tpl']['bootstrap3']['tableStyle']);
            foreach ($ts as $class) {
                if ($class == 'responsive') {
                    $text = str_replace('<div class="table', '<div class="table table-responsive', $text);
                } else {
                    $data['classes'] .= " table-$class";
                }
            }
        }        
        
        // build table
        $text .= '<table class="inline dataplugin_table ' . $data['classes'] . '">';
        // build column headers
        $text .= '<tr>';

        if ($data['rownumbers']) {
            $text .= '<th>#</th>';
        }

        foreach ($data['headers'] as $num => $head) {
            $ckey = $clist[$num];

            $width = '';
            if (isset($data['widths'][$num]) && $data['widths'][$num] != '-') {
                $width = ' style="width: ' . $data['widths'][$num] . ';"';
            }
            $text .= '<th' . $width . '>';

            // add sort arrow
            if (isset($data['sort']) && $ckey == $data['sort'][0]) {
                if ($data['sort'][1] == 'ASC') {
                    $text .= '<span>&darr;</span> ';
                    $ckey = '^' . $ckey;
                } else {
                    $text .= '<span>&uarr;</span> ';
                }
            }

            // Clickable header for dynamic sorting
            $text .= '<a href="' . wl($ID, ['datasrt' => $ckey] + $cur_params) .
                '" title="' . $this->getLang('sort') . '">' . hsc($head) . '</a>';
            $text .= '</th>';
        }
        $text .= '</tr>';

        // Dynamic filters
        if ($data['dynfilters']) {
            $text .= '<tr class="dataflt">';

            if ($data['rownumbers']) {
                $text .= '<th></th>';
            }

            foreach ($data['headers'] as $num => $head) {
                $text .= '<th>';
                $form = new Doku_Form(['method' => 'GET']);
                $form->_hidden = [];
                if (!$conf['userewrite']) {
                    $form->addHidden('id', $ID);
                }

                $key = 'dataflt[' . $data['cols'][$clist[$num]]['colname'] . '*~' . ']';
                $val = $cur_params[$key] ?? '';

                // Add current request params
                foreach ($cur_params as $c_key => $c_val) {
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

    /**
     * Create an empty table
     *
     * @param array $data instruction by handler()
     * @param array $clist keys of the columns
     * @param Doku_Renderer $R
     */
    public function nullList($data, $clist, $R)
    {
        $R->doc .= $this->preList($clist, $data);
        $R->tablerow_open();
        $R->tablecell_open(count($clist), 'center');
        $R->cdata($this->getLang('none'));
        $R->tablecell_close();
        $R->tablerow_close();
        $R->doc .= '</table></div>';
    }

    /**
     * Create table footer
     *
     * @param array $data instruction by handler()
     * @param int $rowcnt number of rows
     * @return string html of table footer
     */
    public function postList($data, $rowcnt)
    {
        global $ID;
        $text = '';
        // if summarize was set, add sums
        if ($data['summarize']) {
            $text .= '<tr>';
            $len = count($data['cols']);

            if ($data['rownumbers']) $text .= '<td></td>';

            for ($i = 0; $i < $len; $i++) {
                $text .= '<td class="' . $data['align'][$i] . 'align">';
                if (!empty($this->sums[$i])) {
                    $text .= '∑ ' . $this->sums[$i];
                } else {
                    $text .= '&nbsp;';
                }
                $text .= '</td>';
            }
            $text .= '<tr>';
        }

        // if limit was set, add control
        if ($data['limit']) {
            $text .= '<tr><th colspan="' . (count($data['cols']) + ($data['rownumbers'] ? 1 : 0)) . '">';
            $offset = (int)$_REQUEST['dataofs'];
            if ($offset) {
                $prev = $offset - $data['limit'];
                if ($prev < 0) {
                    $prev = 0;
                }

                // keep url params
                $params = $this->dthlp->a2ua('dataflt', $_REQUEST['dataflt']);
                if (isset($_REQUEST['datasrt'])) {
                    $params['datasrt'] = $_REQUEST['datasrt'];
                }
                $params['dataofs'] = $prev;

                $text .= '<a href="' . wl($ID, $params) .
                    '" title="' . $this->getLang('prev') .
                    '" class="prev">' . $this->getLang('prev') . '</a>';
            }

            $text .= '&nbsp;';

            if ($rowcnt > $data['limit']) {
                $next = $offset + $data['limit'];

                // keep url params
                $params = $this->dthlp->a2ua('dataflt', $_REQUEST['dataflt']);
                if (isset($_REQUEST['datasrt'])) {
                    $params['datasrt'] = $_REQUEST['datasrt'];
                }
                $params['dataofs'] = $next;

                $text .= '<a href="' . wl($ID, $params) .
                    '" title="' . $this->getLang('next') .
                    '" class="next">' . $this->getLang('next') . '</a>';
            }
            $text .= '</th></tr>';
        }

        $text .= '</table></div>';
        return $text;
    }

    /**
     * Builds the SQL query from the given data
     *
     * @param array &$data instruction by handler
     * @return bool|string SQL query or false
     */
    public function buildSQL(&$data)
    {
        $cnt = 0;
        $tables = [];
        $select = [];
        $from = '';

        $from2 = '';
        $where2 = '1 = 1';

        $sqlite = $this->dthlp->getDB();
        if (!$sqlite) return false;

        // prepare the columns to show
        foreach ($data['cols'] as &$col) {
            $key = $col['key'];
            if ($key == '%pageid%') {
                // Prevent stripping of trailing zeros by forcing a CAST
                $select[] = '" " || pages.page';
            } elseif ($key == '%class%') {
                // Prevent stripping of trailing zeros by forcing a CAST
                $select[] = '" " || pages.class';
            } elseif ($key == '%lastmod%') {
                $select[] = 'pages.lastmod';
            } elseif ($key == '%title%') {
                $select[] = "pages.page || '|' || pages.title";
            } else {
                if (!isset($tables[$key])) {
                    $tables[$key] = 'T' . (++$cnt);
                    $from .= ' LEFT JOIN data AS ' . $tables[$key] . ' ON ' . $tables[$key] . '.pid = W1.pid';
                    $from .= ' AND ' . $tables[$key] . ".key = " . $sqlite->getPdo()->quote($key);
                }
                $type = $col['type'];
                if (is_array($type)) {
                    $type = $type['type'];
                }
                switch ($type) {
                    case 'pageid':
                    case 'wiki':
                        //note in multivalued case: adds pageid only to first value
                        $select[] = "pages.page || '|' || GROUP_CONCAT_DISTINCT(" . $tables[$key] . ".value,'\n')";
                        break;
                    default:
                        // Prevent stripping of trailing zeros by forcing a CAST
                        $select[] = 'GROUP_CONCAT_DISTINCT(" " || ' . $tables[$key] . ".value,'\n')";
                }
            }
        }
        unset($col);

        // prepare sorting
        if (isset($data['sort'])) {
            $col = $data['sort'][0];

            if ($col == '%pageid%') {
                $order = 'ORDER BY pages.page ' . $data['sort'][1];
            } elseif ($col == '%class%') {
                $order = 'ORDER BY pages.class ' . $data['sort'][1];
            } elseif ($col == '%title%') {
                $order = 'ORDER BY pages.title ' . $data['sort'][1];
            } elseif ($col == '%lastmod%') {
                $order = 'ORDER BY pages.lastmod ' . $data['sort'][1];
            } else {
                // sort by hidden column?
                if (!$tables[$col]) {
                    $tables[$col] = 'T' . (++$cnt);
                    $from .= ' LEFT JOIN data AS ' . $tables[$col] . ' ON ' . $tables[$col] . '.pid = W1.pid';
                    $from .= ' AND ' . $tables[$col] . ".key = " . $sqlite->getPdo()->quote($col);
                }

                $order = 'ORDER BY ' . $tables[$col] . '.value ' . $data['sort'][1];
            }
        } else {
            $order = 'ORDER BY 1 ASC';
        }

        // may be disabled from config. as it decreases performance a lot
        $use_dataresolve = $this->getConf('use_dataresolve');

        // prepare filters
        $cnt = 0;
        if (is_array($data['filter']) && count($data['filter'])) {
            foreach ($data['filter'] as $filter) {
                $col = $filter['key'];
                $closecompare = ($filter['compare'] == 'IN(' ? ')' : '');

                if ($col == '%pageid%') {
                    $where2 .= " " . $filter['logic'] . " pages.page " .
                        $filter['compare'] . " " . $filter['value'] . $closecompare;
                } elseif ($col == '%class%') {
                    $where2 .= " " . $filter['logic'] . " pages.class " .
                        $filter['compare'] . " " . $filter['value'] . $closecompare;
                } elseif ($col == '%title%') {
                    $where2 .= " " . $filter['logic'] . " pages.title " .
                        $filter['compare'] . " " . $filter['value'] . $closecompare;
                } elseif ($col == '%lastmod%') {
                    # parse value to int?
                    $filter['value'] = (int)strtotime($filter['value']);
                    $where2 .= " " . $filter['logic'] . " pages.lastmod " .
                        $filter['compare'] . " " . $filter['value'] . $closecompare;
                } else {
                    // filter by hidden column?
                    $table = 'T' . (++$cnt);
                    $from2 .= ' LEFT JOIN data AS ' . $table . ' ON ' . $table . '.pid = pages.pid';
                    $from2 .= ' AND ' . $table . ".key = " . $sqlite->getPdo()->quote($col);

                    // apply data resolving?
                    if ($use_dataresolve && $filter['colname'] && (substr($filter['compare'], -4) == 'LIKE')) {
                        $where2 .= ' ' . $filter['logic'] .
                            ' DATARESOLVE(' . $table . '.value,' . $sqlite->getPdo()->quote($filter['colname']) . ') ' .
                            $filter['compare'] .
                            " " . $filter['value']; //value is already escaped
                    } else {
                        $where2 .= ' ' . $filter['logic'] . ' ' . $table . '.value ' . $filter['compare'] .
                            " " . $filter['value'] . $closecompare; //value is already escaped
                    }
                }
            }
        }

        // build the query
        $sql = "SELECT " . implode(', ', $select) . "
                FROM (
                    SELECT DISTINCT pages.pid AS pid
                    FROM pages $from2
                    WHERE $where2
                ) AS W1
                $from
                LEFT JOIN pages ON W1.pid=pages.pid
                GROUP BY W1.pid
                $order";

        // offset and limit
        if ($data['limit']) {
            $sql .= ' LIMIT ' . ($data['limit'] + 1);
            // offset is added from REQUEST params in updateSQLwithQuery
        }

        return $sql;
    }

    /**
     * Handle request paramaters, rebuild sql when needed
     *
     * @param array $data instruction by handler()
     */
    public function updateSQLwithQuery(&$data)
    {
        if ($this->hasRequestFilter()) {
            if (isset($_REQUEST['datasrt'])) {
                if ($_REQUEST['datasrt'][0] == '^') {
                    $data['sort'] = [substr($_REQUEST['datasrt'], 1), 'DESC'];
                } else {
                    $data['sort'] = [$_REQUEST['datasrt'], 'ASC'];
                }
            }

            // add request filters
            $data['filter'] = array_merge($data['filter'], $this->dthlp->getFilters());

            // Rebuild SQL FIXME do this smarter & faster
            $data['sql'] = $this->buildSQL($data);
        }

        if ($data['limit'] && (int)$_REQUEST['dataofs']) {
            $data['sql'] .= ' OFFSET ' . ((int)$_REQUEST['dataofs']);
        }
    }

    /**
     * Check whether a sort or filter request parameters are available
     *
     * @return bool
     */
    public function hasRequestFilter()
    {
        return isset($_REQUEST['datasrt']) || isset($_REQUEST['dataflt']);
    }

    /**
     * Split values at the commas,
     * - Wrap with quotes to escape comma, quotes escaped by two quotes
     * - Within quotes spaces are stored.
     *
     * @param string $line
     * @return array
     */
    protected function parseValues($line)
    {
        $values = [];
        $inQuote = false;
        $escapedQuote = false;
        $value = '';

        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] == '"') {
                if ($inQuote) {
                    if ($escapedQuote) {
                        $value .= '"';
                        $escapedQuote = false;
                        continue;
                    }
                    if ($line[$i + 1] == '"') {
                        $escapedQuote = true;
                        continue;
                    }
                    $values[] = $value;
                    $inQuote = false;
                    $value = '';
                    continue;
                } else {
                    $inQuote = true;
                    $value = ''; //don't store stuff before the opening quote
                    continue;
                }
            } elseif ($line[$i] == ',') {
                if ($inQuote) {
                    $value .= ',';
                    continue;
                } else {
                    if (strlen($value) < 1) {
                        continue;
                    }
                    $values[] = trim($value);
                    $value = '';
                    continue;
                }
            }

            $value .= $line[$i];
        }
        if (strlen($value) > 0) {
            $values[] = trim($value);
        }
        return $values;
    }
}
