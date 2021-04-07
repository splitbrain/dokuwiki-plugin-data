<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gerrit <klapinklapin@gmail.com>
 *
 * based on cloud.php. Build a list of tags ordered by their counts.
 */

/**
 * Class syntax_plugin_data_taglist
 */
class syntax_plugin_data_taglist extends syntax_plugin_data_cloud {

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *datataglist(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+', $mode, 'plugin_data_taglist');
    }

    protected $before_item = '<ul class="dataplugin_taglist %s">';
    protected $after_item  = '</ul>';
    protected $before_val  = '<li class="tl">';
    protected $after_val   = '</li>';

    /**
     * Create a weighted tag distribution
     *
     * @param &$tags  array The tags to weight ( tag => count)
     * @param $min    int   The lowest count of a single tag
     * @param $max    int   The highest count of a single tag
     * @param $levels int   The number of levels you want. A 5 gives levels 0 to 4.
     */
    protected function _cloud_weight(&$tags, $min, $max, $levels) {
        parent::_cloud_weight($tags, $min, $max, $levels);

        // sort by values. Key is name of the single tag, value the count
        arsort($tags);
    }

}

