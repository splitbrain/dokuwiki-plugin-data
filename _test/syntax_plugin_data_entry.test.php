<?php

require_once DOKU_INC . 'inc/parser/xhtml.php';

class syntax_plugin_data_entry_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    private $exampleEntry;

    function __construct() {
        $this->exampleEntry = "---- dataentry projects ----\n"
            . "type            : web development\n"
            . "volume          : 1 Mrd    # how much do they pay?\n"
            . "employees       : Joe, Jane, Jim\n"
            . "customer_page   : customers:microsoft\n"
            . "deadline_dt     : 2009-08-17\n"
            . "server_pages    : servers:devel01, extern:microsoft\n"
            . "website_url     : http://www.microsoft.com\n"
            . "task_tags       : programming, coding, design, html\n"
            . "----\n";
    }

    function testHandle() {
        $plugin = new syntax_plugin_data_entry();

        $null = null;
        $result = $plugin->handle($this->exampleEntry, 0, 10, $null);

        $this->assertEquals(10,         $result['pos'],     'Position has changed');
        $this->assertEquals(366,        $result['len'],     'wrong entry length');
        $this->assertEquals('projects', $result['classes'], 'wrong class name detected');

        $data = array(
            'type'     => 'web development',
            'volume'   => '1 Mrd',
            'employee' => array('Joe', 'Jane', 'Jim'),
            'customer' => 'customers:microsoft',
            'deadline' => '2009-08-17',
            'server'   => array('servers:devel01', 'extern:microsoft'),
            'website'  => 'http://www.microsoft.com',
            'task'     => array('programming', 'coding', 'design', 'html'),
            '----'     => ''
        );
        $this->assertEquals($data, $result['data'], 'Data array corrupted');

        $cols = array(
            'type' => $this->createColumnEntry('type', false, 'type', 'type', false),
            'volume' => $this->createColumnEntry('volume', false, 'volume', 'volume', false),
            'employee' => $this->createColumnEntry('employees', 1, 'employee', 'employee', false),
            'customer' => $this->createColumnEntry('customer_page', false, 'customer', 'customer', 'page'),
            'deadline' => $this->createColumnEntry('deadline_dt', false, 'deadline', 'deadline', 'dt'),
            'server' => $this->createColumnEntry('server_pages', 1, 'server', 'server', 'page'),
            'website' => $this->createColumnEntry('website_url', false, 'website', 'website', 'url'),
            'task' => $this->createColumnEntry('task_tags', 1, 'task', 'task', 'tag'),
            '----' => $this->createColumnEntry('----', false, '----', '----', false)
        );
        $cols['volume']['comment'] = ' how much do they pay?';
        $this->assertEquals($cols, $result['cols'], 'Cols array corrupted');
    }

    function testHandleEmpty() {
        $plugin = new syntax_plugin_data_entry();

        $entry = "---- dataentry projects ----\n"
            . "\n"
            . "----\n";

        $null = null;
        $result = $plugin->handle($entry, 0, 10, $null);

        $this->assertEquals(10,         $result['pos'],     'Position has changed');
        $this->assertEquals(35,        $result['len'],     'wrong entry length');
        $this->assertEquals('projects', $result['classes'], 'wrong class name detected');

        $data = array(
            '----'     => ''
        );
        $this->assertEquals($data, $result['data'], 'Data array corrupted');

        $cols = array(
            '----' => $this->createColumnEntry('----', false, '----', '----', false)
        );
        $this->assertEquals($cols, $result['cols'], 'Cols array corrupted');
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

    function testShowData() {
        $xhtml = new Doku_Renderer_xhtml();
        $plugin = new syntax_plugin_data_entry();

        $null = null;
        $result = $plugin->handle($this->exampleEntry, 0, 10, $null);

        $plugin->_showData($result, $xhtml);
        $doc = phpQuery::newDocument($xhtml->doc);

        $this->assertEquals(1, pq('div.inline.dataplugin_entry.projects', $doc)->length);
        $this->assertEquals(1, pq('dl dt.type')->length);
        $this->assertEquals(1, pq('dl dd.type')->length);
        $this->assertEquals(1, pq('dl dt.volume')->length);
        $this->assertEquals(1, pq('dl dd.volume')->length);
        $this->assertEquals(1, pq('dl dt.employee')->length);
        $this->assertEquals(3, pq('dl dd.employee')->length);
        $this->assertEquals(1, pq('dl dt.customer')->length);
        $this->assertEquals(1, pq('dl dd.customer')->length);
        $this->assertEquals(1, pq('dl dt.deadline')->length);
        $this->assertEquals(1, pq('dl dd.deadline')->length);
        $this->assertEquals(1, pq('dl dt.server')->length);
        $this->assertEquals(2, pq('dl dd.server')->length);
        $this->assertEquals(1, pq('dl dt.website')->length);
        $this->assertEquals(1, pq('dl dd.website')->length);
        $this->assertEquals(1, pq('dl dt.task')->length);
        $this->assertEquals(4, pq('dl dd.task')->length);
    }
}


