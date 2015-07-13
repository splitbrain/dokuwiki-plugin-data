<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

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

    protected function before_item(Doku_Renderer $R)
    {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= '<li><div class="li">';
                break;
            case 'odt':
                $R->listitem_open();
                $R->listcontent_open();
                break;
        }
        return true;
    }

    protected function after_item(Doku_Renderer $R)
    {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= '</div></li>';
                break;
            case 'odt':
                $R->listcontent_close();
                $R->listitem_close();
                break;
        }
        return true;
    }

    protected function before_val($class, Doku_Renderer $R)
    {
        return true;
    }

    protected function after_val(Doku_Renderer $R)
    {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= ' ';
                break;
            case 'odt':
                $R->doc .= ' ';
                break;
        }
        return true;
    }

    /**
     * Before value in listitem
     *
     * @param array $data  instructions by handler
     * @param int   $colno column number
     * @return string
     */
    protected function beforeVal(&$data, $colno, $class, Doku_Renderer $R) {
        if($data['sepbyheaders'] AND $colno === 0) {
            $R->doc .= $data['headers'][$colno];
        } else {
            $this->before_val($class, $R);
        }
    }

    /**
     * After value in listitem
     *
     * @param array $data
     * @param int   $colno
     * @return string
     */
    protected function afterVal(&$data, $colno, Doku_Renderer $R) {
        if($data['sepbyheaders']) {
            $R->doc .= $data['headers'][$colno + 1];
        } else {
            $this->after_val($R);
        }
    }

    /**
     * Create list header
     *
     * @param array $clist keys of the columns
     * @param array $data  instruction by handler
     * @return string html of table header
     */
    function preList($clist, $data, Doku_Renderer $R) {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= '<div class="dataaggregation"><ul class="dataplugin_list ' . $data['classes'] . '">';
                break;
            case 'odt':
                $R->listu_open();
                break;
        }
        return true;
    }

    /**
     * Create an empty list
     *
     * @param array         $data  instruction by handler()
     * @param array         $clist keys of the columns
     * @param Doku_Renderer $R
     */
    function nullList($data, $clist, Doku_Renderer $R) {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= '<div class="dataaggregation"><p class="dataplugin_list ' . $data['classes'] . '">';
                $R->cdata($this->getLang('none'));
                $R->doc .= '</p></div>';
            break;
            case 'odt':
                $R->p_open();
                $R->cdata($this->getLang('none'));
                $R->p_close();
            break;
        }
    }

    /**
     * Create list footer
     *
     * @param array $data   instruction by handler()
     * @param int   $rowcnt number of rows
     * @return string html of table footer
     */
    function postList($data, $rowcnt, Doku_Renderer $R) {
        switch($R->getFormat())
        {
            case 'xhtml':
                $R->doc .= '</ul></div>';
                break;
            case 'odt':
                $R->listu_close();
                break;
        }
        return true;
    }

}
