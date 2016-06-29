<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\Twins;
use Autowp\TextStorage;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Application_Service_Specifications;
use Cars;
use Cars_Row;
use Comment_Message;
use Comment_Topic;
use Picture;

use Zend_Db_Expr;

class TwinsController extends AbstractActionController
{
    const GROUPS_PER_PAGE = 20;

    /**
     * @var Twins
     */
    private $twins;

    private $textStorage;

    private $cache;

    public function __construct(TextStorage\Service $textStorage, $cache)
    {
        $this->textStorage = $textStorage;
        $this->cache = $cache;
    }

    /**
     * @return Twins
     */
    private function getTwins()
    {
        return $this->twins
            ? $this->twins
            : $this->twins = new Twins();
    }

    private function getBrands(array $selectedIds)
    {
        $language = $this->language();

        $key = 'TWINS_SIDEBAR_4_' . $language;

        $arr = $this->cache->getItem($key, $success);
        if (!$success) {

            $arr = $this->getTwins()->getBrands([
                'language' => $language
            ]);

            foreach ($arr as &$brand) {
                $brand['url'] = $this->url()->fromRoute('twins/brand', [
                    'brand_catname' => $brand['folder']
                ]);
            }
            unset($brand);

            $this->cache->setItem($key, $arr);
        }

        foreach ($arr as &$brand) {
            $brand['selected'] = in_array($brand['id'], $selectedIds);
        }

        return $arr;
    }

    public function specificationsAction()
    {
        $group = $this->getTwins()->getGroup($this->params('id'));
        if (!$group) {
            return $this->getResponse()->setStatusCode(404);
        }

        $service = new Application_Service_Specifications();
        $specs = $service->specifications($this->getTwins()->getGroupCars($group['id']), [
            'language' => 'en'
        ]);

        return [
            'group' => $group,
            'specs' => $specs,
        ];
    }

    public function picturesAction()
    {
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (!$group) {
            return $this->getResponse()->setStatusCode(404);
        }

        $select = $twins->getGroupPicturesSelect($group['id'], [
            'ordering' => $this->catalogue()->picturesOrdering()
        ]);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 4,
            'url'   => function($row) use ($group) {
                return $this->url()->fromRoute('twins/group/pictures/picture', [
                    'id'         => $group['id'],
                    'picture_id' => $row['identity'] ? $row['identity'] : $row['id']
                ]);
            }
        ]);

        return [
            'group'        => $group,
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'brands'       => $this->getBrands($twins->getGroupBrandIds($group['id']))
        ];
    }

    public function groupAction()
    {
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (!$group) {
            return $this->getResponse()->setStatusCode(404);
        }

        $carList = $twins->getGroupCars($group['id']);

        $hasSpecs = false;

        $specService = new Application_Service_Specifications();

        foreach ($carList as $car) {
            $hasSpecs = $hasSpecs || $specService->hasSpecs(1, $car->id);
        }

        $picturesCount = $twins->getGroupPicturesCount($group['id']);

        $description = null;
        if ($group['text_id']) {
            $description = $this->textStorage->getText($group['text_id']);
        }

        return [
            'group'              => $group,
            'description'        => $description,
            'cars'               => $this->car()->listData($carList, [
                'disableTwins'         => true,
                'disableLargePictures' => true,
                'disableSpecs'         => true,
                'pictureUrl'           => function($car, $picture) use ($group) {
                    return $this->url()->fromRoute('twins/group/pictures/picture', [
                        'id'         => $group['id'],
                        'picture_id' => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ]);
                }
            ]),
            'picturesCount'      => $picturesCount,
            'hasSpecs'           => $hasSpecs,
            'specsUrl'           => $this->url()->fromRoute('twins/group/specifications', [
                'id' => $group['id']
            ]),
            'picturesUrl'        => $this->url()->fromRoute('twins/group/pictures', [
                'id' => $group['id'],
            ]),
            'brands'             => $this->getBrands($this->getTwins()->getGroupBrandIds($group['id']))
        ];
    }

    private function prepareList($list)
    {
        $ctTable = new Comment_Topic();
        $pictureTable = new Picture();

        $imageStorage = $this->imageStorage();

        $language = $this->language();

        $specService = new Application_Service_Specifications();

        $ids = [];
        foreach ($list as $group) {
            $ids[] = $group->id;
        }

        $picturesCounts = $this->getTwins()->getGroupPicturesCount($ids);

        $commentsStats = $ctTable->getTopicStat(
            Comment_Message::TWINS_TYPE_ID,
            $ids
        );

        $hasSpecs = $specService->twinsGroupsHasSpecs($ids);

        $carLists = [];
        if (count($ids)) {

            $carTable = new Cars();

            $db = $carTable->getAdapter();

            $langJoinExpr = 'cars.id = car_language.car_id and ' .
                $db->quoteInto('car_language.language = ?', $language);

            $rows = $db->fetchAll(
                $db->select()
                    ->from('cars', [
                        'cars.id',
                        'name' => 'if(length(car_language.name), car_language.name, cars.caption)',
                        'cars.body', 'cars.begin_model_year', 'cars.end_model_year',
                        'cars.begin_year', 'cars.end_year', 'cars.today',
                        'spec' => 'spec.short_name'
                    ])
                    ->join('twins_groups_cars', 'cars.id = twins_groups_cars.car_id', 'twins_group_id')
                    ->joinLeft('car_language', $langJoinExpr, null)
                    ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                    ->where('twins_groups_cars.twins_group_id in (?)', $ids)
                    ->order('name')
            );
            foreach ($rows as $row) {
                $carLists[$row['twins_group_id']][] = $row;
            }
        }

        $groups = [];
        $requests = [];
        foreach ($list as $group) {

            $carList = isset($carLists[$group->id]) ? $carLists[$group->id] : [];

            $picturesShown = 0;
            $cars = [];

            foreach ($carList as $car) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', (int)$car['id'])
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                        ->order([
                            new Zend_Db_Expr('pictures.perspective_id=7 DESC'),
                            new Zend_Db_Expr('pictures.perspective_id=8 DESC')
                        ])
                        ->limit(1)
                );

                $picture = null;
                if ($pictureRow) {
                    $picturesShown++;

                    $key = 'g' . $group->id . 'p' . $pictureRow->id;

                    $request = $pictureRow->getFormatRequest();
                    $requests[$key] = $request;

                    $url = $this->url()->fromRoute('twins/group/pictures/picture', [
                        'id'         => $group['id'],
                        'picture_id' => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                    ]);

                    $picture = [
                        'key' => $key,
                        'url' => $url,
                        'src' => null
                    ];
                }

                $name = Cars_Row::buildFullName([
                    'begin_model_year' => $car['begin_model_year'],
                    'end_model_year'   => $car['end_model_year'],
                    'spec'             => $car['spec'],
                    'body'             => $car['body'],
                    'name'             => $car['name'],
                    'begin_year'       => $car['begin_year'],
                    'end_year'         => $car['end_year'],
                    'today'            => $car['today']
                ]);

                $cars[] = [
                    'name'    => $name,
                    'picture' => $picture
                ];
            }

            $commentsStat = isset($commentsStats[$group->id]) ? $commentsStats[$group->id] : null;
            $msgCount = $commentsStat ? $commentsStat['messages'] : 0;

            $picturesCount = isset($picturesCounts[$group->id]) ? $picturesCounts[$group->id] : null;

            $groups[] = [
                'name'          => $group->name,
                'cars'          => $cars,
                'picturesShown' => $picturesShown,
                'picturesCount' => $picturesCount,
                'hasSpecs'      => isset($hasSpecs[$group->id]) && $hasSpecs[$group->id],
                'msgCount'      => $msgCount,
                'detailsUrl'    => $this->url()->fromRoute('twins/group', [
                    'id' => $group->id
                ]),
                'specsUrl'      => $this->url()->fromRoute('twins/group/specifications', [
                    'id' => $group->id,
                ]),
                'picturesUrl'   => $this->url()->fromRoute('twins/group/pictures', [
                    'id' => $group->id,
                ]),
                'moderUrl'      => $this->url()->fromRoute('moder/twins', [
                    'action'         => 'twins-group',
                    'twins_group_id' => $group->id
                ])
            ];
        }


        // fetch images from storage
        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        foreach ($groups as &$group) {
            foreach ($group['cars'] as &$car) {
                if ($car['picture']) {
                    $key = $car['picture']['key'];
                    if (isset($imagesInfo[$key])) {
                        $car['picture']['src'] = $imagesInfo[$key]->getSrc();
                    }
                }
            }
            unset($car);
        }
        unset($group);

        return $groups;
    }

    public function brandAction()
    {
        $brand = $this->catalogue()->getBrandTable()->findRowByCatname($this->params('brand_catname'));

        if (!$brand) {
            return $this->getResponse()->setStatusCode(404);
        }

        $canEdit = $this->user()->isAllowed('twins', 'edit');

        $paginator = $this->getTwins()->getGroupsPaginator2([
                'brandId' => $brand->id
            ])
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $groups = $this->prepareList($paginator->getCurrentItems());

        return [
            'groups'    => $groups,
            'paginator' => $paginator,
            'brand'     => $brand,
            'canEdit'   => $canEdit,
            'brands'    => $this->getBrands([$brand->id])
        ];
    }

    public function indexAction()
    {
        $canEdit = $this->user()->isAllowed('twins', 'edit');

        $paginator = $this->getTwins()->getGroupsPaginator2()
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $groups = $this->prepareList($paginator->getCurrentItems());

        return [
            'groups'    => $groups,
            'paginator' => $paginator,
            'canEdit'   => $canEdit,
            'brands'    => $this->getBrands([])
        ];
    }

    private function _pictureAction($callback)
    {
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (!$group) {
            return $this->getResponse()->setStatusCode(404);
        }

        $pictureId = (string)$this->params('picture_id');

        $select = $twins->getGroupPicturesSelect($group['id'])
            ->where('pictures.id = ?', $pictureId)
            ->where('pictures.identity IS NULL');

        $picture = $select->getTable()->fetchRow($select);

        if (!$picture) {
            $select = $twins->getGroupPicturesSelect($group['id'])
                ->where('pictures.identity = ?', $pictureId);

            $picture = $select->getTable()->fetchRow($select);
        }

        if (!$picture) {
            return $this->getResponse()->setStatusCode(404);
        }

        return $callback($group, $picture);
    }

    public function pictureAction()
    {
        return $this->_pictureAction(function($group, $picture) {

            $twins = $this->getTwins();

            $select = $twins->getGroupPicturesSelect($group['id'], [
                'ordering' => $this->catalogue()->picturesOrdering()
            ]);

            $data = $this->pic()->picPageData($picture, $select, [], [
                'paginator' => [
                    'route'     => 'twins/group/pictures/picture',
                    'urlParams' => [
                        'id' => $group['id'],
                    ]
                ]
            ]);

            return array_replace($data, [
                'group'      => $group,
                'gallery2'   => true,
                'galleryUrl' => $this->url()->fromRoute('twins/group/pictures/picture/gallery', [
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity'] ? $picture['identity'] : $picture['id']
                ]),
                'brands'     => $this->getBrands($twins->getGroupBrandIds($group['id']))
            ]);
        });
    }

    public function pictureGalleryAction()
    {
        return $this->_pictureAction(function($group, $picture) {

            $select = $this->getTwins()->getGroupPicturesSelect($group['id'], [
                'ordering' => $this->catalogue()->picturesOrdering()
            ]);

            return new JsonModel($this->pic()->gallery2($select, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'route'     => 'twins/group/pictures/picture/gallery',
                'urlParams' => [
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity'] ? $picture['identity'] : $picture['id']
                ]
            ]));

        });
    }
}