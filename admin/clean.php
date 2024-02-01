<?php

use dokuwiki\Extension\AdminPlugin;

/**
 * DokuWiki Plugin data (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
/**
 * Let admin remove non-existing pages from sqlite db
 */
class admin_plugin_data_clean extends AdminPlugin
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
        return 502;
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
        return $this->getLang('menu_clean');
    }

    /**
     * Carry out required processing
     */
    public function handle()
    {
        if (!isset($_REQUEST['data_go']) || !checkSecurityToken()) return;

        $sqlite = $this->dthlp->getDB();
        if (!$sqlite) return;

        $rows = $sqlite->queryAll('SELECT pid, page FROM pages');

        $count = 0;
        foreach ($rows as $row) {
            if (!page_exists($row['page'])) {
                $sqlite->exec('DELETE FROM data WHERE pid = ?', $row['pid']);
                $sqlite->exec('DELETE FROM pages WHERE pid = ?', $row['pid']);
                $count++;
            }
        }

        msg(sprintf($this->getLang('pages_del'), $count), 1);
    }

    /**
     * Render HTML output
     */
    public function html()
    {

        echo $this->locale_xhtml('intro_clean');

        $form = new Doku_Form(['method' => 'post']);
        $form->addHidden('page', 'data_clean');
        $form->addHidden('data_go', 'go');

        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('submit_clean')));
        $form->printForm();
    }
}
