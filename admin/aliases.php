<?php

/**
 * DokuWiki Plugin data (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\ErrorHandler;
use dokuwiki\Utf8\PhpString;

/**
 * Administration form for configuring the type aliases
 */
class admin_plugin_data_aliases extends AdminPlugin
{
    /**
     * will hold the data helper plugin
     * @var helper_plugin_data
     */
    protected $dthlp;

    /**
     * Constructor. Load helper plugin
     */
    public function __construct()
    {
        $this->dthlp = plugin_load('helper', 'data');
    }

    /**
     * Determine position in list in admin window
     * Lower values are sorted up
     *
     * @return int
     */
    public function getMenuSort()
    {
        return 501;
    }

    /**
     * Return true for access only by admins (config:superuser) or false if managers are allowed as well
     *
     * @return bool
     */
    public function forAdminOnly()
    {
        return true;
    }

    /**
     * Return the text that is displayed at the main admin menu
     *
     * @param string $language lang code
     * @return string menu string
     */
    public function getMenuText($language)
    {
        return $this->getLang('menu_alias');
    }

    /**
     * Carry out required processing
     */
    public function handle()
    {
        global $INPUT;
        if (!$INPUT->has('d') || !checkSecurityToken()) return;

        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return;

        $sqlite->getPdo()->beginTransaction();
        try {
            $sqlite->exec('DELETE FROM aliases');

            foreach ($INPUT->arr('d') as $row) {
                $row = array_map('trim', $row);
                $row['name'] = PhpString::strtolower($row['name']);
                $row['name'] = rtrim($row['name'], 's');
                if (!$row['name']) continue;

                // Clean enum
                $arr = preg_split('/\s*,\s*/', $row['enum']);
                $arr = array_unique($arr);
                $row['enum'] = implode(', ', $arr);

                $sqlite->saveRecord('aliases', $row);
            }
            $sqlite->getPdo()->commit();
        } catch (\Exception $exception) {
            msg(hsc($exception->getMessage()), -1);
            ErrorHandler::logException($exception);
            $sqlite->getPdo()->rollBack();
        }
    }

    /**
     * Output html of the admin page
     */
    public function html()
    {
        $sqlite = $this->dthlp->_getDB();
        if (!$sqlite) return;

        echo $this->locale_xhtml('admin_intro');

        $sql = 'SELECT * FROM aliases ORDER BY name';
        $rows = $sqlite->queryAll($sql);

        $form = new Doku_Form(['method' => 'post']);
        $form->addHidden('page', 'data_aliases');
        $form->addElement(
            '<table class="inline">' .
            '<tr>' .
            '<th>' . $this->getLang('name') . '</th>' .
            '<th>' . $this->getLang('type') . '</th>' .
            '<th>' . $this->getLang('prefix') . '</th>' .
            '<th>' . $this->getLang('postfix') . '</th>' .
            '<th>' . $this->getLang('enum') . '</th>' .
            '</tr>'
        );

        // add empty row for adding a new entry
        $rows[] = ['name' => '', 'type' => '', 'prefix' => '', 'postfix' => '', 'enum' => ''];

        $cur = 0;
        foreach ($rows as $row) {
            $form->addElement('<tr>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d[' . $cur . '][name]', $row['name'], ''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeMenuField(
                'd[' . $cur . '][type]',
                ['', 'page', 'title', 'mail', 'url', 'dt', 'wiki', 'tag', 'hidden', 'img'], //'nspage' don't support post/prefixes
                $row['type'],
                ''
            ));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d[' . $cur . '][prefix]', $row['prefix'], ''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d[' . $cur . '][postfix]', $row['postfix'], ''));
            $form->addElement('</td>');

            $form->addElement('<td>');
            $form->addElement(form_makeTextField('d[' . $cur . '][enum]', $row['enum'], ''));
            $form->addElement('</td>');

            $form->addElement('</tr>');

            $cur++;
        }

        $form->addElement('</table>');
        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('submit')));
        $form->printForm();
    }
}
