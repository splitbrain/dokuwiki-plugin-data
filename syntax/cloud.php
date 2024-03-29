<?php

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Class syntax_plugin_data_cloud
 */
class syntax_plugin_data_cloud extends syntax_plugin_data_table
{
    /**
     * will hold the data helper plugin
     * @var $dthlp helper_plugin_data
     */
    public $dthlp;

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->dthlp = plugin_load('helper', 'data');
        if (!$this->dthlp) {
            msg('Loading the data helper failed. Make sure the data plugin is installed.', -1);
        }
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
     *
     * @param $mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *datacloud(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',
            $mode,
            'plugin_data_cloud'
        );
    }

    /**
     * Builds the SQL query from the given data
     *
     * @param array &$data instruction by handler
     * @return bool|string SQL query or false
     */
    public function buildSQL(&$data)
    {
        $ckey = array_keys($data['cols']);
        $ckey = $ckey[0];

        $from = ' ';
        $where = ' ';
        $pagesjoin = '';
        $tables = [];

        $sqlite = $this->dthlp->getDB();
        if (!$sqlite) return false;

        $fields = ['pageid' => 'page', 'class' => 'class', 'title' => 'title'];
        // prepare filters (no request filters - we set them ourselves)
        if (is_array($data['filter']) && count($data['filter'])) {
            $cnt = 0;

            foreach ($data['filter'] as $filter) {
                //Note: value is already escaped
                $col = $filter['key'];
                $closecompare = ($filter['compare'] == 'IN(' ? ')' : '');

                if (preg_match('/^%(\w+)%$/', $col, $m) && isset($fields[$m[1]])) {
                    $where .= " " . $filter['logic'] . " pages." . $fields[$m[1]] .
                        " " . $filter['compare'] . " " . $filter['value'] . $closecompare;
                    $pagesjoin = ' LEFT JOIN pages ON pages.pid = data.pid';
                } else {
                    // filter by hidden column?
                    if (!$tables[$col]) {
                        $tables[$col] = 'T' . (++$cnt);
                        $from .= ' LEFT JOIN data AS ' . $tables[$col] . ' ON ' . $tables[$col] . '.pid = data.pid';
                        $from .= ' AND ' . $tables[$col] . ".key = " . $sqlite->getPdo()->quote($col);
                    }

                    $where .= ' ' . $filter['logic'] . ' ' . $tables[$col] . '.value ' . $filter['compare'] .
                        " " . $filter['value'] . $closecompare;
                }
            }
        }

        // build query
        $sql = "SELECT data.value AS value, COUNT(data.pid) AS cnt
                  FROM data $from $pagesjoin
                 WHERE data.key = " . $sqlite->getPdo()->quote($ckey) . "
                 $where
              GROUP BY data.value";
        if (isset($data['min'])) {
            $sql .= ' HAVING cnt >= ' . $data['min'];
        }
        $sql .= ' ORDER BY cnt DESC';
        if ($data['limit']) {
            $sql .= ' LIMIT ' . $data['limit'];
        }

        return $sql;
    }

    protected $before_item = '<ul class="dataplugin_cloud %s">';
    protected $after_item = '</ul>';
    protected $before_val = '<li class="cl%s">';
    protected $after_val = '</li>';

    /**
     * Create output or save the data
     *
     * @param $format
     * @param Doku_Renderer $renderer
     * @param $data
     * @return bool
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ID;

        if ($format != 'xhtml') return false;
        if (is_null($data)) return false;
        if (!$this->dthlp->ready()) return false;
        $renderer->info['cache'] = false;

        $sqlite = $this->dthlp->getDB();
        if (!$sqlite) return false;

        $ckey = array_keys($data['cols']);
        $ckey = $ckey[0];

        if (!isset($data['page'])) {
            $data['page'] = $ID;
        }

        $this->dthlp->replacePlaceholdersInSQL($data);

        // build cloud data
        $rows = $sqlite->queryAll($data['sql']);
        $min = 0;
        $max = 0;
        $tags = [];
        foreach ($rows as $row) {
            if (!$max) {
                $max = $row['cnt'];
            }
            $min = $row['cnt'];
            $tags[$row['value']]['cnt'] = $row['cnt'];
            $tags[$row['value']]['value'] = $row['value'];
        }
        $this->cloudWeight($tags, $min, $max, 5);

        // output cloud
        $renderer->doc .= sprintf($this->before_item, hsc($data['classes']));
        foreach ($tags as $tag) {
            $tagLabelText = hsc($tag['value']);
            if ($data['summarize'] == 1) {
                $tagLabelText .= '<sub>(' . $tag['cnt'] . ')</sub>';
            }

            $renderer->doc .= sprintf($this->before_val, $tag['lvl']);
            $renderer->doc .= '<a href="' .
                wl($data['page'], $this->dthlp->getTagUrlparam($data['cols'][$ckey], $tag['value'])) .
                '" title="' . sprintf($this->getLang('tagfilter'), hsc($tag['value'])) .
                '" class="wikilink1">' . $tagLabelText . '</a>';
            $renderer->doc .= $this->after_val;
        }
        $renderer->doc .= $this->after_item;
        return true;
    }

    /**
     * Create a weighted tag distribution
     *
     * @param $tags array ref The tags to weight ( tag => count)
     * @param $min int      The lowest count of a single tag
     * @param $max int      The highest count of a single tag
     * @param $levels int   The number of levels you want. A 5 gives levels 0 to 4.
     */
    protected function cloudWeight(&$tags, $min, $max, $levels)
    {
        $levels--;

        // calculate tresholds
        $tresholds = [];
        for ($i = 0; $i <= $levels; $i++) {
            $tresholds[$i] = ($max - $min + 1) ** ($i / $levels) + $min - 1;
        }

        // assign weights
        foreach ($tags as $tag) {
            foreach ($tresholds as $tresh => $val) {
                if ($tag['cnt'] <= $val) {
                    $tags[$tag['value']]['lvl'] = $tresh;
                    break;
                }
                $tags[$tag['value']]['lvl'] = $levels;
            }
        }

        // sort
        ksort($tags);
    }
}
