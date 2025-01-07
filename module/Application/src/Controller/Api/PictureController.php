<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Pic;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\PictureService;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Exception;
use ImagickException;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_merge;
use function count;
use function explode;
use function get_object_vars;
use function strlen;
use function urlencode;

/**
 * @method Pic pic()
 * @method string language()
 * @method UserPlugin user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method ViewModel forbiddenAction()
 * @method void log(string $message, array $objects)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class PictureController extends AbstractRestfulController
{
    private CarOfDay $carOfDay;

    private AbstractRestHydrator $hydrator;

    private PictureItem $pictureItem;

    private HostManager $hostManager;

    private InputFilter $itemInputFilter;

    private InputFilter $postInputFilter;

    private InputFilter $listInputFilter;

    private InputFilter $publicListInputFilter;

    private Item $item;

    private Picture $picture;

    private PictureService $pictureService;

    private Catalogue $catalogue;

    private Storage $imageStorage;

    public function __construct(
        AbstractRestHydrator $hydrator,
        PictureItem $pictureItem,
        HostManager $hostManager,
        CarOfDay $carOfDay,
        InputFilter $itemInputFilter,
        InputFilter $postInputFilter,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        Item $item,
        Picture $picture,
        PictureService $pictureService,
        Catalogue $catalogue,
        Storage $imageStorage
    ) {
        $this->carOfDay              = $carOfDay;
        $this->hydrator              = $hydrator;
        $this->pictureItem           = $pictureItem;
        $this->hostManager           = $hostManager;
        $this->itemInputFilter       = $itemInputFilter;
        $this->postInputFilter       = $postInputFilter;
        $this->listInputFilter       = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->picture               = $picture;
        $this->item                  = $item;
        $this->pictureService        = $pictureService;
        $this->catalogue             = $catalogue;
        $this->imageStorage          = $imageStorage;
    }

    /**
     * @return array|JsonModel
     * @throws Exception
     */
    public function canonicalRouteAction()
    {
        /** @psalm-suppress InvalidCast */
        $picture = $this->picture->getRow(['identity' => (string) $this->params('id')]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        $route = null;

        $itemIds = $this->pictureItem->getPictureItems($picture['id'], PictureItem::PICTURE_CONTENT);
        if ($itemIds) {
            $itemIds = $this->item->getIds([
                'id'           => $itemIds,
                'item_type_id' => [Item::BRAND, Item::VEHICLE, Item::ENGINE, Item::PERSON],
            ]);

            if ($itemIds) {
                $carId = $itemIds[0];

                $paths = $this->catalogue->getCataloguePaths($carId, [
                    'breakOnFirst' => true,
                    'stockFirst'   => true,
                    'toBrand'      => false,
                ]);

                if (count($paths) > 0) {
                    $path = $paths[0];

                    switch ($path['type']) {
                        case 'brand':
                            if ($path['car_catname']) {
                                $route = array_merge(
                                    ['/', $path['brand_catname'], $path['car_catname']],
                                    $path['path'],
                                    ['pictures', $picture['identity']]
                                );
                            } else {
                                $perspectiveId = $this->pictureItem->getPerspective($picture['id'], $carId);

                                switch ($perspectiveId) {
                                    case 22:
                                        $action = 'logotypes';
                                        break;
                                    case 25:
                                        $action = 'mixed';
                                        break;
                                    default:
                                        $action = 'other';
                                        break;
                                }

                                $route = ['/', $path['brand_catname'], $action, $picture['identity']];
                            }
                            break;
                        case 'brand-item':
                            $route = array_merge(
                                ['/', $path['brand_catname'], $path['car_catname']],
                                $path['path'],
                                ['pictures',  $picture['identity']]
                            );
                            break;
                        case 'category':
                            $route = ['/category', $path['category_catname'], 'pictures', $picture['identity']];
                            break;
                        case 'person':
                            $route = ['/persons', $path['id'], $picture['identity']];
                            break;
                    }
                }
            }
        }

        return new JsonModel($route);
    }

    /**
     * @throws Storage\Exception
     * @throws Exception
     */
    public function randomPictureAction(): JsonModel
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'random',
        ]);

        $result = [
            'status' => false,
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage->getImage($pictureRow['image_id']);

            $uri = $this->hostManager->getUriByLanguage($this->language());
            $uri->setPath('/picture/' . urlencode($pictureRow['identity']));

            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $uri->toString(),
            ];
        }

        return new JsonModel($result);
    }

    /**
     * @throws Storage\Exception
     * @throws Exception
     */
    public function newPictureAction(): JsonModel
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'accept_datetime_desc',
        ]);

        $result = [
            'status' => false,
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage->getImage($pictureRow['image_id']);

            $uri = $this->hostManager->getUriByLanguage($this->language());
            $uri->setPath('/picture/' . urlencode($pictureRow['identity']));

            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $uri->toString(),
            ];
        }

        return new JsonModel($result);
    }

    /**
     * @throws Storage\Exception
     * @throws Exception
     */
    public function carOfDayPictureAction(): JsonModel
    {
        $itemOfDay = $this->carOfDay->getCurrent();

        $pictureRow = null;

        if ($itemOfDay) {
            $carRow = $this->item->getRow(['id' => (int) $itemOfDay['item_id']]);
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $filter = [
                        'status' => Picture::STATUS_ACCEPTED,
                        'item'   => [
                            'ancestor_or_self' => $carRow['id'],
                        ],
                        'order'  => 'resolution_desc',
                    ];

                    if ($groupId) {
                        $filter['item']['perspective'] = [
                            'group' => $groupId,
                        ];
                        $filter['order']               = 'perspective_group';
                    }

                    $pictureRow = $this->picture->getRow($filter);
                    if ($pictureRow) {
                        break;
                    }
                }
            }
        }

        $result = [
            'status' => false,
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage->getImage($pictureRow['image_id']);

            $uri = $this->hostManager->getUriByLanguage($this->language());
            $uri->setPath('/picture/' . urlencode($pictureRow['identity']));

            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $uri->toString(),
            ];
        }

        return new JsonModel($result);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $isModer = $this->user()->enforce('global', 'moderate');
        $user    = $this->user()->get();

        $inputFilter = $isModer ? $this->listInputFilter : $this->publicListInputFilter;
        $inputFilter->setData($this->params()->fromQuery());

        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $data = $inputFilter->getValues();

        if ($data['status'] === 'inbox' && ! $user) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'item_id' => [
                            'invalid' => 'inbox not allowed anonymously',
                        ],
                    ],
                ])
            );
        }

        $restricted = ! $isModer && ! $data['exact_item_id'] && ! $data['item_id'] && ! $data['owner_id'] &&
            ! $data['status'] && ! $data['identity'];
        if ($restricted) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'item_id' => [
                            'invalid' => 'item_id or owner_id is required',
                        ],
                    ],
                ])
            );
        }

        $filter = [
            'timezone' => $this->user()->timezone(),
        ];

        if ($data['identity']) {
            $filter['identity'] = $data['identity'];
        }

        if ($data['item_id']) {
            $filter['item']['ancestor_or_self']['id'] = $data['item_id'];
        }

        if ($data['owner_id']) {
            $filter['user'] = $data['owner_id'];
        }

        $orders = [
            1  => 'add_date_desc',
            2  => 'add_date_asc',
            3  => 'resolution_desc',
            4  => 'resolution_asc',
            5  => 'filesize_desc',
            6  => 'filesize_asc',
            7  => 'comments',
            8  => 'views',
            9  => 'moder_votes',
            10 => 'similarity',
            11 => 'removing_date',
            12 => 'likes',
            13 => 'dislikes',
            14 => 'status',
            15 => 'accept_datetime_desc',
            16 => 'perspectives',
        ];

        switch ($data['order']) {
            case 13:
                $filter['has_dislikes'] = true;
                break;
        }

        if ($data['order'] && isset($orders[$data['order']])) {
            $filter['order'] = $orders[$data['order']];
        } else {
            $filter['order'] = $orders[1];
        }

        if (strlen($data['status'])) {
            switch ($data['status']) {
                case Picture::STATUS_INBOX:
                case Picture::STATUS_ACCEPTED:
                case Picture::STATUS_REMOVING:
                    $filter['status'] = $data['status'];
                    break;
                case 'custom1':
                    $filter['status'] = [
                        Picture::STATUS_INBOX,
                        Picture::STATUS_ACCEPTED,
                    ];
                    break;
            }
        }

        if (strlen($data['add_date'])) {
            $filter['add_date'] = $data['add_date'];
        }

        if ($data['perspective_id']) {
            if ($data['perspective_id'] === 'null') {
                $filter['item']['perspective_is_null'] = true;
            } else {
                $filter['item']['perspective'] = (int) $data['perspective_id'];
            }
        }

        if ($data['perspective_exclude_id']) {
            $parts = explode(',', $data['perspective_exclude_id']);
            $value = [];
            foreach ($parts as $part) {
                $part = (int) $part;
                if ($part) {
                    $value[] = $part;
                }
            }

            $filter['item']['perspective_exclude'] = $value;
        }

        if ($data['exact_item_id']) {
            $filter['item']['id'] = $data['exact_item_id'];
        }

        if ($data['exact_item_link_type']) {
            $filter['item']['link_type'] = $data['exact_item_link_type'];
        }

        if ($isModer) {
            if (strlen($data['comments'])) {
                if ($data['comments'] === '1') {
                    $filter['has_comments'] = true;
                } elseif ($data['comments'] === '0') {
                    $filter['has_comments'] = false;
                }
            }

            if ($data['car_type_id']) {
                $filter['item']['vehicle_type'] = $data['car_type_id'];
            }

            if ($data['special_name']) {
                $filter['has_special_name'] = true;
            }

            if ($data['similar']) {
                $filter['has_similar'] = true;
                $data['order']         = 10;
            }

            if (strlen($data['requests'])) {
                switch ($data['requests']) {
                    case '0':
                        $filter['has_moder_votes'] = false;
                        break;

                    case '1':
                        $filter['has_accept_votes'] = true;
                        break;

                    case '2':
                        $filter['has_delete_votes'] = true;
                        break;

                    case '3':
                        $filter['has_moder_votes'] = true;
                        break;
                }
            }

            if (strlen($data['replace'])) {
                if ($data['replace'] === '1') {
                    $filter['is_replace'] = true;
                } elseif ($data['replace'] === '0') {
                    $filter['is_replace'] = false;
                }
            }

            if ($data['lost']) {
                $filter['is_lost'] = true;
            }

            if ($data['gps']) {
                $filter['has_point'] = true;
            }

            if ($data['added_from']) {
                $filter['added_from'] = $data['added_from'];
            }

            if ($data['exclude_item_id']) {
                $filter['item']['exclude_ancestor_or_self']['id'] = $data['exclude_item_id'];
            }
        }

        $paginator = $this->picture->getPaginator($filter);

        if (strlen($data['limit']) > 0) {
            $limit = (int) $data['limit'];
            $limit = $limit >= 0 ? $limit : 0;
        } else {
            $limit = 1;
        }

        $paginator
            ->setItemCountPerPage($limit ?: 1)
            ->setCurrentPageNumber($data['page']);

        $result = [
            'paginator' => get_object_vars($paginator->getPages()),
        ];

        if ($limit > 0) {
            $this->hydrator->setOptions([
                'language'  => $this->language(),
                'user_id'   => $user ? $user['id'] : null,
                'fields'    => $data['fields'],
                'item_id'   => (int) $data['item_id'],
                'paginator' => $data['paginator'],
            ]);

            $pictures = [];
            foreach ($paginator->getCurrentItems() as $pictureRow) {
                $pictures[] = $this->hydrator->extract($pictureRow);
            }
            $result['pictures'] = $pictures;
        }

        return new JsonModel($result);
    }

    /**
     * @throws Storage\Exception
     * @throws ImagickException
     * @return ViewModel|ResponseInterface|array
     */
    public function postAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        /** @var Request $request */
        $request = $this->getRequest();

        $data = array_merge(
            $this->params()->fromPost(),
            $request->getFiles()->toArray()
        );

        $this->postInputFilter->setData($data);
        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $values = $this->postInputFilter->getValues();

        $itemId           = (int) $values['item_id'];
        $replacePictureId = (int) $values['replace_picture_id'];
        $perspectiveId    = (int) $values['perspective_id'];

        if (! $itemId && ! $replacePictureId) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'item_id' => [
                            'invalid' => 'item_id or replace_picture_id is required',
                        ],
                    ],
                ])
            );
        }

        /** @var Request $request */
        $request = $this->getRequest();

        $picture = $this->pictureService->addPictureFromFile(
            $values['file']['tmp_name'],
            $user['id'],
            $request->getServer('REMOTE_ADDR'),
            $itemId,
            $perspectiveId,
            $replacePictureId,
            (string) $values['comment']
        );

        $url = $this->url()->fromRoute('api/picture/picture/item', [
            'id' => $picture['id'],
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user['id'],
            'fields'   => $data['fields'],
        ]);

        /** @psalm-suppress InvalidCast */
        $id  = (int) $this->params('id');
        $row = $this->picture->getRow(['id' => $id]);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     * @throws Storage\Exception
     */
    public function correctFileNamesAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        /** @psalm-suppress InvalidCast */
        $id  = (int) $this->params('id');
        $row = $this->picture->getRow(['id' => $id]);
        if (! $row) {
            return $this->notFoundAction();
        }

        if ($row['image_id']) {
            $this->imageStorage->changeImageName($row['image_id'], [
                'pattern' => $this->picture->getFileNamePattern($row['id']),
            ]);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
