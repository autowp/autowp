<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Controller\Plugin\Pic;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Application\Service\PictureService;
use Application\Service\TelegramService;
use ArrayAccess;
use ArrayObject;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Autowp\TextStorage;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Exception;
use ImagickException;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Uri;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_replace;
use function count;
use function explode;
use function get_object_vars;
use function htmlspecialchars;
use function implode;
use function in_array;
use function max;
use function min;
use function round;
use function sprintf;
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

    private UserPicture $userPicture;

    private HostManager $hostManager;

    private InputFilter $itemInputFilter;

    private InputFilter $postInputFilter;

    private InputFilter $listInputFilter;

    private InputFilter $publicListInputFilter;

    private InputFilter $editInputFilter;

    private TextStorage\Service $textStorage;

    private CommentsService $comments;

    private PictureModerVote $pictureModerVote;

    private Item $item;

    private Picture $picture;

    private User $userModel;

    private PictureService $pictureService;

    private TelegramService $telegram;

    private MessageService $message;

    private Catalogue $catalogue;

    private Storage $imageStorage;

    public function __construct(
        AbstractRestHydrator $hydrator,
        PictureItem $pictureItem,
        UserPicture $userPicture,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        CarOfDay $carOfDay,
        InputFilter $itemInputFilter,
        InputFilter $postInputFilter,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        InputFilter $editInputFilter,
        TextStorage\Service $textStorage,
        CommentsService $comments,
        PictureModerVote $pictureModerVote,
        Item $item,
        Picture $picture,
        User $userModel,
        PictureService $pictureService,
        Catalogue $catalogue,
        Storage $imageStorage
    ) {
        $this->carOfDay = $carOfDay;

        $this->hydrator              = $hydrator;
        $this->pictureItem           = $pictureItem;
        $this->userPicture           = $userPicture;
        $this->hostManager           = $hostManager;
        $this->telegram              = $telegram;
        $this->message               = $message;
        $this->itemInputFilter       = $itemInputFilter;
        $this->postInputFilter       = $postInputFilter;
        $this->listInputFilter       = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->editInputFilter       = $editInputFilter;
        $this->textStorage           = $textStorage;
        $this->comments              = $comments;
        $this->pictureModerVote      = $pictureModerVote;
        $this->picture               = $picture;
        $this->item                  = $item;
        $this->userModel             = $userModel;
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

        if (strlen($data['accept_date'])) {
            $filter['accept_date'] = $data['accept_date'];
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

        if ($data['accepted_in_days']) {
            $filter['accepted_in_days'] = $data['accepted_in_days'];
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
     * @param array|ArrayAccess $picture
     */
    private function canAccept($picture): bool
    {
        return $this->picture->canAccept($picture)
            && $this->user()->enforce('picture', 'accept');
    }

    /**
     * @param array|ArrayObject $user
     */
    private function userModerUrl($user, Uri $uri): string
    {
        $u = clone $uri;
        $u->setPath('/users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']));

        return $u->toString();
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
    public function updateAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        /** @psalm-suppress InvalidCast */
        $id      = (int) $this->params('id');
        $picture = $this->picture->getRow(['id' => $id]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        $data            = (array) $this->processBodyContent($this->getRequest());
        $validationGroup = array_keys($data); // TODO: intersect with real keys
        if (! $validationGroup) {
            return $this->forbiddenAction();
        }
        $this->editInputFilter->setValidationGroup($validationGroup);
        $this->editInputFilter->setData($data);

        if (! $this->editInputFilter->isValid()) {
            return $this->inputFilterResponse($this->editInputFilter);
        }

        $data = $this->editInputFilter->getValues();

        $isModer = $this->user()->enforce('global', 'moderate');

        $set = [];

        if (isset($data['crop'])) {
            $canCrop = $this->user()->enforce('picture', 'crop')
                    || ($picture['owner_id'] === $user['id']) && ($picture['status'] === Picture::STATUS_INBOX);

            if (! $canCrop) {
                return $this->forbiddenAction();
            }

            $left   = round($data['crop']['left']);
            $top    = round($data['crop']['top']);
            $width  = round($data['crop']['width']);
            $height = round($data['crop']['height']);

            $left  = max(0, $left);
            $left  = min($picture['width'], $left);
            $width = max(1, $width);
            $width = min($picture['width'], $width);

            $top    = max(0, $top);
            $top    = min($picture['height'], $top);
            $height = max(1, $height);
            $height = min($picture['height'], $height);

            $crop = null;
            if ($left > 0 || $top > 0 || $width < $picture['width'] || $height < $picture['height']) {
                $crop = [
                    'left'   => $left,
                    'top'    => $top,
                    'width'  => $width,
                    'height' => $height,
                ];
            }

            $this->imageStorage->setImageCrop((int) $picture['image_id'], $crop);

            $this->log(sprintf(
                'Выделение области на картинке %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id'],
            ]);
        }

        if ($isModer) {
            if (array_key_exists('replace_picture_id', $data)) {
                if ($picture['replace_picture_id'] && ! $data['replace_picture_id']) {
                    $replacePicture = $this->picture->getRow(['id' => (int) $picture['replace_picture_id']]);
                    if (! $replacePicture) {
                        return $this->notFoundAction();
                    }

                    if (! $this->user()->enforce('picture', 'move')) {
                        return $this->forbiddenAction();
                    }

                    $set['replace_picture_id'] = null;

                    // log
                    $this->log(sprintf(
                        'Замена %s на %s отклонена',
                        htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => [$picture['id'], $replacePicture['id']],
                    ]);
                }
            }

            if (array_key_exists('taken_year', $data)) {
                $set['taken_year'] = $data['taken_year'];
            }

            if (array_key_exists('taken_month', $data)) {
                $set['taken_month'] = $data['taken_month'];
            }

            if (array_key_exists('taken_day', $data)) {
                $set['taken_day'] = $data['taken_day'];
            }

            if (isset($data['special_name'])) {
                $set['name'] = $data['special_name'];
            }

            if (isset($data['copyrights'])) {
                $text = $data['copyrights'];

                $user = $this->user()->get();

                if ($picture['copyrights_text_id']) {
                    $this->textStorage->setText($picture['copyrights_text_id'], $text, $user['id']);
                } elseif ($text) {
                    $textId                    = $this->textStorage->createText($text, $user['id']);
                    $set['copyrights_text_id'] = $textId;
                }

                $this->log(sprintf(
                    'Редактирование текста копирайтов изображения %s',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [
                    'pictures' => $picture['id'],
                ]);

                if ($picture['copyrights_text_id']) {
                    $userIds = $this->textStorage->getTextUserIds($picture['copyrights_text_id']);

                    foreach ($userIds as $userId) {
                        if ($userId !== (int) $user['id']) {
                            $userRow = $this->userModel->getRow((int) $userId);
                            if ($userRow) {
                                $uri = $this->hostManager->getUriByLanguage($userRow['language']);
                                $uri->setPath('/moder/pictures/' . $picture['id']);

                                $message = sprintf(
                                    $this->translate(
                                        'pm/user-%s-edited-picture-copyrights-%s-%s',
                                        'default',
                                        $userRow['language']
                                    ),
                                    $this->userModerUrl($user, $uri),
                                    $this->pic()->name($picture, $userRow['language']),
                                    $uri->toString()
                                );

                                $this->message->send(null, $userRow['id'], $message);
                            }
                        }
                    }
                }
            }

            if (isset($data['status'])) {
                $user                 = $this->user()->get();
                $previousStatusUserId = (int) $picture['change_status_user_id'];

                if ($data['status'] === Picture::STATUS_ACCEPTED) {
                    $canAccept = $this->canAccept($picture);

                    if (! $canAccept) {
                        return $this->forbiddenAction();
                    }

                    $isFirstTimeAccepted = false;
                    $success             = $this->picture->accept($picture['id'], $user['id'], $isFirstTimeAccepted);
                    if ($success) {
                        $owner = $this->userModel->getRow((int) $picture['owner_id']);

                        if ($owner) {
                            $this->userPicture->refreshPicturesCount($owner['id']);
                        }

                        if ($isFirstTimeAccepted) {
                            if ($owner && ((int) $owner['id'] !== (int) $user['id'])) {
                                $uri = $this->hostManager->getUriByLanguage($owner['language']);

                                $uri->setPath('/picture/' . urlencode($picture['identity']));

                                $message = sprintf(
                                    $this->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                                    $uri->toString()
                                );

                                $this->message->send(null, $owner['id'], $message);
                            }

                            $this->telegram->notifyPicture($picture['id']);
                        }
                    }

                    if ($previousStatusUserId !== (int) $user['id']) {
                        $prevUser = $this->userModel->getRow($previousStatusUserId);
                        if ($prevUser) {
                            $uri = $this->hostManager->getUriByLanguage($prevUser['language']);

                            $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                            $message = sprintf(
                                'Принята картинка %s',
                                $uri->toString()
                            );
                            $this->message->send(null, $prevUser['id'], $message);
                        }
                    }

                    $this->log(sprintf(
                        'Картинка %s принята',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id'],
                    ]);
                }

                if ($data['status'] === Picture::STATUS_INBOX) {
                    if ($picture['status'] === Picture::STATUS_REMOVING) {
                        $canRestore = $this->user()->enforce('picture', 'restore');
                        if (! $canRestore) {
                            return $this->forbiddenAction();
                        }

                        $set = array_replace($set, [
                            'status'                => Picture::STATUS_INBOX,
                            'change_status_user_id' => $user['id'],
                        ]);

                        $this->log(sprintf(
                            'Картинки `%s` восстановлена из очереди удаления',
                            htmlspecialchars($this->pic()->name($picture, $this->language()))
                        ), [
                            'pictures' => $picture['id'],
                        ]);
                    } elseif ($picture['status'] === Picture::STATUS_ACCEPTED) {
                        $canUnaccept = $this->user()->enforce('picture', 'unaccept');
                        if (! $canUnaccept) {
                            return $this->forbiddenAction();
                        }

                        $this->picture->getTable()->update([
                            'status'                => Picture::STATUS_INBOX,
                            'change_status_user_id' => $user['id'],
                        ], [
                            'id' => $picture['id'],
                        ]);

                        if ($picture['owner_id']) {
                            $this->userPicture->refreshPicturesCount($picture['owner_id']);
                        }

                        $this->log(sprintf(
                            'С картинки %s снят статус "принято"',
                            htmlspecialchars($this->pic()->name($picture, $this->language()))
                        ), [
                            'pictures' => $picture['id'],
                        ]);

                        if ($previousStatusUserId !== (int) $user['id']) {
                            $prevUser = $this->userModel->getRow($previousStatusUserId);
                            if ($prevUser) {
                                $uri = $this->hostManager->getUriByLanguage($prevUser['language']);

                                $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                                $message = sprintf(
                                    'С картинки %s снят статус "принято"',
                                    $uri->toString()
                                );
                                $this->message->send(null, $prevUser['id'], $message);
                            }
                        }
                    }
                }

                if ($data['status'] === Picture::STATUS_REMOVING) {
                    $canDelete = $this->pictureCanDelete($picture);
                    if (! $canDelete) {
                        return $this->forbiddenAction();
                    }

                    $user = $this->user()->get();
                    $set  = array_replace($set, [
                        'status'                => Picture::STATUS_REMOVING,
                        'removing_date'         => new Sql\Expression('CURDATE()'),
                        'change_status_user_id' => $user['id'],
                    ]);

                    $owner = $this->userModel->getRow((int) $picture['owner_id']);
                    if ($owner && (int) $owner['id'] !== (int) $user['id']) {
                        $uri = $this->hostManager->getUriByLanguage($owner['language']);

                        $deleteRequests = $this->pictureModerVote->getNegativeVotes($picture['id']);

                        $reasons = [];
                        foreach ($deleteRequests as $request) {
                            $user = $this->userModel->getRow((int) $request['user_id']);
                            if ($user) {
                                $reasons[] = $this->userModerUrl($user, $uri) . ' : ' . $request['reason'];
                            }
                        }

                        $uri->setPath('/picture/' . urlencode($picture['identity']));

                        $message = sprintf(
                            $this->translate('pm/your-picture-%s-enqueued-to-remove-%s', 'default', $owner['language']),
                            $uri->toString(),
                            implode("\n", $reasons)
                        );

                        $this->message->send(null, $owner['id'], $message);
                    }

                    $this->log(sprintf(
                        'Картинка %s поставлена в очередь на удаление',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id'],
                    ]);
                }
            }

            if (isset($data['point']['lat'], $data['point']['lng'])) {
                if ($data['point']['lat'] && $data['point']['lng']) {
                    $set['point'] = new Sql\Expression('Point(?, ?)', [$data['point']['lng'], $data['point']['lat']]);
                } else {
                    $set['point'] = null;
                }
            }
        }

        if ($set) {
            $this->picture->getTable()->update($set, [
                'id' => $picture['id'],
            ]);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
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
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    private function pictureCanDelete($picture): bool
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $canDelete = false;
        $user      = $this->user()->get();
        if ($this->user()->enforce('picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $canDelete = true;
            }
        } elseif ($this->user()->enforce('picture', 'remove_by_vote')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
                $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

                $canDelete = $deleteVotes > $acceptVotes;
            }
        }

        return $canDelete;
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

    /**
     * @param array|ArrayAccess $picture
     * @param array|ArrayAccess $replacedPicture
     */
    private function canReplace($picture, $replacedPicture): bool
    {
        $can1 = false;
        switch ($picture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can1 = true;
                break;

            case Picture::STATUS_INBOX:
                $can1 = $this->user()->enforce('picture', 'accept');
                break;
        }

        $can2 = false;
        switch ($replacedPicture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can2 = $this->user()->enforce('picture', 'unaccept')
                     && $this->user()->enforce('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_INBOX:
                $can2 = $this->user()->enforce('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_REMOVING:
            case Picture::STATUS_REMOVED:
                $can2 = true;
                break;
        }

        return $can1 && $can2 && $this->user()->enforce('picture', 'move');
    }

    /**
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function acceptReplaceAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        /** @psalm-suppress InvalidCast */
        $id      = (int) $this->params('id');
        $picture = $this->picture->getRow(['id' => $id]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $picture['replace_picture_id']) {
            return $this->notFoundAction();
        }

        $replacePicture = $this->picture->getRow(['id' => (int) $picture['replace_picture_id']]);
        if (! $replacePicture) {
            return $this->notFoundAction();
        }

        if (! $this->canReplace($picture, $replacePicture)) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        // statuses
        if ($picture['status'] !== Picture::STATUS_ACCEPTED) {
            $set = [
                'status'                => Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user['id'],
            ];
            if (! $picture['accept_datetime']) {
                $set['accept_datetime'] = new Sql\Expression('NOW()');
            }

            $this->picture->getTable()->update($set, [
                'id' => $picture['id'],
            ]);

            if ($picture['owner_id']) {
                $this->userPicture->refreshPicturesCount($picture['owner_id']);
            }
        }

        if (! in_array($replacePicture['status'], [Picture::STATUS_REMOVING, Picture::STATUS_REMOVED])) {
            $this->picture->getTable()->update([
                'status'                => Picture::STATUS_REMOVING,
                'removing_date'         => new Sql\Expression('now()'),
                'change_status_user_id' => $user['id'],
            ], [
                'id' => $replacePicture['id'],
            ]);
            if ($replacePicture['owner_id']) {
                $this->userPicture->refreshPicturesCount($replacePicture['owner_id']);
            }
        }

        // comments
        $this->comments->moveMessages(
            Comments::PICTURES_TYPE_ID,
            $replacePicture['id'],
            Comments::PICTURES_TYPE_ID,
            $picture['id']
        );

        // pms
        $owner        = $this->userModel->getRow((int) $picture['owner_id']);
        $replaceOwner = $this->userModel->getRow((int) $replacePicture['owner_id']);
        $recepients   = [];
        if ($owner) {
            $recepients[$owner['id']] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner['id']] = $replaceOwner;
        }
        unset($recepients[$user['id']]);
        if ($recepients) {
            foreach ($recepients as $recepient) {
                $uri = $this->hostManager->getUriByLanguage($recepient['language']);

                $url        = $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();
                $replaceUrl = $uri->setPath('/picture/' . urlencode($replacePicture['identity']))->toString();
                $moderUrl   = $uri->setPath('/users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']))
                                ->toString();

                $message = sprintf(
                    $this->translate('pm/user-%s-accept-replace-%s-%s', 'default', $recepient['language']),
                    $moderUrl,
                    $replaceUrl,
                    $url
                );

                $this->message->send(null, $recepient['id'], $message);
            }
        }

        // log
        $this->log(sprintf(
            'Замена %s на %s',
            htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => [$picture['id'], $replacePicture['id']],
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
