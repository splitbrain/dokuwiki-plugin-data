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
        if(!is_null($db) && $db->init('data',dirname(__FILE__).'/db/')){
            return $db;
        }else{
            return false;
        }
    }

    /**
     * Makes sure the given data fits with the given type
     */
    function _cleanData($value, $type, $enum = ''){
        $value = trim($value);
        if(!$value) return '';
        if (trim($enum) !== '' &&
            !preg_match('/(^|,\s*)' . preg_quote_cb($value) . '($|\s*,)/', $enum)) {
            return '';
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
                $mail = '';
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
            default:
                return $value;
        }
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
            switch($column['type']){
                case 'page':
                    if($column['prefix']){
                        $val = $column['prefix'].$val;
                    }else{
                        $val = ':'.$val;
                    }
                    $val .= $column['postfix'];

                    $outs[] = $R->internallink(":$val",NULL,NULL,true);
                    break;
                case 'title':
                    list($id,$title) = explode('|',$val,2);
                    $id = $column['prefix'].$id.$column['postfix'];

                    $outs[] = $R->internallink(":$id",$title,NULL,true);
                    break;
                case 'nspage':
                    // no prefix/postfix here
                    $val = ':'.$column['key'].":$val";

                    $outs[] = $R->internallink($val,NULL,NULL,true);
                    break;
                case 'mail':
                    list($id,$title) = explode(' ',$val,2);
                    $val = $column['prefix'].$val.$column['postfix'];
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
                    $val = $column['prefix'].$val.$column['postfix'];
                    $outs[] = '<a href="'.hsc($val).'" class="urlextern" title="'.hsc($val).'">'.hsc($val).'</a>';
                    break;
                case 'tag':
                    #FIXME handle pre/postfix
                    $outs[] = '<a href="'.wl(str_replace('/',':',cleanID($column['key'])),array('dataflt'=>$column['key'].':'.$val )).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($val)).
                              '" class="wikilink1">'.hsc($val).'</a>';
                    break;
                case 'wiki':
                    global $ID;
                    $oldid = $ID;
                    list($ID,$data) = explode('|',$val,2);
                    $outs[] = p_render('xhtml', p_get_instructions($data), $ignore);
                    $ID = $oldid;
                    break;
                default:
                    $val = $column['prefix'].$val.$column['postfix'];
                    if(substr($column['type'],0,3) == 'img'){
                        $sz = (int) substr($column['type'],3);
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
        $column = array();

        // are mutliple values expected?
        if(strtolower(substr($col,-1)) == 's'){
            $col = substr($col,0,-1);
            $column['multi'] = true;
        }else{
            $column['multi'] = false;
        }

        // get key and type
        list($key,$type) = explode('_',$col,2);
        $column['title'] = $key;
        $key  = utf8_strtolower($key);
        $type = utf8_strtolower($type);
        $column['key']   = $key;

        // fix title for special columns
        if($column['title'] == '%title%'){
            $column['title'] = $this->getLang('page');
            if(!$type) $type = 'title';
        }
        if($column['title'] == '%pageid%'){
            $column['title'] = $this->getLang('title');
            if(!$type) $type = 'page';
        }
        if($column['title'] == '%class%'){
            $column['title'] = $this->getLang('class');
        }

        // check if the type is some alias
        $aliases = $this->_aliases();
        if($aliases[$type]){
            $column['prefix']  = $aliases[$type]['prefix'];
            $column['postfix'] = $aliases[$type]['postfix'];
            $column['type']    = utf8_strtolower($aliases[$type]['type']);
            $column['enum']    = $aliases[$type]['enum'];
            $column['origtype'] = $type;
        }else{
            $column['type'] = $type;
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
            $aliases[$row['name']] = array(
                'type'    => $row['type'],
                'prefix'  => $row['prefix'],
                'postfix' => $row['postfix'],
            );
            if (trim($row['enum']) !== '') {
                $aliases[$row['name']]['enum'] = trim($row['enum']);
            }
        }
        return $aliases;
    }

}
