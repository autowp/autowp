<?php

use Application\Service\TrafficControl;
use Application\Model\Brand;
use Application\Model\Contact;

class UsersController extends Zend_Controller_Action
{
    public function indexAction()
    {
        return $this->_forward('notfound', 'error');
    }

    public function userAction()
    {
        $users = new Users();

        $identity = trim($this->getParam('identity'));

        if ($identity) {
            $user = $users->fetchRow(array(
                'identity = ?' => $identity,
                'not deleted'
            ));
        } else {
            $user = $users->fetchRow(array(
                'id = ?' => (int)$this->getParam('user_id'),
                'identity is null',
                'not deleted'
            ));
        }

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $pictureAdapter = $pictureTable->getAdapter();
        $this->view->picturesExists = $pictureAdapter->fetchOne(
            $pictureAdapter->select()
                ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
                ->where('owner_id = ?', $user->id)
                ->where('status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
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
                ->order(array('datetime DESC'))
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

        $uaRows = $uaTable->fetchAll(array(
            'user_id = ?' => $user->id
        ));

        $contact = new Contact();

        $currentUser = $this->_helper->user()->get();
        $isMe = $currentUser && ($currentUser->id == $user->id);
        $inContacts = $currentUser && !$isMe && $contact->exists($currentUser->id, $user->id);
        $canBeInContacts = $currentUser && !$currentUser->deleted && !$isMe ;


        $this->view->assign(array(
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canRemovePhoto'  => $canBan,
            'canViewIp'       => $canViewIp,
            'canDeleteUser'   => $canDeleteUser,
            'accounts'        => $uaRows,
            'inContacts'      => $inContacts,
            'canBeInContacts' => $canBeInContacts,
            'contactApiUrl'   => sprintf('/api/contacts/%d', $user->id)
        ));

    }

    public function picturesAction()
    {
        $users = new Users();
        $identity = trim($this->getParam('identity'));
        if ($identity) {
            $user = $users->fetchRow(
                $users->select()
                    ->where('identity = ?', $identity)
                    ->where('not deleted')
            );
        } else {
            $user = $users->fetchRow(
                $users->select()
                    ->where('id = ?', (int)$this->getParam('user_id'))
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
                ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->group('brands.id');
        });

        $brands = [];
        foreach ($rows as $row) {
            $brands[] = [
                'img'           => $row['img'],
                'name'          => $row['name'],
                'picturesCount' => $row['pictures_count'],
                'url'           => $this->_helper->url->url(array(
                    'action'        => 'brandpictures',
                    'brand_catname' => $row['catname']
                ), 'users')
            ];
        }

        $this->view->assign(array(
            'brands' => $brands,
            'user'   => $user
        ));
    }

    public function brandpicturesAction()
    {
        $users = new Users();
        $identity = trim($this->getParam('identity'));
        if ($identity) {
            $user = $users->fetchRow(
                $users->select()
                    ->where('identity = ?', $identity)
                    ->where('not deleted')
            );
        } else {
            $user = $users->fetchRow(
                $users->select()
                    ->where('id = ?', (int)$this->getParam('user_id'))
                    ->where('not deleted')
            );
        }

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $language = $this->_helper->language();

        $brandModel = new Brand();
        $brand = $brandModel->getBrandByCatname($this->getParam('brand_catname'), $language);

        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $pictures = $this->_helper->catalogue()->getPictureTable();
        $select = $pictures->select(true)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
            ->where('pictures.owner_id = ?', $user->id)
            ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
            ->where('brands_cars.brand_id = ?', $brand['id'])
            ->group('pictures.id')
            ->order(array('pictures.add_date DESC', 'pictures.id DESC'));

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->getParam('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 6
        ));

        $this->view->assign(array(
            'user'         => $user,
            'brand'        => $brand,
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ));
    }

}