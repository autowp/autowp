<?php

namespace Application\Model;

use Autowp\Commons\Db\Table;
use Autowp\Image;

use Application\ItemNameFormatter;
use Application\Model\Catalogue;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Item;
use Application\Service\SpecificationsService;

use Facebook;

use Zend_Db_Expr;
use Zend_Oauth_Token_Access;
use Zend_Service_Twitter;

use DateInterval;
use DateTime;

class CarOfDay
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var Image\Storage
     */
    private $imageStorage;

    /**
     * @var Catalogue
     */
    private $catalogue;

    private $router;

    private $translator;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    public function __construct(
        ItemNameFormatter $itemNameFormatter,
        Image\Storage $imageStorage,
        Catalogue $catalogue,
        $router,
        $translator,
        SpecificationsService $specsService
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->imageStorage = $imageStorage;
        $this->catalogue = $catalogue;
        $this->router = $router;
        $this->translator = $translator;
        $this->specsService = $specsService;

        $this->table = new Table([
            'name'    => 'of_day',
            'primary' => 'day_date'
        ]);
    }

    /**
     * @return DbTable\Item\ParentTable
     */
    private function getItemParentTable()
    {
        return $this->itemParentTable
            ? $this->itemParentTable
            : $this->itemParentTable = new DbTable\Item\ParentTable();
    }

    public function getCarOfDayCadidate()
    {
        $db = $this->table->getAdapter();
        $sql = '
            SELECT c.id, count(p.id) AS p_count
            FROM item AS c
                INNER JOIN item_parent_cache AS cpc ON c.id=cpc.parent_id
                INNER JOIN picture_item ON cpc.item_id = picture_item.item_id
                INNER JOIN pictures AS p ON picture_item.picture_id=p.id
            WHERE p.status=?
                AND (c.begin_year AND c.end_year OR c.begin_model_year AND c.end_model_year)
                AND c.id NOT IN (SELECT item_id FROM of_day WHERE item_id)
            GROUP BY c.id
            HAVING p_count >= 5
            ORDER BY RAND()
            LIMIT 1
        ';
        return $db->fetchRow($sql, [Picture::STATUS_ACCEPTED]);
    }

    public function pick()
    {
        $row = $this->getCarOfDayCadidate();
        if ($row) {
            print $row['id']  ."\n";

            $now = new DateTime();
            $this->setItemOfDay($now, $row['id'], null);
        }
    }

    public function getCurrent()
    {
        $row = $this->table->fetchRow([
            'day_date <= CURDATE()'
        ], 'day_date DESC');

        return $row ? [
            'item_id' => $row['item_id'],
            'user_id' => $row['user_id']
        ] : null;
    }

    private function pictureByPerspective($pictureTable, $car, $perspective)
    {
        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $car->id)
            ->order([
                'pictures.width DESC', 'pictures.height DESC'
            ])
            ->limit(1);
        if ($perspective) {
            $select->where('picture_item.perspective_id = ?', $perspective);
        }
        return $pictureTable->fetchRow($select);
    }
    
    private static function mb_ucfirst($str) 
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc.mb_substr($str, 1);
    }

    public function putCurrentToTwitter(array $twOptions)
    {
        $dayRow = $this->table->fetchRow([
            'day_date = CURDATE()',
            'not twitter_sent'
        ]);

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $itemTable = new Item();

        $car = $itemTable->fetchRow([
            'id = ?' => (int)$dayRow->item_id
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        $pictureTable = new Picture();

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($pictureTable, $car, $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($pictureTable, $car, false);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'http://wheelsage.org/picture/' . $picture->identity;
        
        if ($car['item_type_id'] == \Application\Model\DbTable\Item\Type::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'en');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'en');
        }

        $text = sprintf(
            self::mb_ucfirst($title) . ': %s %s',
            $this->itemNameFormatter->format($car->getNameData('en'), 'en'),
            $url
        );

        $token = new Zend_Oauth_Token_Access();
        $token->setParams($twOptions['token']);

        $twitter = new Zend_Service_Twitter([
            'username'     => $twOptions['username'],
            'accessToken'  => $token,
            'oauthOptions' => $twOptions['oauthOptions']
        ]);

        $response = $twitter->statusesUpdate($text);

        if ($response->isSuccess()) {
            $dayRow->twitter_sent = true;
            $dayRow->save();

            print 'ok' . PHP_EOL;
        } else {
            print_r($response->getErrors());
        }
    }

    public function putCurrentToFacebook(array $fbOptions)
    {
        $dayRow = $this->table->fetchRow([
            'day_date = CURDATE()',
            'not facebook_sent'
        ]);

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $itemTable = new Item();

        $car = $itemTable->fetchRow([
            'id = ?' => (int)$dayRow->item_id
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        $pictureTable = new Picture();

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($pictureTable, $car, $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($pictureTable, $car, false);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'http://wheelsage.org/picture/' . $picture->identity;
        
        if ($car['item_type_id'] == \Application\Model\DbTable\Item\Type::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'en');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'en');
        }
        
        $text = sprintf(
            self::mb_ucfirst($title) . ': %s %s',
            $this->itemNameFormatter->format($car->getNameData('en'), 'en'),
            $url
        );

        $fb = new Facebook\Facebook([
            'app_id'                => $fbOptions['app_id'],
            'app_secret'            => $fbOptions['app_secret'],
            'default_graph_version' => 'v2.8'
        ]);

        $linkData = [
            'link'    => $url,
            'message' => 'Vehicle of the day: ' . $this->itemNameFormatter->format($car->getNameData('en'), 'en'),
        ];

        try {
            // Returns a `Facebook\FacebookResponse` object
            $response = $fb->post('/296027603807350/feed', $linkData, $fbOptions['page_access_token']);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            return;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            return;
        }

        $dayRow->facebook_sent = true;
        $dayRow->save();

        print 'ok' . PHP_EOL;
    }

    public function putCurrentToVk(array $vkOptions)
    {
        $language = 'ru';

        $dayRow = $this->table->fetchRow([
            'day_date = CURDATE()',
            'not vk_sent'
        ]);

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $itemTable = new Item();

        $car = $itemTable->fetchRow([
            'id = ?' => (int)$dayRow->item_id
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        $pictureTable = new Picture();

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($pictureTable, $car, $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($pictureTable, $car, false);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }



        $url = 'http://autowp.ru/picture/' . $picture->identity;
        
        if ($car['item_type_id'] == \Application\Model\DbTable\Item\Type::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'ru');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'ru');
        }
        
        $text = sprintf(
            self::mb_ucfirst($title) . ': %s',
            $this->itemNameFormatter->format($car->getNameData($language), $language)
        );

        $client = new \Zend\Http\Client('https://api.vk.com/method/wall.post');
        $response = $client
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setParameterPost([
                'owner_id'     => $vkOptions['owner_id'],
                'from_group'   => 1,
                'message'      => $text,
                'attachments'  => $url,
                'access_token' => $vkOptions['token'],
                /*'captcha_sid' => '954673112942',
                'captcha_key' => 'q2d2due'*/
            ])
            ->send();

        if (! $response->isSuccess()) {
            throw new \Exception("Failed to post to vk" . $response->getReasonPhrase());
        }

        $json = \Zend\Json\Json::decode($response->getBody(), \Zend\Json\Json::TYPE_ARRAY);
        if (isset($json['error'])) {
            throw new \Exception("Failed to post to vk" . $json['error']['error_msg']);
        }

        $dayRow->vk_sent = true;
        $dayRow->save();

        print 'ok' . PHP_EOL;
    }

    public function getNextDates()
    {
        $now = new DateTime();
        $interval = new DateInterval('P1D');

        $result = [];

        for ($i = 0; $i < 10; $i++) {
            $dayRow = $this->table->fetchRow([
                'day_date = ?' => $now->format('Y-m-d'),
                'item_id is not null'
            ]);

            $result[] = [
                'date' => clone $now,
                'free' => ! $dayRow
            ];

            $now->add($interval);
        }

        return $result;
    }

    public function getItemOfDay($itemId, $userId, $language)
    {
        $itemTable = new DbTable\Item();
        $carOfDay = $itemTable->find($itemId)->current();

        $carOfDayPictures = $this->getOrientedPictureList($carOfDay);

        // images
        $formatRequests = [];
        foreach ($carOfDayPictures as $idx => $picture) {
            if ($picture) {
                $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';
                $formatRequests[$format][$idx] = $picture->getFormatRequest();
            }
        }

        $imagesInfo = [];
        foreach ($formatRequests as $format => $requests) {
            $imagesInfo[$format] = $this->imageStorage->getFormatedImages($requests, $format);
        }

        // names
        $notEmptyPics = [];
        foreach ($carOfDayPictures as $idx => $picture) {
            if ($picture) {
                $notEmptyPics[] = $picture;
            }
        }
        $pictureTable = $this->catalogue->getPictureTable();
        $names = $pictureTable->getNameData($notEmptyPics, [
            'language' => $language
        ]);

        $paths = $this->catalogue->getCataloguePaths($carOfDay->id, [
            'breakOnFirst' => true
        ]);

        $categoryPath = false;
        if (! $paths) {
            $categoryPaths = $this->getCategoryPaths($carOfDay->id, [
                'breakOnFirst' => true
            ]);
        }

        $carOfDayPicturesData = [];
        foreach ($carOfDayPictures as $idx => $row) {
            if ($row) {
                $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';

                $url = null;
                foreach ($paths as $path) {
                    $url = $this->router->assemble([
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path'],
                        'picture_id'    => $row['identity']
                    ], [
                        'name' => 'catalogue'
                    ]);
                }

                if (! $url) {
                    foreach ($categoryPaths as $path) {
                        $url = $this->router->assemble([
                            'action'           => 'category-picture',
                            'category_catname' => $path['category_catname'],
                            'item_id'          => $path['item_id'],
                            'path'             => $path['path'],
                            'picture_id'       => $row['identity']
                        ], [
                            'name' => 'categories'
                        ]);
                    }
                }

                $carOfDayPicturesData[] = [
                    'src'  => isset($imagesInfo[$format][$idx])
                        ? $imagesInfo[$format][$idx]->getSrc()
                        : null,
                    'name' => isset($names[$row['id']]) ? $names[$row['id']] : null,
                    'url'  => $url
                ];
            }
        }

        return [
            'itemTypeId' => $carOfDay['item_type_id'],
            'name'       => $carOfDay->getNameData($language),
            'pictures'   => $carOfDayPicturesData,
            'links'      => $this->carLinks($carOfDay, $language),
            'userId'     => $userId
        ];
    }

    private function getOrientedPictureList($car)
    {
        $perspectivesGroups = new DbTable\Perspective\Group();

        $db = $perspectivesGroups->getAdapter();
        $perspectivesGroupIds = $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', 6)
                ->order('position')
        );

        $pTable = $this->catalogue->getPictureTable();
        $pictures = [];

        $db = $pTable->getAdapter();
        $usedIds = [];

        foreach ($perspectivesGroupIds as $groupId) {
            $picture = null;

            $select = $pTable->select(true)
                ->where('mp.group_id = ?', $groupId)
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->joinRight(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->order([
                    'item_parent_cache.sport', 'item_parent_cache.tuning', 'mp.position',
                    'pictures.width DESC', 'pictures.height DESC'
                ])
                ->limit(1);
            if ($usedIds) {
                $select->where('pictures.id not in (?)', $usedIds);
            }
            $picture = $pTable->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = $picture->id;
            } else {
                $pictures[] = null;
            }
        }

        $resorted = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $resorted[] = $picture;
            }
        }
        foreach ($pictures as $picture) {
            if (! $picture) {
                $resorted[] = null;
            }
        }
        $pictures = $resorted;

        $left = [];
        foreach ($pictures as $key => $picture) {
            if (! $picture) {
                $left[] = $key;
            }
        }

        if (count($left) > 0) {
            $select = $pTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                //->order('ratio DESC')
                ->limit(count($left));

            if (count($usedIds) > 0) {
                $select->where('pictures.id NOT IN (?)', $usedIds);
            }

            foreach ($pTable->fetchAll($select) as $pic) {
                $key = array_shift($left);
                $pictures[$key] = $pic;
            }
        }

        return $pictures;
    }

    private function carLinks(DbTable\Item\Row $car, $language)
    {
        $items = [];

        $itemTable = $this->catalogue->getItemTable();

        $db = $itemTable->getAdapter();
        $totalPictures = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
        );

        if ($car->item_type_id == DbTable\Item\Type::CATEGORY) {
            $items[] = [
                'icon'  => 'align-left',
                'url'   => $this->router->assemble([
                    'action'           => 'category',
                    'category_catname' => $car->catname,
                ], [
                    'name' => 'categories'
                ]),
                'text'  => $this->translator->translate('carlist/details')
            ];

            if ($totalPictures > 6) {
                $items[] = [
                    'icon'  => 'th',
                    'url'   => $this->router->assemble([
                        'action'           => 'category-pictures',
                        'category_catname' => $car->catname,
                    ], [
                    'name' => 'categories'
                    ]),
                    'text'  => $this->translator->translate('carlist/all pictures'),
                    'count' => $totalPictures
                ];
            }
        } else {
            $cataloguePaths = $this->catalogue->getCataloguePaths($car['id']);

            if ($totalPictures > 6) {
                foreach ($cataloguePaths as $path) {
                    $url = $this->router->assemble([
                        'action'        => 'brand-item-pictures',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path']
                    ], [
                        'name' => 'catalogue'
                    ]);
                    $items[] = [
                        'icon'  => 'th',
                        'url'   => $url,
                        'text'  => $this->translator->translate('carlist/all pictures'),
                        'count' => $totalPictures
                    ];
                    break;
                }
            }

            if ($this->specsService->hasSpecs($car->id)) {
                foreach ($cataloguePaths as $path) {
                    $items[] = [
                        'icon'  => 'list-alt',
                        'url'   => $this->router->assemble([
                            'action'        => 'brand-item-specifications',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ], [
                            'name' => 'catalogue'
                        ]),
                        'text'  => $this->translator->translate('carlist/specifications')
                    ];
                    break;
                }
            }

            $twins = new Twins();
            foreach ($twins->getCarGroups($car->id) as $twinsGroup) {
                $items[] = [
                    'icon'  => 'adjust',
                    'url'   => $this->router->assemble([
                        'id' => $twinsGroup['id']
                    ], [
                        'name' => 'twins/group'
                    ]),
                    'text'  => $this->translator->translate('carlist/twins')
                ];
            }

            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($itemTable->info('name'), [
                        'catname', 'begin_year', 'end_year',
                        'name' => new Zend_Db_Expr('IF(LENGTH(item_language.name)>0,item_language.name,item.name)')
                    ])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft(
                        'item_language',
                        'item.id = item_language.item_id and item_language.language = :language',
                        null
                    )
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id = :item_id')
                    ->group(['item_parent_cache.item_id', 'item.id'])
                    ->bind([
                        'language' => $language,
                        'item_id'  => $car['id']
                    ])
            );

            foreach ($categoryRows as $category) {
                $items[] = [
                    'icon'  => 'tag',
                    'url'   => $this->router->assemble([
                        'action'           => 'category',
                        'category_catname' => $category['catname'],
                    ], [
                        'name' => 'categories'
                    ]),
                    'text'  => $this->itemNameFormatter->format(
                        $category,
                        $language
                    )
                ];
            }
        }

        return $items;
    }

    private function getCategoryPaths($carId, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = [];

        $db = $this->getItemParentTable()->getAdapter();

        $select = $db->select()
            ->from('item_parent', 'item_id')
            ->join('item', 'item_parent.parent_id = item.id', 'catname')
            ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
            ->where('item_parent.item_id = ?', $carId);

        if ($breakOnFirst) {
            $select->limit(1);
        }

        $categoryVehicleRows = $db->fetchAll($select);
        foreach ($categoryVehicleRows as $categoryVehicleRow) {
            $result[] = [
                'category_catname' => $categoryVehicleRow['catname'],
                'item_id'          => $categoryVehicleRow['item_id'],
                'path'             => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->getItemParentTable()->fetchAll([
            'item_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getCategoryPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'category_catname' => $path['category_catname'],
                    'item_id'          => $path['item_id'],
                    'path'             => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }

    public function isComplies($itemId)
    {
        $db = $this->table->getAdapter();
        $sql = '
            SELECT item.id, count(distinct pictures.id) AS p_count
            FROM item 
                INNER JOIN item_parent_cache AS cpc ON item.id = cpc.parent_id
                INNER JOIN picture_item ON cpc.item_id = picture_item.item_id
                INNER JOIN pictures ON picture_item.picture_id = pictures.id
            WHERE pictures.status = ?
                AND item.id NOT IN (SELECT item_id FROM of_day WHERE item_id)
                AND item.id = ?
            HAVING p_count >= 3
            LIMIT 1
        ';
        return (bool)$db->fetchRow($sql, [Picture::STATUS_ACCEPTED, (int)$itemId]);
    }

    public function setItemOfDay(DateTime $dateTime, $itemId, $userId)
    {
        $itemId = (int)$itemId;
        $userId = (int)$userId;

        if (! $this->isComplies($itemId)) {
            return false;
        }

        $dateStr = $dateTime->format('Y-m-d');

        $dayRow = $this->table->fetchRow([
            'day_date = ?' => $dateStr
        ]);

        if (! $dayRow) {
            $dayRow = $this->table->createRow([
                'day_date' => $dateStr
            ]);
        }

        if ($dayRow['item_id']) {
            return false;
        }

        $dayRow->item_id = $itemId;
        $dayRow->user_id = $userId ? $userId : null;
        $dayRow->save();

        return true;
    }
}
