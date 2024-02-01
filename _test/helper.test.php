<?php

class data_dummy_renderer extends Doku_Renderer_xhtml
{

    function internallink($id, $title = '', $ignored = null, $ignored2 = false, $linktype = 'content')
    {
        return "link: $id $title";
    }

}

/**
 * @group plugin_data
 * @group plugins
 */
class helper_plugin_data_test extends DokuWikiTest
{

    protected $pluginsEnabled = array('data', 'sqlite');

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // copy our own config files to the test directory
        TestUtils::rcopy(dirname(DOKU_CONF), dirname(__FILE__) . '/conf');
    }

    function testCleanData()
    {

        $helper = new helper_plugin_data();

        $this->assertEquals('', $helper->cleanData('   ', ''));
        $this->assertEquals('', $helper->cleanData('', ''));
        $this->assertEquals('', $helper->cleanData(null, ''));
        $this->assertEquals('', $helper->cleanData(false, ''));

        $this->assertEquals('', $helper->cleanData('', 'dt'));
        $this->assertEquals('', $helper->cleanData('this is not a date', 'dt'));
        $this->assertEquals('1234-01-01', $helper->cleanData('1234-1-1', 'dt'));
        $this->assertEquals('1234-01-01', $helper->cleanData('1234-01-01', 'dt'));
        $this->assertEquals('%now%', $helper->cleanData('%now%', 'dt'));
        $this->assertEquals('', $helper->cleanData('1234-01-011', 'dt'));

        $this->assertEquals('http://bla', $helper->cleanData('bla', 'url'));
        $this->assertEquals('http://bla', $helper->cleanData('http://bla', 'url'));
        $this->assertEquals('https://bla', $helper->cleanData('https://bla', 'url'));
        $this->assertEquals('tell://bla', $helper->cleanData('tell://bla', 'url'));

        $this->assertEquals('bla@bla.de', $helper->cleanData('bla@bla.de', 'mail'));
        $this->assertEquals('bla@bla.de bla', $helper->cleanData('bla@bla.de bla', 'mail'));
        $this->assertEquals('bla@bla.de bla word', $helper->cleanData('bla@bla.de bla word', 'mail'));
        $this->assertEquals('bla@bla.de bla bla word', $helper->cleanData('bla bla@bla.de bla word', 'mail'));
        $this->assertEquals('bla@bla.de bla bla word', $helper->cleanData(' bla bla@bla.de bla word ', 'mail'));

        $this->assertEquals('123', $helper->cleanData('123', 'page'));
        $this->assertEquals('123_123', $helper->cleanData('123 123', 'page'));
        $this->assertEquals('123', $helper->cleanData('123', 'nspage'));

        $this->assertEquals('test', $helper->cleanData('test', ''));

        $this->assertEquals('test', $helper->cleanData('test', array('type' => '')));
        $this->assertEquals('', $helper->cleanData('test', array('type' => '', 'enum' => 'some other')));
    }

    function testColumn()
    {
        global $conf;
        $helper = new helper_plugin_data();

        $this->assertEquals($this->createColumnEntry('type', false, 'type', 'type', 'type', ''), $helper->column('type'));
        $this->assertEquals($this->createColumnEntry('types', true, 'type', 'type', 'type', ''), $helper->column('types'));
        $this->assertEquals($this->createColumnEntry('', false, '', '', '', ''), $helper->column(''));
        $this->assertEquals($this->createColumnEntry('type_url', false, 'type', 'type', 'type', 'url'), $helper->column('type_url'));
        $this->assertEquals($this->createColumnEntry('type_urls', true, 'type', 'type', 'type', 'url'), $helper->column('type_urls'));

        $this->assertEquals($this->createColumnEntry('type_hidden', false, 'type', 'type', 'type', 'hidden'), $helper->column('type_hidden'));
        $this->assertEquals($this->createColumnEntry('type_hiddens', true, 'type', 'type', 'type', 'hidden'), $helper->column('type_hiddens'));

        $this->assertEquals($this->createColumnEntry('%title%', false, '%title%', '%title%', 'Page', 'title'), $helper->column('%title%'));
        $this->assertEquals($this->createColumnEntry('%pageid%', false, '%pageid%', '%pageid%', 'Title', 'page'), $helper->column('%pageid%'));
        $this->assertEquals($this->createColumnEntry('%class%', false, '%class%', '%class%', 'Page Class', ''), $helper->column('%class%'));
        $this->assertEquals($this->createColumnEntry('%lastmod%', false, '%lastmod%', '%lastmod%', 'Last Modified', 'timestamp'), $helper->column('%lastmod%'));

        $this->assertEquals($this->createColumnEntry('Type', false, 'type', 'Type', 'Type', ''), $helper->column('Type'));


        // test translated key name
        $this->assertEquals($this->createColumnEntry('trans_urls', true, 'trans', 'trans', 'Translated Title', 'url'), $helper->column('trans_urls'));
        // retry in different language
        $conf['lang'] = 'de';
        $helper = new helper_plugin_data();
        $this->assertEquals($this->createColumnEntry('trans_urls', true, 'trans', 'trans', 'Übersetzter Titel', 'url'), $helper->column('trans_urls'));
    }

    function testAddPrePostFixes()
    {
        global $conf;
        $helper = new helper_plugin_data();

        $this->assertEquals('value', $helper->addPrePostFixes('', 'value'));
        $this->assertEquals('prevaluepost', $helper->addPrePostFixes('', 'value', 'pre', 'post'));
        $this->assertEquals('valuepost', $helper->addPrePostFixes('', 'value', '', 'post'));
        $this->assertEquals('prevalue', $helper->addPrePostFixes('', 'value', 'pre'));
        $this->assertEquals('prevaluepost', $helper->addPrePostFixes(array('prefix' => 'pre', 'postfix' => 'post'), 'value'));

        $conf['lang'] = 'en';
        $this->assertEquals('envalue', $helper->addPrePostFixes(array('prefix' => '%lang%'), 'value'));

        $this->assertEquals('value', $helper->addPrePostFixes(array('prefix' => '%trans%'), 'value'));

        $plugininstalled = in_array('translation', plugin_list('helper', $all = true));
        if (!$plugininstalled) $this->markTestSkipped('Pre-condition not satisfied: translation plugin must be installed');

        if ($plugininstalled && plugin_enable('translation')) {
            global $ID;
            $conf['plugin']['translation']['translations'] = 'de';
            $ID = 'de:somepage';
            $this->assertEquals('de:value', $helper->addPrePostFixes(array('prefix' => '%trans%:'), 'value'));
        }

    }

    function testResolveData()
    {
        $helper = new helper_plugin_data();

        $this->assertEquals('tom', $helper->resolveData('tom', 'name'));
        $this->assertEquals('jerry', $helper->resolveData('jerry', 'name'));

        $this->assertEquals('wiki:syntax Formatting Syntax', $helper->resolveData('wiki:syntax', 'name_title'));
        $this->assertEquals('none:existing ', $helper->resolveData('none:existing', 'name_title'));
    }

    function testFormatData()
    {
        global $conf;
        global $ID;
        $ID = '';

        $helper = new helper_plugin_data();
        $renderer = new data_dummy_renderer();

        $this->assertEquals('value1, value2, val',
            $helper->formatData(array('type' => ''), "value1\n value2\n val", $renderer));

        $this->assertEquals('link: :page ',
            $helper->formatData(array('type' => 'page'), "page", $renderer));

        $this->assertEquals('link: :page title',
            $helper->formatData(array('type' => 'title'), "page|title", $renderer));

        $this->assertEquals('link: page title',
            $helper->formatData(array('type' => 'pageid'), "page|title", $renderer));

        $this->assertEquals('link: :key:page ',
            $helper->formatData(array('type' => 'nspage', 'key' => 'key'), "page", $renderer));

        $conf['mailguard'] = '';
        $this->assertEquals('<a href="mailto:pa:ge" class="mail" title="pa:ge">pa:ge</a>',
            $helper->formatData(array('type' => 'mail'), "pa:ge", $renderer));

        $this->assertEquals('<a href="mailto:pa:ge" class="mail" title="pa:ge">some user</a>',
            $helper->formatData(array('type' => 'mail'), "pa:ge some user", $renderer));

        $conf['mailguard'] = 'visible';
        $this->assertEquals('<a href="mailto:pa%3Age" class="mail" title="pa%3Age">pa:ge</a>',
            $helper->formatData(array('type' => 'mail'), "pa:ge", $renderer));

        $this->assertEquals('<a href="mailto:pa%3Age" class="mail" title="pa%3Age">some user</a>',
            $helper->formatData(array('type' => 'mail'), "pa:ge some user", $renderer));

        $this->assertEquals('<a href=\'url\' class=\'urlextern\' rel="nofollow">url</a>',
            $helper->formatData(array('type' => 'url'), "url", $renderer));

        $this->assertEquals('<a href="' . wl('start', array('dataflt[0]' => '_=value')) . '" title="Show pages matching \'value\'" class="wikilink1">value</a>',
            $helper->formatData(array('type' => 'tag', 'key' => ''), "value", $renderer));

        $this->assertEquals(strftime('%Y/%m/%d %H:%M', 1234567),
            $helper->formatData(array('type' => 'timestamp'), "1234567", $renderer));

        $this->assertEquals('<strong>bla</strong>',
            $helper->formatData(array('type' => 'wiki'), '|**bla**', $renderer));


        $this->assertEquals('<a rel="lightbox" href="' . ml('wiki:dokuwiki-128.png', array('cache' => null)) . '" class="media" title="wiki:dokuwiki-128.png"><img src="' . ml('wiki:dokuwiki-128.png', array('w' => 300, 'cache' => null)) . '" class="media" loading="lazy" title=": dokuwiki-128.png" alt=": dokuwiki-128.png" width="300" /></a>',
            $helper->formatData(array('type' => 'img300', 'key' => ''), 'wiki:dokuwiki-128.png', $renderer));
    }

    function testReplacePlaceholdersInSQL()
    {
        global $USERINFO;
        global $INPUT;
        $helper = new helper_plugin_data();

        $data = array('sql' => '%user%');
        $INPUT->server->set('REMOTE_USER', 'test');
        $helper->replacePlaceholdersInSQL($data);
        $this->assertEquals('test', $data['sql']);

        $data = array('sql' => '%groups%');
        $USERINFO['grps'] = array('test', 'admin');
        $helper->replacePlaceholdersInSQL($data);
        $this->assertEquals("test','admin", $data['sql']);

        $data = array('sql' => '%now%');
        $helper->replacePlaceholdersInSQL($data);
        $this->assertRegExp('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $data['sql']);

        $data = array('sql' => '%lang%');
        $helper->replacePlaceholdersInSQL($data);
        $this->assertEquals('en', $data['sql']);
    }

    protected function createColumnEntry($name, $multi, $key, $origkey, $title, $type)
    {
        return array(
            'colname' => $name,
            'multi' => $multi,
            'key' => $key,
            'origkey' => $origkey,
            'title' => $title,
            'type' => $type
        );
    }

    public function testNoSqlPlugin()
    {
        plugin_disable('sqlite');
        $this->expectException(\Exception::class);
        $helper = new helper_plugin_data();
        $helper->getDB();
    }

    public function testParseFilter()
    {
        $helper = new helper_plugin_data();

        $this->assertEquals($this->createFilterArray('name', "'tom'", '=', 'name_some', 'some')
            , $helper->parseFilter('name_some = tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '=', 'name', '')
            , $helper->parseFilter('name = tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '!=', 'name', '')
            , $helper->parseFilter('name != tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '!=', 'name', '')
            , $helper->parseFilter('name <> tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '<', 'name', '')
            , $helper->parseFilter('name < tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '>', 'name', '')
            , $helper->parseFilter('name > tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '<=', 'name', '')
            , $helper->parseFilter('name <= tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", '>=', 'name', '')
            , $helper->parseFilter('name >= tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", 'LIKE', 'name', '')
            , $helper->parseFilter('name ~ tom'));

        $this->assertEquals($this->createFilterArray('name', "'%tom%'", 'LIKE', 'name', '')
            , $helper->parseFilter('name *~ tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", 'NOT LIKE', 'name', '')
            , $helper->parseFilter('name !~ tom'));

        $this->assertEquals($this->createFilterArray('name', "'%tom'", 'LIKE', 'name', '')
            , $helper->parseFilter('name ~ *tom'));

        $this->assertEquals($this->createFilterArray('name', "'tom%'", 'LIKE', 'name', '')
            , $helper->parseFilter('name ~ tom*'));

        $this->assertEquals($this->createFilterArray('name', "'%tom%'", 'LIKE', 'name', '')
            , $helper->parseFilter('name ~ *tom*'));

        $this->assertEquals($this->createFilterArray('name', "'tom'", 'IN(', 'name', '')
            , $helper->parseFilter('name ~~ tom'));

        $this->assertEquals($this->createFilterArray('name', "'t''om','john*'", 'IN(', 'name', '')
            , $helper->parseFilter("name ~~ t'om,john*"));

        $this->assertEquals(false, $helper->parseFilter('name is *tom*'));
        $this->assertEquals(false, $helper->parseFilter(''));
    }

    protected function createFilterArray($key, $value, $compare, $colname, $type)
    {
        return array(
            'key' => $key,
            'value' => $value,
            'compare' => $compare,
            'colname' => $colname,
            'type' => $type
        );
    }

    public function testGetFilters()
    {
        $helper = new helper_plugin_data();

        $this->assertEquals(array(), $helper->getFilters());

        $_REQUEST['dataflt'] = 'name = tom';
        $this->assertEquals(array($this->createFilterArrayListEntry('name', "'tom'", '=', 'name', '', 'AND')),
            $helper->getFilters());

        $_REQUEST['dataflt'] = array();
        $_REQUEST['dataflt'][] = 'name = tom';
        $this->assertEquals(array($this->createFilterArrayListEntry('name', "'tom'", '=', 'name', '', 'AND')),
            $helper->getFilters());

        $_REQUEST['dataflt'] = array();
        $_REQUEST['dataflt'][] = 'name = tom';
        $_REQUEST['dataflt'][] = 'unit_url = dokuwiki.org';
        $this->assertEquals(
            array(
                $this->createFilterArrayListEntry('name', "'tom'", '=', 'name', '', 'AND'),
                $this->createFilterArrayListEntry('unit', "'http://dokuwiki.org'", '=', 'unit_url', 'url', 'AND')
            ),
            $helper->getFilters());
    }

    private function createFilterArrayListEntry($key, $value, $compare, $colname, $type, $logic)
    {
        $item = $this->createFilterArray($key, $value, $compare, $colname, $type);
        $item['logic'] = $logic;
        return $item;
    }

    public function testA2UA()
    {
        $helper = new helper_plugin_data();

        $array = array(
            'id' => '1',
            'name' => 'tom'
        );

        $result = array(
            'table[id]' => '1',
            'table[name]' => 'tom'
        );

        $this->assertEquals($result, $helper->a2ua('table', $array));
    }

    public function testMakeTranslationReplacement()
    {
        $helper = new helper_plugin_data();

        $this->assertEquals('en', $helper->makeTranslationReplacement('%lang%'));
        $this->assertEquals('', $helper->makeTranslationReplacement('%trans%'));

        $plugininstalled = in_array('translation', plugin_list('helper', $all = true));
        if (!$plugininstalled) $this->markTestSkipped('Pre-condition not satisfied: translation plugin must be installed');

        if ($plugininstalled && plugin_enable('translation')) {
            global $conf;
            global $ID;
            $conf['plugin']['translation']['translations'] = 'de';
            $ID = 'de:somepage';
            $this->assertEquals('en', $helper->makeTranslationReplacement('%lang%'));
            $this->assertEquals('de', $helper->makeTranslationReplacement('%trans%'));
        }
    }
}
