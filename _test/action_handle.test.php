<?php
/**
 * @group plugin_data
 * @group plugins
 */
class action_handle_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite');

    protected $action;
    /** @var helper_plugin_data */
    protected $helper;
    /** @var helper_plugin_sqlite */
    protected $db;

    public function tearDown() {
        parent::tearDown();

        $this->db->query('DELETE FROM pages WHERE page = ?','test');
    }

    public function setUp() {
        parent::setUp();

        $this->action = new action_plugin_data();
        $this->helper = plugin_load('helper', 'data');
        $this->db = $this->helper->_getDB();

        $this->db->query('INSERT INTO pages ( pid, page, title , class , lastmod) VALUES
            (?, ?, ?, ?, ?)', 1 , 'test', 'title', 'class', time());
    }

    function testHandleStillPresent() {

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

    function testHandleDelete() {
        $data = array(
            0 => array(
                1 => 'no entry'
            ),
            1 => '',
            2 => 'test'
        );

        $event = new Doku_Event('', $data);
        $this->action->_handle($event, null);

        $res = $this->db->query('SELECT pid FROM pages WHERE page = ?','test');
        $pid = $this->db->res2single($res);
        $this->assertTrue(!$pid);
    }


    private function getTestPageId() {
        $res = $this->db->query('SELECT pid FROM pages WHERE page = ?','test');
        $pid = (int) $this->db->res2single($res);
        return $pid;
    }
}
