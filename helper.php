<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/infoutils.php');


/**
 * This is the base class for all syntax classes, providing some general stuff
 */
class helper_plugin_data extends DokuWiki_Plugin {

    var $db = null;

    /**
     * constructor
     */
    function helper_plugin_data(){
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
            'date'   => '2008-02-08',
            'name'   => 'Structured Data Plugin',
            'desc'   => 'Add and query structured data in your wiki',
            'url'    => 'http://wiki.splitbrain.org/plugins:data',
        );
    }

    /**
     * Makes sure the given data fits with the given type
     */
    function _cleanData($value, $type){
        $value = trim($value);
        if(!$value) return '';
        switch($type){
            case 'page':
                return cleanID($value);
            case 'nspage':
                return cleanID($value);
            case 'dt':
                if(preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/',$value,$m)){
                    return sprintf('%d-%02d-%02d',$m[1],$m[2],$m[3]);
                }
                return '';
            case 'url':
                $value = strtolower($value);
                if(!preg_match('!^[a-z]+://!',$value)) $value='http://'.$value;
            default:
                return $value;
        }
    }

    /**
     * Return XHTML formated data, depending on type
     */
    function _formatData($key, $value, $type, &$R){
        $vals = explode("\n",$value);
        $outs = array();
        foreach($vals as $val){
            $val = trim($val);
            if($val=='') continue;
            switch($type){
                case 'page':
                    $outs[] = $R->internallink(":$val",NULL,NULL,true);
                    break;
                case 'title':
                    list($id,$title) = explode('|',$val,2);
                    $outs[] = $R->internallink(":$id",$title,NULL,true);
                    break;
                case 'nspage':
                    $outs[] = $R->internallink(":$key:$val",NULL,NULL,true);
                    break;
                case 'url':
                    $outs[] = '<a href="'.hsc($val).'" class="urlextern" title="'.hsc($val).'">'.hsc($val).'</a>';
                    break;
                case 'tag':
                    $outs[] = '<a href="'.wl(str_replace('/',':',cleanID($key)),array('dataflt'=>$key.':'.$val )).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($val)).
                              '" class="wikilink1">'.hsc($val).'</a>';
                    break;
                default:
                    $outs[] = hsc($val);
            }
        }
        return join(', ',$outs);
    }

    /**
     * Split a column name into its parts
     *
     * @returns array with key, type, ismulti, title
     */
    function _column($col){
        if(strtolower(substr($col,-1)) == 's'){
            $col = substr($col,0,-1);
            $multi = true;
        }else{
            $multi = false;
        }
        list($key,$type) = explode('_',$col,2);
        return array(utf8_strtolower($key),utf8_strtolower($type),$multi,$key);
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
        sqlite_query($this->db,'CREATE TABLE pages (pid INTEGER PRIMARY KEY, page, title);');
        sqlite_query($this->db,'CREATE UNIQUE INDEX idx_page ON pages(page);');
        sqlite_query($this->db,'CREATE TABLE data (eid INTEGER PRIMARY KEY, pid INTEGER, key, value);');
        sqlite_query($this->db,'CREATE INDEX idx_key ON data(key);');
    }
}
