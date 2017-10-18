<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\User;

use Application\Comments;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;

class RatingController extends AbstractActionController
{
    private $cache;

    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var RestHydrator
     */
    private $userHydrator;

    public function __construct(
        $cache,
        Comments $comments,
        Picture $picture,
        Item $item,
        User $userModel,
        RestHydrator $userHydrator
    ) {
        $this->cache = $cache;
        $this->comments = $comments;
        $this->picture = $picture;
        $this->item = $item;
        $this->userModel = $userModel;
        $this->userHydrator = $userHydrator;
    }

    public function specsAction()
    {
        $rows = $this->userModel->getRows([
            'not_deleted' => true,
            'has_specs'   => true,
            'limit'       => 30,
            'order'       => 'specs_volume desc'
        ]);

        $precisionLimit = 50;

        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);

        $users = [];
        foreach ($rows as $idx => $user) {
            $brands = [];
            if ($idx < 5) {
                $cacheKey = 'RATING_USER_BRAND_5_'.$precisionLimit.'_' . $user['id'];
                $brands = $this->cache->getItem($cacheKey, $success);
                if (! $success) {
                    $data = $this->item->getCountPairs([
                        'item_type_id' => Item::BRAND,
                        'descendant' => [
                            'has_specs_of_user' => $user['id']
                        ],
                        'limit'        => $precisionLimit
                    ]);

                    arsort($data, SORT_NUMERIC);
                    $data = array_slice($data, 0, 3, true);

                    foreach ($data as $brandId => $value) {
                        $row = $this->item->getRow([
                            'id'           => $brandId,
                            'item_type_id' => Item::BRAND
                        ]);
                        $brands[] = [
                            'name' => $row['name'],
                            'url'  => $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand',
                                'brand_catname' => $row['catname']
                            ]),
                            'value' => $value
                        ];
                    }
                }

                $this->cache->setItem($cacheKey, $brands);
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($user),
                'volume' => $user['specs_volume'],
                'brands' => $brands,
                'weight' => $user['specs_weight']
            ];
        }

        return new JsonModel([
            'users' => $users
        ]);
    }

    public function picturesAction()
    {
        $rows = $this->userModel->getRows([
            'not_deleted'  => true,
            'limit'        => 30,
            'order'        => 'pictures_total desc',
            'has_pictures' => true
        ]);

        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);

        $users = [];
        foreach ($rows as $idx => $user) {
            $brands = [];
            if ($idx < 10) {
                $cacheKey = 'RATING_USER_PICTURES_BRAND_6_' . $user['id'];
                $brands = $this->cache->getItem($cacheKey, $success);
                if (! $success) {
                    $rows = $this->item->getRows([
                        'item_type_id' => Item::BRAND,
                        'descendant' => [
                            'pictures' => [
                                'user'   => $user['id'],
                                'status' => Picture::STATUS_ACCEPTED
                            ]
                        ],
                        'order' => new Sql\Expression('count(distinct p1.id) desc'),
                        'limit' => 3
                    ]);

                    foreach ($rows as $brand) {
                        $brands[] = [
                            'name' => $brand['name'],
                            'url'  => $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand',
                                'brand_catname' => $brand['catname']
                            ]),
                        ];
                    }
                }

                $this->cache->setItem($cacheKey, $brands);
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($user),
                'volume' => (int)$user['pictures_total'],
                'brands' => $brands
            ];
        }

        return new JsonModel([
            'users' => $users
        ]);
    }

    public function likesAction()
    {
        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);

        $users = [];
        foreach ($this->comments->service()->getTopAuthors(30) as $id => $volume) {
            $users[] = [
                'user'   => $this->userHydrator->extract($this->userModel->getRow($id)),
                'volume' => $volume,
                'brands' => []
            ];
        }

        return new JsonModel([
            'users' => $users,
        ]);
    }

    public function pictureLikesAction()
    {
        $this->userHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);

        $users = [];
        $idx = 0;
        foreach ($this->picture->getTopLikes(30) as $ownerId => $volume) {
            $fans = [];
            if ($idx++ < 10) {
                foreach ($this->picture->getTopOwnerFans($ownerId, 2) as $fanId => $fanVolume) {
                    $fans[] = [
                        'user_id' => $fanId,
                        'volume'  => $fanVolume
                    ];
                }
            }

            $users[] = [
                'user'   => $this->userHydrator->extract($this->userModel->getRow($ownerId)),
                'volume' => $volume,
                'brands' => [],
                'fans'   => $fans
            ];
        }

        return new JsonModel([
            'users' => $users
        ]);
    }
}
