<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * This inherits from the table syntax, because it's basically the
 * same, just different output
 */
class syntax_plugin_data_list extends syntax_plugin_data_table {

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *datalist(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+', $mode, 'plugin_data_list');
    }

    protected $before_item = '<li><div class="li">';
    protected $after_item  = '</div></li>';
    protected $before_val  = '';
    protected $after_val   = ' ';

    /**
     * Before value in listitem
     *
     * @param array $data  instructions by handler
     * @param int   $colno column number
     * @return string
     */
    protected function beforeVal(&$data, $colno) {
        if($data['sepbyheaders'] AND $colno === 0) {
            return $data['headers'][$colno];
        } else {
            return $this->before_val;
        }
    }

    /**
     * After value in listitem
     *
     * @param array $data
     * @param int   $colno
     * @return string
     */
    protected function afterVal(&$data, $colno) {
        if($data['sepbyheaders']) {
            return $data['headers'][$colno + 1];
        } else {
            return $this->after_val;
        }
    }

    /**
     * Create list header
     *
     * @param array $clist keys of the columns
     * @param array $data  instruction by handler
     * @return string html of table header
     */
    function preList($clist, $data) {
        return '<div class="dataaggregation"><ul class="dataplugin_list ' . $data['classes'] . '">';
    }

    /**
     * Create an empty list
     *
     * @param array         $data  instruction by handler()
     * @param array         $clist keys of the columns
     * @param Doku_Renderer $R
     */
    function nullList($data, $clist, $R) {
        $R->doc .= '<div class="dataaggregation"><p class="dataplugin_list ' . $data['classes'] . '">';
        $R->cdata($this->getLang('none'));
        $R->doc .= '</p></div>';
    }

    /**
     * Create list footer
     *
     * @param array $data   instruction by handler()
     * @param int   $rowcnt number of rows
     * @return string html of table footer
     */
    function postList($data, $rowcnt) {
        return '</ul></div>';
    }

}
