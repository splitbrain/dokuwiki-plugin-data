<?php

class data_dummy_renderer {

    function internallink($id, $title = '', $ignored=null, $ignored2=false) {
        return "link: $id $title";
    }

}

/**
 * This is the base class for all syntax classes, providing some general stuff
 */
class helper_plugin_data_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    function testCleanData() {

        $helper = new helper_plugin_data();

        $this->assertEquals('', $helper->_cleanData('   ', ''));
        $this->assertEquals('', $helper->_cleanData('', ''));
        $this->assertEquals('', $helper->_cleanData(null, ''));
        $this->assertEquals('', $helper->_cleanData(false, ''));

        $this->assertEquals('', $helper->_cleanData('', 'dt'));
        $this->assertEquals('', $helper->_cleanData('this is not a date', 'dt'));
        $this->assertEquals('1234-01-01', $helper->_cleanData('1234-1-1', 'dt'));
        $this->assertEquals('1234-01-01', $helper->_cleanData('1234-01-01', 'dt'));
        $this->assertEquals('', $helper->_cleanData('1234-01-011', 'dt'));

        $this->assertEquals('http://bla', $helper->_cleanData('bla', 'url'));
        $this->assertEquals('http://bla', $helper->_cleanData('http://bla', 'url'));
        $this->assertEquals('https://bla', $helper->_cleanData('https://bla', 'url'));
        $this->assertEquals('tell://bla', $helper->_cleanData('tell://bla', 'url'));

        $this->assertEquals('bla@bla.de', $helper->_cleanData('bla@bla.de', 'mail'));
        $this->assertEquals('bla@bla.de bla', $helper->_cleanData('bla@bla.de bla', 'mail'));
        $this->assertEquals('bla@bla.de bla word', $helper->_cleanData('bla@bla.de bla word', 'mail'));
        $this->assertEquals('bla@bla.de bla bla word', $helper->_cleanData('bla bla@bla.de bla word', 'mail'));
        $this->assertEquals('bla@bla.de bla bla word', $helper->_cleanData(' bla bla@bla.de bla word ', 'mail'));

        $this->assertEquals('123', $helper->_cleanData('123', 'page'));
        $this->assertEquals('123_123', $helper->_cleanData('123 123', 'page'));
        $this->assertEquals('123', $helper->_cleanData('123', 'nspage'));

        $this->assertEquals('test', $helper->_cleanData('test', ''));

        $this->assertEquals('test', $helper->_cleanData('test', array('type' => '')));
        $this->assertEquals('', $helper->_cleanData('test', array('type' => '', 'enum' => 'some other')));
    }

    function testColumn() {
        $helper = new helper_plugin_data();

        $this->assertEquals($this->createColumnEntry('type', false, 'type', 'type', ''), $helper->_column('type'));
        $this->assertEquals($this->createColumnEntry('types', true, 'type', 'type', ''), $helper->_column('types'));
        $this->assertEquals($this->createColumnEntry('', false, '', '', ''), $helper->_column(''));
        $this->assertEquals($this->createColumnEntry('type_url', false, 'type', 'type', 'url'), $helper->_column('type_url'));
        $this->assertEquals($this->createColumnEntry('type_urls', true, 'type', 'type', 'url'), $helper->_column('type_urls'));

        $this->assertEquals($this->createColumnEntry('type_hidden', false, 'type', 'type', 'hidden'), $helper->_column('type_hidden'));
        $this->assertEquals($this->createColumnEntry('type_hiddens', true, 'type', 'type', 'hidden'), $helper->_column('type_hiddens'));

        $this->assertEquals($this->createColumnEntry('%title%', false, '%title%', 'Page', 'title'), $helper->_column('%title%'));
        $this->assertEquals($this->createColumnEntry('%pageid%', false, '%pageid%', 'Title', 'page'), $helper->_column('%pageid%'));
        $this->assertEquals($this->createColumnEntry('%class%', false, '%class%', 'Page Class', ''), $helper->_column('%class%'));
        $this->assertEquals($this->createColumnEntry('%lastmod%', false, '%lastmod%', 'Last Modified', 'timestamp'), $helper->_column('%lastmod%'));
    }

    function testAddPrePostFixes() {
        $helper = new helper_plugin_data();

        $this->assertEquals('value', $helper->_addPrePostFixes('', 'value'));
        $this->assertEquals('prevaluepost', $helper->_addPrePostFixes('', 'value', 'pre', 'post'));
        $this->assertEquals('valuepost', $helper->_addPrePostFixes('', 'value', '', 'post'));
        $this->assertEquals('prevalue', $helper->_addPrePostFixes('', 'value', 'pre'));
        $this->assertEquals('prevaluepost', $helper->_addPrePostFixes(array('prefix' => 'pre', 'postfix' => 'post'), 'value'));
    }

    function testResolveData() {
        $helper = new helper_plugin_data();

        $this->assertEquals('tom', $helper->_resolveData('tom', 'name'));
        $this->assertEquals('jerry', $helper->_resolveData('jerry', 'name'));

        $this->assertEquals('Formatting Syntax', $helper->_resolveData('wiki:syntax', 'name_title'));
        $this->assertEquals(null, $helper->_resolveData('none:existing', 'name_title'));
    }

    function testFormatData() {
        global $conf;

        $helper = new helper_plugin_data();
        $renderer = new data_dummy_renderer();

        $this->assertEquals('value1, value2, val',
            $helper->_formatData(array('type' => ''), "value1\n value2\n val", $renderer));

        $this->assertEquals('link: :page ',
            $helper->_formatData(array('type' => 'page'), "page", $renderer));

        $this->assertEquals('link: :page title',
            $helper->_formatData(array('type' => 'title'), "page|title", $renderer));

        $this->assertEquals('link: :page title',
            $helper->_formatData(array('type' => 'pageid'), "page|title", $renderer));

        $this->assertEquals('link: :key:page ',
            $helper->_formatData(array('type' => 'nspage', 'key' => 'key'), "page", $renderer));

        $conf['mailguard'] = '';
        $this->assertEquals('<a href="mailto:pa:ge" class="mail" title="pa:ge">pa:ge</a>',
            $helper->_formatData(array('type' => 'mail'), "pa:ge", $renderer));

        $this->assertEquals('<a href="mailto:pa:ge" class="mail" title="pa:ge">some user</a>',
            $helper->_formatData(array('type' => 'mail'), "pa:ge some user", $renderer));

        $conf['mailguard'] = 'visible';
        $this->assertEquals('<a href="mailto:pa%3Age" class="mail" title="pa%3Age">pa:ge</a>',
            $helper->_formatData(array('type' => 'mail'), "pa:ge", $renderer));

        $this->assertEquals('<a href="mailto:pa%3Age" class="mail" title="pa%3Age">some user</a>',
            $helper->_formatData(array('type' => 'mail'), "pa:ge some user", $renderer));

        $this->assertEquals('<a href="url" class="urlextern" title="url">url</a>',
            $helper->_formatData(array('type' => 'url'), "url", $renderer));

        $this->assertEquals('<a href="/./doku.php?id=start&amp;dataflt=%3Dvalue" title="Show pages matching \'value\'" class="wikilink1">value</a>',
            $helper->_formatData(array('type' => 'tag'), "value", $renderer));

        $this->assertEquals('1970/01/15 07:56',
            $helper->_formatData(array('type' => 'timestamp'), "1234567", $renderer));

        $this->assertEquals('<strong>bla</strong>',
            $helper->_formatData(array('type' => 'wiki'), '|**bla**', $renderer));


        $this->assertEquals('<a href="/./lib/exe/fetch.php?media=wiki:dokuwiki-128.png" class="media" rel="lightbox"><img src="/./lib/exe/fetch.php?w=300&amp;media=wiki:dokuwiki-128.png" alt=": dokuwiki-128.png" title=": dokuwiki-128.png" width="300" /></a>',
            $helper->_formatData(array('type' => 'img300'), 'wiki:dokuwiki-128.png', $renderer));
    }

    function testReplacePlaceholdersInSQL() {
        $helper = new helper_plugin_data();

        $data = array('sql' => '%user%');
        $_SERVER['REMOTE_USER'] = 'test';
        $helper->_replacePlaceholdersInSQL($data);
        $this->assertEquals('test', $data['sql']);

        $data = array('sql' => '%now%');
        $helper->_replacePlaceholdersInSQL($data);
        $this->assertRegExp('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $data['sql']);
    }

    protected function createColumnEntry($name, $multi, $key, $title, $type) {
        return array(
            'colname' => $name,
            'multi' => $multi,
            'key' => $key,
            'title' => $title,
            'type' => $type
        );
    }

    public function testNoSqlPlugin() {
        $helper = new helper_plugin_data();
        plugin_disable('sqlite');
        $this->assertFalse($helper->_getDB());
    }

    public function testParseFilter() {
        $helper = new helper_plugin_data();

        $this->assertEquals($this->createFilterArray('name', 'tom', '=', 'name_some', 'some')
            , $helper->_parse_filter('name_some = tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '=', 'name', '')
            , $helper->_parse_filter('name = tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '!=', 'name', '')
            , $helper->_parse_filter('name != tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '!=', 'name', '')
            , $helper->_parse_filter('name <> tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '<', 'name', '')
            , $helper->_parse_filter('name < tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '>', 'name', '')
            , $helper->_parse_filter('name > tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '<=', 'name', '')
            , $helper->_parse_filter('name <= tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', '>=', 'name', '')
            , $helper->_parse_filter('name >= tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', 'LIKE', 'name', '')
            , $helper->_parse_filter('name ~ tom'));

        $this->assertEquals($this->createFilterArray('name', '%tom%', 'LIKE', 'name', '')
            , $helper->_parse_filter('name *~ tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom', 'NOT LIKE', 'name', '')
            , $helper->_parse_filter('name !~ tom'));

        $this->assertEquals($this->createFilterArray('name', '%tom', 'LIKE', 'name', '')
            , $helper->_parse_filter('name ~ *tom'));

        $this->assertEquals($this->createFilterArray('name', 'tom%', 'LIKE', 'name', '')
            , $helper->_parse_filter('name ~ tom*'));

        $this->assertEquals($this->createFilterArray('name', '%tom%', 'LIKE', 'name', '')
            , $helper->_parse_filter('name ~ *tom*'));

        $this->assertEquals(false, $helper->_parse_filter('name is *tom*'));
        $this->assertEquals(false, $helper->_parse_filter(''));
    }

    protected function createFilterArray($key, $value, $compare, $colname, $type) {
        return array(
            'key' => $key,
            'value' => $value,
            'compare' => $compare,
            'colname' => $colname,
            'type' => $type
        );
    }

    public function testGetFilters() {
        $helper = new helper_plugin_data();

        $this->assertEquals(array(), $helper->_get_filters());

        $_REQUEST['dataflt'] = 'name = tom';
        $this->assertEquals(array($this->createFilterArrayListEntry('name', 'tom', '=', 'name', '', 'AND')),
            $helper->_get_filters());

        $_REQUEST['dataflt'] = array();
        $_REQUEST['dataflt'][] = 'name = tom';
        $this->assertEquals(array($this->createFilterArrayListEntry('name', 'tom', '=', 'name', '', 'AND')),
            $helper->_get_filters());

        $_REQUEST['dataflt'] = array();
        $_REQUEST['dataflt'][] = 'name = tom';
        $_REQUEST['dataflt'][] = 'unit_url = dokuwiki.org';
        $this->assertEquals(
            array(
                $this->createFilterArrayListEntry('name', 'tom', '=', 'name', '', 'AND'),
                $this->createFilterArrayListEntry('unit', 'http://dokuwiki.org', '=', 'unit_url', 'url', 'AND')
            ),
            $helper->_get_filters());
    }

    private function createFilterArrayListEntry($key, $value, $compare, $colname, $type, $logic) {
        $item =  $this->createFilterArray($key, $value, $compare, $colname, $type);
        $item['logic'] = $logic;
        return $item;
    }

    public function testA2UA() {
        $helper = new helper_plugin_data();

        $array = array(
            'id' => '1',
            'name' => 'tom'
        );

        $result = array(
            'table[id]' => '1',
            'table[name]' => 'tom'
        );

        $this->assertEquals($result, $helper->_a2ua('table', $array));
    }
}
