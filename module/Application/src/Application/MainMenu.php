<?php

namespace Application;

use Application\Model\Message;
use Application\Language;

use Zend\Cache\Storage\StorageInterface;
use Zend\Router\Http\TreeRouteStack;

use Category;
use Category_Language;
use Pages;
use Users_Row;

class MainMenu
{
    /**
     * @var Pages
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
     * @var StorageInterface
     */
    private $cache;

    /**
     * @var array
     */
    private $icons = [
        29  => 'fa fa-fw fa-upload',
        89  => 'fa fa-fw fa-comment',
        136 => 'fa fa-fw fa-info',
        48  => 'fa fa-fw fa-user',
        90  => 'fa fa-fw fa-sign-out',
        124 => 'fa fa-fw fa fa-users',
        91  => 'fa fa-fw fa fa-pencil'
    ];

    /**
     * @var array
     */
    private $hosts = [];
    
    private $translator;

    public function __construct($request, TreeRouteStack $router, Language $language, StorageInterface $cache, $hosts, $translator)
    {
        $this->request = $request;
        $this->router = $router;
        $this->language = $language;
        $this->hosts = $hosts;
        $this->cache = $cache;

        $this->pageTable = new Pages();
        
        $this->translator = $translator;
    }

    /**
     * @param int $id
     * @param boolean $logedIn
     * @param string $language
     * @return array
     */
    private function getMenuData($id, $logedIn, $language)
    {
        $db = $this->pageTable->getAdapter();

        $select = $db->select()
            ->from($this->pageTable->info('name'), [
                'id', 'url', 'class'
            ])
            ->where('pages.parent_id = ?', $id)
            ->order('pages.position');
        if ($logedIn) {
            $select->where('NOT pages.guest_only');
        } else {
            $select->where('NOT pages.registered_only');
        }

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            
            $key = 'page/' . $row['id'] . '/name';
            
            $name = $this->translator->translate($key);
            if (!$name) {
                $name = $this->translator->translate($key, null, 'en');
            }
            
            $result[] = [
                'id'    => $row['id'],
                'url'   => $row['url'],
                'name'  => $name,
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

        $key = 'ZF2_CATEGORY_MENU_3_' . $language;

        $categories = $this->cache->getItem($key, $success);
        if (!$success) {

            $categories = [];

            $categoryTable = new Category();
            $categoryLangTable = new Category_Language();

            $rows = $categoryTable->fetchAll([
                'parent_id is null',
            ], 'short_name');

            foreach ($rows as $row) {

                $langRow = $categoryLangTable->fetchRow([
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ]);

                $categories[] = [
                    'id'             => $row->id,
                    'url'            => $this->router->assemble([
                        'category_catname' => $row->catname
                    ], [
                        'action' => 'category',
                        'name'   => 'categories'
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $row->getCarsCount(),
                    'new_cars_count' => $row->getWeekCarsCount(),
                ];
            }

            $this->cache->setItem($key, $categories);
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

        $key = 'ZF2_SECOND_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '11_' . $language;

        $secondMenu = $this->cache->getItem($key, $success);
        if (!$success) {
            $secondMenu = $this->getMenuData(87, $logedIn, $language);

            foreach ($secondMenu as &$item) {
                if (isset($this->icons[$item['id']])) {
                    $item['icon'] = $this->icons[$item['id']];
                }
            }
            unset($item);

            $this->cache->setItem($key, $secondMenu);
        }

        return $secondMenu;
    }

    /**
     * @param boolean $logedIn
     * @return array
     */
    private function getPrimaryMenu($logedIn)
    {
        $language = $this->language->getLanguage();

        $key = 'ZF2_MAIN_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '_7_' . $language;

        $pages = $this->cache->getItem($key, $success);
        if (!$success) {
            $pages = $this->getMenuData(2, $logedIn, $language);

            $this->cache->setItem($key, $pages);
        }

        return $pages;
    }

    /**
     * @param Users_Row $user
     * @return array
     */
    public function getMenu(Users_Row $user = null)
    {
        $newMessages = 0;
        if ($user) {
            $mModel = new Message();
            $newMessages = $mModel->getNewCount($user->id);
        }

        $language = $this->language->getLanguage();

        $searchHostname = 'www.autowp.ru';

        $languages = [];

        $uri = $this->request->getUri();
        foreach ($this->hosts as $itemLanguage => $item) {
            $active = $itemLanguage == $language;
            if ($active) {
                $searchHostname = $item['hostname'];
            }

            $clone = clone $uri;
            $clone->setHost($item['hostname']);

            $languages[] = [
                'name'     => $item['name'],
                'language' => $itemLanguage,
                'hostname' => $item['hostname'],
                'flag'     => $item['flag'],
                'url'      => $clone->__toString(),
                'active'   => $active
            ];
        }

        $logedIn = (bool)$user;

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