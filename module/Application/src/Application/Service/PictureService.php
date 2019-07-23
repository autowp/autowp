<?php

namespace Application\Service;

use Application\Comments;
use ArrayObject;
use Autowp\Comments\Attention;
use Exception;

use geoPHP;
use Point;

use Zend\Db\Sql;

use Autowp\Comments\CommentsService;
use Autowp\Image;

use Application\DuplicateFinder;
use Application\ExifGPSExtractor;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\UserPicture;

class PictureService
{
    const QUEUE_LIFETIME = 7; // days

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var CommentsService
     */
    private $comments;

    /**
     * @var Image\Storage
     */
    private $imageStorage;

    /**
     * @var TelegramService
     */
    private $telegram;

    /**
     * @var DuplicateFinder
     */
    private $duplicateFinder;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var UserPicture
     */
    private $userPicture;

    public function __construct(
        Picture $picture,
        CommentsService $comments,
        Image\Storage $imageStorage,
        TelegramService $telegram,
        DuplicateFinder $duplicateFinder,
        PictureItem $pictureItem,
        UserPicture $userPicture
    ) {
        $this->picture = $picture;
        $this->comments = $comments;
        $this->imageStorage = $imageStorage;
        $this->telegram = $telegram;
        $this->duplicateFinder = $duplicateFinder;
        $this->pictureItem = $pictureItem;
        $this->userPicture = $userPicture;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function clearQueue()
    {
        $select = $this->picture->getTable()->getSql()->select();

        $select->where([
            'status' => Picture::STATUS_REMOVING,
            new Sql\Predicate\Expression(
                '(removing_date is null OR (removing_date < DATE_SUB(CURDATE(), INTERVAL ? DAY) ))',
                [self::QUEUE_LIFETIME]
            ),
        ])->limit(1000);

        $pictures = $this->picture->getTable()->selectWith($select);

        $count = count($pictures);

        if ($count) {
            print sprintf("Removing %d pictures\n", $count);

            foreach ($pictures as $picture) {
                $this->pictureItem->setPictureItems($picture['id'], PictureItem::PICTURE_CONTENT, []);
                $this->pictureItem->setPictureItems($picture['id'], PictureItem::PICTURE_AUTHOR, []);

                $this->comments->deleteTopic(
                    Comments::PICTURES_TYPE_ID,
                    $picture['id']
                );

                $imageId = $picture['image_id'];
                if ($imageId) {
                    $this->picture->getTable()->delete([
                        'id = ?' => $picture['id']
                    ]);

                    $this->imageStorage->removeImage($imageId);
                } else {
                    print "Broken image `{$picture['id']}`. Skip\n";
                }
            }
        } else {
            print "Nothing to clear\n";
        }
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param string $path
     * @param int $userId
     * @param string $remoteAddr
     * @param int $itemId
     * @param int $perspectiveId
     * @param int $replacePictureId
     * @param string $note
     * @return array|ArrayObject|null
     * @throws Image\Storage\Exception
     */
    public function addPictureFromFile(
        string $path,
        int $userId,
        string $remoteAddr,
        int $itemId,
        int $perspectiveId,
        int $replacePictureId,
        string $note
    ) {
        list ($width, $height, $imageType) = getimagesize($path);
        $width = (int)$width;
        $height = (int)$height;
        if ($width <= 0) {
            throw new Exception("Width <= 0");
        }

        if ($height <= 0) {
            throw new Exception("Height <= 0");
        }

        // generate filename
        switch ($imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_PNG:
                break;
            default:
                throw new Exception("Unsupported image type");
        }
        $ext = image_type_to_extension($imageType, false);

        $imageId = $this->imageStorage->addImageFromFile($path, 'picture', [
            'extension' => $ext,
            'pattern'   => 'autowp_' . rand()
        ]);

        $image = $this->imageStorage->getImage($imageId);
        $fileSize = $image->getFileSize();

        $resolution = $this->imageStorage->getImageResolution($imageId);

        // add record to db
        $this->picture->getTable()->insert([
            'image_id'      => $imageId,
            'width'         => $width,
            'height'        => $height,
            'dpi_x'         => $resolution ? $resolution['x'] : null,
            'dpi_y'         => $resolution ? $resolution['y'] : null,
            'owner_id'      => $userId,
            'add_date'      => new Sql\Expression('NOW()'),
            'filesize'      => $fileSize,
            'status'        => Picture::STATUS_INBOX,
            'removing_date' => null,
            'ip'            => inet_pton($remoteAddr),
            'identity'      => $this->picture->generateIdentity(),
            'replace_picture_id' => $replacePictureId ? $replacePictureId : null,
        ]);

        $pictureId = (int) $this->picture->getTable()->getLastInsertValue();

        $picture = $this->picture->getRow(['id' => (int)$pictureId]);

        if ($itemId) {
            $this->pictureItem->setPictureItems($pictureId, PictureItem::PICTURE_CONTENT, [$itemId]);
            $this->pictureItem->setProperties($pictureId, $itemId, PictureItem::PICTURE_CONTENT, [
                'perspective' => $perspectiveId
            ]);
        } elseif ($replacePictureId) {
            $itemsData = $this->pictureItem->getPictureItemsData($replacePictureId);
            foreach ($itemsData as $item) {
                $this->pictureItem->add($pictureId, $item['item_id'], $item['type']);
                if ($item['perspective_id']) {
                    $this->pictureItem->setProperties($pictureId, $item['item_id'], $item['type'], [
                        'perspective' => $item['perspective_id']
                    ]);
                }
            }
        }

        // increment uploads counter
        $this->userPicture->incrementUploads($userId);

        // rename file to new
        $this->imageStorage->changeImageName($imageId, [
            'pattern' => $this->picture->getFileNamePattern($picture['id'])
        ]);

        // add comment
        if ($note) {
            $this->comments->add([
                'typeId'             => Comments::PICTURES_TYPE_ID,
                'itemId'             => $pictureId,
                'parentId'           => null,
                'authorId'           => $userId,
                'message'            => $note,
                'ip'                 => $remoteAddr,
                'moderatorAttention' => Attention::NONE
            ]);
        }

        $this->comments->subscribe(
            Comments::PICTURES_TYPE_ID,
            $pictureId,
            $userId
        );

        // read gps
        $exif = $this->imageStorage->getImageEXIF($imageId);
        $extractor = new ExifGPSExtractor();
        $gps = $extractor->extract($exif);
        if ($gps !== false) {
            geoPHP::version();
            $point = new Point($gps['lng'], $gps['lat']);

            $this->picture->getTable()->update([
                'point' => new Sql\Expression('ST_GeomFromWKB(?)', [$point->out('wkb')])
            ], [
                'id' => $pictureId
            ]);
        }

        $this->imageStorage->getFormatedImage($picture['image_id'], 'picture-thumb');
        $this->imageStorage->getFormatedImage($picture['image_id'], 'picture-medium');
        $this->imageStorage->getFormatedImage($picture['image_id'], 'picture-thumb-medium');
        $this->imageStorage->getFormatedImage($picture['image_id'], 'picture-thumb-large');
        $this->imageStorage->getFormatedImage($picture['image_id'], 'picture-gallery-full');

        // index
        $this->duplicateFinder->indexImage($pictureId);

        $this->telegram->notifyInbox($pictureId);

        return $picture;
    }
}
