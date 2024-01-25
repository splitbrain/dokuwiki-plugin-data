<?php

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\plugin\data\Form\DropdownElement;
use dokuwiki\Form\InputElement;
use dokuwiki\Form\CheckableElement;
use dokuwiki\Form\Element;
use dokuwiki\Utf8\PhpString;

/**
 * Class syntax_plugin_data_entry
 */
class syntax_plugin_data_entry extends SyntaxPlugin
{
    /**
     * @var helper_plugin_data will hold the data helper plugin
     */
    public $dthlp;

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->dthlp = plugin_load('helper', 'data');
        if (!$this->dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.', -1);
    }

    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 155;
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *dataentry(?: [ a-zA-Z0-9_]*)?-+\n.*?\n----+', $mode, 'plugin_data_entry');
    }

    /**
     * Handle the match - parse the data
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        if (!$this->dthlp->ready()) return null;

        // get lines
        $lines = explode("\n", $match);
        array_pop($lines);
        $class = array_shift($lines);
        $class = str_replace('dataentry', '', $class);
        $class = trim($class, '- ');

        // parse info
        $data = [];
        $columns = [];
        foreach ($lines as $line) {
            // ignore comments
            preg_match('/^(.*?(?<![&\\\\]))(?:#(.*))?$/', $line, $matches);
            $line = $matches[1];
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) continue;
            $line = preg_split('/\s*:\s*/', $line, 2);

            $column = $this->dthlp->_column($line[0]);
            if (isset($matches[2])) {
                $column['comment'] = $matches[2];
            }
            if ($column['multi']) {
                if (!isset($data[$column['key']])) {
                    // init with empty array
                    // Note that multiple occurrences of the field are
                    // practically merged
                    $data[$column['key']] = [];
                }
                $vals = explode(',', $line[1]);
                foreach ($vals as $val) {
                    $val = trim($this->dthlp->_cleanData($val, $column['type']));
                    if ($val == '') continue;
                    if (!in_array($val, $data[$column['key']])) {
                        $data[$column['key']][] = $val;
                    }
                }
            } else {
                $data[$column['key']] = $this->dthlp->_cleanData($line[1] ?? '', $column['type']);
            }
            $columns[$column['key']] = $column;
        }
        return ['data' => $data, 'cols' => $columns, 'classes' => $class, 'pos' => $pos, 'len' => strlen($match)]; // not utf8_strlen
    }

    /**
     * Create output or save the data
     *
     * @param   $format   string        output format being rendered
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if (is_null($data)) return false;
        if (!$this->dthlp->ready()) return false;

        global $ID;
        switch ($format) {
            case 'xhtml':
                /** @var $renderer Doku_Renderer_xhtml */
                $this->_showData($data, $renderer);
                return true;
            case 'metadata':
                /** @var $renderer Doku_Renderer_metadata */
                $this->_saveData($data, $ID, $renderer->meta['title'] ?? '');
                return true;
            case 'plugin_data_edit':
                /** @var $renderer Doku_Renderer_plugin_data_edit */
                if (is_a($renderer->form, 'Doku_Form')) {
                    $this->_editDataLegacy($data, $renderer);
                } else {
                    $this->_editData($data, $renderer);
                }
                return true;
            default:
                return false;
        }
    }

    /**
     * Output the data in a table
     *
     * @param array $data
     * @param Doku_Renderer_xhtml $R
     */
    public function _showData($data, $R)
    {
        global $ID;
        $ret = '';

        $sectionEditData = ['target' => 'plugin_data', 'hid' => 'data_entry'];
        $data['classes'] .= ' ' . $R->startSectionEdit($data['pos'], $sectionEditData);

        $ret .= '<div class="inline dataplugin_entry ' . $data['classes'] . '"><dl>';
        $class_names = [];
        foreach ($data['data'] as $key => $val) {
            if ($val == '' || is_null($val) || (is_array($val) && count($val) == 0)) continue;
            $type = $data['cols'][$key]['type'];
            if (is_array($type)) {
                $type = $type['type'];
            }
            if ($type === 'hidden') continue;

            $class_name = hsc(sectionID($key, $class_names));
            $ret .= '<dt class="' . $class_name . '">' . hsc($data['cols'][$key]['title']) . '<span class="sep">: </span></dt>';
            $ret .= '<dd class="' . $class_name . '">';
            if (is_array($val)) {
                $cnt = count($val);
                for ($i = 0; $i < $cnt; $i++) {
                    if ($type === 'wiki') {
                        $val[$i] = $ID . '|' . $val[$i];
                    }
                    $ret .= $this->dthlp->_formatData($data['cols'][$key], $val[$i], $R);
                    if ($i < $cnt - 1) {
                        $ret .= '<span class="sep">, </span>';
                    }
                }
            } else {
                if ($type === 'wiki') {
                    $val = $ID . '|' . $val;
                }
                $ret .= $this->dthlp->_formatData($data['cols'][$key], $val, $R);
            }
            $ret .= '</dd>';
        }
        $ret .= '</dl></div>';
        $R->doc .= $ret;
        $R->finishSectionEdit($data['len'] + $data['pos']);
    }

    /**
     * Save date to the database
     */
    public function _saveData($data, $id, $title)
    {
        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return false;

        if (!$title) {
            $title = $id;
        }

        $class = $data['classes'];

        $sqlite->getPdo()->beginTransaction();
        try {
            // store page info
            $this->replaceQuery(
                "INSERT OR IGNORE INTO pages (page,title,class) VALUES (?,?,?)",
                $id,
                $title,
                $class
            );

            // Update title if insert failed (record already saved before)
            $revision = filemtime(wikiFN($id));
            $this->replaceQuery(
                "UPDATE pages SET title = ?, class = ?, lastmod = ? WHERE page = ?",
                $title,
                $class,
                $revision,
                $id
            );

            // fetch page id
            /** @var PDOStatement $res */
            $res = $this->replaceQuery("SELECT pid FROM pages WHERE page = ?", $id);
            $all = $res->fetchAll(\PDO::FETCH_ASSOC);
            $res->closeCursor();
            $pid = (int)$all[0]['pid'];
            if (!$pid) {
                throw new Exception("data plugin: failed saving data");
            }

            // remove old data
            $sqlite->query("DELETE FROM DATA WHERE pid = ?", $pid);

            // insert new data
            foreach ($data['data'] as $key => $val) {
                if (is_array($val)) foreach ($val as $v) {
                    $this->replaceQuery(
                        "INSERT INTO DATA (pid, KEY, VALUE) VALUES (?, ?, ?)",
                        $pid,
                        $key,
                        $v
                    );
                } else {
                    $this->replaceQuery(
                        "INSERT INTO DATA (pid, KEY, VALUE) VALUES (?, ?, ?)",
                        $pid,
                        $key,
                        $val
                    );
                }
            }

            // finish transaction
            $sqlite->getPdo()->commit();
        } catch (\Exception $exception) {
            $sqlite->getPdo()->rollBack();
            msg(hsc($exception->getMessage()), -1);
        }

        return true;
    }

    /**
     *
     * @fixme replace this madness
     * @return bool|mixed
     */
    public function replaceQuery()
    {
        $args = func_get_args();
        $argc = func_num_args();

        if ($argc > 1) {
            for ($i = 1; $i < $argc; $i++) {
                $data = [];
                $data['sql'] = $args[$i];
                $this->dthlp->_replacePlaceholdersInSQL($data);
                $args[$i] = $data['sql'];
            }
        }

        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return false;

        return call_user_func_array(array(&$sqlite, 'query'), $args);
    }


    /**
     * The custom editor for editing data entries
     *
     * Gets called from action_plugin_data::_editform() where also the form member is attached
     *
     * @param array $data
     * @param Doku_Renderer_plugin_data_edit $renderer
     * @deprecated                          _editData() is used since Igor
     */
    protected function _editDataLegacy($data, &$renderer)
    {
        $renderer->form->startFieldset($this->getLang('dataentry'));
        $renderer->form->_content[count($renderer->form->_content) - 1]['class'] = 'plugin__data';
        $renderer->form->addHidden('range', '0-0'); // Adora Belle bugfix

        if ($this->getConf('edit_content_only')) {
            $renderer->form->addHidden('data_edit[classes]', $data['classes']);

            $columns = ['title', 'value', 'comment'];
            $class = 'edit_content_only';
        } else {
            $renderer->form->addElement(form_makeField('text', 'data_edit[classes]', $data['classes'], $this->getLang('class'), 'data__classes'));

            $columns = ['title', 'type', 'multi', 'value', 'comment'];
            $class = 'edit_all_content';

            // New line
            $data['data'][''] = '';
            $data['cols'][''] = ['type' => '', 'multi' => false];
        }

        $renderer->form->addElement("<table class=\"$class\">");

        //header
        $header = '<tr>';
        foreach ($columns as $column) {
            $header .= '<th class="' . $column . '">' . $this->getLang($column) . '</th>';
        }
        $header .= '</tr>';
        $renderer->form->addElement($header);

        //rows
        $n = 0;
        foreach ($data['cols'] as $key => $vals) {
            $fieldid = 'data_edit[data][' . $n++ . ']';
            $content = $vals['multi'] ? implode(', ', $data['data'][$key]) : $data['data'][$key];
            if (is_array($vals['type'])) {
                $vals['basetype'] = $vals['type']['type'];
                if (isset($vals['type']['enum'])) {
                    $vals['enum'] = $vals['type']['enum'];
                }
                $vals['type'] = $vals['origtype'];
            } else {
                $vals['basetype'] = $vals['type'];
            }

            if ($vals['type'] === 'hidden') {
                $renderer->form->addElement('<tr class="hidden">');
            } else {
                $renderer->form->addElement('<tr>');
            }
            if ($this->getConf('edit_content_only')) {
                if (isset($vals['enum'])) {
                    $values = preg_split('/\s*,\s*/', $vals['enum']);
                    if (!$vals['multi']) {
                        array_unshift($values, '');
                    }
                    $content = form_makeListboxField(
                        $fieldid . '[value][]',
                        $values,
                        $data['data'][$key],
                        $vals['title'],
                        '',
                        '',
                        ($vals['multi'] ? ['multiple' => 'multiple'] : [])
                    );
                } else {
                    $classes = 'data_type_' . $vals['type'] . ($vals['multi'] ? 's' : '') . ' '
                        . 'data_type_' . $vals['basetype'] . ($vals['multi'] ? 's' : '');

                    $attr = [];
                    if ($vals['basetype'] == 'date' && !$vals['multi']) {
                        $attr['class'] = 'datepicker';
                    }

                    $content = form_makeField('text', $fieldid . '[value]', $content, $vals['title'], '', $classes, $attr);
                }
                $cells = [hsc($vals['title']) . ':', $content, '<span title="' . hsc($vals['comment']) . '">' . hsc($vals['comment']) . '</span>'];
                foreach (['multi', 'comment', 'type'] as $field) {
                    $renderer->form->addHidden($fieldid . "[$field]", $vals[$field]);
                }
                $renderer->form->addHidden($fieldid . "[title]", $vals['origkey']); //keep key as key, even if title is translated
            } else {
                $check_data = $vals['multi'] ? ['checked' => 'checked'] : [];
                $cells = [
                    form_makeField('text', $fieldid . '[title]', $vals['origkey'], $this->getLang('title')),
                    // when editable, always use the pure key, not a title
                    form_makeMenuField(
                        $fieldid . '[type]',
                        array_merge(
                            ['', 'page', 'nspage', 'title', 'img', 'mail', 'url', 'tag', 'wiki', 'dt', 'hidden'],
                            array_keys($this->dthlp->_aliases())
                        ),
                        $vals['type'],
                        $this->getLang('type')
                    ),
                    form_makeCheckboxField($fieldid . '[multi]', ['1', ''], $this->getLang('multi'), '', '', $check_data),
                    form_makeField('text', $fieldid . '[value]', $content, $this->getLang('value')),
                    form_makeField('text', $fieldid . '[comment]', $vals['comment'], $this->getLang('comment'), '', 'data_comment', ['readonly' => 1, 'title' => $vals['comment']]),
                ];
            }

            foreach ($cells as $index => $cell) {
                $renderer->form->addElement("<td class=\"{$columns[$index]}\">");
                $renderer->form->addElement($cell);
                $renderer->form->addElement('</td>');
            }
            $renderer->form->addElement('</tr>');
        }

        $renderer->form->addElement('</table>');
        $renderer->form->endFieldset();
    }

    /**
     * The custom editor for editing data entries
     *
     * Gets called from action_plugin_data::_editform() where also the form member is attached
     *
     * @param array $data
     * @param Doku_Renderer_plugin_data_edit $renderer
     */
    protected function _editData($data, &$renderer)
    {
        $renderer->form->addFieldsetOpen($this->getLang('dataentry'))->attr('class', 'plugin__data');

        if ($this->getConf('edit_content_only')) {
            $renderer->form->setHiddenField('data_edit[classes]', $data['classes']);

            $columns = ['title', 'value', 'comment'];
            $class = 'edit_content_only';
        } else {
            $renderer->form->addTextInput('data_edit[classes]', $this->getLang('class'))
                ->id('data__classes')
                ->val($data['classes']);

            $columns = ['title', 'type', 'multi', 'value', 'comment'];
            $class = 'edit_all_content';

            // New line
            $data['data'][''] = '';
            $data['cols'][''] = ['type' => '', 'multi' => false];
        }

        $renderer->form->addHTML("<table class=\"$class\">");

        //header
        $header = '<tr>';
        foreach ($columns as $column) {
            $header .= '<th class="' . $column . '">' . $this->getLang($column) . '</th>';
        }
        $header .= '</tr>';
        $renderer->form->addHTML($header);

        //rows
        $n = 0;
        foreach ($data['cols'] as $key => $vals) {
            $fieldid = 'data_edit[data][' . $n++ . ']';
            $content = $vals['multi'] ? implode(', ', $data['data'][$key]) : $data['data'][$key];
            if (is_array($vals['type'])) {
                $vals['basetype'] = $vals['type']['type'];
                if (isset($vals['type']['enum'])) {
                    $vals['enum'] = $vals['type']['enum'];
                }
                $vals['type'] = $vals['origtype'];
            } else {
                $vals['basetype'] = $vals['type'];
            }

            if ($vals['type'] === 'hidden') {
                $renderer->form->addHTML('<tr class="hidden">');
            } else {
                $renderer->form->addHTML('<tr>');
            }
            if ($this->getConf('edit_content_only')) {
                if (isset($vals['enum'])) {
                    $values = preg_split('/\s*,\s*/', $vals['enum']);
                    if (!$vals['multi']) {
                        array_unshift($values, '');
                    }

                    $el = new DropdownElement(
                        $fieldid . '[value]',
                        $values,
                        $vals['title']
                    );
                    $el->useInput(false);
                    $el->attrs(($vals['multi'] ? ['multiple' => 'multiple'] : []));
                    $el->attr('selected', $data['data'][$key]);
                    $el->val($data['data'][$key]);
                } else {
                    $classes = 'data_type_' . $vals['type'] . ($vals['multi'] ? 's' : '') . ' '
                        . 'data_type_' . $vals['basetype'] . ($vals['multi'] ? 's' : '');

                    $attr = [];
                    if ($vals['basetype'] == 'date' && !$vals['multi']) {
                        $attr['class'] = 'datepicker';
                    }

                    $el = new InputElement('text', $fieldid . '[value]', $vals['title']);
                    $el->useInput(false);
                    $el->val($content);
                    $el->addClass($classes);
                    $el->attrs($attr);
                }
                $cells = [
                    hsc($vals['title']) . ':', $el,
                    '<span title="' . hsc($vals['comment'] ?? '') . '">' . hsc($vals['comment'] ?? '') . '</span>'
                ];
                foreach (['multi', 'comment', 'type'] as $field) {
                    $renderer->form->setHiddenField($fieldid . "[$field]", $vals[$field] ?? '');
                }
                $renderer->form->setHiddenField($fieldid . "[title]", $vals['origkey'] ?? ''); //keep key as key, even if title is translated
            } else {
                $check_data = $vals['multi'] ? ['checked' => 'checked'] : [];
                $cells = [];

                $el = new InputElement('text', $fieldid . '[title]', $this->getLang('title'));
                $el->val($vals['origkey'] ?? '');
                $cells[] = $el;

                $el = new \dokuwiki\Form\DropdownElement(
                    $fieldid . '[type]',
                    array_merge(
                        ['', 'page', 'nspage', 'title', 'img', 'mail', 'url', 'tag', 'wiki', 'dt', 'hidden'],
                        array_keys($this->dthlp->_aliases())
                    ),
                    $this->getLang('type')
                );
                $el->val($vals['type']);
                $cells[] = $el;

                $el = new CheckableElement('checkbox', $fieldid . '[multi]', $this->getLang('multi'));
                $el->attrs($check_data);
                $cells[] = $el;

                $el = new InputElement('text', $fieldid . '[value]', $this->getLang('value'));
                $el->val($content);
                $cells[] = $el;

                $el = new InputElement('text', $fieldid . '[comment]', $this->getLang('comment'));
                $el->addClass('data_comment');
                $el->attrs(['readonly' => '1', 'title' => $vals['comment'] ?? '']);
                $el->val($vals['comment'] ?? '');
                $cells[] = $el;
            }

            foreach ($cells as $index => $cell) {
                $renderer->form->addHTML("<td class=\"{$columns[$index]}\">");
                if (is_a($cell, Element::class)) {
                    $renderer->form->addElement($cell);
                } else {
                    $renderer->form->addHTML($cell);
                }
                $renderer->form->addHTML('</td>');
            }
            $renderer->form->addHTML('</tr>');
        }

        $renderer->form->addHTML('</table>');
        $renderer->form->addFieldsetClose();
    }

    /**
     * Escapes the given value against being handled as comment
     *
     * @param $txt
     * @return mixed
     * @todo bad naming
     */
    public static function _normalize($txt)
    {
        return str_replace('#', '\#', trim($txt));
    }

    /**
     * Handles the data posted from the editor to recreate the entry syntax
     *
     * @param array $data data given via POST
     * @return string
     */
    public static function editToWiki($data)
    {
        $nudata = [];

        $len = 0; // we check the maximum lenght for nice alignment later
        foreach ($data['data'] as $field) {
            if (is_array($field['value'])) {
                $field['value'] = implode(', ', $field['value']);
            }
            $field = array_map('trim', $field);
            if ($field['title'] === '') continue;

            $name = syntax_plugin_data_entry::_normalize($field['title']);

            if ($field['type'] !== '') {
                $name .= '_' . syntax_plugin_data_entry::_normalize($field['type']);
            } elseif (substr($name, -1, 1) === 's') {
                $name .= '_'; // when the field name ends in 's' we need to secure it against being assumed as multi
            }
            // 's' is added to either type or name for multi
            if ($field['multi'] === '1') {
                $name .= 's';
            }

            $nudata[] = [$name, syntax_plugin_data_entry::_normalize($field['value']), $field['comment']];
            $len = max($len, PhpString::strlen($nudata[count($nudata) - 1][0]));
        }

        $ret = '---- dataentry ' . trim($data['classes']) . ' ----' . DOKU_LF;
        foreach ($nudata as $field) {
            $ret .= $field[0] . str_repeat(' ', $len + 1 - PhpString::strlen($field[0])) . ': ';
            $ret .= $field[1];
            if ($field[2] !== '') {
                $ret .= ' # ' . $field[2];
            }
            $ret .= DOKU_LF;
        }
        $ret .= "----\n";
        return $ret;
    }
}
