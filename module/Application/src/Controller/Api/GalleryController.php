<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\PictureNameFormatter;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User;
use Exception;
use ImagickException;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

use function floor;

/**
 * @method User user($user = null)
 * @method Storage imageStorage()
 * @method string language()
 */
class GalleryController extends AbstractRestfulController
{
    private Picture $picture;

    private CommentsService $comments;

    private PictureNameFormatter $pictureNameFormatter;

    private ItemNameFormatter $itemNameFormatter;

    private PictureItem $pictureItem;

    private Item $itemModel;

    private int $itemsPerPage = 10;

    public function __construct(
        Picture $picture,
        PictureItem $pictureItem,
        Item $itemModel,
        CommentsService $comments,
        PictureNameFormatter $pictureNameFormatter,
        ItemNameFormatter $itemNameFormatter
    ) {
        $this->picture              = $picture;
        $this->pictureItem          = $pictureItem;
        $this->itemModel            = $itemModel;
        $this->comments             = $comments;
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->itemNameFormatter    = $itemNameFormatter;
    }

    private function getPicturePage(array $filter, string $identity): int
    {
        unset($filter['identity']);
        $filter['columns'] = ['identity'];
        $rows              = $this->picture->getRows($filter);
        foreach ($rows as $index => $row) {
            if ($row['identity'] === $identity) {
                return (int) floor($index / $this->itemsPerPage) + 1;
            }
        }

        return 1;
    }

    /**
     * @return array|JsonModel
     * @throws Storage\Exception
     * @throws ImagickException
     * @throws Exception
     */
    public function galleryAction()
    {
        $itemID = (int) $this->params()->fromQuery('item_id');

        $filter = [
            'order'  => 'resolution_desc',
            'status' => Picture::STATUS_ACCEPTED,
        ];

        $exact = (bool) $this->params()->fromQuery('exact');

        if ($itemID) {
            if ($exact) {
                $filter['item']['id'] = $itemID;
            } else {
                $filter['item']['ancestor_or_self'] = $itemID;
            }
        }

        $exactItemID = (int) $this->params()->fromQuery('exact_item_id');
        if ($exactItemID) {
            $filter['item']['id'] = $exactItemID;
        }

        $exactItemLinkType = (int) $this->params()->fromQuery('exact_item_link_type');
        if ($exactItemLinkType) {
            $filter['item']['link_type'] = $exactItemLinkType;
        }

        $page            = $this->params()->fromQuery('page');
        $pictureIdentity = $this->params()->fromQuery('picture_identity');

        if (! $itemID && ! $pictureIdentity) {
            return $this->forbiddenAction();
        }

        switch ($this->params()->fromQuery('status')) {
            case Picture::STATUS_INBOX:
                $filter['status'] = Picture::STATUS_INBOX;
                break;
            case Picture::STATUS_REMOVING:
                $filter['status'] = Picture::STATUS_REMOVING;
                break;
            case Picture::STATUS_ACCEPTED:
            default:
                $filter['status'] = Picture::STATUS_ACCEPTED;
                break;
        }

        $imageStorage = $this->imageStorage();

        $language = $this->language();

        if ($pictureIdentity) {
            if (! $itemID && ! $exactItemID) {
                $filter['identity'] = $pictureIdentity;
            }

            // look for page of that picture
            $filterCopy = $filter;
            unset($filterCopy['status']);
            $filterCopy['columns']  = ['status'];
            $filterCopy['identity'] = $pictureIdentity;

            $row = $this->picture->getRow($filterCopy);

            if (! $row) {
                return $this->notFoundAction();
            }

            $filter['status'] = $row['status'];
            $page             = $this->getPicturePage($filter, $pictureIdentity);
        }

        $filter['columns'] = [
            'id',
            'identity',
            'name',
            'width',
            'height',
            'image_id',
            'filesize',
            'messages',
        ];

        $paginator = $this->picture->getPaginator($filter);

        $paginator
            ->setItemCountPerPage($this->itemsPerPage)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        // prefetch
        $ids          = [];
        $fullRequests = [];
        $cropRequests = [];
        $crops        = [];
        $imageIds     = [];
        foreach ($rows as $idx => $picture) {
            $imageId            = (int) $picture['image_id'];
            $fullRequests[$idx] = $imageId;

            $crop = $imageStorage->getImageCrop($imageId);

            if ($crop) {
                $cropRequests[$idx] = $imageId;
                $crops[$idx]        = $crop;
            }
            $ids[]      = (int) $picture['id'];
            $imageIds[] = (int) $imageId;
        }

        // images
        $images         = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');

        // names
        $names = $this->picture->getNameData($rows, [
            'language' => $language,
        ]);

        // comments
        $user        = $this->user()->get();
        $userId      = $user ? $user['id'] : null;
        $newMessages = [];
        if ($userId) {
            $newMessages = $this->comments->getNewMessages(
                Comments::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }

        $gallery = [];
        foreach ($rows as $idx => $row) {
            $imageId = (int) $row['image_id'];

            if (! $imageId) {
                continue;
            }

            $image = $images[$imageId] ?? null;
            if (! $image) {
                continue;
            }

            $itemID = (int) $row['id'];

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

            $msgCount    = $row['messages'];
            $newMsgCount = 0;
            if ($userId) {
                $newMsgCount = $newMessages[$itemID] ?? $msgCount;
            }

            $name = $names[$itemID] ?? null;
            $name = $this->pictureNameFormatter->format($name, $language);

            $itemsData = $this->pictureItem->getData([
                'picture'      => $row['id'],
                'onlyWithArea' => true,
            ]);

            $areas = [];
            foreach ($itemsData as $pictureItem) {
                $item = $this->itemModel->getRow(['id' => $pictureItem['item_id']]);
                $area = null;
                if ($pictureItem['area']) {
                    $area = [
                        'left'   => $pictureItem['area'][0] / $image->getWidth(),
                        'top'    => $pictureItem['area'][1] / $image->getHeight(),
                        'width'  => $pictureItem['area'][2] / $image->getWidth(),
                        'height' => $pictureItem['area'][3] / $image->getHeight(),
                    ];
                }
                $areas[] = [
                    'area' => $area,
                    'name' => $this->itemNameFormatter->formatHtml(
                        $this->itemModel->getNameData($item, $language),
                        $language
                    ),
                ];
            }

            $gallery[] = [
                'id'          => $itemID,
                'identity'    => $row['identity'],
                'sourceUrl'   => $sUrl,
                'crop'        => $crop,
                'full'        => $full,
                'messages'    => $msgCount,
                'newMessages' => $newMsgCount,
                'name'        => $name,
                'filesize'    => (int) $row['filesize'],
                'areas'       => $areas,
            ];
        }

        return new JsonModel([
            'page'   => $paginator->getCurrentPageNumber(),
            'pages'  => $paginator->count(),
            'count'  => $paginator->getTotalItemCount(),
            'items'  => $gallery,
            'status' => $filter['status'],
        ]);
    }
}
