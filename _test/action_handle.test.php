<?php

use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * @group plugin_data
 * @group plugins
 */
class action_handle_test extends DokuWikiTest
{

    protected $pluginsEnabled = array('data', 'sqlite');

    protected $action;
    /** @var helper_plugin_data */
    protected $helper;
    /** @var SQLiteDB */
    protected $db;

    public function tearDown(): void
    {
        parent::tearDown();

        $this->db->exec('DELETE FROM pages WHERE page = ?', 'test');
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->action = new action_plugin_data();
        $this->helper = plugin_load('helper', 'data');
        $this->db = $this->helper->_getDB();

        $this->db->exec(
            'INSERT INTO pages ( pid, page, title , class , lastmod) VALUES (?, ?, ?, ?, ?)',
            [1, 'test', 'title', 'class', time()]
        );
    }

    function testHandleStillPresent()
    {

        $data = array(
            0 => array(
                1 => 'dataentry'
            ),
            1 => '',
            2 => 'test'
        );
        $event = new Doku_Event('', $data);
        $this->action->_handle($event, null);

        $pid = $this->getTestPageId();
        $this->assertFalse(!$pid);
    }

    function testHandleDelete()
    {
        $data = array(
            0 => array(
                1 => 'no entry'
            ),
            1 => '',
            2 => 'test'
        );

        $event = new Doku_Event('', $data);
        $this->action->_handle($event, null);

        $pid = $this->db->queryValue('SELECT pid FROM pages WHERE page = ?', 'test');
        $this->assertTrue(!$pid);
    }


    private function getTestPageId()
    {
        $pid = (int) $this->db->queryValue('SELECT pid FROM pages WHERE page = ?', 'test');
        return $pid;
    }
}
