<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\User;

use Application\Model\Brand;
use Application\Model\Picture;

class UsersController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Brand
     */
    private $brand;

    private $userModel;

    public function __construct(
        Picture $picture,
        Brand $brand,
        User $userModel
    ) {
        $this->picture = $picture;
        $this->brand = $brand;
        $this->userModel = $userModel;
    }

    private function getUser()
    {
        $identity = $this->params('user_id');

        if (preg_match('|^user([0-9]+)$|isu', $identity, $match)) {
            return $this->userModel->getRow([
                'id'               => (int)$match[1],
                'identity_is_null' => true,
                'not_deleted'      => true
            ]);
        }

        return $this->userModel->getRow([
            'identity'   => $identity,
            'not_deleted' => true
        ]);
    }

    public function picturesAction()
    {
        $user = $this->getUser();

        if (! $user) {
            return $this->notFoundAction();
        }


        // СПИСОК БРЕНДОВ
        $options = [
            'language' => $this->language(),
            'columns'  => [
                'logo_id',
                'pictures_count' => new Sql\Expression('COUNT(distinct pictures.id)')
            ]
        ];

        $rows = $this->brand->getList($options, function (Sql\Select $select) use ($user) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->join('pictures', 'picture_item.picture_id = pictures.id', [])
                ->where([
                    'pictures.owner_id' => $user['id'],
                    'pictures.status'   => Picture::STATUS_ACCEPTED
                ])
                ->group('item.id');
        });

        $brands = [];
        foreach ($rows as $row) {
            $brands[] = [
                'logo_id'       => $row['logo_id'],
                'name'          => $row['name'],
                'catname'       => $row['catname'],
                'picturesCount' => $row['pictures_count'],
                'url'           => $this->url()->fromRoute('users/user/pictures/brand', [
                    'user_id'       => $user['identity'] ? $user['identity'] : 'user' . $user['id'],
                    'brand_catname' => $row['catname']
                ])
            ];
        }

        return [
            'brands' => $brands,
            'user'   => $user
        ];
    }

    public function brandpicturesAction()
    {
        $user = $this->getUser();

        if (! $user) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $brand = $this->brand->getBrandByCatname($this->params('brand_catname'), $language);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $paginator = $this->picture->getPaginator([
            'user'   => $user['id'],
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $brand['id']
            ],
            'order'  => 'add_date_desc'
        ]);

        $paginator
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->params('page'));

        $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 6
        ]);

        return [
            'user'         => $user,
            'brand'        => $brand,
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'urlParams'    => [
                'user_id'       => $user['identity'] ? $user['identity'] : 'user' . $user['id'],
                'brand_catname' => $brand['catname']
            ]
        ];
    }
}
