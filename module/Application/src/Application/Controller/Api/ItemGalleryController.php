<?php

namespace Application\Controller\Api;

use Application\Comments;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\PictureNameFormatter;

/**
 * Class ItemGalleryController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method Storage imageStorage()
 * @method string language()
 */
class ItemGalleryController extends AbstractRestfulController
{
    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var CommentsService
     */
    private $comments;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var integer
     */
    private $itemsPerPage = 10;

    public function __construct(
        Picture $picture,
        PictureItem $pictureItem,
        Item $itemModel,
        CommentsService $comments,
        PictureNameFormatter $pictureNameFormatter,
        ItemNameFormatter $itemNameFormatter
    ) {
        $this->picture = $picture;
        $this->pictureItem = $pictureItem;
        $this->itemModel = $itemModel;
        $this->comments = $comments;
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->itemNameFormatter = $itemNameFormatter;
    }

    private function getPicturePage(array $filter, $identity): int
    {
        unset($filter['identity']);
        $filter['columns'] = ['identity'];
        $rows = $this->picture->getRows($filter);
        foreach ($rows as $index => $row) {
            if ($row['identity'] == $identity) {
                return floor($index / $this->itemsPerPage) + 1;
            }
        }

        return 1;
    }

    public function galleryAction()
    {
        $id = $this->params()->fromRoute('id');

        $filter = [
            'order'  => 'resolution_desc',
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $id
            ]
        ];

        $page = $this->params()->fromQuery('page');
        $pictureIdentity = $this->params()->fromQuery('picture_identity');

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
            // look for page of that picture
            $filterCopy = $filter;
            unset($filterCopy['status']);
            $filterCopy['columns'] = ['status'];
            $filterCopy['identity'] = $pictureIdentity;

            $row = $this->picture->getRow($filterCopy);

            if (! $row) {
                return $this->notFoundAction();
            }

            $filter['status'] = $row['status'];
            $page = $this->getPicturePage($filter, $pictureIdentity);
        }

        $filter['columns'] = [
            'id', 'identity', 'name', 'width', 'height',
            'image_id', 'filesize', 'messages'
        ];

        $paginator = $this->picture->getPaginator($filter);

        $paginator
            ->setItemCountPerPage($this->itemsPerPage)
            ->setCurrentPageNumber($page);

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
        $userId = $this->user()->get()['id'];
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
                'identity'    => $row['identity'],
                'sourceUrl'   => $sUrl,
                'crop'        => $crop,
                'full'        => $full,
                'messages'    => $msgCount,
                'newMessages' => $newMsgCount,
                'name'        => $name,
                'filesize'    => (int) $row['filesize'],
                'areas'       => $areas
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
