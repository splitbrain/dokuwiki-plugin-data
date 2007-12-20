<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


/**
 * This is the base class for all syntax classes, providing some general stuff
 */
class syntaxbase_plugin_data extends DokuWiki_Syntax_Plugin {

    var $db = null;

    /**
     * constructor
     */
    function syntaxbase_plugin_data(){
        if (!extension_loaded('sqlite')) {
            $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
            @dl($prefix . 'sqlite.' . PHP_SHLIB_SUFFIX);
        }

        if(!function_exists('sqlite_open')){
            msg('data plugin: SQLite support missing in this PHP install - plugin will not work',-1);
        }
    }

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'andi@splitbrain.org',
            'date'   => '2007-12-14',
            'name'   => 'Structured Data Plugin',
            'desc'   => 'Add and query structured data in your wiki',
            'url'    => 'http://wiki.splitbrain.org/plugins:data',
        );
    }


    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }

    /**
     * Makes sure the given data fits with the given type
     */
    function _cleanData($value, $type){
        switch($type){
            case 'page':
                return cleanID($value);
            case 'dt':
                $value = trim($value);
                if(preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/',$value,$m)){
                    return sprintf('%d-%02d-%02d',$m[1],$m[2],$m[3]);
                }
                return '';
            default:
                return $value;
        }
    }

    /**
     * Return XHTML formated data, depending on type
     */
    function _formatData($value, $type, &$R){
        switch($type){
            case 'page':
                return $R->internallink($value,NULL,NULL,true);
            default:
                return hsc($value);
        }
    }

    /**
     * Split a column name into its parts
     */
    function _column($col){
        $col = utf8_strtolower($col);
        if(substr($col,-1) == 's'){
            $col = substr($col,0,-1);
            $multi = true;
        }else{
            $multi = false;
        }
        list($key,$type) = explode('_',$col,2);
        return array($key,$type,$multi);
    }


    /**
     * Open the database
     */
    function _dbconnect(){
        global $conf;

        $dbfile = $conf['cachedir'].'/dataplugin.sqlite';
        $init   = (!@file_exists($dbfile) || !@filesize($dbfile));

        $error='';
        $this->db = sqlite_open($dbfile, 0666, $error);
        if(!$this->db){
            msg("data plugin: failed to open SQLite database ($error)",-1);
            return false;
        }

        if($init) $this->_initdb();
        return true;
    }


    /**
     * create the needed tables
     */
    function _initdb(){
        sqlite_query($this->db,'CREATE TABLE pages (pid INTEGER PRIMARY KEY, page);');
        sqlite_query($this->db,'CREATE UNIQUE INDEX idx_page ON pages(page);');
        sqlite_query($this->db,'CREATE TABLE data (eid INTEGER PRIMARY KEY, pid INTEGER, key, value);');
        sqlite_query($this->db,'CREATE INDEX idx_key ON data(key);');
        sqlite_query($this->db,'CREATE TABLE meta (key PRIMARY KEY, type, multi);');
    }
}
