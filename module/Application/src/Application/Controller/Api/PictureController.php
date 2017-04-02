<?php

namespace Application\Controller\Api;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\DuplicateFinder;
use Application\Form\Moder\Inbox as InboxForm;
use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\CarOfDay;
use Application\Model\DbTable;
use Application\Model\Log;
use Application\Model\PictureItem;
use Application\Model\UserPicture;
use Application\Service\TelegramService;

class PictureController extends AbstractRestfulController
{
    /**
     * @var CarOfDay
     */
    private $carOfDay;
    
    /**
     * @var Form
     */
    private $form;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;
    
    /**
     * @var PictureItem
     */
    private $pictureItem;
    
    /**
     * @var DuplicateFinder
     */
    private $duplicateFinder;
    
    /**
     * @var DbTable\Picture
     */
    private $table;
    
    /**
     * @var UserPicture
     */
    private $userPicture;
    
    /**
     * @var Log
     */
    private $log;
    
    /**
     * @var HostManager
     */
    private $hostManager;

    public function __construct(
        RestHydrator $hydrator,
        PictureItem $pictureItem,
        DuplicateFinder $duplicateFinder,
        Adapter $adapter,
        UserPicture $userPicture,
        Log $log,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        CarOfDay $carOfDay
    ) {
        $this->carOfDay = $carOfDay;
        
        $this->hydrator = $hydrator;
        $this->pictureItem = $pictureItem;
        $this->duplicateFinder = $duplicateFinder;
        $this->userPicture = $userPicture;
        $this->log = $log;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
        
        $this->table = new DbTable\Picture();
    }

    public function randomPictureAction()
    {
        $select = $this->table->select(true)
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
            ->order('rand() desc')
            ->limit(1);

        $pictureRow = $this->table->fetchRow($select);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }

    public function newPictureAction()
    {
        $select = $this->table->select(true)
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
            ->order('accept_datetime desc')
            ->limit(1);

        $pictureRow = $this->table->fetchRow($select);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }

    public function carOfDayPictureAction()
    {
        $itemOfDay = $this->carOfDay->getCurrent();

        $pictureRow = null;

        if ($itemOfDay) {
            $itemTable = new DbTable\Item();

            $carRow = $itemTable->find($itemOfDay['item_id'])->current();
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $select = $this->table->select(true)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $carRow->id)
                        ->limit(1);

                    if ($groupId) {
                        $select
                            ->join(
                                ['mp' => 'perspectives_groups_perspectives'],
                                'picture_item.perspective_id = mp.perspective_id',
                                null
                            )
                            ->where('mp.group_id = ?', $groupId)
                            ->order([
                                'mp.position',
                                'pictures.width DESC', 'pictures.height DESC'
                            ]);
                    } else {
                        $select
                            ->order([
                                'pictures.width DESC', 'pictures.height DESC'
                            ]);
                    }

                    $pictureRow = $this->table->fetchRow($select);
                    if ($pictureRow) {
                        break;
                    }
                }
            }
        }

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }
    
    private function getFilterForm($status)
    {
        $db = $this->table->getAdapter();
    
        $brandMultioptions = [];
    
        $form = new InboxForm(null, [
            'perspectiveOptions' => [
                ''     => 'moder/pictures/filter/perspective/any',
                'null' => 'moder/pictures/filter/perspective/empty'
            ] + $db->fetchPairs(
                $db
                ->select()
                ->from('perspectives', ['id', 'name'])
                ->order('position')
                ),
            'brandOptions'       => [
                '' => 'moder/pictures/filter/brand/any'
            ] + $brandMultioptions,
        ]);
    
        $form->setAttribute('action', $this->url()->fromRoute(null, [
            'action' => 'index'
        ]));
    
        return $form;
    }
    
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $user = $this->user()->get();
    
        $perPage = 24;
    
        $orders = [
            1 => ['sql' => 'pictures.add_date DESC'],
            2 => ['sql' => 'pictures.add_date'],
            3 => ['sql' => ['pictures.width DESC', 'pictures.height DESC']],
            4 => ['sql' => ['pictures.width', 'pictures.height']],
            5 => ['sql' => 'pictures.filesize DESC'],
            6 => ['sql' => 'pictures.filesize'],
            7 => ['sql' => 'comment_topic.messages DESC'],
            8 => ['sql' => 'picture_view.views DESC'],
            9 => ['sql' => 'pdr.day_date DESC'],
            10 => ['sql' => 'df_distance.distance ASC'],
            11 => ['sql' => ['pictures.removing_date DESC', 'pictures.id']],
            12 => ['sql' => 'picture_vote_summary.positive DESC'],
            13 => ['sql' => 'picture_vote_summary.negative DESC'],
        ];
    
        $form = $this->getFilterForm($this->params()->fromQuery('status'));
        $form->setData($this->params()->fromQuery());
        $form->isValid();
    
        $formdata = $form->getData();
    
        $select = $this->table->select(true)
            ->group('pictures.id');
    
        $joinPdr = false;
        $joinLeftComments = false;
        $joinComments = false;
        $pictureItemJoined = false;
        $similarPictureJoined = false;
    
        if (strlen($formdata['status'])) {
            switch ($formdata['status']) {
                case DbTable\Picture::STATUS_INBOX:
                case DbTable\Picture::STATUS_ACCEPTED:
                case DbTable\Picture::STATUS_REMOVING:
                    $select->where('pictures.status = ?', $formdata['status']);
                    break;
                case 'custom1':
                    $select->where('pictures.status not in (?)', [
                        DbTable\Picture::STATUS_REMOVING,
                        DbTable\Picture::STATUS_REMOVED
                    ]);
                    break;
            }
        }
    
        if ($formdata['item_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $formdata['item_id']);
        }
    
        if ($formdata['perspective_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            if ($formdata['perspective_id'] == 'null') {
                $select->where('picture_item.perspective_id IS NULL');
            } else {
                $select->where('picture_item.perspective_id = ?', $formdata['perspective_id']);
            }
        }
    
        if (strlen($formdata['comments'])) {
            if ($formdata['comments'] == '1') {
                $joinComments = true;
                $select->where('comment_topic.messages > 0');
            } elseif ($formdata['comments'] == '0') {
                $joinLeftComments = true;
                $select->where('comment_topic.messages = 0 or comment_topic.messages is null');
            }
        }
    
        if ($formdata['owner_id']) {
            $select->where('pictures.owner_id = ?', $formdata['owner_id']);
        }
    
        if ($formdata['car_type_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            $select
                ->join('item', 'picture_item.item_id = item.id', null)
                ->join('car_types_parents', 'item.car_type_id=car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $formdata['car_type_id']);
        }
    
        if ($formdata['special_name']) {
            $select->where('pictures.name <> "" and pictures.name is not null');
        }
    
        if ($formdata['similar']) {
            $formdata['order'] = 10;
            $select
                ->join('df_distance', 'pictures.id = df_distance.src_picture_id', null)
                ->where('not df_distance.hide');
    
            if (strlen($formdata['status'])) {
                if (! $similarPictureJoined) {
                    $similarPictureJoined = true;
                    $select->join(['similar' => 'pictures'], 'df_distance.dst_picture_id = similar.id', null);
                }
    
                switch ($formdata['status']) {
                    case DbTable\Picture::STATUS_INBOX:
                    case DbTable\Picture::STATUS_ACCEPTED:
                    case DbTable\Picture::STATUS_REMOVING:
                        $select->where('similar.status = ?', $formdata['status']);
                        break;
                    case 'custom1':
                        $select->where('similar.status not in (?)', [
                            DbTable\Picture::STATUS_REMOVING,
                            DbTable\Picture::STATUS_REMOVED
                        ]);
                        break;
                }
            }
        }
    
        if (strlen($formdata['requests'])) {
            switch ($formdata['requests']) {
                case '0':
                    $select
                        ->joinLeft(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.picture_id IS NULL');
                    break;
    
                case '1':
                    $select
                        ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.vote > 0');
                    break;
    
                case '2':
                    $select
                        ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.vote <= 0');
                    break;
    
                case '3':
                    $joinPdr = true;
                    break;
            }
        }
    
        if (strlen($formdata['replace'])) {
            if ($formdata['replace'] == '1') {
                $select->where('pictures.replace_picture_id');
            } elseif ($formdata['replace'] == '0') {
                $select->where('pictures.replace_picture_id is null');
            }
        }
    
        if ($formdata['lost']) {
            $select
            ->joinLeft(
                ['pi_left' => 'picture_item'],
                'pictures.id = pi_left.picture_id',
                null
            )
            ->where('pi_left.item_id IS NULL');
        }
    
        if ($formdata['gps']) {
            $select->where('pictures.point IS NOT NULL');
        }
    
        if ($formdata['order']) {
            $select->order($orders[$formdata['order']]['sql']);
            switch ($formdata['order']) {
                case 7:
                    $joinLeftComments = true;
                    break;
                case 8:
                    $select->joinLeft('picture_view', 'pictures.id = picture_view.picture_id', null);
                    break;
                case 9:
                    $joinPdr = true;
                    break;
                case 12:
                    $select
                        ->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', null)
                        ->where('picture_vote_summary.positive > 0');
                    break;
                case 13:
                    $select
                        ->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', null)
                        ->where('picture_vote_summary.negative > 0');
                    break;
            }
        } else {
            $select->order($orders[1]['sql']);
        }
    
        if ($joinPdr) {
            $select->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null);
        }
    
        if ($joinLeftComments) {
            $expr = 'pictures.id = comment_topic.item_id and ' .
                $this->table->getAdapter()->quoteInto(
                    'comment_topic.type_id = ?',
                    \Application\Comments::PICTURES_TYPE_ID
                );
                $select->joinLeft('comment_topic', $expr, null);
        } elseif ($joinComments) {
            $select
                ->join('comment_topic', 'pictures.id = comment_topic.item_id', null)
                ->where('comment_topic.type_id = ?', \Application\Comments::PICTURES_TYPE_ID);
        }
    
        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
            );
    
        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));
    
        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());
    
        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);
    
        $pictures = [];
        foreach ($this->table->fetchAll($select) as $pictureRow) {
            $pictures[] = $this->hydrator->extract($pictureRow);
        }
    
        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'pictures'  => $pictures,
        ]);
    }
    
    private function canAccept(DbTable\Picture\Row $picture)
    {
        return $picture->canAccept() && $this->user()->isAllowed('picture', 'accept');
    }
    
    public function updateAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $picture = $this->table->find($this->params('id'))->current();
    
        if (! $picture) {
            return $this->notFoundAction();
        }
    
        $request = $this->getRequest();
    
        $data = $this->processBodyContent($request);
    
        if (isset($data['status'])) {
            if ($data['status'] == DbTable\Picture::STATUS_ACCEPTED) {
                $canAccept = $this->canAccept($picture);
    
                if (! $canAccept) {
                    return $this->forbiddenAction();
                }
    
                $user = $this->user()->get();
    
                $previousStatusUserId = $picture->change_status_user_id;
    
                $success = $this->table->accept($picture->id, $user->id, $isFirstTimeAccepted);
                if ($success) {
                    $owner = $picture->findParentRow(User::class, 'Owner');
    
                    if ($owner) {
                        $this->userPicture->refreshPicturesCount($owner['id']);
                    }
    
                    if ($isFirstTimeAccepted) {
                        if ($owner && ($owner->id != $user->id)) {
                            $uri = $this->hostManager->getUriByLanguage($owner->language);
    
                            $message = sprintf(
                                $this->translate('pm/your-picture-accepted-%s', 'default', $owner->language),
                                $this->pic()->url($picture->identity, true, $uri)
                            );
    
                            $this->message->send(null, $owner->id, $message);
                        }
    
                        $this->telegram->notifyPicture($picture->id);
                    }
                }
    
    
                if ($previousStatusUserId != $user->id) {
                    $userTable = new User();
                    foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                        $message = sprintf(
                            'Принята картинка %s',
                            $this->pic()->url($picture->identity, true)
                        );
                        $this->message->send(null, $prevUser->id, $message);
                    }
                }
    
                $this->log(sprintf(
                    'Картинка %s принята',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), $picture);
            }
        }
    
        return $this->getResponse()->setStatusCode(200);
    }
}
