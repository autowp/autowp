<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\Commons\Db\Table\Row;
use Autowp\Image;
use Autowp\User\Model\User;

use Application\Comments;
use Application\DuplicateFinder;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Application\Model\PictureVote;
use Application\PictureNameFormatter;

class PictureHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;

    private $acl;

    /**
     * @var PictureVote
     */
    private $pictureVote;

    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var DuplicateFinder
     */
    private $duplicateFinder;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Image\Storage
     */
    private $imageStorage;

    private $textStorage;

    /**
     * @var PictureView
     */
    private $pictureView;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var array
     */
    private $itemsOptions = [];

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->picture = $serviceManager->get(Picture::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $this->pictureView = $serviceManager->get(PictureView::class);
        $this->pictureModerVote = $serviceManager->get(PictureModerVote::class);

        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->pictureVote = $serviceManager->get(\Application\Model\PictureVote::class);
        $this->comments = $serviceManager->get(\Application\Comments::class);
        $this->pictureNameFormatter = $serviceManager->get(PictureNameFormatter::class);
        $this->duplicateFinder = $serviceManager->get(DuplicateFinder::class);
        $this->pictureItem = $serviceManager->get(PictureItem::class);
        $this->imageStorage = $serviceManager->get(Image\Storage::class);
        $this->textStorage = $serviceManager->get(\Autowp\TextStorage\Service::class);
        $this->perspective = $serviceManager->get(\Application\Model\Perspective::class);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('image', $strategy);
        $this->addStrategy('thumb', $strategy);
        $this->addStrategy('thumb_medium', $strategy);
        $this->addStrategy('image_gallery_full', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('owner', $strategy);
        $this->addStrategy('change_status_user', $strategy);
        $this->addStrategy('moder_vote_user', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('add_date', $strategy);

        $strategy = new Strategy\PictureItems($serviceManager);
        $this->addStrategy('items', $strategy);

        $strategy = new Strategy\Similar($serviceManager);
        $this->addStrategy('similar', $strategy);

        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('replaceable', $strategy);
        $this->addStrategy('siblings', $strategy);

        $strategy = new Strategy\Ip($serviceManager);
        $this->addStrategy('ip', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        if (isset($options['items']) && is_array($options['items'])) {
            $this->itemsOptions = $options['items'];
        }

        return $this;
    }

    public function setUserId($userId)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
            $this->userRole = null;
        }

        $this->getStrategy('ip')->setUserId($this->userId);
        $this->getStrategy('items')->setUserId($this->userId);

        return $this;
    }

    public function extract($object)
    {
        if ($object === null || $object === false) {
            return null;
        }

        if (! isset($object['id'], $object['status'], $object['filesize'])) {
            throw new Exception("Unexpected object");
        }

        $isModer = false;
        $role = $this->getUserRole();
        if ($role) {
            $isModer = $this->acl->inheritsRole($role, 'moder');
        }

        $cropped = Picture::checkCropParameters($object);

        $picture = [
            'id'             => (int)$object['id'],
            'identity'       => (string)$object['identity'],
            'url'            => $this->router->assemble([
                'picture_id' => $object['identity']
            ], [
                'name' => 'picture/picture'
            ]),
            'resolution'     => (int)$object['width'] . '×' . (int)$object['height'],
            'cropped'        => $cropped,
            'cropResolution' => $cropped ? $object['crop_width'] . '×' . $object['crop_height'] : null,
            'status'         => $object['status'],
            'owner_id'       => $object['owner_id'] ? (int)$object['owner_id'] : null,
            'width'          => (int)$object['width'],
            'height'         => (int)$object['height'],
            'filesize'       => $object['filesize']
        ];

        if ($this->filterComposite->filter('views')) {
            $picture['views'] = $this->pictureView->get($object['id']);
        }

        $showNameHtml = $this->filterComposite->filter('name_html');
        $showNameText = $this->filterComposite->filter('name_text');

        $nameData = null;
        if ($showNameHtml || $showNameText) {
            $nameDatas = $this->picture->getNameData([$object], [
                'language' => $this->language
            ]);
            $nameData = $nameDatas[$object['id']];
        }

        if ($showNameHtml) {
            $picture['name_html'] = $this->pictureNameFormatter->formatHtml($nameData, $this->language);
        }

        if ($showNameText) {
            $picture['name_text'] = $this->pictureNameFormatter->format($nameData, $this->language);
        }

        if ($this->filterComposite->filter('owner')) {
            $owner = null;
            if ($object['owner_id']) {
                $owner = $this->userModel->getRow((int)$object['owner_id']);
            }

            $picture['owner'] = $owner ? $this->extractValue('owner', $owner) : null;
        }

        if ($this->filterComposite->filter('thumb')) {
            $picture['thumb'] =  $this->extractValue('thumb', [
                'image'  => Picture::buildFormatRequest((array)$object),
                'format' => 'picture-thumb'
            ]);
        }

        if ($this->filterComposite->filter('thumb_medium')) {
            $picture['thumb_medium'] =  $this->extractValue('thumb_medium', [
                'image'  => Picture::buildFormatRequest((array)$object),
                'format' => 'picture-thumb-medium'
            ]);
        }

        if ($this->filterComposite->filter('votes')) {
            $picture['votes'] = $this->pictureVote->getVote($object['id'], null);
        }

        if ($this->filterComposite->filter('comments_count')) {
            $msgCount = $this->comments->service()->getMessagesCount(
                \Application\Comments::PICTURES_TYPE_ID,
                $object['id']
            );

            $newMessages = $this->comments->service()->getNewMessages(
                \Application\Comments::PICTURES_TYPE_ID,
                $object['id'],
                $this->userId
            );
            $picture['comments_count'] = [
                'total' => $msgCount,
                'new'   => $newMessages
            ];
        }

        if ($this->filterComposite->filter('image_gallery_full')) {
            $picture['image_gallery_full'] = $this->extractValue('image_gallery_full', [
                'image'  => Picture::buildFormatRequest((array)$object),
                'format' => 'picture-gallery-full'
            ]);
        }

        if ($this->filterComposite->filter('crop')) {
            if ($cropped) {
                $picture['crop'] = [
                    'left'   => (int)$object['crop_left'],
                    'top'    => (int)$object['crop_top'],
                    'width'  => (int)$object['crop_width'],
                    'height' => (int)$object['crop_height'],
                ];
            } else {
                $picture['crop'] = null;
            }
        }

        if ($this->filterComposite->filter('perspective_item')) {
            $itemIds = $this->pictureItem->getPictureItemsByItemType(
                $object['id'],
                [Item::VEHICLE, Item::BRAND]
            );

            $picture['perspective_item'] = null;

            if (count($itemIds) == 1) {
                $item = $itemIds[0];

                $perspective = $this->pictureItem->getPerspective($object['id'], $item['item_id']);

                $picture['perspective_item'] = [
                    'item_id'        => (int)$item['item_id'],
                    'type'           => (int)$item['type'],
                    'perspective_id' => $perspective ? (int)$perspective : null,
                ];
            }
        }

        if ($isModer) {
            if ($this->filterComposite->filter('image')) {
                $picture['image'] = $this->extractValue('image', [
                    'image'  => $object['image_id']
                ]);
            }

            if ($this->filterComposite->filter('iptc')) {
                $picture['iptc'] = $this->imageStorage->getImageIPTC($object['image_id']);
            }

            if ($this->filterComposite->filter('exif')) {
                $exif = $this->imageStorage->getImageEXIF($object['image_id']);
                $exifStr = '';
                $notSections = ['FILE', 'COMPUTED'];
                if ($exif !== false) {
                    foreach ($exif as $key => $section) {
                        if (array_search($key, $notSections) !== false) {
                            continue;
                        }

                        $exifStr .= '<p>['.htmlspecialchars($key).']';
                        foreach ($section as $name => $val) {
                            $exifStr .= "<br />".htmlspecialchars($name).": ";
                            if (is_array($val)) {
                                $exifStr .= htmlspecialchars(implode(', ', $val));
                            } else {
                                $exifStr .= htmlspecialchars($val);
                            }
                        }

                        $exifStr .= '</p>';
                    }
                }

                $picture['exif'] = $exifStr;
            }

            if ($this->filterComposite->filter('add_date')) {
                $addDate = Row::getDateTimeByColumnType('timestamp', $object['add_date']);
                $picture['add_date'] = $this->extractValue('add_date', $addDate);
            }

            if ($this->filterComposite->filter('moder_vote')) {
                $picture['moder_vote'] = $this->pictureModerVote->getVoteCount($object['id']);
            }

            if ($this->filterComposite->filter('moder_votes')) {
                $moderVotes = [];
                foreach ($this->pictureModerVote->getVotes($object['id']) as $row) {
                    $user = $this->userModel->getRow((int)$row['user_id']);
                    $moderVotes[] = [
                        'reason' => $row['reason'],
                        'vote'   => (int)$row['vote'],
                        'user'   => $user ? $this->extractValue('moder_vote_user', $user) : null
                    ];
                }
                $picture['moder_votes'] = $moderVotes;
            }

            if ($this->filterComposite->filter('moder_voted')) {
                $picture['moder_voted'] = $this->pictureModerVote->hasVote($object['id'], $this->userId);
            }

            if ($this->filterComposite->filter('similar')) {
                $picture['similar'] = null;
                $similar = $this->duplicateFinder->findSimilar($object['id']);
                if ($similar) {
                    $picture['similar'] = $this->extractValue('similar', $similar);
                }
            }

            if ($this->filterComposite->filter('items')) {
                $typeId = 0;
                if (isset($this->itemsOptions['type_id'])) {
                    $typeId = (int) $this->itemsOptions['type_id'];
                }

                $rows = $this->pictureItem->getPictureItemsData($object['id'], $typeId);
                $picture['items'] = $this->extractValue('items', $rows);
            }

            if ($this->filterComposite->filter('special_name')) {
                $picture['special_name'] = $object['name'];
            }

            if ($this->filterComposite->filter('copyrights')) {
                $picture['copyrights'] = null;
                if ($object['copyrights_text_id']) {
                    $text = $this->textStorage->getText($object['copyrights_text_id']);
                    $picture['copyrights'] = $text;
                }
            }

            if ($this->filterComposite->filter('change_status_user')) {
                $user = $this->userModel->getRow((int)$object['change_status_user_id']);
                $picture['change_status_user'] = $user ? $this->extractValue('change_status_user', $user) : null;
            }

            if ($this->filterComposite->filter('rights')) {
                $picture['rights'] = [
                    'move'      => false,
                    'accept'    => false,
                    'unaccept'  => false,
                    'restore'   => false,
                    'normalize' => false,
                    'flop'      => false,
                    'crop'      => false,
                    'delete'    => false
                ];

                $role = $this->getUserRole();
                if ($role) {
                    $picture['rights'] = [
                        'move'      => $this->acl->isAllowed($role, 'picture', 'move'),
                        'unaccept'  => ($object['status'] == Picture::STATUS_ACCEPTED)
                                    && $this->acl->isAllowed($role, 'picture', 'unaccept'),
                        'accept'    => $this->picture->canAccept($object)
                                    && $this->acl->isAllowed($role, 'picture', 'accept'),
                        'restore'   => ($object['status'] == Picture::STATUS_REMOVING)
                                    && $this->acl->isAllowed($role, 'picture', 'restore'),
                        'normalize' => ($object['status'] == Picture::STATUS_INBOX)
                                    && $this->acl->isAllowed($role, 'picture', 'normalize'),
                        'flop'      => ($object['status'] == Picture::STATUS_INBOX)
                                    && $this->acl->isAllowed($role, 'picture', 'flop'),
                        'crop'      => $this->acl->isAllowed($role, 'picture', 'crop'),
                        'delete'    => $this->canDelete($object)
                    ];
                }
            }

            if ($this->filterComposite->filter('is_last')) {
                $isLastPicture = null;
                if ($object['status'] == Picture::STATUS_ACCEPTED) {
                    $isLastPicture = ! $this->picture->isExists([
                        'id_exclude' => $object['id'],
                        'status'     => Picture::STATUS_ACCEPTED,
                        'item'       => [
                            'contains_picture' => $object['id']
                        ]
                    ]);
                }

                $picture['is_last'] = $isLastPicture;
            }

            if ($this->filterComposite->filter('accepted_count')) {
                $picture['accepted_count'] = $this->picture->getCount([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => [
                        'contains_picture' => $object['id']
                    ]
                ]);
            }

            if ($this->filterComposite->filter('replaceable')) {
                $picture['replaceable'] = null;
                if ($object['replace_picture_id']) {
                    $row = $this->picture->getRow(['id' => (int)$object['replace_picture_id']]);
                    if ($row) {
                        $picture['replaceable'] = $this->extractValue('replaceable', $row);
                    }
                }
            }

            if ($this->filterComposite->filter('siblings')) {
                $picture['siblings'] = [
                    'prev' => null,
                    'next' => null,
                    'prev_new' => null,
                    'next_new' => null
                ];

                $prevPicture = $this->picture->getRow([
                    'id_lt' => $object['id'],
                    'order' => 'id_desc'
                ]);
                if ($prevPicture) {
                    $picture['siblings']['prev'] = $this->extractValue('siblings', $prevPicture);
                }

                $nextPicture = $this->picture->getRow([
                    'id_gt' => $object['id'],
                    'order' => 'id_asc'
                ]);
                if ($nextPicture) {
                    $picture['siblings']['next'] = $this->extractValue('siblings', $nextPicture);
                }

                $prevNewPicture = $this->picture->getRow([
                    'id_lt'  => $object['id'],
                    'status' => Picture::STATUS_INBOX,
                    'order'  => 'id_desc'
                ]);
                if ($prevNewPicture) {
                    $picture['siblings']['prev_new'] = $this->extractValue('siblings', $prevNewPicture);
                }

                $nextNewPicture = $this->picture->getRow([
                    'id_gt'  => $object['id'],
                    'status' => Picture::STATUS_INBOX,
                    'order'  => 'id_asc'
                ]);
                if ($nextNewPicture) {
                    $picture['siblings']['next_new'] = $this->extractValue('siblings', $nextNewPicture);
                }
            }

            if ($this->filterComposite->filter('ip') && $this->acl->isAllowed($role, 'user', 'ip')) {
                $picture['ip'] = $object['ip'] ? $this->extractValue('ip', inet_ntop($object['ip'])) : null;
            }
        }

        return $picture;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }

    private function canDelete($picture)
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }

        if ($this->acl->isAllowed($role, 'picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $this->userId)) {
                return true;
            }
        }

        if (! $this->acl->isAllowed($role, 'picture', 'remove_by_vote')) {
            return false;
        }

        if (! $this->pictureModerVote->hasVote($picture['id'], $this->userId)) {
            return false;
        }

        $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
        $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

        return $deleteVotes > $acceptVotes;
    }
}
