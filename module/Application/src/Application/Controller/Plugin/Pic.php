<?php

namespace Application\Controller\Plugin;

use ArrayIterator;
use ArrayObject;
use DateTime;
use Exception;

use geoPHP;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Router\Http\TreeRouteStack;

use Autowp\Comments;
use Autowp\Commons\Db\Table\Row;
use Autowp\TextStorage;
use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Brand;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Application\Model\PictureVote;
use Application\Model\UserAccount;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;

class Pic extends AbstractPlugin
{
    /**
     * @var PictureView
     */
    private $pictureView = null;

    /**
     * @var TextStorage\Service
     */
    private $textStorage;

    private $translator;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var PictureVote
     */
    private $pictureVote;

    /**
     * @var Catalogue
     */
    private $catalogue;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var UserAccount
     */
    private $userAccount;

    /**
     * @var TableGateway
     */
    private $itemLinkTable;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var TreeRouteStack
     */
    private $httpRouter;

    public function __construct(
        TextStorage\Service $textStorage,
        $translator,
        PictureNameFormatter $pictureNameFormatter,
        ItemNameFormatter $itemNameFormatter,
        SpecificationsService $specsService,
        PictureItem $pictureItem,
        TreeRouteStack $httpRouter,
        Comments\CommentsService $comments,
        PictureVote $pictureVote,
        Catalogue $catalogue,
        PictureView $pictureView,
        Item $itemModel,
        Perspective $perspective,
        UserAccount $userAccount,
        TableGateway $itemLinkTable,
        PictureModerVote $pictureModerVote,
        Brand $brand,
        Picture $picture,
        User $userModel
    ) {
        $this->textStorage = $textStorage;
        $this->translator = $translator;
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->specsService = $specsService;
        $this->pictureItem = $pictureItem;
        $this->httpRouter = $httpRouter;
        $this->comments = $comments;
        $this->pictureVote = $pictureVote;
        $this->catalogue = $catalogue;
        $this->pictureView = $pictureView;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->userAccount = $userAccount;
        $this->itemLinkTable = $itemLinkTable;
        $this->pictureModerVote = $pictureModerVote;
        $this->brand = $brand;
        $this->picture = $picture;
        $this->userModel = $userModel;
    }

    public function href($row, array $options = [])
    {
        $defaults = [
            'fallback'  => true,
            'canonical' => false,
            'uri'       => null
        ];
        $options = array_replace($defaults, $options);

        $url = null;

        $itemIds = $this->pictureItem->getPictureItems($row['id'], PictureItem::PICTURE_CONTENT);
        if ($itemIds) {
            $itemIds = $this->itemModel->getIds([
                'id'           => $itemIds,
                'item_type_id' => [Item::BRAND, Item::VEHICLE, Item::ENGINE]
            ]);

            if ($itemIds) {
                $carId = $itemIds[0];

                $paths = $this->catalogue->getCataloguePaths($carId, [
                    'breakOnFirst' => true,
                    'stockFirst'   => true
                ]);


                if (count($paths) > 0) {
                    $path = $paths[0];

                    if ($path['car_catname']) {
                        $url = $this->httpRouter->assemble([
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $row['identity']
                        ], [
                            'name'            => 'catalogue',
                            'force_canonical' => $options['canonical']
                        ]);
                    } else {
                        $perspectiveId = $this->pictureItem->getPerspective($row['id'], $carId);

                        switch ($perspectiveId) {
                            case 22:
                                $action = 'logotypes-picture';
                                break;
                            case 25:
                                $action = 'mixed-picture';
                                break;
                            default:
                                $action = 'other-picture';
                                break;
                        }

                        $url = $this->httpRouter->assemble([
                            'action'        => $action,
                            'brand_catname' => $path['brand_catname'],
                            'picture_id'    => $row['identity']
                        ], [
                            'name' => 'catalogue',
                            'force_canonical' => $options['canonical']
                        ]);
                    }
                }
            }
        }

        if ($options['fallback'] && ! $url) {
            $url = $this->url($row['identity'], $options['canonical']);
        }

        return $url;
    }

    public function url($identity, $absolute = false, $uri = null)
    {
        return $this->httpRouter->assemble([
            'picture_id' => $identity
        ], [
            'name'            => 'picture/picture',
            'force_canonical' => $absolute,
            'uri'             => $uri
        ]);
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
            if (! $colClass) {
                $colClass = 'col-lg-' . (12 / $width) . ' col-md-' . (12 / $width);
            }
        }

        $controller = $this->getController();
        $imageStorage = $controller->imageStorage();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isModer = $controller->user()->inheritsRole('pictures-moder');
        $userId = null;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($controller->user()->logedIn()) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $user = $controller->user()->get();
            $userId = $user ? $user['id'] : null;
        }

        $language = $controller->language();

        $ids = [];

        if ($pictures instanceof ArrayIterator) {
            $rows = [];
            foreach ($pictures as $picture) {
                $rows[] = (array)$picture;
            }
            $pictures = $rows;
        }

        if (is_array($pictures)) {
            $rows = [];
            foreach ($pictures as $picture) {
                $ids[] = $picture['id'];
                $rows[] = $picture;
            }

            // moder votes
            $moderVotes = $this->pictureModerVote->getVoteCountArray($ids);

            // views
            $views = [];
            if (! $options['disableBehaviour']) {
                $views = $this->pictureView->getValues($ids);
            }

            // messages
            $messages = [];
            if (! $options['disableBehaviour'] && count($ids)) {
                $messages = $this->comments->getMessagesCounts(
                    \Application\Comments::PICTURES_TYPE_ID,
                    $ids
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
                if (! $options['disableBehaviour']) {
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
        } else {
            throw new Exception(sprintf("Unexpected type of pictures: %s", get_class($pictures)));
        }

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = $picture['image_id'];
        }

        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb-medium');

        // names
        $names = $this->picture->getNameData($rows, [
            'language' => $language
        ]);

        // comments
        $newMessages = [];
        if (! $options['disableBehaviour']) {
            if ($userId) {
                $newMessages = $this->comments->getNewMessages(
                    \Application\Comments::PICTURES_TYPE_ID,
                    $ids,
                    $userId
                );
            }
        }

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
                'name'      => $name,
                'url'       => $url,
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                'moderVote' => $row['moder_votes_count'] > 0 ? $row['moder_votes'] : null,
                //'perspective_id' => $row['perspective_id']
            ];

            if (! $options['disableBehaviour']) {
                $msgCount = $row['messages'];
                $newMsgCount = 0;
                if ($userId) {
                    $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                }

                $likes = 5;

                $votes = $this->pictureVote->getVote($row['id'], null);

                $item = array_replace($item, [
                    'resolution'      => (int)$row['width'] . '×' . (int)$row['height'],
                    'status'          => $row['status'],
                    'views'           => (int)$row['views'],
                    'msgCount'        => $msgCount,
                    'newMsgCount'     => $newMsgCount,
                    'likes'           => $likes,
                    'ownerId'         => $row['owner_id'],
                    'votes'           => $votes
                ]);

                if ($isModer) {
                    $crop = $imageStorage->getImageCrop($row['image_id']);

                    $item['cropped']         = (bool)$crop;
                    $item['crop_resolution'] = $crop ? $crop['width'] . '×' . $crop['height'] : null;
                }
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

    private function picPageItemsData($picture, $carIds)
    {
        $controller = $this->getController();

        $language = $controller->language();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isModer = $controller->user()->inheritsRole('moder');

        $multioptions = [];
        if ($isModer) {
            $multioptions = array_replace([
                '' => '--'
            ], $this->perspective->getPairs());
        }

        $itemRows = [];
        if ($carIds) {
            $itemRows = $this->itemModel->getRows([
                'id'           => $carIds,
                'item_type_id' => Item::VEHICLE
            ]);
        }
        $itemsCount = count($itemRows);

        $items = [];
        foreach ($itemRows as $item) {
            $twins = [];
            $designProject = null;
            $detailsUrl = null;
            $factories = [];
            $text = null;
            $fullText = null;
            $specsEditUrl = null;

            if ($itemsCount == 1) {
                $twinsGroupsRows = $this->itemModel->getRows([
                    'item_type_id' => Item::TWINS,
                    'descendant'   => $item['id'],
                    'columns'      => ['id']
                ]);

                foreach ($twinsGroupsRows as $twinsGroup) {
                    $twins[] = [
                        'url' => '/ng/twins/group/' . $twinsGroup['id']
                    ];
                }

                $designProject = $this->itemModel->getDesignInfo($this->httpRouter, $item['id'], $language);

                $texts = $this->itemModel->getTextsOfItem($item['id'], $language);

                $fullText = $texts['full_text'];
                $text = $texts['text'];

                if ((bool)$fullText) {
                    foreach ($this->catalogue->getCataloguePaths($item['id']) as $path) {
                        $detailsUrl = $this->httpRouter->assemble([
                            'action'        => 'brand-item',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ], [
                            'name' => 'catalogue'
                        ]);
                        break;
                    }
                }

                // factories
                $factoryRows = $this->itemModel->getRows([
                    'item_type_id' => Item::FACTORY,
                    'descendant'   => $item['id']
                ]);
                foreach ($factoryRows as $factoryRow) {
                    $factories[] = [
                        'name' => $factoryRow['name'], // TODO: formatter
                        'url'  => '/ng/factories/' . $factoryRow['id']
                    ];
                }

                /* @phan-suppress-next-line PhanUndeclaredMethod */
                if ($controller->user()->isAllowed('specifications', 'edit')) {
                    $specsEditUrl = '/ng/cars/specifications-editor?item_id=' . $item['id'];
                }
            }

            $uploadUrl = null;
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            if ($controller->user()->logedIn()) {
                $uploadUrl = '/ng/upload?item_id=' . $item['id'];
            }

            // alt names
            $altNames = [];
            $altNames2 = [];

            $langNames = $this->itemModel->getNames($item['id']);

            $currentLangName = null;
            foreach ($langNames as $lang => $langName) {
                if ($lang == 'xx') {
                    continue;
                }
                $name = $langName;
                if (! isset($altNames[$name])) {
                    $altNames[$langName] = [];
                }
                $altNames[$name][] = $lang;

                if ($language == $lang) {
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

            // categories
            $categories = [];

            $categoryRows = $this->itemModel->getRows([
                'language'     => $language,
                'columns'      => ['id', 'name', 'catname'],
                'item_type_id' => Item::CATEGORY,
                'child'        => [
                    'item_type_id' => [Item::VEHICLE, Item::ENGINE],
                    'descendant_or_self' => [
                        'id'      => $item['id'],
                        'columns' => [
                            'item_id' => 'id'
                        ]
                    ]
                ]
            ]);

            foreach ($categoryRows as $row) {
                $categories[$row['id']] = [
                    'name' => $row,
                    'url'  => '/ng/category/' . urlencode($row['catname'])
                ];
            }

            $perspective = null;
            if ($isModer) {
                $perspective = [
                    'options' => $multioptions,
                    'url'     => $this->httpRouter->assemble([
                        'picture_id' => $picture['id'],
                        'item_id'    => $item['id'],
                        'type'       => PictureItem::PICTURE_CONTENT
                    ], [
                        'name' => 'api/picture-item/item/update'
                    ]),
                    'value'   => $this->pictureItem->getPerspective($picture['id'], $item['id']),
                    'name'    => $this->itemModel->getNameData($item, $language)
                ];
            }

            $hasSpecs = $this->specsService->hasSpecs($item['id']);
            $specsUrl = null;
            foreach ($this->catalogue->getCataloguePaths($item['id']) as $path) {
                $specsUrl = $this->httpRouter->assemble([
                    'action'        => 'brand-item-specifications',
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => $path['path']
                ], [
                    'name' => 'catalogue'
                ]);
                break;
            }

            $items[] = [
                'id'            => $item['id'],
                'name'          => $this->itemModel->getNameData($item, $language),
                'specsUrl'      => $specsUrl,
                //'row'           => $item,
                'hasSpecs'      => $hasSpecs,
                'twins'         => $twins,
                'altNames'      => $altNames2,
                'langName'      => $currentLangName,
                'designProject' => $designProject,
                'categories'    => $categories,
                'detailsUrl'    => $detailsUrl,
                'factories'     => $factories,
                'description'   => $text,
                'text'          => $fullText,
                'perspective'   => $perspective,
                'specsEditUrl'  => $specsEditUrl,
                'uploadUrl'     => $uploadUrl
            ];
        }

        return $items;
    }

    private function picPageEnginesData($itemIds)
    {
        $controller = $this->getController();

        $language = $controller->language();

        $engineRows = [];
        if ($itemIds) {
            $engineRows = $this->itemModel->getRows([
                'id'           => $itemIds,
                'item_type_id' => Item::ENGINE
            ]);
        }

        $engines = [];
        foreach ($engineRows as $engineRow) {
            $vehicles = [];

            $vehicleIds = $this->itemModel->getEngineVehiclesGroups($engineRow['id']);

            if ($vehicleIds) {
                $carRows = $this->itemModel->getRows([
                    'id'    => $vehicleIds,
                    'order' => $this->catalogue->itemOrdering()
                ]);

                foreach ($carRows as $carRow) {
                    $cataloguePaths = $this->catalogue->getCataloguePaths($carRow['id']);

                    foreach ($cataloguePaths as $cPath) {
                        $vehicles[] = [
                            'name' => $controller->car()->formatName($carRow, $language), // TODO: formatter
                            'url'  => $this->httpRouter->assemble([
                                'action'        => 'brand-item',
                                'brand_catname' => $cPath['brand_catname'],
                                'car_catname'   => $cPath['car_catname'],
                                'path'          => $cPath['path']
                            ], [
                                'name' => 'catalogue'
                            ])
                        ];
                        break;
                    }
                }
            }

            $specsUrl = false;
            $hasSpecs = $this->specsService->hasSpecs($engineRow['id']);

            if ($hasSpecs) {
                $cataloguePaths = $this->catalogue->getCataloguePaths($engineRow['id']);

                foreach ($cataloguePaths as $path) {
                    $specsUrl = $this->httpRouter->assemble([
                        'action'        => 'brand-item-specifications',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path']
                    ], [
                        'name' => 'catalogue'
                    ]);
                    break;
                }
            }

            $specsEditUrl = null;
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            if ($controller->user()->isAllowed('specifications', 'edit')) {
                $specsEditUrl = '/ng/cars/specifications-editor?item_id=' . $engineRow['id'];
            }

            $engines[] = [
                'name'         => $engineRow['name'],
                'vehicles'     => $vehicles,
                'hasSpecs'     => $hasSpecs,
                'specsUrl'     => $specsUrl,
                'specsEditUrl' => $specsEditUrl
            ];
        }

        return $engines;
    }

    private function picPageFactoriesData($picture)
    {
        $controller = $this->getController();

        $language = $controller->language();

        $factories = $this->itemModel->getRows([
            'item_type_id' => Item::FACTORY,
            'pictures'     => [
                'id'     => $picture['id'],
                'status' => Picture::STATUS_ACCEPTED
            ]
        ]);

        $result = [];

        foreach ($factories as $factory) {
            $factoryCars = [];
            $factoryCarsMore = false;

            $carIds = $this->itemModel->getRelatedCarGroupId($factory['id']);
            if ($carIds) {
                $carRows = $this->itemModel->getRows([
                    'id'    => $carIds,
                    'order' => $this->catalogue->itemOrdering()
                ]);

                $limit = 10;

                if (count($carRows) > $limit) {
                    $rows = [];
                    foreach ($carRows as $carRow) {
                        $rows[] = $carRow;
                    }
                    $carRows = array_slice($rows, 0, $limit);
                    $factoryCarsMore = true;
                }

                foreach ($carRows as $carRow) {
                    $cataloguePaths = $this->catalogue->getCataloguePaths($carRow['id'], [
                        'breakOnFirst' => true,
                        'toBrand'      => null,
                        'stockFirst'   => true
                    ]);

                    foreach ($cataloguePaths as $cPath) {
                        switch ($cPath['type']) {
                            case 'brand-item':
                                $factoryCars[] = [
                                    'name' => $controller->car()->formatName($carRow, $language),
                                    'url'  => $this->httpRouter->assemble([
                                        'action'        => 'brand-item',
                                        'brand_catname' => $cPath['brand_catname'],
                                        'car_catname'   => $cPath['car_catname'],
                                        'path'          => $cPath['path']
                                    ], [
                                        'name' => 'catalogue'
                                    ])
                                ];
                                break;
                            case 'brand':
                                $factoryCars[] = [
                                    'name' => $controller->car()->formatName($carRow, $language),
                                    'url'  => $this->httpRouter->assemble([
                                        'action'        => 'brand',
                                        'brand_catname' => $cPath['brand_catname']
                                    ], [
                                        'name' => 'catalogue'
                                    ])
                                ];
                                break;
                            case 'category':
                                $factoryCars[] = [
                                    'name' => $controller->car()->formatName($carRow, $language),
                                    'url'  => $this->httpRouter->assemble([
                                        'action'           => 'category',
                                        'category_catname' => $cPath['category_catname']
                                    ], [
                                        'name' => 'catalogue'
                                    ])
                                ];
                                break;
                        }
                        break;
                    }
                }
            }

            $result[] = [
                'id'        => $factory['id'],
                'items'     => $factoryCars,
                'itemsMore' => $factoryCarsMore,
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @param $picture
     * @param array $filter
     * @param array $brandIds
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function picPageData($picture, array $filter, $brandIds = [], array $options = [])
    {
        $options = array_replace([
            'paginator' => [
                'route'     => null,
                'urlParams' => []
            ]
        ], $options);

        $controller = $this->getController();
        $imageStorage = $controller->imageStorage();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isModer = $controller->user()->inheritsRole('moder');

        $brandIds = $this->itemModel->getIds([
            'item_type_id'       => Item::BRAND,
            'descendant_or_self' => [
                'pictures' => [
                    'id' => $picture['id']
                ]
            ]
        ]);

        $language = $controller->language();

        // links
        $ofLinks = [];
        if (count($brandIds)) {
            $links = $this->itemLinkTable->select([
                new Sql\Predicate\In('item_id', $brandIds),
                'type' => 'official'
            ]);
            foreach ($links as $link) {
                $ofLinks[$link['id']] = $link;
            }
        }

        $replacePicture = null;
        if ($picture['replace_picture_id']) {
            $replacePictureRow = $this->picture->getRow(['id' => (int)$picture['replace_picture_id']]);

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $replacePicture = $controller->pic()->href($replacePictureRow);

            if ($replacePictureRow['status'] == Picture::STATUS_REMOVING) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                if (! $controller->user()->inheritsRole('moder')) {
                    $replacePicture = null;
                }
            }
        }

        $moderLinks = [];
        if ($isModer) {
            $moderLinks = $this->getModerLinks($picture);
        }

        $moderVotes = [];
        foreach ($this->pictureModerVote->getVotes($picture['id']) as $moderVote) {
            $moderVotes[] = [
                'vote'   => $moderVote['vote'],
                'reason' => $moderVote['reason'],
                'user'   => $this->userModel->getRow((int)$moderVote['user_id'])
            ];
        }

        $image = $imageStorage->getImage($picture['image_id']);
        $sourceUrl = $image ? $image->getSrc() : null;

        $preview = $imageStorage->getFormatedImage($picture['image_id'], 'picture-preview-large');
        $previewUrl = $preview ? $preview->getSrc() : null;

        $galleryImage = $imageStorage->getFormatedImage(
            $picture['image_id'],
            'picture-gallery'
        );

        $paginator = false;
        $pageNumbers = false;

        if ($filter) {
            $paginator = $this->picture->getPaginator($filter);

            $total = $paginator->getTotalItemCount();

            if ($total < 500) {
                $paginatorPicturesFilter = $filter;
                $paginatorPicturesFilter['columns'] = ['id', 'identity'];

                $paginatorPictures = $this->picture->getRows($paginatorPicturesFilter);

                $pageNumber = 0;
                foreach ($paginatorPictures as $n => $p) {
                    if ($p['id'] == $picture['id']) {
                        $pageNumber = $n + 1;
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

                foreach ($pageNumbers as $page => &$val) {
                    $pic = $paginatorPictures[$page - 1];

                    $val = $controller->url()->fromRoute(
                        $options['paginator']['route'],
                        array_replace($options['paginator']['urlParams'], [
                            'picture_id' => $pic['identity']
                        ]),
                        [],
                        true
                    );
                }
                unset($val);
            } else {
                $paginator = false;
            }
        }

        $names = $this->picture->getNameData([$picture], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$picture['id']];

        $copyrights = null;
        if ($picture['copyrights_text_id']) {
            $copyrights = $this->textStorage->getText($picture['copyrights_text_id']);
        }

        $copyrightTexts = [];
        $copyrightRows = $this->itemModel->getRows([
            'item_type_id' => Item::COPYRIGHT,
            'pictures' => [
                'id'   => $picture['id']
            ],
            'limit' => 3
        ]);
        foreach ($copyrightRows as $copyrightRow) {
            $texts = $this->itemModel->getTextsOfItem($copyrightRow['id'], $language);
            $copyrightTexts[] = $texts['text'];
        }

        $point = null;
        if ($picture['point']) {
            $point = geoPHP::load(substr($picture['point'], 4), 'wkb');
        }

        $takenDate = null;
        if ($picture['taken_year']) {
            $date = new DateTime();
            $date->setDate($picture['taken_year'], 1, 1);
            $format = 'Y';
            if ($picture['taken_month']) {
                $date->setDate($picture['taken_year'], $picture['taken_month'], 1);
                $format = 'm.Y';
                if ($picture['taken_day']) {
                    $date->setDate($picture['taken_year'], $picture['taken_month'], $picture['taken_day']);
                    $format = 'd.m.Y';
                }
            }
            $takenDate = $date->format($format);
        }

        $itemIds = $this->pictureItem->getPictureItems($picture['id'], PictureItem::PICTURE_CONTENT);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $controller->user()->get();
        $votes = $this->pictureVote->getVote($picture['id'], $user ? $user['id'] : null);

        $subscribed = false;
        if ($user) {
            $subscribed = $this->comments->userSubscribed(
                \Application\Comments::PICTURES_TYPE_ID,
                $picture['id'],
                $user['id']
            );
        }

        $twitterCreatorId = null;
        if ($picture['owner_id']) {
            $twitterCreatorId = $this->userAccount->getServiceExternalId($picture['owner_id'], 'twitter');
        }

        $authors = [];
        $pictureAuthors = $this->pictureItem->getPictureItemsData($picture['id'], PictureItem::PICTURE_AUTHOR);
        foreach ($pictureAuthors as $pictureAuthor) {
            $item = $this->itemModel->getRow([
                'id'       => $pictureAuthor['item_id'],
                'language' => $language,
                'columns'  => ['name']
            ]);

            $authors[] = [
                'name' => $item['name'],
                'url'  => '/ng/persons/' . $pictureAuthor['item_id']
            ];
        }

        $data = [
            'id'                => $picture['id'],
            'authors'           => $authors,
            'point'             => $point,
            'takenDate'         => $takenDate,
            'copyrights'        => $copyrights,
            'identity'          => $picture['identity'],
            'name'              => $name,
            'picture'           => $picture,
            'owner'             => $this->userModel->getRow((int)$picture['owner_id']),
            'addDate'           => Row::getDateTimeByColumnType('timestamp', $picture['add_date']),
            'ofLinks'           => $ofLinks,
            'moderVotes'        => $moderVotes,
            'sourceUrl'         => $sourceUrl,
            'preview'           => $preview,
            'previewUrl'        => $previewUrl,
            'canonicalUrl'      => $this->url($picture['identity'], [
                'canonical' => true
            ]),
            'replacePicture'    => $replacePicture,
            'gallery'           => [
                'current' => $picture['id']
            ],
            'paginator'         => $paginator,
            'paginatorPictures' => $pageNumbers,
            'moderLinks'        => $moderLinks,
            'pictureVote'       => $this->getController()->pictureVote($picture['id'], [
                'hideVote' => true
            ]),
            //'picturePerspectives' => $picturePerspectives,
            'items'             => $this->picPageItemsData($picture, $itemIds),
            'engines'           => $this->picPageEnginesData($itemIds),
            'factories'         => $this->picPageFactoriesData($picture),
            'votes'             => $votes,
            'subscribed'        => $subscribed,
            'subscribeUrl'      => $this->httpRouter->assemble([
                'item_id' => $picture['id'],
                'type_id' => \Application\Comments::PICTURES_TYPE_ID
            ], [
                'name' => 'api/comment/subscribe'
            ]),
            'galleryImage'      => $galleryImage,
            'twitterCreatorId'  => $twitterCreatorId,
            'copyrightTexts'    => $copyrightTexts
        ];

        $this->pictureView->inc($picture['id']);

        return $data;
    }

    private function getModerLinks($picture)
    {
        $controller = $this->getController();
        $language = $controller->language();

        $links = [];
        $links['/ng/moder/pictures/' . $picture['id']] = sprintf(
            $this->translator->translate('moder/picture/edit-picture-%s'),
            $picture['id']
        );

        $carIds = $this->pictureItem->getPictureItems($picture['id'], PictureItem::PICTURE_CONTENT);
        if ($carIds) {
            $rows = $this->itemModel->getRows([
                'id' => $carIds
            ]);

            foreach ($rows as $car) {
                $url = '/ng/moder/items/item/' . $car['id'];
                $links[$url] = sprintf(
                    $this->translator->translate('moder/picture/edit-item-%s'),
                    $controller->car()->formatName($car, $language)
                );

                $brands = $this->brand->getList(['language' => $language], function (Sql\Select $select) use ($car) {
                    $select
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                        ->where(['item_parent_cache.item_id' => $car['id']])
                        ->group('item.id');
                });

                foreach ($brands as $brand) {
                    $url = '/ng/moder/items/item/' . $brand['id'];
                    $links[$url] = sprintf(
                        $this->translator->translate('moder/picture/edit-brand-%s'),
                        $brand['name']
                    );
                }
            }
        }
        return $links;
    }


    public function gallery2(array $filter, array $options = [])
    {
        $defaults = [
            'page'      => 1,
            'pictureId' => null,
            'route'     => null,
            'urlParams' => []
        ];
        $options = array_replace($defaults, $options);

        $itemsPerPage = 10;

        $gallery = [];

        $controller = $this->getController();
        $imageStorage = $controller->imageStorage();

        $language = $controller->language();

        if ($options['pictureId']) {
            // look for page of that picture
            $filterCopy = $filter;
            $filterCopy['columns'] = ['id'];

            $rows = $this->picture->getRows($filterCopy);

            foreach ($rows as $index => $row) {
                if ($row['id'] == $options['pictureId']) {
                    $options['page'] = ceil(($index + 1) / $itemsPerPage);
                    break;
                }
            }
        }

        $filter['columns'] = [
            'id', 'identity', 'name', 'width', 'height',
            'image_id', 'filesize', 'messages'
        ];

        $paginator = $this->picture->getPaginator($filter);

        $paginator
            ->setItemCountPerPage($itemsPerPage)
            ->setCurrentPageNumber($options['page']);

        $rows = $paginator->getCurrentItems();

        // prefetch
        $ids = [];
        $fullRequests = [];
        $cropRequests = [];
        $crops = [];
        $imageIds = [];
        foreach ($rows as $idx => $picture) {
            $imageId = (int)$picture['image_id'];
            $fullRequests[$idx] = $imageId;

            $crop = $imageStorage->getImageCrop($imageId);

            if ($crop) {
                $cropRequests[$idx] = $imageId;
                $crops[$idx] = $crop;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$imageId;
        }

        // images
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $names = $this->picture->getNameData($rows, [
            'language' => $language
        ]);

        // comments
        $userId = null;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($controller->user()->logedIn()) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $userId = $controller->user()->get()['id'];
        }

        $newMessages = [];
        if ($userId) {
            $newMessages = $this->comments->getNewMessages(
                \Application\Comments::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }

        $route = $options['route'] ? $options['route'] : null;


        foreach ($rows as $idx => $row) {
            $imageId = (int)$row['image_id'];

            if (! $imageId) {
                continue;
            }

            $image = isset($images[$imageId]) ? $images[$imageId] : null;
            if (! $image) {
                continue;
            }

            $id = (int)$row['id'];

            $sUrl = $image->getSrc();

            $crop = null;
            if (isset($cropImagesInfo[$idx]) && isset($crops[$idx])) {
                $crop = $cropImagesInfo[$idx]->toArray();

                $cropInfo = $crops[$idx];

                if ($cropInfo) {
                    $crop['crop'] = [
                        'left'   => $cropInfo['left'] / $image->getWidth(),
                        'top'    => $cropInfo['top'] / $image->getHeight(),
                        'width'  => $cropInfo['width'] / $image->getWidth(),
                        'height' => $cropInfo['height'] / $image->getHeight(),
                    ];
                }
            }

            $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

            $msgCount = $row['messages'];
            $newMsgCount = 0;
            if ($userId) {
                $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
            }

            $name = isset($names[$id]) ? $names[$id] : null;
            $name = $this->pictureNameFormatter->format($name, $language);

            $reuseParams = isset($options['reuseParams']) && $options['reuseParams'];
            $url = $controller->url()->fromRoute($route, array_replace($options['urlParams'], [
                'picture_id' => $row['identity'],
                'gallery'    => null,
            ]), [], $reuseParams);

            $itemsData = $this->pictureItem->getData([
                'picture'      => $row['id'],
                'onlyWithArea' => true
            ]);

            $areas = [];
            foreach ($itemsData as $pictureItem) {
                $item = $this->itemModel->getRow(['id' => $pictureItem['item_id']]);
                $areas[] = [
                    'area' => [
                        'left'   => $pictureItem['area'][0] / $image->getWidth(),
                        'top'    => $pictureItem['area'][1] / $image->getHeight(),
                        'width'  => $pictureItem['area'][2] / $image->getWidth(),
                        'height' => $pictureItem['area'][3] / $image->getHeight(),
                    ],
                    'name' => $this->itemNameFormatter->formatHtml(
                        $this->itemModel->getNameData($item, $language),
                        $language
                    )
                ];
            }

            $gallery[] = [
                'id'          => $id,
                'url'         => $url,
                'sourceUrl'   => $sUrl,
                'crop'        => $crop,
                'full'        => $full,
                'messages'    => $msgCount,
                'newMessages' => $newMsgCount,
                'name'        => $name,
                'filesize'    => $row['filesize'], //$view->fileSize($row['filesize'])
                'areas'       => $areas
            ];
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
        if ($pictureRow instanceof ArrayObject) {
            $pictureRow = (array)$pictureRow;
        }

        $names = $this->picture->getNameData([$pictureRow], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
