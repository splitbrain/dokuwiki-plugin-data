<?php


/**
 * @group plugin_data
 * @group plugins
 * @group slow
 */
class db_data_entry_test extends DokuWikiTest {

    protected $pluginsEnabled = array('data', 'sqlite',);

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
    }


    public function setUp() {
        parent::setUp();

        saveWikiText('foo',"====== Page-Heading ======",'summary');
        $req = new TestRequest();
        $req->get(array(),'/doku.php?id=foo');


        saveWikiText('testpage',"---- dataentry Testentry ----\n"
                               . "test1_title: foo|bar\n"
                               . "----\n",'summary');
        //trigger save to db
        $req = new TestRequest();
        $req->get(array(),'/doku.php?id=testpage');
    }

    function test_title_input_id () {

        $test_table = "---- datatable Testtable ----\n"
        . "cols: %pageid%, test1\n"
        . "filter: test1~ *foo*\n";

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_table');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_table, 0, 0, $handler);
        $renderer = new Doku_Renderer_xhtml();
        $plugin->render('xhtml',$renderer,$data);

        $result = $renderer->doc;

        $actual_value = substr($result,strpos($result,'<td class="align test1">')+24);
        $actual_value = substr($actual_value,0,strpos($actual_value,'</td>'));
        $expected_value = 'foo|bar';
        $this->assertSame($expected_value,$actual_value);

        $actual_link = substr($result,strpos($result,'<td class="align pageid">')+25);
        $actual_link = substr($actual_link,strpos($actual_link,'doku.php'));
        $actual_link = substr($actual_link,0,strpos($actual_link,'</a>'));

        $this->assertSame('doku.php?id=testpage" class="wikilink1" title="testpage" data-wiki-id="testpage">testpage',$actual_link);

    }

    function test_title_input_title () {

        $test_table = "---- datatable Testtable ----\n"
            . "cols: %pageid%, test1\n"
            . "filter: test1~ *bar*\n";

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_table');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_table, 0, 0, $handler);
        $renderer = new Doku_Renderer_xhtml();
        $plugin->render('xhtml',$renderer,$data);

        $result = $renderer->doc;

        $actual_value = substr($result,strpos($result,'<td class="align test1">')+24);
        $actual_value = substr($actual_value,0,strpos($actual_value,'</td>'));
        $expected_value = 'foo|bar';
        $this->assertSame($expected_value,$actual_value);

        $actual_link = substr($result,strpos($result,'<td class="align pageid">')+25);
        $actual_link = substr($actual_link,strpos($actual_link,'doku.php'));
        $actual_link = substr($actual_link,0,strpos($actual_link,'</a>'));

        $this->assertSame('doku.php?id=testpage" class="wikilink1" title="testpage" data-wiki-id="testpage">testpage',$actual_link);
    }

    function test_title_input_Heading () {

        $test_table = "---- datatable Testtable ----\n"
            . "cols: %pageid%, test1\n"
            . "filter: test1_title~ *Heading*\n";

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_table');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_table, 0, 0, $handler);
        $renderer = new Doku_Renderer_xhtml();
        $plugin->render('xhtml',$renderer,$data);

        $result = $renderer->doc;

        $actual_value = substr($result,strpos($result,'<td class="align test1">')+24);
        $actual_value = substr($actual_value,0,strpos($actual_value,'</td>'));
        $expected_value = 'foo|bar';
        $this->assertSame($expected_value,$actual_value);

        $actual_link = substr($result,strpos($result,'<td class="align pageid">')+25);
        $actual_link = substr($actual_link,strpos($actual_link,'doku.php'));
        $actual_link = substr($actual_link,0,strpos($actual_link,'</a>'));

        $this->assertSame('doku.php?id=testpage" class="wikilink1" title="testpage" data-wiki-id="testpage">testpage',$actual_link);
    }

    function test_title_input_stackns () {

        $test_table = "---- datatable Testtable ----\n"
            . "cols: %pageid%, test1\n";

        global $ID;
        $ID = 'foo:bar:start';

        /** @var syntax_plugin_data_entry $plugin */
        $plugin = plugin_load('syntax','data_table');

        $handler = new Doku_Handler();
        $data = $plugin->handle($test_table, 0, 0, $handler);
        $renderer = new Doku_Renderer_xhtml();
        $plugin->render('xhtml',$renderer,$data);

        $result = $renderer->doc;

        $actual_value = substr($result,strpos($result,'<td class="align test1">')+24);
        $actual_value = substr($actual_value,0,strpos($actual_value,'</td>'));
        $expected_value = 'foo|bar';
        $this->assertSame($expected_value,$actual_value);

        $actual_link = substr($result,strpos($result,'<td class="align pageid">')+25);
        $actual_link = substr($actual_link,strpos($actual_link,'doku.php'));
        $actual_link = substr($actual_link,0,strpos($actual_link,'</a>'));

        $this->assertSame('doku.php?id=testpage" class="wikilink1" title="testpage" data-wiki-id="testpage">testpage',$actual_link);
    }

}
