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

    /**
     * load the sqlite helper
     */
    function _getDB(){
        $db =& plugin_load('helper', 'sqlite');
        if (is_null($db)) {
            msg('The data plugin needs the sqlite plugin', -1);
            return false;
        }
        if($db->init('data',dirname(__FILE__).'/db/')){
            return $db;
        }else{
            return false;
        }
    }

    /**
     * Makes sure the given data fits with the given type
     */
    function _cleanData($value, $type){
        $value = trim($value);
        if(!$value) return '';
        if (is_array($type)) {
            if (isset($type['enum']) &&
                !preg_match('/(^|,\s*)' . preg_quote_cb($value) . '($|\s*,)/', $type['enum'])) {
                return '';
            }
            $type = $type['type'];
        }
        switch($type){
            case 'dt':
                if(preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/',$value,$m)){
                    return sprintf('%d-%02d-%02d',$m[1],$m[2],$m[3]);
                }
                return '';
            case 'url':
                if(!preg_match('!^[a-z]+://!i',$value)) $value='http://'.$value;
                return $value;
            case 'mail':
                $email = '';
                $name = '';
                $part = '';
                $parts = preg_split('/\s+/',$value);
                do{
                    $part = array_shift($parts);
                    if(!$email && mail_isvalid($part)){
                        $email = strtolower($part);
                        continue;
                    }
                    $name .= $part.' ';
                }while($part);
                return trim($email.' '.$name);
            case 'page': case 'nspage':
                return cleanID($value);
            default:
                return $value;
        }
    }

    function _addPrePostFixes($type, $val, $pre='', $post='') {
        if (is_array($type)) {
            if (isset($type['prefix'])) $pre = $type['prefix'];
            if (isset($type['postfix'])) $post = $type['postfix'];
        }
        return $pre.$val.$post;
    }

    /**
     * Return XHTML formated data, depending on column type
     */
    function _formatData($column, $value, &$R){
        global $conf;
        $vals = explode("\n",$value);
        $outs = array();
        foreach($vals as $val){
            $val = trim($val);
            if($val=='') continue;
            $type = $column['type'];
            if (is_array($type)) $type = $type['type'];
            switch($type){
                case 'page':
                    $val = $this->_addPrePostFixes($column['type'], $val, ':');
                    $outs[] = $R->internallink($val,null,null,true);
                    break;
                case 'title':
                case 'pageid':
                    list($id,$title) = explode('|',$val,2);
                    $id = $this->_addPrePostFixes($column['type'], $id, ':');
                    $outs[] = $R->internallink($id,$title,null,true);
                    break;
                case 'nspage':
                    // no prefix/postfix here
                    $val = ':'.$column['key'].":$val";

                    $outs[] = $R->internallink($val,null,null,true);
                    break;
                case 'mail':
                    list($id,$title) = explode(' ',$val,2);
                    $id = $this->_addPrePostFixes($column['type'], $id);
                    $id = obfuscate(hsc($id));
                    if(!$title){
                        $title = $id;
                    }else{
                        $title = hsc($title);
                    }
                    if($conf['mailguard'] == 'visible') $id = rawurlencode($id);
                    $outs[] = '<a href="mailto:'.$id.'" class="mail" title="'.$id.'">'.$title.'</a>';
                    break;
                case 'url':
                    $val = $this->_addPrePostFixes($column['type'], $val);
                    $outs[] = '<a href="'.hsc($val).'" class="urlextern" title="'.hsc($val).'">'.hsc($val).'</a>';
                    break;
                case 'tag':
                    // per default use keyname as target page, but prefix on aliases
                    if(!is_array($column['type'])){
                        $target = $column['key'].':';
                    }else{
                        $target = $this->_addPrePostFixes($column['type'],'');
                    }

                    $outs[] = '<a href="'.wl(str_replace('/',':',cleanID($target)),array('dataflt'=>$column['key'].'='.$val )).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($val)).
                              '" class="wikilink1">'.hsc($val).'</a>';
                    break;
                case 'wiki':
                    global $ID;
                    $oldid = $ID;
                    list($ID,$data) = explode('|',$val,2);
                    $data = $this->_addPrePostFixes($column['type'], $data);
                    // Trim document_{start,end}, p_{open,close}
                    $ins = array_slice(p_get_instructions($data), 2, -2);
                    $outs[] = p_render('xhtml', $ins, $byref_ignore);
                    $ID = $oldid;
                    break;
                default:
                    $val = $this->_addPrePostFixes($column['type'], $val);
                    if(substr($type,0,3) == 'img'){
                        $sz = (int) substr($type,3);
                        if(!$sz) $sz = 40;
                        $title = $column['key'].': '.basename(str_replace(':','/',$val));
                        $outs[] = '<a href="'.ml($val).'" class="media" rel="lightbox"><img src="'.ml($val,"w=$sz").'" alt="'.hsc($title).'" title="'.hsc($title).'" width="'.$sz.'" /></a>';
                    }else{
                        $outs[] = hsc($val);
                    }
            }
        }
        return join(', ',$outs);
    }

    /**
     * Split a column name into its parts
     *
     * @returns array with key, type, ismulti, title, opt
     */
    function _column($col){
        preg_match('/^([^_]*)(?:_(.*))?((?<!s)|s)$/', $col, $matches);
        $column = array('multi' => ($matches[3] === 's'),
                        'key'   => utf8_strtolower($matches[1]),
                        'title' => $matches[1],
                        'type'  => utf8_strtolower($matches[2]));

        // fix title for special columns
        static $specials = array('%title%'  => array('page', 'title'),
                                 '%pageid%' => array('title', 'page'),
                                 '%class%'  => array('class'));
        if (isset($specials[$column['title']])) {
            $s = $specials[$column['title']];
            $column['title'] = $this->getLang($s[0]);
            if($column['type'] === '' && isset($s[1])) {
                $column['type'] = $s[1];
            }
        }

        // check if the type is some alias
        $aliases = $this->_aliases();
        if(isset($aliases[$column['type']])){
            $column['origtype'] = $column['type'];
            $column['type']     = $aliases[$column['type']];
        }
        return $column;
    }

    /**
     * Load defined type aliases
     */
    function _aliases(){
        static $aliases = null;
        if(!is_null($aliases)) return $aliases;

        $sqlite = $this->_getDB();
        if(!$sqlite) return array();

        $aliases = array();
        $res = $sqlite->query("SELECT * FROM aliases");
        $rows = $sqlite->res2arr($res);
        foreach($rows as $row){
            $name = $row['name'];
            unset($row['name']);
            $aliases[$name] = array_filter(array_map('trim', $row));
            if (!isset($aliases[$name]['type'])) $aliases[$name]['type'] = '';
        }
        return $aliases;
    }

    /**
     * Parse a filter line into an array
     *
     * @return mixed - array on success, false on error
     */
    function _parse_filter($filterline){
        if(preg_match('/^(.*?)([\*=<>!~]{1,2})(.*)$/',$filterline,$matches)){
            $column = $this->_column(trim($matches[1]));

            $com = $matches[2];
            $aliasses = array('<>' => '!=', '=!' => '!=', '~!' => '!~',
                              '==' => '=',  '~=' => '~',  '=~' => '~');

            if (isset($aliasses[$com])) {
                $com = $aliasses[$com];
            } elseif (!preg_match('/(!?[=~])|([<>]=?)|(\*~)/', $com)) {
                msg('Failed to parse comparison "'.hsc($com).'"',-1);
                return false;
            }

            $val = trim($matches[3]);
            // allow current user name in filter:
            $val = str_replace('%user%',$_SERVER['REMOTE_USER'],$val);
            // allow current date in filter:
            $val = str_replace('%now%', dformat(null, '%Y-%m-%d'),$val);

            if(strpos($com, '~') !== false) {
                if ($com === '*~') {
                    $val = '*' . $val . '*';
                    $com = '~';
                }
                $val = str_replace('*','%',$val);
                if ($com == '!~'){
                    $com = 'NOT LIKE';
                } else {
                    $com = 'LIKE';
                }
            } else {
                // Clean if there are no asterisks I could kill
                $val = $this->_cleanData($val, $column['type']);
            }
            $val = sqlite_escape_string($val); //pre escape

            return array('key'     => $column['key'],
                         'value'   => $val,
                         'compare' => $com,
                        );
        }
        msg('Failed to parse filter "'.hsc($filterline).'"',-1);
        return false;
    }

    /**
     * Get filters given in the request via GET or POST
     */
    function _get_filters(){
        $flt = array();
        $filters = array();

        if(!isset($_REQUEST['dataflt'])){
            $flt = array();
        }elseif(!is_array($_REQUEST['dataflt'])){
            $flt = (array) $_REQUEST['dataflt'];
        }else{
            $flt = $_REQUEST['dataflt'];
        }
        foreach($flt as $key => $line){
            // we also take the column and filtertype in the key:
            if(!is_numeric($key)) $line = $key.$line;
            $f = $this->_parse_filter($line);
            if(is_array($f)){
                $f['logic'] = 'AND';
                $filters[] = $f;
            }
        }
        return $filters;
    }

    /**
     * prepare an array to be passed through buildURLparams()
     */
    function _a2ua($name,$array){
        $urlarray = array();
        foreach((array) $array as $key => $val){
            $urlarray[$name.'['.$key.']'] = $val;
        }
        return $urlarray;
    }

}
