<?php
/**
 * Dummy renderer for data entry editing
 *
 * @author     Adrian Lang <lang@cosmocode.de>
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

require_once DOKU_INC . 'inc/parser/renderer.php';

class Doku_Renderer_plugin_data_edit extends Doku_Renderer {
    function getFormat(){
        return 'plugin_data_edit';
    }
}
