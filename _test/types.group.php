<?php
/**
 * Tests for the field types
 */

require_once(DOKU_INC.'_test/lib/unittest.php');
class data_test_types extends Doku_GroupTest {
    function group_test() {
        $dir = dirname(__FILE__).'/';
        $this->GroupTest('data_test_types');
        foreach(array('wiki') as $type) {
            $this->addTestFile($dir . $type . '.test.php');
        }
    }
}
