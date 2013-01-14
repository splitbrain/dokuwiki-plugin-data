<?php

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
