<?php

class Doku_Renderer_xhtml_mock extends Doku_Renderer_xhtml {

    function internallink($id, $name = null, $search = null, $returnonly = false, $linktype = 'content') {
        $inputvalues = array(
            'id' => $id,
            'name' => $name,
            'search' => $search,
            'returnonly' => $returnonly,
            'linktype' => $linktype
        );
        return "<internallink>" . serialize($inputvalues) . "</internallink>";
    }
}

/**
 * @group plugin_data
 * @group plugins
 */
class syntax_plugin_data_entry_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    private $exampleEntry = "---- dataentry projects ----\n"
    . "type          : web development\n"
    . "volume        : 1 Mrd # how much do they pay?\n"
    . "employees     : Joe, Jane, Jim\n"
    . "customer_page : customers:microsoft\n"
    . "deadline_dt   : 2009-08-17\n"
    . "server_pages  : servers:devel01, extern:microsoft\n"
    . "Website_url   : http://www.microsoft.com\n"
    . "task_tags     : programming, coding, design, html\n"
    . "tests_        : \\#5 done\n"
    . "----\n";

    function testHandle() {
        $plugin = new syntax_plugin_data_entry();

        $handler = new Doku_Handler();
        $result = $plugin->handle($this->exampleEntry, 0, 10, $handler);

        $this->assertEquals(10, $result['pos'], 'Position has changed');
        $this->assertEquals('projects', $result['classes'], 'wrong class name detected');

        $data = array(
            'type' => 'web development',
            'volume' => '1 Mrd',
            'employee' => array('Joe', 'Jane', 'Jim'),
            'customer' => 'customers:microsoft',
            'deadline' => '2009-08-17',
            'server' => array('servers:devel01', 'extern:microsoft'),
            'website' => 'http://www.microsoft.com',
            'task' => array('programming', 'coding', 'design', 'html'),
            'tests' => '#5 done',
            '----' => ''
        );
        $this->assertEquals($data, $result['data'], 'Data array corrupted');

        $cols = array(
            'type' => $this->createColumnEntry('type', false, 'type', 'type', 'type', false),
            'volume' => $this->createColumnEntry('volume', false, 'volume', 'volume', 'volume', false),
            'employee' => $this->createColumnEntry('employees', 1, 'employee', 'employee', 'employee', false),
            'customer' => $this->createColumnEntry('customer_page', false, 'customer', 'customer', 'customer',
                'page'),
            'deadline' => $this->createColumnEntry('deadline_dt', false, 'deadline', 'deadline', 'deadline', 'dt'),
            'server' => $this->createColumnEntry('server_pages', 1, 'server', 'server', 'server', 'page'),
            'website' => $this->createColumnEntry('Website_url', false, 'website', 'Website', 'Website', 'url'),
            'task' => $this->createColumnEntry('task_tags', 1, 'task', 'task', 'task', 'tag'),
            'tests' => $this->createColumnEntry('tests_', 0, 'tests', 'tests', 'tests', false),
            '----' => $this->createColumnEntry('----', false, '----', '----', '----', false)
        );
        $cols['volume']['comment'] = ' how much do they pay?';
        $this->assertEquals($cols, $result['cols'], 'Cols array corrupted');
    }

    function test_pageEntry_noTitle() {
        $test_entry = '---- dataentry ----
        test1_page: foo
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame(':foo',$result['id']);
        $this->assertSame(null,$result['name'], 'page does not accept a title. useheading decides');
    }

    function test_pageEntry_withTitle() {
        $test_entry = '---- dataentry ----
        test1_page: foo|bar
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame(':foo_bar',$result['id'], 'for type page a title becomes part of the id');
        $this->assertSame(null,$result['name'], 'page never accepts a title. useheading decides');
    }

    function test_pageidEntry_noTitle() {
        $test_entry = '---- dataentry ----
        test1_pageid: foo
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame('foo',$result['id']);
        $this->assertSame('foo',$result['name'], 'pageid: use the pageid as title if no title is provided.');
    }

    function test_pageidEntry_withTitle() {
        $test_entry = '---- dataentry ----
        test1_pageid: foo|bar
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame('foo',$result['id'], "wrong id handed to internal link");
        $this->assertSame('bar',$result['name'], 'pageid: use the provided title');
    }

    function test_titleEntry_noTitle() {
        $test_entry = '---- dataentry ----
        test1_title: foo
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame(':foo',$result['id']);
        $this->assertSame(null,$result['name'], 'no title should be given to internal link. Let useheading decide.');
    }


    function test_titleEntry_withTitle() {
        $test_entry = '---- dataentry ----
        test3_title: link:to:page|TitleOfPage
        ----';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_entry');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_entry, 0, 10, $handler);
        $renderer = new Doku_Renderer_xhtml_mock();
        $plugin->render('xhtml',$renderer,$data);
        $result = $renderer->doc;
        $result = substr($result,0,strpos($result,'</internallink>'));
        $result = substr($result,strpos($result,'<internallink>')+14);
        $result = unserialize($result);

        $this->assertSame(':link:to:page',$result['id']);
        $this->assertSame('TitleOfPage',$result['name'], 'The Title provided should be the title shown.');
    }

    function test_editToWiki() {
        $data = array(
            'classes' => 'projects',
            'data' => array(
                array(
                    'title'   => 'type',
                    'type'    => '',
                    'multi'   => '',
                    'value'   => 'web development',
                    'comment' => '',
                ),
                array(
                    'title'   => 'volume',
                    'type'    => '',
                    'multi'   => '',
                    'value'   => '1 Mrd',
                    'comment' => 'how much do they pay?',
                ),
                array(
                    'title'   => 'employee',
                    'type'    => '',
                    'multi'   => '1',
                    'value'   => 'Joe, Jane, Jim',
                    'comment' => '',
                ),
                array(
                    'title'   => 'customer',
                    'type'    => 'page',
                    'multi'   => '',
                    'value'   => 'customers:microsoft',
                    'comment' => '',
                ),
                array(
                    'title'   => 'deadline',
                    'type'    => 'dt',
                    'multi'   => '',
                    'value'   => '2009-08-17',
                    'comment' => '',
                ),
                array(
                    'title'   => 'server',
                    'type'    => 'page',
                    'multi'   => '1',
                    'value'   => 'servers:devel01, extern:microsoft',
                    'comment' => '',
                ),
                array(
                    'title'   => 'Website',
                    'type'    => 'url',
                    'multi'   => '',
                    'value'   => 'http://www.microsoft.com',
                    'comment' => '',
                ),
                array(
                    'title'   => 'task',
                    'type'    => 'tag',
                    'multi'   => '1',
                    'value'   => 'programming, coding, design, html',
                    'comment' => '',
                ),
                array(
                    'title'   => 'tests',
                    'type'    => '',
                    'multi'   => '',
                    'value'   => '#5 done',
                    'comment' => '',
                ),
                //empty row
                array(
                    'title'   => '',
                    'type'    => '',
                    'multi'   => '',
                    'value'   => '',
                    'comment' => '',
                )
            )
        );

        $plugin = new syntax_plugin_data_entry();
        $this->assertEquals($this->exampleEntry, $plugin->editToWiki($data));
    }


    function testHandleEmpty() {
        $plugin = new syntax_plugin_data_entry();

        $entry = "---- dataentry projects ----\n"
            . "\n"
            . "----\n";

        $handler = new Doku_Handler();
        $result = $plugin->handle($entry, 0, 10, $handler);

        $this->assertEquals(10,         $result['pos'],     'Position has changed');
        $this->assertEquals(35,        $result['len'],     'wrong entry length');
        $this->assertEquals('projects', $result['classes'], 'wrong class name detected');

        $data = array(
            '----'     => ''
        );
        $this->assertEquals($data, $result['data'], 'Data array corrupted');

        $cols = array(
            '----' => $this->createColumnEntry('----', false, '----', '----', '----', false)
        );
        $this->assertEquals($cols, $result['cols'], 'Cols array corrupted');
    }

    protected function createColumnEntry($name, $multi, $key, $origkey, $title, $type) {
        return array(
            'colname' => $name,
            'multi' => $multi,
            'key' => $key,
            'origkey' => $origkey,
            'title' => $title,
            'type' => $type
        );
    }

    function testShowData() {
        $handler = new Doku_Handler();
        $xhtml = new Doku_Renderer_xhtml();
        $plugin = new syntax_plugin_data_entry();

        $result = $plugin->handle($this->exampleEntry, 0, 10, $handler);

        $plugin->_showData($result, $xhtml);
        $doc = phpQuery::newDocument($xhtml->doc);

        $this->assertEquals(1, pq('div.inline.dataplugin_entry.projects', $doc)->length);
        $this->assertEquals(1, pq('dl dt.type')->length);
        $this->assertEquals(1, pq('dl dd.type')->length);
        $this->assertEquals(1, pq('dl dt.volume')->length);
        $this->assertEquals(1, pq('dl dd.volume')->length);
        $this->assertEquals(1, pq('dl dt.employee')->length);
        $this->assertEquals(1, pq('dl dd.employee')->length);
        $this->assertEquals(1, pq('dl dt.customer')->length);
        $this->assertEquals(1, pq('dl dd.customer')->length);
        $this->assertEquals(1, pq('dl dt.deadline')->length);
        $this->assertEquals(1, pq('dl dd.deadline')->length);
        $this->assertEquals(1, pq('dl dt.server')->length);
        $this->assertEquals(1, pq('dl dd.server')->length);
        $this->assertEquals(1, pq('dl dt.website')->length);
        $this->assertEquals(1, pq('dl dd.website')->length);
        $this->assertEquals(1, pq('dl dt.task')->length);
        $this->assertEquals(1, pq('dl dd.task')->length);
        $this->assertEquals(1, pq('dl dt.tests')->length);
        $this->assertEquals(1, pq('dl dd.tests')->length);
    }

    function testComments() {
        $entry = "---- dataentry projects ----\n"
            . "volume        : 1 Mrd # how much do they pay?\n"
            . "server        : http://www.microsoft.com      # Comment\n"
            . "Website_url   : http://www.microsoft.com\#test # Comment\n"
            . "Site_url      : https://www.microsoft.com/page\#test\n"
            . "tests_        : \\#5 done\n"
            . "----\n";

        $plugin = new syntax_plugin_data_entry();

        $handler = new Doku_Handler();
        $result = $plugin->handle($entry, 0, 10, $handler);

        $this->assertEquals(10,         $result['pos'],     'Position has changed');
        $this->assertEquals('projects', $result['classes'], 'wrong class name detected');

        $data = array(
            'volume'   => '1 Mrd',
            'server'   => 'http://www.microsoft.com',
            'website'  => 'http://www.microsoft.com#test',
            'site'     => 'https://www.microsoft.com/page#test',
            'tests'    => '#5 done',
            '----'     => ''
        );
        $this->assertEquals($data, $result['data'], 'Data array corrupted');

    }
}


