<?php

class syntax_plugin_data_entry_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    function testHandle() {
        $plugin = new syntax_plugin_data_entry();

        $entry = "---- dataentry projects ----\n"
               . "type            : web development\n"
               . "volume          : 1 Mrd    # how much do they pay?\n"
               . "employees       : Joe, Jane, Jim\n"
               . "customer_page   : customers:microsoft\n"
               . "deadline_dt     : 2009-08-17\n"
               . "server_pages    : servers:devel01, extern:microsoft\n"
               . "website_url     : http://www.microsoft.com\n"
               . "task_tags       : programming, coding, design, html\n"
               . "----\n";

        $null = null;
        $result = $plugin->handle($entry, 0, 10, $null);

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
}


