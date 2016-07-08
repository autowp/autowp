<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Service\TrafficControl;
use Application\Model\Brand;
use Application\Model\Contact;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Brands;
use Comment_Message;
use Picture;
use User_Account;
use User_Renames;
use Users;

use Zend_Db_Expr;

class UsersController extends AbstractActionController
{
    private $cache;

    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    private function getUser()
    {
        $users = new Users();

        $identity = $this->params('user_id');

        if (preg_match('|^user([0-9]+)$|isu', $identity, $match)) {
            return $users->fetchRow([
                'id = ?' => (int)$match[1],
                'identity is null',
                'not deleted'
            ]);
        }
        
        return $users->fetchRow([
            'identity = ?' => $identity,
            'not deleted'
        ]);
    }

    public function userAction()
    {
        $users = new Users();

        $user = $this->getUser();

        if (!$user) {
            return $this->notFoundAction();
        }

        $pictureTable = $this->catalogue()->getPictureTable();
        $pictureAdapter = $pictureTable->getAdapter();
        $picturesExists = $pictureAdapter->fetchOne(
            $pictureAdapter->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->where('owner_id = ?', $user->id)
                ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
        );

        $pictures = $this->catalogue()->getPictureTable();
        $lastPictures = $pictures->fetchAll(
            $pictures->select()
                ->from('pictures')
                ->where('owner_id = ?', $user->id)
                ->order('id DESC')
                ->limit(12)
        );


        $comments = new Comment_Message();
        $lastComments = $comments->fetchAll(
            $comments->select()
                ->where('author_id = ?', $user->id)
                ->where('type_id <> ?', Comment_Message::FORUMS_TYPE_ID)
                ->where('not deleted')
                ->order(['datetime DESC'])
                ->limit(15)
        );

        $userRenames = new User_Renames();
        $renames = $userRenames->fetchAll(
            $userRenames->select(true)
                ->where('user_id = ?', $user->id)
                ->order('date DESC')
        );

        $ban = $canBan = $canViewIp = $canDeleteUser = false;
        if ($this->user()->logedIn()) {
            if ($this->user()->get()->id != $user->id) {
                $canBan = $this->user()->isAllowed('user', 'ban');
                $canDeleteUser = $this->user()->isAllowed('user', 'delete');
            }
            $canViewIp = $this->user()->isAllowed('user', 'ip');
        }

        if ($canBan && $user->last_ip !== null) {
            $service = new TrafficControl();
            $ban = $service->getBanInfo(inet_ntop($user->last_ip));
            if ($ban) {
                $ban['user'] = $users->find($ban['user_id'])->current();
            }
        }

        $uaTable = new User_Account();

        $uaRows = $uaTable->fetchAll([
            'user_id = ?' => $user->id
        ]);

        $contact = new Contact();

        $currentUser = $this->user()->get();
        $isMe = $currentUser && ($currentUser->id == $user->id);
        $inContacts = $currentUser && !$isMe && $contact->exists($currentUser->id, $user->id);
        $canBeInContacts = $currentUser && !$currentUser->deleted && !$isMe ;

        return [
            'currentUser'     => $user,
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canRemovePhoto'  => $canBan,
            'canViewIp'       => $canViewIp,
            'canDeleteUser'   => $canDeleteUser,
            'accounts'        => $uaRows,
            'inContacts'      => $inContacts,
            'canBeInContacts' => $canBeInContacts,
            'contactApiUrl'   => sprintf('/api/contacts/%d', $user->id),
            'picturesExists'  => $picturesExists,
            'last_pictures'   => $lastPictures,
            'last_comments'   => $lastComments,
            'renames'         => $renames
        ];
    }

    public function picturesAction()
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->notFoundAction();
        }


        // СПИСОК БРЕНДОВ
        $brandModel = new Brand();

        $options = [
            'language' => $this->language(),
            'columns'  => [
                'img',
                'pictures_count' => new Zend_Db_Expr('COUNT(distinct pictures.id)')
            ]
        ];

        $rows = $brandModel->getList($options, function($select) use ($user) {
            $select
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.owner_id = ?', $user->id)
                ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->group('brands.id');
        });

        $brands = [];
        foreach ($rows as $row) {
            $brands[] = [
                'img'           => $row['img'],
                'name'          => $row['name'],
                'picturesCount' => $row['pictures_count'],
                'url'           => $this->url()->fromRoute('users/user/pictures/brand', [
                    'user_id'       => $user->identity ? $user->identity : 'user' . $user->id,
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

        if (!$user) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $brandModel = new Brand();
        $brand = $brandModel->getBrandByCatname($this->params('brand_catname'), $language);

        if (!$brand) {
            return $this->notFoundAction();
        }

        $pictures = $this->catalogue()->getPictureTable();
        $select = $pictures->select(true)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
            ->where('pictures.owner_id = ?', $user->id)
            ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
            ->where('brands_cars.brand_id = ?', $brand['id'])
            ->group('pictures.id')
            ->order(['pictures.add_date DESC', 'pictures.id DESC']);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 6
        ]);

        return [
            'user'         => $user,
            'brand'        => $brand,
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'urlParams'    => [
                'user_id'       => $user->identity ? $user->identity : 'user' . $user->id,
                'brand_catname' => $brand['catname']
            ]
        ];
    }

    public function onlineAction()
    {
        $userTable = new Users();

        $viewModel = new ViewModel([
            'users' => $userTable->fetchAll(
                $userTable->select(true)
                    ->join('session', 'users.id = session.user_id', null)
                    ->where('session.modified >= ?', time() - 5*60)
                    ->group('users.id')
            )
        ]);
        $viewModel->setTerminal($this->getRequest()->isXmlHttpRequest());

        return $viewModel;
    }

    private function specsRating()
    {
        $userTable = new Users();
        $brandTable = new Brands();
        
        $select = $userTable->select(true)
            ->where('not deleted')
            ->limit(30)
            ->where('specs_volume > 0')
            ->order('specs_volume desc');
        
        $valueTitle = 'users/rating/specs-volume';
        
        $db = $brandTable->getAdapter();
        
        $precisionLimit = 50;
        
        $users = [];
        foreach ($userTable->fetchAll($select) as $idx => $user) {
            $brands = [];
            if ($idx < 5) {
        
                $cacheKey = 'RATING_USER_BRAND_5_'.$precisionLimit.'_' . $user->id;
                $brands = $this->cache->getItem($cacheKey, $success);
                if (!$success) {
        
                    $carSelect = $db->select()
                        ->from('brands_cars', ['brand_id', 'count(1)'])
                        ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                        ->join('attrs_user_values', 'car_parent_cache.car_id = attrs_user_values.item_id', null)
                        ->where('attrs_user_values.item_type_id = 1')
                        ->where('attrs_user_values.user_id = ?', $user->id)
                        ->group('brands_cars.brand_id')
                        ->order('count(1) desc')
                        ->limit($precisionLimit);
        
                    $engineSelect = $db->select()
                        ->join('brand_engine', ['brand_id', 'count(1)'])
                        ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                        ->join('attrs_user_values', 'engine_parent_cache.engine_id = attrs_user_values.item_id', null)
                        ->where('attrs_user_values.item_type_id = 3')
                        ->where('attrs_user_values.user_id = ?', $user->id)
                        ->group('brand_engine.brand_id')
                        ->order('count(1) desc')
                        ->limit($precisionLimit);
        
                    $data = [];
                    foreach ([$carSelect, $engineSelect] as $select) {
                        $pairs = $db->fetchPairs($select);
                        foreach ($pairs as $brandId => $value) {
                            if (!isset($data[$brandId])) {
                                $data[$brandId] = $value;
                            } else {
                                $data[$brandId] += $value;
                            }
                        }
                    }
        
                    arsort($data, SORT_NUMERIC);
                    $data = array_slice($data, 0, 3, true);
        
                    foreach ($data as $brandId => $value) {
                        $row = $brandTable->find($brandId)->current();
                        $brands[] = [
                            'name' => $row->caption,
                            'url'  => $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand',
                                'brand_catname' => $row->folder
                            ]),
                            'value' => $value
                        ];
                    }
                }
        
                $this->cache->setItem($cacheKey, $brands);
            }
        
            $users[] = [
                'row'    => $user,
                'volume' => $user->specs_volume,
                'brands' => $brands,
                'weight' => $user->specs_weight
            ];
        }
        
        return [
            'users'      => $users,
            'rating'     => 'specs',
            'valueTitle' => $valueTitle
        ];
    }
    
    private function picturesRating()
    {
        $userTable = new Users();
        $brandTable = new Brands();
        
        $select = $userTable->select(true)
            ->where('not deleted')
            ->limit(30)
            ->where('pictures_added > 0')
            ->order('pictures_added desc');
        
        $valueTitle = 'users/rating/pictures';
        
        $users = [];
        foreach ($userTable->fetchAll($select) as $idx => $user) {
            $brands = [];
            if ($idx < 10) {
        
                $cacheKey = 'RATING_USER_PICTURES_BRAND_6_' . $user->id;
                $brands = $this->cache->getItem($cacheKey, $success);
                if (!$success) {
        
                    $select = $brandTable->select(true)
                        ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                        ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                        ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->group('brands_cars.brand_id')
                        ->where('pictures.owner_id = ?', $user->id)
                        ->order('count(distinct pictures.id) desc')
                        ->limit(3);
        
                    foreach ($brandTable->fetchAll($select) as $brand) {
                        $brands[] = [
                            'name' => $brand->caption,
                            'url'  => $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand',
                                'brand_catname' => $brand->folder
                            ]),
                        ];
                    }
                }
        
                $this->cache->setItem($cacheKey, $brands);
            }
        
            $users[] = [
                'row'    => $user,
                'volume' => $user->pictures_added,
                'brands' => $brands
            ];
        }
        
        return [
            'users'      => $users,
            'rating'     => 'pictures',
            'valueTitle' => $valueTitle
        ];
    }

    public function ratingAction()
    {
        $rating = $this->params('rating', 'specs');

        switch ($rating) {
            case 'specs':
                return $this->specsRating();
                break;

            case 'pictures':
                return $this->picturesRating();
                break;
        }

        return $this->notFoundAction();
    }
}