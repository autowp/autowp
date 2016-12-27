<?php

namespace Application;

use Zend\Cache\Storage\StorageInterface;
use Zend\Router\Http\TreeRouteStack;

use Autowp\User\Model\DbTable\User\Row as UserRow;

use Application\Model\DbTable;
use Application\Model\Message;
use Application\Language;

class MainMenu
{
    /**
     * @var DbTable\Page
     */
    private $pageTable;

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

    /**
     * @var LanguagePicker
     */
    private $languagePicker;

    /**
     * @var Message
     */
    private $message;

    public function __construct(
        TreeRouteStack $router,
        Language $language,
        StorageInterface $cache,
        $hosts,
        $translator,
        LanguagePicker $languagePicker,
        Message $message
    ) {

        $this->router = $router;
        $this->language = $language;
        $this->hosts = $hosts;
        $this->cache = $cache;

        $this->pageTable = new DbTable\Page();

        $this->translator = $translator;
        $this->languagePicker = $languagePicker;
        $this->message = $message;
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
            if (! $name) {
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

        $key = 'ZF2_CATEGORY_MENU_8_' . $language;

        $categories = $this->cache->getItem($key, $success);
        if (! $success) {
            $categories = [];

            $itemTable = new DbTable\Vehicle();
            $itemLangTable = new DbTable\Vehicle\Language();

            $rows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('item_parent', 'cars.id = item_parent.item_id', null)
                    ->where('item_parent.item_id IS NULL')
                    ->order('cars.name')
            );

            foreach ($rows as $row) {
                $langRow = $itemLangTable->fetchRow([
                    'language = ?' => $language,
                    'item_id = ?'  => $row['id']
                ]);

                $categories[] = [
                    'id'             => $row['id'],
                    'url'            => $this->router->assemble([
                        'action'           => 'category',
                        'category_catname' => $row->catname
                    ], [
                        'name' => 'categories'
                    ]),
                    'name'           => $langRow && $langRow['name'] ? $langRow['name'] : $row['name'],
                    'cars_count'     => $itemTable->getVehiclesAndEnginesCount($row['id']),
                    'new_cars_count' => 0,//$row->getWeekCarsCount(),
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

        $key = 'ZF2_SECOND_MENU_' . ($logedIn ? 'LOGED' : 'NOTLOGED') . '12_' . $language;

        $secondMenu = $this->cache->getItem($key, $success);
        if (! $success) {
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
        if (! $success) {
            $pages = $this->getMenuData(2, $logedIn, $language);

            $this->cache->setItem($key, $pages);
        }

        return $pages;
    }

    /**
     * @param UserRow $user
     * @return array
     */
    public function getMenu(UserRow $user = null)
    {
        $newMessages = 0;
        if ($user) {
            $newMessages = $this->message->getNewCount($user->id);
        }

        $language = $this->language->getLanguage();

        $searchHostname = 'www.autowp.ru';

        foreach ($this->hosts as $itemLanguage => $item) {
            if ($itemLanguage == $language) {
                $searchHostname = $item['hostname'];
            }
        }

        $logedIn = (bool)$user;

        return [
            'pages'          => $this->getPrimaryMenu($logedIn),
            'secondMenu'     => $this->getSecondaryMenu($logedIn),
            'pm'             => $newMessages,
            'categories'     => $this->getCategoriesItems(),
            'languages'      => $this->languagePicker->getItems(),
            'language'       => $language,
            'searchHostname' => $searchHostname
        ];
    }
}
