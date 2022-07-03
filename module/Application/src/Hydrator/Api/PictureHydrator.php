<?php

namespace Application\Hydrator\Api;

use Application\Comments;
use Application\DuplicateFinder;
use Application\Hydrator\Api\Strategy\Items;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Application\Model\PictureVote;
use Application\PictureNameFormatter;
use ArrayAccess;
use Autowp\Commons\Db\Table\Row;
use Autowp\Image;
use Autowp\TextStorage;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use DateTime;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_search;
use function array_values;
use function Autowp\Commons\parsePointWkb;
use function count;
use function explode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function inet_ntop;
use function is_array;
use function urlencode;

class PictureHydrator extends AbstractRestHydrator
{
    private Comments $comments;

    private Enforcer $acl;

    private PictureVote $pictureVote;

    private int $userId = 0;

    private ?string $userRole;

    private int $itemId = 0;

    private array $paginator;

    private PictureNameFormatter $pictureNameFormatter;

    private Picture $picture;

    private User $userModel;

    private DuplicateFinder $duplicateFinder;

    private PictureItem $pictureItem;

    private Image\Storage $imageStorage;

    private TextStorage\Service $textStorage;

    private PictureView $pictureView;

    private PictureModerVote $pictureModerVote;

    private array $itemsOptions = [];

    private Item $itemModel;

    private TableGateway $linksTable;

    private ItemParent $itemParentModel;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->picture         = $serviceManager->get(Picture::class);
        $this->userModel       = $serviceManager->get(User::class);
        $this->itemModel       = $serviceManager->get(Item::class);
        $this->itemParentModel = $serviceManager->get(ItemParent::class);

        $this->pictureView      = $serviceManager->get(PictureView::class);
        $this->pictureModerVote = $serviceManager->get(PictureModerVote::class);

        $this->acl                  = $serviceManager->get(Enforcer::class);
        $this->pictureVote          = $serviceManager->get(PictureVote::class);
        $this->comments             = $serviceManager->get(Comments::class);
        $this->pictureNameFormatter = $serviceManager->get(PictureNameFormatter::class);
        $this->duplicateFinder      = $serviceManager->get(DuplicateFinder::class);
        $this->pictureItem          = $serviceManager->get(PictureItem::class);
        $this->imageStorage         = $serviceManager->get(Image\Storage::class);
        $this->textStorage          = $serviceManager->get(TextStorage\Service::class);

        $this->linksTable = $serviceManager->get('TableManager')->get('links');

        $strategy = new Strategy\Image($serviceManager); // @phpstan-ignore-line
        $this->addStrategy('image', $strategy);
        $this->addStrategy('thumb', $strategy);
        $this->addStrategy('thumb_medium', $strategy);
        $this->addStrategy('image_gallery_full', $strategy);
        $this->addStrategy('preview_large', $strategy);

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

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('twins', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('categories', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('factories', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('copyright_blocks', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        if (isset($options['items']) && is_array($options['items'])) {
            $this->itemsOptions = $options['items'];
        }

        if (isset($options['item_id'])) {
            $this->itemId = (int) $options['item_id'];
        }

        if (isset($options['paginator'])) {
            $this->paginator = $options['paginator'];
        }

        return $this;
    }

    public function setUserId(int $userId): self
    {
        if ($this->userId !== $userId) {
            $this->userId   = $userId;
            $this->userRole = null;
        }

        /** @var Items $strategy */
        $strategy = $this->getStrategy('items');
        $strategy->setUserId($this->userId);

        return $this;
    }

    /**
     * @throws Exception
     */
    private function getPath(int $pictureId, int $targetItemId): array
    {
        $piRows = $this->pictureItem->getPictureItemsData($pictureId, PictureItem::PICTURE_CONTENT);
        $result = [];
        foreach ($piRows as $piRow) {
            $item = $this->getItemRoute($piRow['item_id'], $targetItemId);
            if ($item) {
                $result[] = [
                    'type'           => (int) $piRow['type'],
                    'perspective_id' => $piRow['perspective_id'] ? (int) $piRow['perspective_id'] : null,
                    'item'           => $item,
                ];
            }
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    private function getItemRoute(int $itemId, int $targetItemId): ?array
    {
        $row = $this->itemModel->getRow([
            'id' => $itemId,
        ]);
        if (! $row) {
            return null;
        }

        $parents = [];
        if (in_array($row['item_type_id'], [Item::CATEGORY, Item::ENGINE, Item::VEHICLE])) {
            $parents = $this->getItemParentRoute($row['id'], $targetItemId);
        }

        $isMatched = ! $targetItemId || $parents || $itemId === $targetItemId;
        if (! $isMatched) {
            return null;
        }

        return [
            'item_type_id' => (int) $row['item_type_id'],
            'catname'      => $row['catname'],
            'parents'      => $parents,
        ];
    }

    /**
     * @throws Exception
     */
    private function getItemParentRoute(int $itemId, int $targetItemId): array
    {
        $result = [];
        foreach ($this->itemParentModel->getParentRows($itemId) as $row) {
            $item = $this->getItemRoute($row['parent_id'], $targetItemId);
            if ($item) {
                $result[] = [
                    'catname' => $row['catname'],
                    'item'    => $item,
                ];
            }
        }

        return $result;
    }

    /**
     * @param mixed $object
     * @throws Image\Storage\Exception
     * @throws Exception
     */
    public function extract($object): ?array
    {
        if (! (is_array($object) || $object instanceof ArrayAccess)) {
            return null;
        }

        if (! isset($object['id'], $object['status'], $object['filesize'])) {
            throw new Exception("Unexpected object");
        }

        $isModer = false;
        $role    = $this->getUserRole();
        if ($role) {
            $isModer = $this->acl->enforce($role, 'global', 'moderate');
        }

        $addDate = Row::getDateTimeByColumnType('timestamp', $object['add_date']);

        $picture = [
            'id'         => (int) $object['id'],
            'identity'   => (string) $object['identity'],
            'url'        => '/picture/' . urlencode($object['identity']),
            'resolution' => (int) $object['width'] . '×' . (int) $object['height'],
            'status'     => $object['status'],
            'owner_id'   => $object['owner_id'] ? (int) $object['owner_id'] : null,
            'width'      => (int) $object['width'],
            'height'     => (int) $object['height'],
            'filesize'   => (int) $object['filesize'],
            'add_date'   => $this->extractValue('add_date', $addDate),
            'dpi_x'      => $object['dpi_x'],
            'dpi_y'      => $object['dpi_y'],
            'point'      => null,
        ];

        if ($object['point']) {
            $point = parsePointWkb($object['point']);
            if ($point) {
                $picture['point'] = [
                    'lng' => $point->getLng(),
                    'lat' => $point->getLat(),
                ];
            }
        }

        if ($this->filterComposite->filter('taken')) {
            $picture['taken_year']  = $object['taken_year'] ? (int) $object['taken_year'] : null;
            $picture['taken_month'] = $object['taken_month'] ? (int) $object['taken_month'] : null;
            $picture['taken_day']   = $object['taken_day'] ? (int) $object['taken_day'] : null;
        }

        if ($this->filterComposite->filter('path')) {
            $picture['path'] = $this->getPath($object['id'], $this->itemId);
        }

        if ($this->filterComposite->filter('paginator') && $this->paginator) {
            $filter = [
                'order'  => 'resolution_desc',
                'status' => $object['status'],
            ];

            if ($this->paginator['item_id']) {
                $filter['item']['ancestor_or_self'] = $this->paginator['item_id'];
                $filter['order']                    = 'perspectives';
            }

            if ($this->paginator['exact_item_id']) {
                $filter['item']['id'] = $this->paginator['exact_item_id'];
                $filter['order']      = 'perspectives';
            }

            if ($this->paginator['exact_item_link_type']) {
                $filter['item']['link_type'] = $this->paginator['exact_item_link_type'];
            }

            if ($this->paginator['perspective_id']) {
                $filter['item']['perspective'] = $this->paginator['perspective_id'];
            }

            if ($this->paginator['perspective_exclude_id']) {
                $parts = explode(',', $this->paginator['perspective_exclude_id']);
                $value = [];
                foreach ($parts as $part) {
                    $part = (int) $part;
                    if ($part) {
                        $value[] = $part;
                    }
                }

                $filter['item']['perspective_exclude'] = $value;
            }

            $paginator = $this->picture->getPaginator($filter);

            $total = $paginator->getTotalItemCount();

            if ($total < 500) {
                $paginatorPicturesFilter            = $filter;
                $paginatorPicturesFilter['columns'] = ['id', 'identity'];

                $paginatorPictures = $this->picture->getRows($paginatorPicturesFilter);

                $pageNumber = 0;
                foreach ($paginatorPictures as $n => $p) {
                    if ((int) $p['id'] === (int) $object['id']) {
                        $pageNumber = $n + 1;
                        break;
                    }
                }

                $paginator
                    ->setItemCountPerPage(1)
                    ->setPageRange(15)
                    ->setCurrentPageNumber($pageNumber);

                $pages = $paginator->getPages();

                if (isset($pages->previous)) {
                    $pages->previous = $paginatorPictures[$pages->previous - 1]['identity'];
                }
                if (isset($pages->next)) {
                    $pages->next = $paginatorPictures[$pages->next - 1]['identity'];
                }
                if (isset($pages->first)) {
                    $pages->first = $paginatorPictures[$pages->first - 1]['identity'];
                }
                if (isset($pages->last)) {
                    $pages->last = $paginatorPictures[$pages->last - 1]['identity'];
                }
                if (isset($pages->current)) {
                    $pages->current = $paginatorPictures[$pages->current - 1]['identity'];
                }
                $pagesInRange = [];
                foreach ($pages->pagesInRange as $i) {
                    $pagesInRange[] = [
                        'page'     => $i,
                        'identity' => $paginatorPictures[$i - 1]['identity'],
                    ];
                }
                $pages->pagesInRange = $pagesInRange;

                $picture['paginator'] = $pages;
            }
        }

        if ($this->filterComposite->filter('subscribed')) {
            $subscribed = false;
            if ($this->userId) {
                $subscribed = $this->comments->service()->userSubscribed(
                    Comments::PICTURES_TYPE_ID,
                    $object['id'],
                    $this->userId
                );
            }

            $picture['subscribed'] = $subscribed;
        }

        if ($this->filterComposite->filter('copyright_blocks')) {
            $rows = $this->itemModel->getRows([
                'language'     => $this->language,
                'item_type_id' => Item::COPYRIGHT,
                'pictures'     => [
                    'id' => $picture['id'],
                ],
                'limit'        => 3,
            ]);

            $blocks = [];
            foreach ($rows as $row) {
                $blocks[] = $this->extractValue('copyright_blocks', $row);
            }

            $picture['copyright_blocks'] = $blocks;
        }

        if ($this->filterComposite->filter('factories')) {
            $rows = $this->itemModel->getRows([
                'language'           => $this->language,
                'item_type_id'       => Item::FACTORY,
                'descendant_or_self' => [
                    'pictures' => [
                        'id' => $object['id'],
                    ],
                ],
            ]);

            $factories = [];
            foreach ($rows as $row) {
                $factories[] = $this->extractValue('factories', $row);
            }

            $picture['factories'] = $factories;
        }

        if ($this->filterComposite->filter('twins')) {
            $rows = $this->itemModel->getRows([
                'language'           => $this->language,
                'item_type_id'       => Item::TWINS,
                'descendant_or_self' => [
                    'pictures' => [
                        'id' => $object['id'],
                    ],
                ],
            ]);

            $twins = [];
            foreach ($rows as $row) {
                $twins[] = $this->extractValue('twins', $row);
            }

            $picture['twins'] = $twins;
        }

        if ($this->filterComposite->filter('categories')) {
            $categoryRows = $this->itemModel->getRows([
                'language'     => $this->language,
                'item_type_id' => Item::CATEGORY,
                'child'        => [
                    'item_type_id'       => [Item::VEHICLE, Item::ENGINE],
                    'descendant_or_self' => [
                        'pictures' => [
                            'id' => $object['id'],
                        ],
                    ],
                ],
            ]);

            $categories = [];
            foreach ($categoryRows as $row) {
                $categories[] = $this->extractValue('categories', $row);
            }

            $picture['categories'] = $categories;
        }

        if ($this->filterComposite->filter('authors')) {
            $authors        = [];
            $pictureAuthors = $this->pictureItem->getPictureItemsData($object['id'], PictureItem::PICTURE_AUTHOR);
            foreach ($pictureAuthors as $pictureAuthor) {
                $item = $this->itemModel->getRow([
                    'id'       => $pictureAuthor['item_id'],
                    'language' => $this->language,
                    'columns'  => ['name'],
                ]);

                $authors[] = [
                    'id'   => $pictureAuthor['item_id'],
                    'name' => $item['name'],
                ];
            }

            $picture['authors'] = $authors;
        }

        if ($isModer) {
            $crop                       = $this->imageStorage->getImageCrop($object['image_id']);
            $picture['cropped']         = (bool) $crop;
            $picture['crop_resolution'] = $crop ? $crop['width'] . '×' . $crop['height'] : null;
        }

        if ($this->filterComposite->filter('views')) {
            $picture['views'] = $this->pictureView->get($object['id']);
        }

        $showNameHtml = $this->filterComposite->filter('name_html');
        $showNameText = $this->filterComposite->filter('name_text');

        $nameData = null;
        if ($showNameHtml || $showNameText) {
            $nameDatas = $this->picture->getNameData([$object], [
                'language' => $this->language,
            ]);
            $nameData  = $nameDatas[$object['id']];
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
                $owner = $this->userModel->getRow((int) $object['owner_id']);
            }

            $picture['owner'] = $owner ? $this->extractValue('owner', $owner) : null;
        }

        if ($this->filterComposite->filter('thumb')) {
            $picture['thumb'] = $this->extractValue('thumb', [
                'image'  => $object['image_id'],
                'format' => 'picture-thumb',
            ]);
        }

        if ($this->filterComposite->filter('thumb_medium')) {
            $picture['thumb_medium'] = $this->extractValue('thumb_medium', [
                'image'  => $object['image_id'],
                'format' => 'picture-thumb-medium',
            ]);
        }

        if ($this->filterComposite->filter('votes')) {
            $picture['votes'] = $this->pictureVote->getVote($object['id'], $this->userId);
        }

        if ($this->filterComposite->filter('comments_count')) {
            $msgCount = $this->comments->service()->getMessagesCount(
                Comments::PICTURES_TYPE_ID,
                $object['id']
            );

            $newMessages               = $this->comments->service()->getNewMessages(
                Comments::PICTURES_TYPE_ID,
                $object['id'],
                (int) $this->userId
            );
            $picture['comments_count'] = [
                'total' => $msgCount,
                'new'   => $newMessages,
            ];
        }

        if ($this->filterComposite->filter('image_gallery_full')) {
            $picture['image_gallery_full'] = $this->extractValue('image_gallery_full', [
                'image'  => $object['image_id'],
                'format' => 'picture-gallery-full',
            ]);
        }

        if ($this->filterComposite->filter('preview_large')) {
            $picture['preview_large'] = $this->extractValue('preview_large', [
                'image'  => $object['image_id'],
                'format' => 'picture-preview-large',
            ]);
        }

        if ($this->filterComposite->filter('crop')) {
            $picture['crop'] = null;

            $crop = $this->imageStorage->getImageCrop($object['image_id']);
            if ($crop) {
                $picture['crop'] = [
                    'left'   => (int) $crop['left'],
                    'top'    => (int) $crop['top'],
                    'width'  => (int) $crop['width'],
                    'height' => (int) $crop['height'],
                ];
            }
        }

        if ($this->filterComposite->filter('perspective_item')) {
            $itemIds = $this->pictureItem->getPictureItemsByItemType(
                $object['id'],
                [Item::VEHICLE, Item::BRAND]
            );

            $picture['perspective_item'] = null;

            if (count($itemIds) === 1) {
                $item = $itemIds[0];

                $perspective = $this->pictureItem->getPerspective($object['id'], $item['item_id']);

                $picture['perspective_item'] = [
                    'item_id'        => (int) $item['item_id'],
                    'type'           => (int) $item['type'],
                    'perspective_id' => $perspective ? (int) $perspective : null,
                ];
            }
        }

        if ($this->filterComposite->filter('items')) {
            $typeId = 0;
            if (isset($this->itemsOptions['type_id'])) {
                $typeId = (int) $this->itemsOptions['type_id'];
            }

            $rows             = $this->pictureItem->getPictureItemsData($object['id'], $typeId);
            $picture['items'] = $this->extractValue('items', $rows);
        }

        if ($this->filterComposite->filter('moder_vote')) {
            $picture['moder_vote'] = $this->pictureModerVote->getVoteCount($object['id']);
        }

        if ($this->filterComposite->filter('moder_votes')) {
            $moderVotes = [];
            foreach ($this->pictureModerVote->getVotes($object['id']) as $row) {
                $user         = $this->userModel->getRow((int) $row['user_id']);
                $moderVotes[] = [
                    'reason' => $row['reason'],
                    'vote'   => (int) $row['vote'],
                    'user'   => $user ? $this->extractValue('moder_vote_user', $user) : null,
                ];
            }
            $picture['moder_votes'] = $moderVotes;
        }

        if ($this->filterComposite->filter('image')) {
            $picture['image'] = $this->extractValue('image', [
                'image' => $object['image_id'],
            ]);
        }

        if ($this->filterComposite->filter('of_links')) {
            $brandIds = $this->itemModel->getIds([
                'item_type_id'       => Item::BRAND,
                'descendant_or_self' => [
                    'pictures' => [
                        'id' => $object['id'],
                    ],
                ],
            ]);

            $ofLinks = [];
            if (count($brandIds)) {
                $links = $this->linksTable->select([
                    new Sql\Predicate\In('item_id', $brandIds),
                    'type' => 'official',
                ]);
                foreach ($links as $link) {
                    $ofLinks[$link['id']] = $link;
                }
            }

            $picture['of_links'] = array_values($ofLinks);
        }

        if ($this->filterComposite->filter('copyrights')) {
            $picture['copyrights'] = null;
            if ($object['copyrights_text_id']) {
                $text                          = $this->textStorage->getText($object['copyrights_text_id']);
                $picture['copyrights']         = $text;
                $picture['copyrights_text_id'] = (int) $object['copyrights_text_id'];
            }
        }

        if ($this->filterComposite->filter('taken_date')) {
            $takenDate = null;
            if ($object['taken_year']) {
                $date = new DateTime();
                $date->setDate($object['taken_year'], 1, 1);
                $format = 'Y';
                if ($object['taken_month']) {
                    $date->setDate($object['taken_year'], $object['taken_month'], 1);
                    $format = 'm.Y';
                    if ($object['taken_day']) {
                        $date->setDate($object['taken_year'], $object['taken_month'], $object['taken_day']);
                        $format = 'd.m.Y';
                    }
                }
                $takenDate = $date->format($format);
            }
            $picture['taken_date'] = $takenDate;
        }

        if ($isModer) {
            $picture['special_name'] = $object['name'];

            if ($this->filterComposite->filter('iptc')) {
                $picture['iptc'] = null;
            }

            if ($this->filterComposite->filter('exif')) {
                $exif        = $this->imageStorage->getImageEXIF($object['image_id']);
                $exifStr     = '';
                $notSections = ['FILE', 'COMPUTED'];
                if ($exif) {
                    foreach ($exif as $key => $section) {
                        if (array_search($key, $notSections) !== false) {
                            continue;
                        }

                        $exifStr .= '<p>[' . htmlspecialchars($key) . ']';
                        foreach ($section as $name => $val) {
                            $exifStr .= "<br />" . htmlspecialchars($name) . ": ";
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

            if ($this->filterComposite->filter('moder_voted')) {
                $picture['moder_voted'] = $this->pictureModerVote->hasVote($object['id'], $this->userId);
            }

            if ($this->filterComposite->filter('similar')) {
                $picture['similar'] = null;
                $similar            = $this->duplicateFinder->findSimilar($object['id']);
                if ($similar) {
                    $picture['similar'] = $this->extractValue('similar', $similar);
                }
            }

            if ($this->filterComposite->filter('change_status_user')) {
                $user                          = $this->userModel->getRow((int) $object['change_status_user_id']);
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
                    'delete'    => false,
                ];

                $role = $this->getUserRole();
                if ($role) {
                    $picture['rights'] = [
                        'move'      => $this->acl->enforce($role, 'picture', 'move'),
                        'unaccept'  => ($object['status'] === Picture::STATUS_ACCEPTED)
                                    && $this->acl->enforce($role, 'picture', 'unaccept'),
                        'accept'    => $this->picture->canAccept($object)
                                    && $this->acl->enforce($role, 'picture', 'accept'),
                        'restore'   => ($object['status'] === Picture::STATUS_REMOVING)
                                    && $this->acl->enforce($role, 'picture', 'restore'),
                        'normalize' => ($object['status'] === Picture::STATUS_INBOX)
                                    && $this->acl->enforce($role, 'picture', 'normalize'),
                        'flop'      => ($object['status'] === Picture::STATUS_INBOX)
                                    && $this->acl->enforce($role, 'picture', 'flop'),
                        'crop'      => $this->acl->enforce($role, 'picture', 'crop'),
                        'delete'    => $this->canDelete($object),
                    ];
                }
            }

            if ($this->filterComposite->filter('is_last')) {
                $isLastPicture = null;
                if ($object['status'] === Picture::STATUS_ACCEPTED) {
                    $isLastPicture = ! $this->picture->isExists([
                        'id_exclude' => $object['id'],
                        'status'     => Picture::STATUS_ACCEPTED,
                        'item'       => [
                            'contains_picture' => $object['id'],
                        ],
                    ]);
                }

                $picture['is_last'] = $isLastPicture;
            }

            if ($this->filterComposite->filter('accepted_count')) {
                $picture['accepted_count'] = $this->picture->getCount([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => [
                        'contains_picture' => $object['id'],
                    ],
                ]);
            }

            if ($this->filterComposite->filter('replaceable')) {
                $picture['replaceable'] = null;
                if ($object['replace_picture_id']) {
                    $row = $this->picture->getRow(['id' => (int) $object['replace_picture_id']]);
                    if ($row) {
                        $picture['replaceable'] = $this->extractValue('replaceable', $row);
                    }
                }
            }

            if ($this->filterComposite->filter('siblings')) {
                $picture['siblings'] = [
                    'prev'     => null,
                    'next'     => null,
                    'prev_new' => null,
                    'next_new' => null,
                ];

                $prevPicture = $this->picture->getRow([
                    'id_lt' => $object['id'],
                    'order' => 'id_desc',
                ]);
                if ($prevPicture) {
                    $picture['siblings']['prev'] = $this->extractValue('siblings', $prevPicture);
                }

                $nextPicture = $this->picture->getRow([
                    'id_gt' => $object['id'],
                    'order' => 'id_asc',
                ]);
                if ($nextPicture) {
                    $picture['siblings']['next'] = $this->extractValue('siblings', $nextPicture);
                }

                $prevNewPicture = $this->picture->getRow([
                    'id_lt'  => $object['id'],
                    'status' => Picture::STATUS_INBOX,
                    'order'  => 'id_desc',
                ]);
                if ($prevNewPicture) {
                    $picture['siblings']['prev_new'] = $this->extractValue('siblings', $prevNewPicture);
                }

                $nextNewPicture = $this->picture->getRow([
                    'id_gt'  => $object['id'],
                    'status' => Picture::STATUS_INBOX,
                    'order'  => 'id_asc',
                ]);
                if ($nextNewPicture) {
                    $picture['siblings']['next_new'] = $this->extractValue('siblings', $nextNewPicture);
                }
            }

            if ($this->acl->enforce($role, 'user', 'ip')) {
                $picture['ip'] = $object['ip'] ? inet_ntop($object['ip']) : null;
            }
        }

        return $picture;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }

    private function getUserRole(): ?string
    {
        if (! $this->userId) {
            return null;
        }

        if (! isset($this->userRole)) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }

    /**
     * @param array|ArrayAccess $picture
     */
    private function canDelete($picture): bool
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }

        if ($this->acl->enforce($role, 'picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $this->userId)) {
                return true;
            }
        }

        if (! $this->acl->enforce($role, 'picture', 'remove_by_vote')) {
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
