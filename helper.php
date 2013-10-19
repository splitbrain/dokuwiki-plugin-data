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
     * @var helper_plugin_sqlite initialized via _getDb()
     */
    protected $db = null;

    /**
     * @var array stores the alias definitions
     */
    protected $aliases = null;

    /**
     * @var array stores custom key localizations
     */
    protected $locs = array();

    /**
     * Constructor
     *
     * Loads custom translations
     */
    public function __construct(){
        $this->loadLocalizedLabels();
    }

    private function  loadLocalizedLabels() {
        $lang = array();
        $path = DOKU_CONF.'/lang/en/data-plugin.php';
        if(file_exists($path)) include($path);
        $path = DOKU_CONF.'/lang/'.$this->determineLang().'/data-plugin.php';
        if(file_exists($path)) include($path);
        foreach ($lang as $key => $val) {
            $lang[utf8_strtolower($key)] = $val;
        }
        $this->locs = $lang;
    }

    protected function  determineLang() {
        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper','translation');
        if ($trans) {
            $value = $trans->getLangPart(getID());
            if ($value) return $value;
        }
        global $conf;
        return $conf['lang'];
    }

    /**
     * Simple function to check if the database is ready to use
     */
    public function ready(){
        return (bool) $this->_getDB();
    }

    /**
     * @return helper_plugin_sqlite load the sqlite helper
     */
    function _getDB(){
        if ($this->db === null) {
            $this->db = plugin_load('helper', 'sqlite');
            if ($this->db === null) {
                msg('The data plugin needs the sqlite plugin', -1);
                return false;
            }
            if(!$this->db->init('data',dirname(__FILE__).'/db/')){
                $db = null;
                return false;
            }
            $this->db->create_function('DATARESOLVE',array($this,'_resolveData'),2);
        }
        return $this->db;
    }

    /**
     * Makes sure the given data fits with the given type
     */
    function _cleanData($value, $type){
        $value = trim($value);
        if(!$value AND $value!=='0') return '';
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
                if ($value === '%now%') {
                    return $value;
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

    /**
     * Add pre and postfixs to the given value
     *
     * $type may be an column array with pre and postfixes
     */
    function _addPrePostFixes($type, $val, $pre='', $post='') {
        if (is_array($type)) {
            if (isset($type['prefix'])) $pre = $type['prefix'];
            if (isset($type['postfix'])) $post = $type['postfix'];
        }
        $val = $pre.$val.$post;
        $val = $this->replacePlaceholders($val);
        return $val;
    }

    /**
     * Resolve a value according to its column settings
     *
     * This function is registered as a SQL function named DATARESOLVE
     */
    function _resolveData($value, $colname){
        // resolve pre and postfixes
        $column = $this->_column($colname);
        $value = $this->_addPrePostFixes($column['type'], $value);

        // for pages, resolve title
        $type = $column['type'];
        if(is_array($type)) $type = $type['type'];
        if($type == 'title' || ($type == 'page' && useHeading('content'))){
            $id = $value;
            if($type == 'title'){
                list($id,) = explode('|',$value,2);
            }
            //DATARESOLVE is only used with the 'LIKE' comparator, so concatenate the different strings is fine.
            $value .= ' ' . p_get_first_heading($id);
        }
        return $value;
    }

    /**
     * Return XHTML formated data, depending on column type
     *
     * @param $column
     * @param $value
     * @param $R Doku_Renderer_xhtml
     * @return string
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
                    $val = $this->_addPrePostFixes($column['type'], $val);
                    $outs[] = $R->internallink($val,null,null,true);
                    break;
                case 'title':
                case 'pageid':
                    list($id,$title) = explode('|',$val,2);
                    $id = $this->_addPrePostFixes($column['type'], $id);
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
                    $outs[] = $this->external_link($val,false,'urlextern');
                    break;
                case 'tag':
                    // per default use keyname as target page, but prefix on aliases
                    if(!is_array($column['type'])){
                        $target = $column['key'].':';
                    }else{
                        $target = $this->_addPrePostFixes($column['type'],'');
                    }

                    $outs[] = '<a href="'.wl(str_replace('/',':',cleanID($target)), $this->_getTagUrlparam($column, $val)).
                              '" title="'.sprintf($this->getLang('tagfilter'),hsc($val)).
                              '" class="wikilink1">'.hsc($val).'</a>';
                    break;
                case 'timestamp':
                    $outs[] = dformat($val);
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
        $column = array(
            'colname' => $col,
            'multi'   => ($matches[3] === 's'),
            'key'     => utf8_strtolower($matches[1]),
            'title'   => $matches[1],
            'type'    => utf8_strtolower($matches[2])
        );

        // fix title for special columns
        static $specials = array('%title%'   => array('page', 'title'),
                                 '%pageid%'  => array('title', 'page'),
                                 '%class%'   => array('class'),
                                 '%lastmod%' => array('lastmod','timestamp'));
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

        // use custom localization for keys
        if(isset($this->locs[$column['key']])){
            $column['title'] = $this->locs[$column['key']];
        }

        return $column;
    }

    /**
     * Load defined type aliases
     */
    function _aliases(){
        if(!is_null($this->aliases)) return $this->aliases;

        $sqlite = $this->_getDB();
        if(!$sqlite) return array();

        $this->aliases = array();
        $res = $sqlite->query("SELECT * FROM aliases");
        $rows = $sqlite->res2arr($res);
        foreach($rows as $row){
            $name = $row['name'];
            unset($row['name']);
            $this->aliases[$name] = array_filter(array_map('trim', $row));
            if (!isset($this->aliases[$name]['type'])) $this->aliases[$name]['type'] = '';
        }
        return $this->aliases;
    }

    /**
     * Parse a filter line into an array
     *
     * @param $filterline
     * @return mixed - array on success, false on error
     */
    function _parse_filter($filterline){
        //split filterline on comparator
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
            $sqlite = $this->_getDB();
            if(!$sqlite) return false;
            $val = $sqlite->escape_string($val); //pre escape

            return array('key'     => $column['key'],
                         'value'   => $val,
                         'compare' => $com,
                         'colname' => $column['colname'],
                         'type'    => $column['type']
                        );
        }
        msg('Failed to parse filter "'.hsc($filterline).'"',-1);
        return false;
    }

    /**
     * Replace placeholders in sql
     */
    function _replacePlaceholdersInSQL(&$data){
        // allow current user name in filter:
        $data['sql'] = str_replace('%user%', $_SERVER['REMOTE_USER'], $data['sql']);
        // allow current date in filter:
        $data['sql'] = str_replace('%now%', dformat(null, '%Y-%m-%d'),$data['sql']);

        // language filter
        $data['sql'] = $this->makeTranslationReplacement($data['sql']);
    }

    public function makeTranslationReplacement($data) {
        global $conf;
        global $ID;

        $patterns[] = '%lang%';
        if (isset($conf['lang_before_translation'])) {
            $values[] = $conf['lang_before_translation'];
        } else {
            $values[] = $conf['lang'];
        }

        // if translation plugin available, get current translation (empty for default lang)
        $patterns[] = '%trans%';
        $trans = plugin_load('helper','translation');
        if($trans) {
            $local = $trans->getLangPart($ID);
            if ($local === '') {
                $local = $conf['lang'];
            }
            $values[] = $local;
        }
        else $values[]   = '';
        return str_replace($patterns, $values, $data);
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

    /**
     * get current URL parameters
     */
    function _get_current_param($returnURLparams=true){
        $cur_params = array();
        if(isset($_REQUEST['dataflt'])){
            $cur_params = $this->_a2ua('dataflt', $_REQUEST['dataflt']);
        }
        if (isset($_REQUEST['datasrt'])) {
            $cur_params['datasrt'] = $_REQUEST['datasrt'];
        }
        if (isset($_REQUEST['dataofs'])) {
            $cur_params['dataofs'] = $_REQUEST['dataofs'];
        }

        //combine key and value
        if(!$returnURLparams){
            $flat_param=array();
            foreach($cur_params as $key => $val){
                $flat_param[]=$key.$val;
            }
            $cur_params=$flat_param;
        }
        return $cur_params;
    }

    /**
     * Get url parameters, remove all filters for given column and add filter for desired tag
     * @param array  $column
     * @param string $tag
     * @return array of url parameters
     */
    function _getTagUrlparam($column, $tag) {
        $param = array();

        if(isset($_REQUEST['dataflt'])) {
            $param = (array) $_REQUEST['dataflt'];

            //remove all filters equal to column
            foreach($param as $key => $flt) {
                if(!is_numeric($key)) $flt = $key.$flt;
                $filter = $this->_parse_filter($flt);
                if($filter['key'] == $column['key']) {
                    unset($param[$key]);
                }
            }
        }
        $param[] = $column['key']."_=$tag";
        $param   = $this->_a2ua('dataflt', $param);

        if(isset($_REQUEST['datasrt'])) {
            $param['datasrt'] = $_REQUEST['datasrt'];
        }
        if(isset($_REQUEST['dataofs'])) {
            $param['dataofs'] = $_REQUEST['dataofs'];
        }

        return $param;
    }

    private function replacePlaceholders($value) {
        return $this->makeTranslationReplacement($value);
    }
}
