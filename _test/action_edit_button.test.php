<?php

/**
 * @group plugin_data
 * @group plugins
 */
class data_action_plugin_edit_button_test extends DokuWikiTest
{

    protected $pluginsEnabled = array('data', 'sqlite');

    function testSetName()
    {
        $action = new action_plugin_data();
        $data = array(
            'target' => 'plugin_data'
        );
        $event = new Doku_Event('', $data);
        $action->editButton($event, null);

        $this->assertTrue(isset($data['name']));
    }

    function testWrongTarget()
    {
        $action = new action_plugin_data();
        $data = array(
            'target' => 'default target'
        );
        $event = new Doku_Event('', $data);
        $action->editButton($event, null);

        $this->assertFalse(isset($data['name']));
    }

}
