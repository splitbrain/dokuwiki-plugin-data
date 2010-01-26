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
                    $outs[] = $R->internallink(":$val",NULL,NULL,true);
                    break;
                case 'title':
                    list($id,$title) = explode('|',$val,2);
                    $outs[] = $R->internallink(":$id",$title,NULL,true);
                    break;
                case 'nspage':
                    $outs[] = $R->internallink(':'.$column['key'].":$val",NULL,NULL,true);
                    break;
                case 'mail':
                    list($id,$title) = explode(' ',$val,2);
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
                    $outs[] = '<a href="'.hsc($val).'" class="urlextern" title="'.hsc($val).'">'.hsc($val).'</a>';
                    break;
                case 'tag':
                    $outs[] = '<a href="'.wl(str_replace('/',':',cleanID($key)),array('dataflt'=>$column['key'].':'.$val )).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($val)).
                              '" class="wikilink1">'.hsc($val).'</a>';
                    break;
                default:
                    if(substr($column['type'],0,3) == 'img'){
                        $sz = (int) substr($type,3);
                        if(!$sz) $sz = 40;
                        $title = $key.': '.basename(str_replace(':','/',$val));
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
        if($column['title'] == '%title%')  $column['title'] = 'page'; #FIXME localize
        if($column['title'] == '%pageid%') $column['title'] = 'pagename'; #FIXME localize

        // check if the type is some alias
        $aliases = $this->_aliases();
        if($aliases[$type]){
            $column['prefix']  = $aliases[$type]['prefix'];
            $column['postfix'] = $aliases[$type]['postfix'];
            $column['type']    = utf8_strtolower($aliases[$type]['type']);
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
        }
        return $aliases;
    }

}
