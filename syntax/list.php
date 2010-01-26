<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(dirname(__FILE__).'/table.php');

/**
 * This inherits from the table syntax, because it's basically the
 * same, just different output
 */
class syntax_plugin_data_list extends syntax_plugin_data_table {

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *datalist(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+',$mode,'plugin_data_list');
    }


    /**
     * Create output
     */
    function render($format, &$R, $data) {
        global $ID;

        if($format != 'xhtml') return false;
        if(is_null($data)) return false;
        $R->info['cache'] = false;

        $sqlite = $this->dthlp->_getDB();
        if(!$sqlite) return false;

        #dbg($data);
        $sql = $this->_buildSQL($data); // handles GET params, too
        #dbg($sql);

        // run query
        $clist = array_keys($data['cols']);
        $res = $sqlite->query($sql);

        // build list
        $R->doc .= '<ul class="dataplugin_list '.$data['classes'].'">';

        $cnt = 0;
        while ($row = sqlite_fetch_array($res, SQLITE_NUM)) {
            $R->doc .= '<li><div class="li">';
            foreach($row as $num => $cval){
                $R->doc .= $this->dthlp->_formatData($data['cols'][$clist[$num]],$cval,$R)."\n";
            }
            $R->doc .= '</div></li>';
            $cnt++;
            if($data['limit'] && ($cnt == $data['limit'])) break; // keep an eye on the limit
        }

        $R->doc .= '</ul>';
    }

}
