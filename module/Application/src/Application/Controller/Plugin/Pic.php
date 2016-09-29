<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\Brand;
use Application\Model\DbTable\BrandLink;
use Application\Model\DbTable\Modification as ModificationTable;
use Application\Paginator\Adapter\Zend1DbSelect;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;

use Exception;

use Brands;
use Brand_Car;
use Car_Parent;
use Car_Language;
use Cars;
use Category;
use Category_Language;
use Comment_Message;
use Comment_Topic;
use Engines;
use Perspectives;
use Picture;
use Picture_View;
use Picture_Moder_Vote;
use Picture_Row;
use Users;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table_Select;

class Pic extends AbstractPlugin
{
    /**
     * @var Picture_View
     */
    private $pictureViewTable = null;

    private $moderVoteTable = null;

    private $textStorage;

    private $carHelper;

    private $translator;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    public function __construct($textStorage, $carHelper, $translator, PictureNameFormatter $pictureNameFormatter)
    {
        $this->textStorage = $textStorage;
        $this->carHelper = $carHelper;
        $this->translator = $translator;
        $this->pictureNameFormatter = $pictureNameFormatter;
    }

    /**
     * @return Picture_Moder_Vote
     */
    private function getModerVoteTable()
    {
        return $this->moderVoteTable
            ? $this->moderVoteTable
            : $this->moderVoteTable = new Picture_Moder_Vote();
    }

    /**
     * @return Picture_View
     */
    private function getPictureViewTable()
    {
        return $this->pictureViewTable
            ? $this->pictureViewTable
            : $this->pictureViewTable = new Picture_View();
    }

    public function href($row, array $options = [])
    {
        $defaults = [
            'fallback'  => true,
            'canonical' => false,
            'uri'       => null
        ];
        $options = array_replace($defaults, $options);

        $controller = $this->getController();

        $brandTable = new Brands();

        $url = null;
        switch ($row['type']) {
            case Picture::LOGO_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'logotypes-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ], [
                        'force_canonical' => $options['canonical'],
                        'uri'             => $options['uri']
                    ]);
                }
                break;

            case Picture::MIXED_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'mixed-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ], [
                        'force_canonical' => $options['canonical']
                    ]);
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'other-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ], [
                        'force_canonical' => $options['canonical']
                    ]);
                }
                break;

            case Picture::CAR_TYPE_ID:
                if ($row['car_id']) {
                    $carParentTable = new Car_Parent();
                    $paths = $carParentTable->getPaths($row['car_id'], [
                        'breakOnFirst' => true
                    ]);

                    if (count($paths) > 0) {
                        $path = $paths[0];

                        $url = $controller->url()->fromRoute('catalogue', [
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                        ], [
                            'force_canonical' => $options['canonical']
                        ]);
                    }
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                if ($row['engine_id']) {
                    $engineTable = new Engines();

                    $parentId = $row['engine_id'];
                    $path = [];
                    do {
                        $engineRow = $engineTable->find($parentId)->current();
                        $path[] = $engineRow->id;

                        $brandRow = $brandTable->fetchRow(
                            $brandTable->select(true)
                                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                                ->where('brand_engine.engine_id = ?', $engineRow->id)
                        );
                        $parentId = $engineRow->parent_id;
                    } while ($parentId && !$brandRow);

                    if ($brandRow && $path) {
                        $url = $controller->url()->fromRoute('catalogue', [
                            'action'        => 'engine-picture',
                            'brand_catname' => $brandRow['folder'],
                            'path'          => array_reverse($path),
                            'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                        ], [
                        'force_canonical' => $options['canonical']
                    ]);
                    }
                }
                break;
        }

        if ($options['fallback'] && !$url) {
            $url = $this->url($row['id'], $row['identity'], $options['canonical']);
        }

        return $url;
    }

    public function url($id, $identity, $absolute = false, $uri = null)
    {
        $controller = $this->getController();

        return $controller->url()->fromRoute('picture/picture', [
            'picture_id' => $identity ? $identity : $id,
        ], [
            'force_canonical' => $absolute,
            'uri'             => $uri
        ], true);
    }

    public function listData($pictures, array $options = [])
    {
        $defaults = [
            'width'            => null,
            'disableBehaviour' => false,
            'url'              => null
        ];

        $options = array_replace($defaults, $options);

        $urlCallback = $options['url'];

        $colClass = '';
        $width = null;

        if ($options['width']) {
            $width = (int)$options['width'];
            if (!$colClass) {
                $colClass = 'col-lg-' . (12 / $width) . ' col-md-' . (12 / $width);
            }
        }

        $controller = $this->getController();
        $imageStorage = $controller->imageStorage();
        $isModer = $controller->user()->inheritsRole('pictures-moder');
        $userId = null;
        if ($controller->user()->logedIn()) {
            $user = $controller->user()->get();
            $userId = $user ? $user->id : null;
        }

        $language = $controller->language();

        $ids = [];

        if (is_array($pictures)) {

            $rows = [];
            foreach ($pictures as $picture) {
                $ids[] = $picture['id'];
                $rows[] = $picture->toArray();
            }

            // moder votes
            $moderVotes = [];
            if (count($ids)) {
                $moderVoteTable = $this->getModerVoteTable();
                $db = $moderVoteTable->getAdapter();

                $voteRows = $db->fetchAll(
                    $db->select()
                        ->from($moderVoteTable->info('name'), [
                            'picture_id',
                            'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                            'count' => 'count(1)'
                        ])
                        ->where('picture_id in (?)', $ids)
                        ->group('picture_id')
                );

                foreach ($voteRows as $row) {
                    $moderVotes[$row['picture_id']] = [
                        'moder_votes'       => (int)$row['vote'],
                        'moder_votes_count' => (int)$row['count']
                    ];
                }
            }

            // views
            $views = [];
            if (!$options['disableBehaviour']) {
                $views = $this->getPictureViewTable()->getValues($ids);
            }

            // messages
            $messages = [];
            if (!$options['disableBehaviour'] && count($ids)) {
                $ctTable = new Comment_Topic();
                $db = $ctTable->getAdapter();
                $messages = $db->fetchPairs(
                    $ctTable->select()
                        ->from($ctTable->info('name'), ['item_id', 'messages'])
                        ->where('item_id in (?)', $ids)
                        ->where('type_id = ?', Comment_Message::PICTURES_TYPE_ID)
                );
            }

            foreach ($rows as &$row) {
                $id = $row['id'];
                if (isset($moderVotes[$id])) {
                    $vote = $moderVotes[$id];
                    $row['moder_votes'] = $vote['moder_votes'];
                    $row['moder_votes_count'] = $vote['moder_votes_count'];
                } else {
                    $row['moder_votes'] = null;
                    $row['moder_votes_count'] = 0;
                }
                if (!$options['disableBehaviour']) {
                    if (isset($views[$id])) {
                        $row['views'] = $views[$id];
                    } else {
                        $row['views'] = 0;
                    }
                    if (isset($messages[$id])) {
                        $row['messages'] = $messages[$id];
                    } else {
                        $row['messages'] = 0;
                    }
                }
            }
            unset($row);

        } elseif ($pictures instanceof Zend_Db_Table_Select) {

            $table = $pictures->getTable();
            $db = $table->getAdapter();

            $select = clone $pictures;
            $bind = [];

            $select
                ->reset(Zend_Db_Select::COLUMNS)
                ->setIntegrityCheck(false)
                ->columns([
                    'pictures.id', 'pictures.identity', 'pictures.name',
                    'pictures.width', 'pictures.height',
                    'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                    'pictures.status', 'pictures.image_id',
                    'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                    'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
                ]);

            $select
                ->group('pictures.id')
                ->joinLeft('pictures_moder_votes', 'pictures.id = pictures_moder_votes.picture_id', [
                    'moder_votes'       => 'sum(if(pictures_moder_votes.vote, 1, -1))',
                    'moder_votes_count' => 'count(pictures_moder_votes.picture_id)'
                ]);



            if (!$options['disableBehaviour']) {
                $select
                    ->joinLeft(['pv' => 'picture_view'], 'pictures.id = pv.picture_id', 'views')
                    ->joinLeft(['ct' => 'comment_topic'], 'ct.type_id = :type_id and ct.item_id = pictures.id', 'messages');

                $bind['type_id'] = Comment_Message::PICTURES_TYPE_ID;
            }

            $rows = $db->fetchAll($select, $bind);


            foreach ($rows as $idx => $picture) {
                $ids[] = (int)$picture['id'];
            }

        } else {
            throw new Exception("Unexpected type of pictures");
        }

        //print $select;

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = Picture_Row::buildFormatRequest($picture);
        }

        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNameData($rows, [
            'language' => $language
        ]);

        // comments
        if (!$options['disableBehaviour']) {
            if ($userId) {
                $ctTable = new Comment_Topic();
                $newMessages = $ctTable->getNewMessages(
                    Comment_Message::PICTURES_TYPE_ID,
                    $ids,
                    $userId
                );
            }
        }

        $brandTable = new Brands();
        $carParentTable = new Car_Parent();

        $items = [];
        foreach ($rows as $idx => $row) {

            $id = (int)$row['id'];

            $name = isset($names[$id]) ? $names[$id] : null;

            if ($urlCallback) {
                $url = $urlCallback($row);
            } else {
                $url = $this->href($row);
            }

            $item = [
                'id'        => $id,
                'type'      => $row['type'],
                'name'      => $name,
                'url'       => $url,
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                'moderVote' => $row['moder_votes_count'] > 0 ? $row['moder_votes'] : null,
                'perspective_id' => $row['perspective_id']
            ];

            if (!$options['disableBehaviour']) {
                $msgCount = $row['messages'];
                $newMsgCount = 0;
                if ($userId) {
                    $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                }

                $item = array_replace($item, [
                    'resolution'     => (int)$row['width'] . '×' . (int)$row['height'],
                    'cropped'        => Picture_Row::checkCropParameters($row),
                    'cropResolution' => $row['crop_width'] . '×' . $row['crop_height'],
                    'status'         => $row['status'],
                    'views'          => (int)$row['views'],
                    'msgCount'       => $msgCount,
                    'newMsgCount'    => $newMsgCount,
                ]);
            }



            $items[] = $item;
        }

        return [
            'items'            => $items,
            'colClass'         => $colClass,
            'disableBehaviour' => $options['disableBehaviour'],
            'isModer'          => $isModer,
            'width'            => $width
        ];
    }

    public function picPageData($picture, $picSelect, $brandIds = [], array $options = [])
    {
        $options = array_replace([
            'paginator' => [
                'route'     => 'picture/picture',
                'urlParams' => []
            ]
        ], $options);

        $controller = $this->getController();
        $catalogue = $controller->catalogue();
        $imageStorage = $controller->imageStorage();

        $isModer = $controller->user()->inheritsRole('moder');

        $pictureTable = $catalogue->getPictureTable();
        $db = $pictureTable->getAdapter();

        $engine = null;
        $engineCars = [];
        $engineHasSpecs = false;
        $engineSpecsUrl = false;
        $factory = null;
        $factoryCars = [];
        $factoryCarsMore = false;
        $altNames2 = [];
        $currentLangName = null;
        $designProject = null;
        $categories = [];

        $car = null;
        $carDetailsUrl = null;

        $language = $controller->language();

        switch ($picture->type) {
            case Picture::ENGINE_TYPE_ID:
                if ($engine = $picture->findParentEngines()) {

                    $brandIds = $db->fetchCol(
                        $db->select()
                            ->from('brand_engine', 'brand_id')
                            ->where('engine_id = ?', $engine->id)
                    );

                    $carIds = $engine->getRelatedCarGroupId();
                    if ($carIds) {
                        $carTable = $catalogue->getCarTable();

                        $carRows = $carTable->fetchAll([
                            'id in (?)' => $carIds
                        ], $catalogue->carsOrdering());

                        foreach ($carRows as $carRow) {
                            $cataloguePaths = $catalogue->cataloguePaths($carRow);

                            foreach ($cataloguePaths as $cPath) {
                                $engineCars[] = [
                                    'name' => $carRow->getFullName($language),
                                    'url'  => $controller->url()->fromRoute('catalogue', [
                                        'action'        => 'brand-car',
                                        'brand_catname' => $cPath['brand_catname'],
                                        'car_catname'   => $cPath['car_catname'],
                                        'path'          => $cPath['path']
                                    ])
                                ];
                                break;
                            }
                        }
                    }

                    $specService = new SpecificationsService();
                    $engineHasSpecs = $specService->hasSpecs(3, $engine->id);

                    if ($engineHasSpecs) {

                        $cataloguePaths = $catalogue->engineCataloguePaths($engine, [
                            'limit' => 1
                        ]);

                        foreach ($cataloguePaths as $cataloguePath) {
                            $engineSpecsUrl = $controller->url()->fromRoute('catalogue', [
                                'action'        => 'engine-specs',
                                'brand_catname' => $cataloguePath['brand_catname'],
                                'path'          => $cataloguePath['path']
                            ]);
                        }
                    }

                }

                break;

            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
                $brandIds = [$picture->brand_id];
                break;

            case Picture::FACTORY_TYPE_ID:
                if ($factory = $picture->findParentFactory()) {
                    $carIds = $factory->getRelatedCarGroupId();
                    if ($carIds) {
                        $carTable = $catalogue->getCarTable();

                        $carRows = $carTable->fetchAll([
                            'id in (?)' => $carIds
                        ], $catalogue->carsOrdering());

                        $limit = 10;

                        if (count($carRows) > $limit) {
                            $a = [];
                            foreach ($carRows as $carRow) {
                                $a[] = $carRow;
                            }
                            $carRows = array_slice($a, 0, $limit);
                            $factoryCarsMore = true;
                        }

                        foreach ($carRows as $carRow) {
                            $cataloguePaths = $catalogue->cataloguePaths($carRow);

                            foreach ($cataloguePaths as $cPath) {
                                $factoryCars[] = [
                                    'name' => $carRow->getFullName($language),
                                    'url'  => $controller->url()->fromRoute('catalogue', [
                                        'action'        => 'brand-car',
                                        'brand_catname' => $cPath['brand_catname'],
                                        'car_catname'   => $cPath['car_catname'],
                                        'path'          => $cPath['path']
                                    ])
                                ];
                                break;
                            }
                        }
                    }
                }
                break;
            case Picture::CAR_TYPE_ID:
                $car = $picture->findParentCars();
                if ($car) {

                    $brandIds = $db->fetchCol(
                        $db->select()
                            ->from('brands_cars', 'brand_id')
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    // alt names
                    $altNames = [];

                    $carLangTable = new Car_Language();
                    $carLangRows = $carLangTable->fetchAll([
                        'car_id = ?' => $car->id
                    ]);

                    $defaultName = $car->caption;
                    foreach ($carLangRows as $carLangRow) {
                        $name = $carLangRow->name;
                        if (!isset($altNames[$name])) {
                            $altNames[$carLangRow->name] = [];
                        }
                        $altNames[$name][] = $carLangRow->language;

                        if ($language == $carLangRow->language) {
                            $currentLangName = $name;
                        }
                    }

                    foreach ($altNames as $name => $codes) {
                        if (strcmp($name, $currentLangName) != 0) {
                            $altNames2[$name] = $codes;
                        }
                    }

                    if ($currentLangName) {
                        unset($altNames2[$currentLangName]);
                    }


                    $designCarsRow = $db->fetchRow(
                        $db->select()
                            ->from('brands', [
                                'brand_name'    => 'caption',
                                'brand_catname' => 'folder'
                            ])
                            ->join('brands_cars', 'brands.id = brands_cars.brand_id', [
                                'brand_car_catname' => 'catname'
                            ])
                            ->where('brands_cars.type = ?', Brand_Car::TYPE_DESIGN)
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', 'car_id')
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );
                    if ($designCarsRow) {
                        $designProject = [
                            'brand' => $designCarsRow['brand_name'],
                            'url'   => $controller->url()->fromRoute('catalogue', [
                                'action'        => 'brand-car',
                                'brand_catname' => $designCarsRow['brand_catname'],
                                'car_catname'   => $designCarsRow['brand_car_catname']
                            ])
                        ];
                    }


                    $cdTable = new Category();
                    $cdlTable = new Category_Language();

                    $categoryRows = $cdTable->fetchAll(
                        $cdTable->select(true)
                            ->join('category_car', 'category.id = category_car.category_id', null)
                            ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    foreach ($categoryRows as $row) {
                        $lRow = $cdlTable->fetchRow([
                            'language = ?'    => $language,
                            'category_id = ?' => $row->id
                        ]);
                        $categories[$row->id] = [
                            'name' => $lRow ? $lRow->name : $row->name,
                            'url'  => $controller->url()->fromRoute('categories', [
                                'action'           => 'category',
                                'category_catname' => $row['catname'],
                            ])
                        ];
                    }

                    if ($car->full_text_id) {
                        foreach ($catalogue->cataloguePaths($car) as $path) {
                            $carDetailsUrl = $controller->url()->fromRoute('catalogue', [
                                'action'        => 'brand-car',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path']
                            ]);
                            break;
                        }
                    }
                }
                break;
        }



        // links
        $ofLinks = [];
        $linksTable = new BrandLink();
        if (count($brandIds)) {
            $links = $linksTable->fetchAll(
                $linksTable->select(true)
                    ->where('brandId in (?)', $brandIds)
                    ->where('type = ?', 'official')
            );
            foreach ($links as $link) {
                $ofLinks[$link->id] = $link;
            }
        }

        $replacePicture = null;
        if ($picture->replace_picture_id) {
            $replacePictureRow = $pictureTable->find($picture->replace_picture_id)->current();

            $replacePicture = $controller->pic()->href($replacePictureRow->toArray());

            if ($replacePictureRow->status == Picture::STATUS_REMOVING) {
                if (!$controller->user()->inheritsRole('moder')) {
                    $replacePicture = null;
                }
            }
        }

        $moderLinks = [];
        if ($isModer) {
            $moderLinks = $this->getModerLinks($picture);
        }

        $userTable = new Users();

        $moderVotes = [];
        foreach ($picture->findPicture_Moder_Vote() as $moderVote) {
            $moderVotes[] = [
                'vote'   => $moderVote->vote,
                'reason' => $moderVote->reason,
                'user'   => $userTable->find($moderVote->user_id)->current()
            ];
        }

        $image = $imageStorage->getImage($picture->image_id);
        $sourceUrl = $image ? $image->getSrc() : null;

        $preview = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-medium');
        $previewUrl = $preview ? $preview->getSrc() : null;


        $paginator = false;
        $pageNumbers = false;

        if ($picSelect) {

            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($picSelect)
            );

            $total = $paginator->getTotalItemCount();

            if ($total < 500) {

                $db = $pictureTable->getAdapter();

                $paginatorPictures = $db->fetchAll(
                    $db->select()
                        ->from(['_pic' => new Zend_Db_Expr('('.$picSelect->assemble() .')')], ['id', 'identity'])
                );

                //$paginatorPictures = $pictureTable->fetchAll($picSelect);

                $pageNumber = 0;
                foreach ($paginatorPictures as $n => $p) {
                    if ($p['id'] == $picture->id) {
                        $pageNumber = $n+1;
                        break;
                    }
                }

                $paginator
                    ->setItemCountPerPage(1)
                    ->setPageRange(15)
                    ->setCurrentPageNumber($pageNumber);

                $pages = $paginator->getPages();

                $pageNumbers = $pages->pagesInRange;
                if (isset($pages->previous)) {
                    $pageNumbers[] = $pages->previous;
                }
                if (isset($pages->next)) {
                    $pageNumbers[] = $pages->next;
                }

                $pageNumbers = array_unique($pageNumbers);
                $pageNumbers = array_combine($pageNumbers, $pageNumbers);

                foreach($pageNumbers as $page => &$val) {
                    $pic = $paginatorPictures[$page - 1];
                    $val = $controller->url()->fromRoute($options['paginator']['route'], array_replace($options['paginator']['urlParams'], [
                        'picture_id' => $pic['identity'] ? $pic['identity'] : $pic['id']
                    ]));
                }
                unset($val);

            } else {
                $paginator = false;
            }
        }

        $names = $pictureTable->getNameData([$picture->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$picture->id];

        $mTable = new ModificationTable();
        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->join('modification_picture', 'modification.id = modification_picture.modification_id', null)
                ->where('modification_picture.picture_id = ?', $picture['id'])
                ->order('modification.name')
        );

        $modifications = [];
        foreach ($mRows as $mRow) {

            $url = null;
            $carTable = new Cars();
            $carRow = $carTable->find($mRow->car_id)->current();
            if ($carRow) {
                $carParentTable = new Car_Parent();
                $paths = $carParentTable->getPaths($carRow->id, [
                    'breakOnFirst' => true
                ]);
                if (count($paths) > 0) {
                    $path = $paths[0];

                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'brand-car-pictures',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path'],
                        'mod'           => $mRow->id
                    ]);
                }
            }

            $modifications[] = [
                'name' => $mRow->name,
                'url'  => $url
            ];
        }

        $carDescription = null;
        if ($car && $car->text_id) {
            $carDescription = $this->textStorage->getText($car->text_id);
        }

        $carText = null;
        if ($car && $car->full_text_id) {
            $carText = $this->textStorage->getText($car->full_text_id);
        }

        $copyrights = null;
        if ($picture->copyrights_text_id) {
            $copyrights = $this->textStorage->getText($picture->copyrights_text_id);
        }

        $picturePerspective = null;
        if ($this->getController()->user()->inheritsRole('moder')) {
            if ($picture->type == Picture::CAR_TYPE_ID) {
                $perspectives = new Perspectives();

                $multioptions = $perspectives->getAdapter()->fetchPairs(
                    $perspectives->getAdapter()->select()
                        ->from($perspectives->info('name'), ['id', 'name'])
                        ->order('position')
                );

                $multioptions = array_replace([
                    '' => '--'
                ], $multioptions);

                $user = $picture->findParentUsersByChange_Perspective_User();

                $picturePerspective = [
                    'options' => $multioptions,
                    'url'     => $this->getController()->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'picture-perspective',
                        'picture_id' => $picture->id
                    ]),
                    'user'    => $user,
                    'value'   => $picture->perspective_id
                ];
            }
        }

        $data = [
            'id'                => $picture['id'],
            'copyrights'        => $copyrights,
            'identity'          => $picture['identity'],
            'name'              => $name,
            'picture'           => $picture,
            'owner'             => $picture->findParentUsersByOwner(),
            'addDate'           => $picture->getDateTime('add_date'),
            'ofLinks'           => $ofLinks,
            'moderVotes'        => $moderVotes,
            'sourceUrl'         => $sourceUrl,
            'previewUrl'        => $previewUrl,
            'replacePicture'    => $replacePicture,
            'gallery'           => [
                'current' => $picture->id
            ],
            'paginator'         => $paginator,
            'paginatorPictures' => $pageNumbers,
            'engine'            => $engine,
            'engineCars'        => $engineCars,
            'engineHasSpecs'    => $engineHasSpecs,
            'engineSpecsUrl'    => $engineSpecsUrl,
            'factory'           => $factory,
            'factoryCars'       => $factoryCars,
            'factoryCarsMore'   => $factoryCarsMore,
            'moderLinks'        => $moderLinks,
            'altNames'          => $altNames2,
            'langName'          => $currentLangName,
            'designProject'     => $designProject,
            'categories'        => $categories,
            'carDetailsUrl'     => $carDetailsUrl,
            'carHtml'           => $carText,
            'carDescription'    => $carDescription,
            'modifications'     => $modifications,
            'pictureVote'       => $this->getController()->pictureVote($picture->id, [
                'hideVote' => true
            ]),
            'picturePerspective' => $picturePerspective
        ];

        // Обвновляем количество просмотров
        $views = new Picture_View();
        $views->inc($picture);

        return $data;
    }

    private function getModerLinks($picture)
    {
        $controller = $this->getController();
        $language = $controller->language();

        $links = [];
        $links[$controller->url()->fromRoute('moder/pictures/params', [
            'action'     => 'picture',
            'picture_id' => $picture->id
        ])] = sprintf($this->translator->translate('moder/picture/edit-picture-%s'), $picture->id);

        switch ($picture->type) {
            case Picture::CAR_TYPE_ID:
                $car = $picture->findParentCars();
                if ($car) {
                    $url = $controller->url()->fromRoute('moder/cars/params', [
                        'action' => 'car',
                        'car_id' => $car->id
                    ]);
                    $links[$url] = sprintf($this->translator->translate('moder/picture/edit-vehicle-%s'), $car->getFullName($language));

                    $brandModel = new Brand();
                    $brands = $brandModel->getList(['language' => $language], function($select) use ($car) {
                        $select
                            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                            ->group('brands.id');
                    });

                        foreach ($brands as $brand) {
                            $url = $controller->url()->fromRoute('moder/brands/params', [
                                'action'   => 'brand',
                                'brand_id' => $brand['id']
                            ]);
                            $links[$url] = sprintf($this->translator->translate('moder/picture/edit-brand-%s'), $brand['name']);
                        }

                }

                break;

            case Picture::ENGINE_TYPE_ID:
                if ($engine = $picture->findParentEngines()) {
                    $url = $controller->url()->fromRoute('moder/engines/params', [
                        'action'    => 'engine',
                        'engine_id' => $engine->id
                    ]);
                    $links[$url] = sprintf($this->translator->translate('moder/picture/edit-engine-%s'), $engine->caption);
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                if ($factory = $picture->findParentFactory()) {
                    $links[$controller->url()->fromRoute('moder/factories/params', [
                        'action'     => 'factory',
                        'factory_id' => $factory->id
                    ])] = sprintf($this->translator->translate('moder/picture/edit-factory-%s'), $factory->name);
                }
                break;

            case Picture::MIXED_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:

                $brandModel = new Brand();
                $brand = $brandModel->getBrandById($picture->brand_id, $language);
                if ($brand) {
                    $url = $controller->url()->fromRoute('moder/brands/params', [
                        'action'   => 'brand',
                        'brand_id' => $brand['id']
                    ]);
                    $links[$url] = sprintf($this->translator->translate('moder/picture/edit-brand-%s'), $brand['name']);
                }
                break;
        }

        return $links;
    }

    public function gallery(Zend_Db_Table_Select $picSelect)
    {
        $galleryStatuses = [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW];

        $gallery = [];

        $controller = $this->getController();
        $catalogue = $controller->catalogue();
        $imageStorage = $controller->imageStorage();

        $language = $controller->language();

        $select = clone $picSelect;

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->setIntegrityCheck(false)
            ->columns([
                'pictures.id', 'pictures.identity', 'pictures.name',
                'pictures.width', 'pictures.height',
                'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                'pictures.image_id', 'pictures.filesize',
                'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
            ])
            ->joinLeft(['ct' => 'comment_topic'], 'ct.type_id = :type_id and ct.item_id = pictures.id', 'messages');

        $rows = $select->getAdapter()->fetchAll($select, [
            'type_id' => Comment_Message::PICTURES_TYPE_ID
        ]);



        // prefetch
        $fullRequests = [];
        $cropRequests = [];
        $imageIds = [];
        foreach ($rows as $idx => $picture) {
            $request = Picture_Row::buildFormatRequest($picture);
            $fullRequests[$idx] = $request;
            if (Picture_Row::checkCropParameters($picture)) {
                $cropRequests[$idx] = $request;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$picture['image_id'];
        }

        // images
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, [
            'language' => $language
        ]);

        // comments
        $userId = null;
        if ($controller->user()->logedIn()) {
            $userId = $controller->user()->get()->id;
        }

        if ($userId) {
            $ctTable = new Comment_Topic();
            $newMessages = $ctTable->getNewMessages(
                Comment_Message::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }


        foreach ($rows as $idx => $row) {

            $imageId = (int)$row['image_id'];

            if ($imageId) {

                $id = (int)$row['id'];

                $image = isset($images[$imageId]) ? $images[$imageId] : null;
                if ($image) {
                    $sUrl = $image->getSrc();

                    if (Picture_Row::checkCropParameters($row)) {
                        $crop = isset($cropImagesInfo[$idx]) ? $cropImagesInfo[$idx]->toArray() : null;

                        $crop['crop'] = [
                            'left'   => $row['crop_left'] / $image->getWidth(),
                            'top'    => $row['crop_top'] / $image->getHeight(),
                            'width'  => $row['crop_width'] / $image->getWidth(),
                            'height' => $row['crop_height'] / $image->getHeight(),
                        ];

                    } else {
                        $crop = null;
                    }

                    $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

                    $msgCount = $row['messages'];
                    $newMsgCount = 0;
                    if ($userId) {
                        $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                    }

                    $name = isset($names[$id]) ? $names[$id] : null;

                    $url = $controller->url('picture/picture', [
                        'picture_id' => $row['identity'] ? $row['identity'] : $id,
                        'gallery'    => null
                    ]);

                    $gallery[] = [
                        'id'          => $id,
                        'url'         => $url,
                        'sourceUrl'   => $sUrl,
                        'crop'        => $crop,
                        'full'        => $full,
                        'messages'    => $msgCount,
                        'newMessages' => $newMsgCount,
                        'name'        => $name,
                        'filesize'    => $controller->fileSize($row['filesize'])
                    ];
                }
            }
        }

        return $gallery;
    }

    public function gallery2(Zend_Db_Table_Select $picSelect, array $options = [])
    {
        $defaults = [
            'page'      => 1,
            'pictureId' => null,
            'route'     => null,
            'urlParams' => []
        ];
        $options = array_replace($defaults, $options);

        $itemsPerPage = 10;

        $galleryStatuses = [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW];

        $gallery = [];

        $controller = $this->getController();
        $catalogue = $controller->catalogue();
        $imageStorage = $controller->imageStorage();

        $language = $controller->language();

        if ($options['pictureId']) {
            // look for page of that picture
            $select = clone $picSelect;

            $select
                ->setIntegrityCheck(false)
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns([
                    'pictures.id'
                ]);

            $col = $select->getAdapter()->fetchCol($select);
            foreach ($col as $index => $id) {
                if ($id == $options['pictureId']) {
                    $options['page'] = ceil(($index+1) / $itemsPerPage);
                    break;
                }
            }
        }

        $select = clone $picSelect;

        $select
            ->setIntegrityCheck(false)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns([
                'pictures.id', 'pictures.identity', 'pictures.name',
                'pictures.width', 'pictures.height',
                'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                'pictures.image_id', 'pictures.filesize',
                'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                'pictures.perspective_id', 'pictures.type', 'pictures.factory_id',
                'pictures.type'
            ])
            ->joinLeft(
                ['ct' => 'comment_topic'],
                'ct.type_id = :type_id and ct.item_id = pictures.id',
                'messages'
            )
            ->bind([
                'type_id' => Comment_Message::PICTURES_TYPE_ID
            ]);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbSelect($select)
        );

        $count = $paginator->getTotalItemCount();

        $paginator = new \Zend\Paginator\Paginator(
            new \Zend\Paginator\Adapter\NullFill($count)
        );

        $paginator
            ->setItemCountPerPage($itemsPerPage)
            ->setCurrentPageNumber($options['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $rows = $select->getAdapter()->fetchAll($select);



        // prefetch
        $ids = [];
        $fullRequests = [];
        $cropRequests = [];
        $imageIds = [];
        foreach ($rows as $idx => $picture) {
            $request = Picture_Row::buildFormatRequest($picture);
            $fullRequests[$idx] = $request;
            if (Picture_Row::checkCropParameters($picture)) {
                $cropRequests[$idx] = $request;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$picture['image_id'];
        }

        // images
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, [
            'language'   => $language,
            'translator' => $this->translator
        ]);

        // comments
        $userId = null;
        if ($controller->user()->logedIn()) {
            $userId = $controller->user()->get()->id;
        }

        if ($userId) {
            $ctTable = new Comment_Topic();
            $newMessages = $ctTable->getNewMessages(
                Comment_Message::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }

        $route = $options['route'] ? $options['route'] : null;


        foreach ($rows as $idx => $row) {

            $imageId = (int)$row['image_id'];

            if ($imageId) {

                $id = (int)$row['id'];

                $image = isset($images[$imageId]) ? $images[$imageId] : null;
                if ($image) {
                    $sUrl = $image->getSrc();

                    if (Picture_Row::checkCropParameters($row)) {
                        $crop = isset($cropImagesInfo[$idx]) ? $cropImagesInfo[$idx]->toArray() : null;

                        $crop['crop'] = [
                            'left'   => $row['crop_left'] / $image->getWidth(),
                            'top'    => $row['crop_top'] / $image->getHeight(),
                            'width'  => $row['crop_width'] / $image->getWidth(),
                            'height' => $row['crop_height'] / $image->getHeight(),
                        ];

                    } else {
                        $crop = null;
                    }

                    $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

                    $msgCount = $row['messages'];
                    $newMsgCount = 0;
                    if ($userId) {
                        $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                    }

                    $name = isset($names[$id]) ? $names[$id] : null;
                    // TODO: extract HTML to view script
                    if (($row['type'] == Picture::CAR_TYPE_ID) && is_array($name)) {
                        $name = $this->carHelper->htmlTitle($name);
                    } else {
                        $name = htmlspecialchars($name);
                    }

                    $reuseParams = isset($options['reuseParams']) && $options['reuseParams'];
                    $url = $controller->url()->fromRoute($route, array_replace($options['urlParams'], [
                        'picture_id' => $row['identity'] ? $row['identity'] : $id,
                        'gallery'    => null,
                    ]), [], $reuseParams);

                    $gallery[] = [
                        'id'          => $id,
                        'type'        => $row['type'],
                        'url'         => $url,
                        'sourceUrl'   => $sUrl,
                        'crop'        => $crop,
                        'full'        => $full,
                        'messages'    => $msgCount,
                        'newMessages' => $newMsgCount,
                        'name'        => $name,
                        'filesize'    => $row['filesize'] //$view->fileSize($row['filesize'])
                    ];
                }
            }
        }

        return [
            'page'  => $paginator->getCurrentPageNumber(),
            'pages' => $paginator->count(),
            'count' => $paginator->getTotalItemCount(),
            'items' => $gallery
        ];
    }

    public function name($pictureRow, $language)
    {
        $pictureTable = new Picture();
        $names = $pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow->id];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
