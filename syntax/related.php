<?php
/**
 * List related pages based on similar data in the given column(s)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Class syntax_plugin_data_related
 */
class syntax_plugin_data_related extends syntax_plugin_data_table
{

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *datarelated(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+', $mode, 'plugin_data_related');
    }

    /**
     * Handles the actual output creation.
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $data data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format != 'xhtml') return false;
        if (is_null($data)) return false;
        if (!$this->dthlp->ready()) return false;

        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return false;

        if (!$data['sql']) return true; // sql build
        $this->dthlp->_replacePlaceholdersInSQL($data);

        $rows = $sqlite->queryAll($data['sql']);
        if (!$rows) return true; // no rows matched

        $renderer->doc .= '<dl class="' . $data['classes'] . '">';
        $renderer->doc .= '<dt>' . htmlspecialchars($data['title']) . '</dt>';
        $renderer->doc .= '<dd>';
        $renderer->listu_open();
        foreach ($rows as $row) {
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
    function _buildSQL(&$data, $id = null)
    {
        global $ID;
        if (is_null($id)) $id = $ID;

        $cnt = 1;
        $tables = array();
        $cond = array();
        $from = '';
        $where = '';

        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return false;

        // prepare the columns to match against
        $found = false;
        foreach (array_keys($data['cols']) as $col) {
            // get values for current page:
            $sql = "SELECT A.value
                      FROM data A, pages B
                     WHERE key = ?
                       AND A.pid = B.pid
                       AND B.page = ?";
            $rows = $sqlite->queryAll($sql, $col, $id);
            if(!$rows) continue; // no values? ignore the column.
            $values = array_column($rows, 'value');
            $found = true;

            $in = join(',', array_map([$sqlite->getPdo(), 'quote'], $values));
            $cond[] = " ( T1.key = " . $sqlite->getPdo()->quote($col) .
                " AND T1.value IN (" . $in . ") )\n";
        }
        $where .= ' AND (' . join(' OR ', $cond) . ') ';

        // any tags to compare?
        if (!$found) return false;

        // prepare sorting
        if ($data['sort'][0]) {
            $col = $data['sort'][0];

            if ($col == '%pageid%') {
                $order = ', pages.page ' . $data['sort'][1];
            } elseif ($col == '%title%') {
                $order = ', pages.title ' . $data['sort'][1];
            } else {
                // sort by hidden column?
                if (!$tables[$col]) {
                    $tables[$col] = 'T' . (++$cnt);
                    $from .= ' LEFT JOIN data AS ' . $tables[$col] . ' ON ' . $tables[$col] . '.pid = pages.pid';
                    $from .= ' AND ' . $tables[$col] . ".key = " . $sqlite->getPdo()->quote($col);
                }

                $order = ', ' . $tables[$col] . '.value ' . $data['sort'][1];
            }
        } else {
            $order = ', pages.page';
        }

        // add filters
        if (is_array($data['filter']) && count($data['filter'])) {
            $where .= ' AND ( 1=1 ';

            foreach ($data['filter'] as $filter) {
                // note: value is already escaped
                $col = $filter['key'];
                $closecompare = ($filter['compare'] == 'IN(' ? ')' : '');

                if ($col == '%pageid%') {
                    $where .= " " . $filter['logic'] . " pages.page " . $filter['compare'] . " " . $filter['value'] . $closecompare;
                } elseif ($col == '%title%') {
                    $where .= " " . $filter['logic'] . " pages.title " . $filter['compare'] . " " . $filter['value'] . $closecompare;
                } else {
                    // filter by hidden column?
                    if (!$tables[$col]) {
                        $tables[$col] = 'T' . (++$cnt);
                        $from .= ' LEFT JOIN data AS ' . $tables[$col] . ' ON ' . $tables[$col] . '.pid = pages.pid';
                        $from .= ' AND ' . $tables[$col] . ".key = " . $sqlite->getPdo()->quote($col);
                    }

                    $where .= ' ' . $filter['logic'] . ' ' . $tables[$col] . '.value ' . $filter['compare'] .
                        " " . $filter['value'] . $closecompare;
                }
            }

            $where .= ' ) ';
        }

        // build the query
        $sql = "SELECT pages.pid, pages.page as page, pages.title as title, COUNT(*) as rel
                  FROM pages, data as T1 $from
                 WHERE pages.pid = T1.pid
                   AND pages.page != " . $sqlite->getPdo()->quote($id) . "
                       $where
              GROUP BY pages.pid
              ORDER BY rel DESC$order";

        // limit
        if ($data['limit']) {
            $sql .= ' LIMIT ' . ($data['limit']);
        }

        return $sql;
    }
}
