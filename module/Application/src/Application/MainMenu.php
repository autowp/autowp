<?php

namespace Application;

use Application\Model\Message;
use Application\Language;
use Zend_Cache_Manager;

use Zend\Router\Http\TreeRouteStack;

use Category;
use Category_Language;
use Pages;

class MainMenu
{
    /**
     * @var Page
     */
    private $pageTable;

    private $request;

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @var Language
     */
    private $language;

    /**
     * @var Zend_Cache_Manager
     */
    private $cacheManager;

    public function __construct($request, TreeRouteStack $router, Language $language, Zend_Cache_Manager $cacheManager)
    {
        $this->request = $request;
        $this->router = $router;
        $this->language = $language;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @return Page
     */
    private function getPageTable()
    {
        return $this->pageTable
            ? $this->pageTable
            : $this->pageTable = new Pages();
    }

    private function getMenuData($id, $logedIn, $language)
    {
        $table = $this->getPageTable();
        $db = $table->getAdapter();

        $expr = 'pages.id = page_language.page_id and ' .
                $db->quoteInto('page_language.language = ?', $language);

        $select = $db->select()
            ->from($table->info('name'), [
                'id', 'url', 'class',
                'name' => 'if(length(page_language.name) > 0, page_language.name, pages.name)'
            ])
            ->joinLeft('page_language', $expr, null)
            ->where('pages.parent_id = ?', $id)
            ->order('pages.position');
        if ($logedIn) {
            $select->where('NOT pages.guest_only');
        } else {
            $select->where('NOT pages.registered_only');
        }

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $result[] = [
                'id'    => $row['id'],
                'url'   => $row['url'],
                'name'  => $row['name'],
                'class' => $row['class']
            ];
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getCategoriesItems()
    {
        $language = $this->language->getLanguage();
        $cache = $this->cacheManager->getCache('long');

        $key = 'ZF2_CATEGORY_MENU_2_' . $language;

        if (!($categories = $cache->load($key))) {

            $categories = [];

            $categoryTable = new Category();
            $categoryLanguageTable = new Category_Language();

            $rows = $categoryTable->fetchAll([
                'parent_id is null',
            ], 'short_name');

            foreach ($rows as $row) {

                $langRow = $categoryLanguageTable->fetchRow([
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ]);

                $categories[] = [
                    'id'             => $row->id,
                    'url'            => $this->router->assemble([
                        'category_catname' => $row->catname
                    ], [
                        'name' => 'categories/category'
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $row->getCarsCount(),
                    'new_cars_count' => $row->getWeekCarsCount(),
                ];
            }

            $cache->save($categories, null, [], 1800);
        }

        return $categories;
    }

    /**
     * @param boolean $logedIn
     * @return array
     */
    private function getSecondaryMenu($logedIn)
    {
        $language = $this->language->getLanguage();
        $cache = $this->cacheManager->getCache('long');

        $key = 'ZF2_SECOND_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '7_' . $language;
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

            $cache->save($secondMenu, null, [], 1800);
        }

        return $secondMenu;
    }

    private function getPrimaryMenu($logedIn)
    {
        $language = $this->language->getLanguage();
        $cache = $this->cacheManager->getCache('long');

        $key = 'ZF2_MAIN_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '2_' . $language;
        if (!($pages = $cache->load($key))) {
            $pages = $this->getMenuData(2, $logedIn, $language);

            $cache->save($pages, null, [], 1800);
        }

        return $pages;
    }

    public function getMenu()
    {
        $user = false;//$this->_helper->user()->get();

        $newMessages = 0;
        if ($user) {
            $mModel = new Message();
            $newMessages = $mModel->getNewCount($user->id);
        }

        $language = $this->language->getLanguage();

        $searchHostname = 'www.autowp.ru';

        $languages = [
            [
                'name'     => 'Русский',
                'language' => 'ru',
                'hostname' => 'www.autowp.ru',
                'flag'     => 'flag-RU',
            ],
            [
                'name'     => 'English (beta)',
                'language' => 'en',
                'hostname' => 'en.wheelsage.org',
                'flag'     => 'flag-GB'
            ],
            [
                'name'     => 'Français (beta)',
                'language' => 'fr',
                'hostname' => 'fr.wheelsage.org',
                'flag'     => 'flag-FR'
            ]
        ];

        $uri = $this->request->getUri();
        foreach ($languages as &$item) {
            $active = $item['language'] == $language;
            $item['active'] = $active;
            if ($active) {
                $searchHostname = $item['hostname'];
            }

            $clone = clone $uri;

            $clone->setHost($item['hostname']);

            $item['url'] = $clone->__toString();
        }
        unset($item); // prevent future bugs

        $logedIn = false; //$this->_helper->user()->logedIn();



        return [
            'pages'          => $this->getPrimaryMenu($logedIn),
            'secondMenu'     => $this->getSecondaryMenu($logedIn),
            'pm'             => $newMessages,
            'categories'     => $this->getCategoriesItems(),
            'languages'      => $languages,
            'language'       => $language,
            'searchHostname' => $searchHostname
        ];
    }
}