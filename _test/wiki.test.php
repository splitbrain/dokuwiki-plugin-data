<?php
require_once DOKU_INC.'_test/lib/unittest.php';
require_once DOKU_INC.'inc/init.php';

class data_test_types_wiki extends Doku_UnitTestCase {
    function test() {
        global $ID;
        $this->assertEqual(p_render('xhtml',p_get_instructions(
"---- dataentry ----
test_wiki: [[.|Link]]
----"),$info), '<div class="inline dataplugin_entry  sectionedit1"><dl><dt class="test">test<span class="sep">: </span></dt><dd class="test"><span class="curid"><a href="' . wl($ID) . '" class="wikilink2" rel="nofollow">Link</a></span></dd></dl></div><!-- EDIT1 PLUGIN_DATA [1-47] -->');
    }
}
