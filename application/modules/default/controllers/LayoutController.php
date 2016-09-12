<?php

use Application\Model\Message;

class LayoutController extends Zend_Controller_Action
{
    /**
     * @var Page
     */
    private $_pageTable;

    public function flashMessagesAction()
    {
        $this->view->flashMessages = $this->_helper->flashMessenger->getMessages();
    }

    public function sidebarRightAction()
    {
        if ($this->_helper->user()->logedIn()) {
            $mModel = new Message();
            $count = $mModel->getNewCount($this->_helper->user()->get()->id);

            $this->view->newPersonalMessages = $count;
        }
    }

    /**
     * @return Page
     */
    private function _getPageTable()
    {
        return $this->_pageTable
            ? $this->_pageTable
            : $this->_pageTable = new Pages();
    }

    private function getMenuData($id, $logedIn, $language)
    {
        $table = $this->_getPageTable();
        $db = $this->_getPageTable()->getAdapter();

        $expr = 'pages.id = page_language.page_id and ' .
                $db->quoteInto('page_language.language = ?', $language);

        $select = $db->select()
            ->from($table->info('name'), array(
                'id', 'url', 'class'
            ))
            ->joinLeft('page_language', $expr, null)
            ->where('pages.parent_id = ?', $id)
            ->order('pages.position');
        if ($logedIn) {
            $select->where('NOT pages.guest_only');
        } else {
            $select->where('NOT pages.registered_only');
        }

        $result = array();
        foreach ($db->fetchAll($select) as $row) {
            
            $key = 'page/' . $row['id'] . '/name';
            
            $name = $this->view->translate($key);
            if (!$name) {
                $name = $this->view->translate($key, 'en');
            }
            
            $result[] = array(
                'id'    => $row['id'],
                'url'   => $row['url'],
                'name'  => $name,
                'class' => $row['class']
            );
        }

        return $result;
    }

    private function getMenu($id, $logedIn)
    {
        $select = $this->_getPageTable()->select(true)
            ->where('parent_id = ?', $id)
            ->order('position');
        if ($logedIn) {
            $select->where('NOT guest_only');
        } else {
            $select->where('NOT registered_only');
        }

        return $this->_getPageTable()->fetchAll($select);
    }

    public function mainMenuAction()
    {
        $user = $this->_helper->user()->get();

        $pm = 0;
        if ($user) {
            $mModel = new Message();
            $pm = $mModel->getNewCount($user->id);
        }

        $language = $this->_helper->language();

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $key = 'CATEGORY_MENU_3_' . $language;

        if (!($categories = $cache->load($key))) {

            $categories = array();

            $categoryTable = new Category();
            $categoryLanguageTable = new Category_Language();

            $rows = $categoryTable->fetchAll(array(
                'parent_id is null',
            ), 'short_name');

            foreach ($rows as $row) {

                $langRow = $categoryLanguageTable->fetchRow(array(
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ));

                $categories[] = array(
                    'id'             => $row->id,
                    'url'            => $this->_helper->url->url(array(
                        'controller'       => 'category',
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                        'other'            => null
                    ), 'category', true),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $row->getCarsCount(),
                    'new_cars_count' => $row->getWeekCarsCount(),
                );
            }

            $cache->save($categories, null, array(), 1800);
        }

        $searchHostname = 'www.autowp.ru';

        $languages = array(
            array(
                'name'     => 'Русский',
                'language' => 'ru',
                'hostname' => 'www.autowp.ru',
                'flag'     => 'flag-RU',
            ),
            array(
                'name'     => 'English (beta)',
                'language' => 'en',
                'hostname' => 'en.wheelsage.org',
                'flag'     => 'flag-GB'
            ),
            array(
                'name'     => 'Français (beta)',
                'language' => 'fr',
                'hostname' => 'fr.wheelsage.org',
                'flag'     => 'flag-FR'
            ),
            array(
                'name'     => '中文 (beta)',
                'language' => 'zh',
                'hostname' => 'zh.wheelsage.org',
                'flag'     => 'flag-CN'
            ),
        );

        $scheme = $this->getRequest()->getScheme();
        foreach ($languages as &$item) {
            $active = $item['language'] == $language;
            $item['active'] = $active;
            if ($active) {
                $searchHostname = $item['hostname'];
            }

            $item['url'] = $scheme . '://' . $item['hostname'] . $this->_helper->url->url();
        }
        unset($item); // prevent future bugs

        $logedIn = $this->_helper->user()->logedIn();


        $key = 'MAIN_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '6_' . $language;
        if (!($pages = $cache->load($key))) {
            $pages = $this->getMenuData(2, $logedIn, $language);

            $cache->save($pages, null, array(), 1800);
        }

        $key = 'SECOND_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '12_' . $language;
        if (!($secondMenu = $cache->load($key))) {
            $secondMenu = $this->getMenuData(87, $logedIn, $language);

            foreach ($secondMenu as &$item) {
                switch($item['id']) {
                    case  29: $item['icon'] = 'fa fa-fw fa-upload'; break;
                    case  89: $item['icon'] = 'fa fa-fw fa-comment'; break;
                    case 136: $item['icon'] = 'fa fa-fw fa-info'; break;
                    case  48: $item['icon'] = 'fa fa-fw fa-user'; break;
                    case  90: $item['icon'] = 'fa fa-fw fa-sign-out'; break;
                    case 124: $item['icon'] = 'fa fa-fw fa fa-users'; break;
                    case  91: $item['icon'] = 'fa fa-fw fa fa-pencil'; break;
                }
            }
            unset($item);

            $cache->save($secondMenu, null, array(), 1800);
        }

        $this->view->assign(array(
            'pages'          => $pages,
            'secondMenu'     => $secondMenu,
            'pm'             => $pm,
            'categories'     => $categories,
            'languages'      => $languages,
            'language'       => $language,
            'searchHostname' => $searchHostname
        ));
    }
}