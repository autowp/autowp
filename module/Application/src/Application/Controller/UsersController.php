<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Service\TrafficControl;
use Application\Model\Brand;
use Application\Model\Contact;

use Application_Service_Specifications;
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

    public function userAction()
    {
        $users = new Users();

        $identity = trim($this->params('identity'));

        if ($identity) {
            $user = $users->fetchRow([
                'identity = ?' => $identity,
                'not deleted'
            ]);
        } else {
            $user = $users->fetchRow([
                'id = ?' => (int)$this->params('user_id'),
                'identity is null',
                'not deleted'
            ]);
        }

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $pictureAdapter = $pictureTable->getAdapter();
        $this->view->picturesExists = $pictureAdapter->fetchOne(
            $pictureAdapter->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->where('owner_id = ?', $user->id)
                ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
        );

        $this->view->current_user = $user;

        $pictures = $this->_helper->catalogue()->getPictureTable();
        $this->view->last_pictures = $pictures->fetchAll(
            $pictures->select()
                ->from('pictures')
                ->where('owner_id = ?', $user->id)
                ->order('id DESC')
                ->limit(12)
        );


        $comments = new Comment_Message();
        $this->view->last_comments = $comments->fetchAll(
            $comments->select()
                ->where('author_id = ?', $user->id)
                ->where('type_id <> ?', Comment_Message::FORUMS_TYPE_ID)
                ->where('not deleted')
                ->order(['datetime DESC'])
                ->limit(15)
        );

        $userRenames = new User_Renames();
        $this->view->renames = $userRenames->fetchAll(
            $userRenames->select(true)
                ->where('user_id = ?', $user->id)
                ->order('date DESC')
        );

        $ban = $canBan = $canViewIp = $canDeleteUser = false;
        if ($this->_helper->user()->logedIn()) {
            if ($this->_helper->user()->get()->id != $user->id) {
                $canBan = $this->_helper->user()->isAllowed('user', 'ban');
                $canDeleteUser = $this->_helper->user()->isAllowed('user', 'delete');
            }
            $canViewIp = $this->_helper->user()->isAllowed('user', 'ip');
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

        $currentUser = $this->_helper->user()->get();
        $isMe = $currentUser && ($currentUser->id == $user->id);
        $inContacts = $currentUser && !$isMe && $contact->exists($currentUser->id, $user->id);
        $canBeInContacts = $currentUser && !$currentUser->deleted && !$isMe ;

        return [
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canRemovePhoto'  => $canBan,
            'canViewIp'       => $canViewIp,
            'canDeleteUser'   => $canDeleteUser,
            'accounts'        => $uaRows,
            'inContacts'      => $inContacts,
            'canBeInContacts' => $canBeInContacts,
            'contactApiUrl'   => sprintf('/api/contacts/%d', $user->id)
        ];
    }

    public function picturesAction()
    {
        $users = new Users();
        $identity = trim($this->params('identity'));
        if ($identity) {
            $user = $users->fetchRow(
                $users->select()
                    ->where('identity = ?', $identity)
                    ->where('not deleted')
            );
        } else {
            $user = $users->fetchRow(
                $users->select()
                    ->where('id = ?', (int)$this->params('user_id'))
                    ->where('not deleted')
            );
        }

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }


        // СПИСОК БРЕНДОВ
        $brandModel = new Brand();

        $options = [
            'language' => $this->_helper->language(),
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
                'url'           => $this->_helper->url->url([
                    'action'        => 'brandpictures',
                    'brand_catname' => $row['catname']
                ], 'users')
            ];
        }

        return [
            'brands' => $brands,
            'user'   => $user
        ];
    }

    public function brandpicturesAction()
    {
        $users = new Users();
        $identity = trim($this->params('identity'));
        if ($identity) {
            $user = $users->fetchRow(
                $users->select()
                    ->where('identity = ?', $identity)
                    ->where('not deleted')
            );
        } else {
            $user = $users->fetchRow(
                $users->select()
                    ->where('id = ?', (int)$this->params('user_id'))
                    ->where('not deleted')
            );
        }

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $language = $this->_helper->language();

        $brandModel = new Brand();
        $brand = $brandModel->getBrandByCatname($this->params('brand_catname'), $language);

        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $pictures = $this->_helper->catalogue()->getPictureTable();
        $select = $pictures->select(true)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
            ->where('pictures.owner_id = ?', $user->id)
            ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
            ->where('brands_cars.brand_id = ?', $brand['id'])
            ->group('pictures.id')
            ->order(['pictures.add_date DESC', 'pictures.id DESC']);

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, [
            'width' => 6
        ]);

        return [
            'user'         => $user,
            'brand'        => $brand,
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
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


    public function ratingAction()
    {
        $userTable = new Users();
        $brandTable = new Brands();

        $select = $userTable->select(true)
            ->where('not deleted')
            ->limit(30);

        $rating = $this->params('rating', 'specs');

        $valueTitle = '';

        switch ($rating) {
            case 'specs':
                $valueTitle = 'users/rating/specs-volume';

                $db = $brandTable->getAdapter();

                $select->where('specs_volume > 0')
                    ->order('specs_volume desc');

                $precisionLimit = 50;

                $service = new Application_Service_Specifications();

                $users = [];
                foreach ($userTable->fetchAll($select) as $idx => $user) {
                    $brands = [];
                    if ($idx < 5) {

                        $cacheKey = 'RATING_USER_BRAND_4_'.$precisionLimit.'_' . $user->id;
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
                break;

            case 'pictures':
                $valueTitle = 'users/rating/pictures';

                $select->where('pictures_added > 0')
                    ->order('pictures_added desc');

                $users = [];
                foreach ($userTable->fetchAll($select) as $idx => $user) {
                    $brands = [];
                    if ($idx < 5) {

                        $cacheKey = 'RATING_USER_PICTURES_BRAND_3_' . $user->id;
                        $brands = $this->cache->getItem($cacheKey, $success);
                        if (!$success) {

                            $select = $brandTable->select(true)
                                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                                ->group('brands_cars.brand_id')
                                ->where('pictures.owner_id = ?', $user->id)
                                ->order('count(1) desc')
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
                break;
        }

        return [
            'users'      => $users,
            'rating'     => $rating,
            'valueTitle' => $valueTitle
        ];
    }
}