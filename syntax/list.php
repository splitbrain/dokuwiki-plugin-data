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
    function render($format, &$renderer, $data) {
        global $ID;

        if($format != 'xhtml') return false;
        if(!$this->dthlp->_dbconnect()) return false;
        if(is_null($data)) return false;
        $renderer->info['cache'] = false;

        #dbg($data);
        $sql = $this->_buildSQL($data); // handles GET params, too
        #dbg($sql);

        // run query
        $types = array_values($data['cols']);
        $res = sqlite_query($this->dthlp->db,$sql);

        // build table
        $renderer->doc .= '<ul class="inline dataplugin_table '.$data['classes'].'">';


        $cnt = 0;
        while ($row = sqlite_fetch_array($res, SQLITE_NUM)) {
            $renderer->doc .= '<li><div class="li">';
            foreach($row as $num => $col){
                $renderer->doc .= $this->dthlp->_formatData($data['cols'][$num],$col,$types[$num],$renderer)."\n";
            }
            $renderer->doc .= '</div></li>';
            $cnt++;
            if($data['limit'] && ($cnt == $data['limit'])) break; // keep an eye on the limit
        }


        $renderer->doc .= '</ul>';
    }

}
