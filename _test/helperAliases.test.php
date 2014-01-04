<?php
/**
 * @group plugin_data
 * @group plugins
 */
class helper_plugin_data_test_aliases extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    public function testAliases() {
        $helper = new helper_plugin_data();
        $db = $helper->_getDB();
        $this->assertTrue($db !== false);
        $db->query("INSERT INTO aliases (name, type, prefix, postfix, enum) VALUES (?,?,?,?,?)",
            'alias', 'wiki', '[[', ']]', '');

        $expect = array(
            'alias' => array(
                'type' => 'wiki',
                'prefix' => '[[',
                'postfix' => ']]'
            )
        );
        $this->assertEquals($expect, $helper->_aliases());

    }
}
