<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Sql;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

use function array_slice;
use function arsort;

use const SORT_NUMERIC;

class RatingController extends AbstractActionController
{
    private StorageInterface $cache;

    private Comments $comments;

    private Picture $picture;

    private Item $item;

    private User $userModel;

    private AbstractRestHydrator $userHydrator;

    public function __construct(
        StorageInterface $cache,
        Comments $comments,
        Picture $picture,
        Item $item,
        User $userModel,
        AbstractRestHydrator $userHydrator
    ) {
        $this->cache        = $cache;
        $this->comments     = $comments;
        $this->picture      = $picture;
        $this->item         = $item;
        $this->userModel    = $userModel;
        $this->userHydrator = $userHydrator;
    }

    public function specsAction(): JsonModel
    {
        $rows = $this->userModel->getRows([
            'not_deleted' => true,
            'has_specs'   => true,
            'limit'       => 30,
            'order'       => 'specs_volume desc',
        ]);

        $precisionLimit = 50;

        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
        ]);

        $users = [];
        foreach ($rows as $idx => $user) {
            $brands = [];
            if ($idx < 5) {
                $cacheKey = 'RATING_USER_BRAND_5_' . $precisionLimit . '_' . $user['id'];
                $brands   = $this->cache->getItem($cacheKey, $success);
                if (! $success) {
                    $data = $this->item->getCountPairs([
                        'item_type_id' => Item::BRAND,
                        'descendant'   => [
                            'has_specs_of_user' => $user['id'],
                        ],
                        'limit'        => $precisionLimit,
                    ]);

                    arsort($data, SORT_NUMERIC);
                    $data = array_slice($data, 0, 3, true);

                    foreach ($data as $brandId => $value) {
                        $row      = $this->item->getRow([
                            'id'           => $brandId,
                            'item_type_id' => Item::BRAND,
                        ]);
                        $brands[] = [
                            'name'  => $row['name'],
                            'route' => ['/', $row['catname']],
                            'value' => $value,
                        ];
                    }
                }

                $this->cache->setItem($cacheKey, $brands);
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($user),
                'volume' => (float) $user['specs_volume'],
                'brands' => $brands,
                'weight' => (float) $user['specs_weight'],
            ];
        }

        return new JsonModel([
            'users' => $users,
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function picturesAction(): JsonModel
    {
        $rows = $this->userModel->getRows([
            'not_deleted'  => true,
            'limit'        => 30,
            'order'        => 'pictures_total desc',
            'has_pictures' => true,
        ]);

        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
        ]);

        $users = [];
        foreach ($rows as $idx => $user) {
            $brands = [];
            if ($idx < 10) {
                $cacheKey = 'RATING_USER_PICTURES_BRAND_6_' . $user['id'];
                $brands   = $this->cache->getItem($cacheKey, $success);
                if (! $success) {
                    $rows = $this->item->getRows([
                        'item_type_id' => Item::BRAND,
                        'descendant'   => [
                            'pictures' => [
                                'user'   => $user['id'],
                                'status' => Picture::STATUS_ACCEPTED,
                            ],
                        ],
                        'order'        => new Sql\Expression('count(distinct p1.id) desc'),
                        'limit'        => 3,
                    ]);

                    foreach ($rows as $brand) {
                        $brands[] = [
                            'name'  => $brand['name'],
                            'route' => ['/', $brand['catname']],
                        ];
                    }
                }

                $this->cache->setItem($cacheKey, $brands);
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($user),
                'volume' => (int) $user['pictures_total'],
                'brands' => $brands,
            ];
        }

        return new JsonModel([
            'users' => $users,
        ]);
    }

    public function likesAction(): JsonModel
    {
        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
        ]);

        $users = [];
        foreach ($this->comments->service()->getTopAuthors(30) as $id => $volume) {
            $users[] = [
                'user'   => $this->userHydrator->extract($this->userModel->getRow($id)),
                'volume' => $volume,
                'brands' => [],
            ];
        }

        return new JsonModel([
            'users' => $users,
        ]);
    }

    public function pictureLikesAction(): JsonModel
    {
        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
        ]);

        $users = [];
        $idx   = 0;
        foreach ($this->picture->getTopLikes(30) as $ownerId => $volume) {
            $fans = [];
            if ($idx++ < 10) {
                foreach ($this->picture->getTopOwnerFans($ownerId, 2) as $fanId => $fanVolume) {
                    $fans[] = [
                        'user'   => $this->userHydrator->extract($this->userModel->getRow($fanId)),
                        'volume' => $fanVolume,
                    ];
                }
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($this->userModel->getRow($ownerId)),
                'volume' => $volume,
                'brands' => [],
                'fans'   => $fans,
            ];
        }

        return new JsonModel([
            'users' => $users,
        ]);
    }
}
