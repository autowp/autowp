<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
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

use Zend_Db_Expr;

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

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var InputFilter
     */
    private $editInputFilter;

    private $textStorage;
    
    /**
     * @var \Autowp\Comments\CommentsService
     */
    private $comments;

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
        CarOfDay $carOfDay,
        InputFilter $itemInputFilter,
        InputFilter $listInputFilter,
        InputFilter $editInputFilter,
        $textStorage,
        \Autowp\Comments\CommentsService $comments
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
        $this->itemInputFilter = $itemInputFilter;
        $this->listInputFilter = $listInputFilter;
        $this->editInputFilter = $editInputFilter;
        $this->textStorage = $textStorage;
        $this->comments = $comments;

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

    private function getFilterForm()
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

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

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

        $select = $this->table->select(true)
            ->group('pictures.id');

        $joinPdr = false;
        $joinLeftComments = false;
        $joinComments = false;
        $pictureItemJoined = false;
        $similarPictureJoined = false;

        if (strlen($data['status'])) {
            switch ($data['status']) {
                case DbTable\Picture::STATUS_INBOX:
                case DbTable\Picture::STATUS_ACCEPTED:
                case DbTable\Picture::STATUS_REMOVING:
                    $select->where('pictures.status = ?', $data['status']);
                    break;
                case 'custom1':
                    $select->where('pictures.status not in (?)', [
                        DbTable\Picture::STATUS_REMOVING,
                        DbTable\Picture::STATUS_REMOVED
                    ]);
                    break;
            }
        }

        if ($data['item_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $data['item_id']);
        }

        if ($data['perspective_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            if ($data['perspective_id'] == 'null') {
                $select->where('picture_item.perspective_id IS NULL');
            } else {
                $select->where('picture_item.perspective_id = ?', $data['perspective_id']);
            }
        }

        if (strlen($data['comments'])) {
            if ($data['comments'] == '1') {
                $joinComments = true;
                $select->where('comment_topic.messages > 0');
            } elseif ($data['comments'] == '0') {
                $joinLeftComments = true;
                $select->where('comment_topic.messages = 0 or comment_topic.messages is null');
            }
        }

        if ($data['owner_id']) {
            $select->where('pictures.owner_id = ?', $data['owner_id']);
        }

        if ($data['car_type_id']) {
            if (! $pictureItemJoined) {
                $pictureItemJoined = true;
                $select->join('picture_item', 'pictures.id = picture_item.picture_id', null);
            }
            $select
                ->join('item', 'picture_item.item_id = item.id', null)
                ->join('car_types_parents', 'item.car_type_id=car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $data['car_type_id']);
        }

        if ($data['special_name']) {
            $select->where('pictures.name <> "" and pictures.name is not null');
        }

        if ($data['similar']) {
            $data['order'] = 10;
            $select
                ->join('df_distance', 'pictures.id = df_distance.src_picture_id', null)
                ->where('not df_distance.hide');

            if (strlen($data['status'])) {
                if (! $similarPictureJoined) {
                    $similarPictureJoined = true;
                    $select->join(['similar' => 'pictures'], 'df_distance.dst_picture_id = similar.id', null);
                }

                switch ($data['status']) {
                    case DbTable\Picture::STATUS_INBOX:
                    case DbTable\Picture::STATUS_ACCEPTED:
                    case DbTable\Picture::STATUS_REMOVING:
                        $select->where('similar.status = ?', $data['status']);
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

        if (strlen($data['requests'])) {
            switch ($data['requests']) {
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

        if (strlen($data['replace'])) {
            if ($data['replace'] == '1') {
                $select->where('pictures.replace_picture_id');
            } elseif ($data['replace'] == '0') {
                $select->where('pictures.replace_picture_id is null');
            }
        }

        if ($data['lost']) {
            $select
            ->joinLeft(
                ['pi_left' => 'picture_item'],
                'pictures.id = pi_left.picture_id',
                null
            )
            ->where('pi_left.item_id IS NULL');
        }

        if ($data['gps']) {
            $select->where('pictures.point IS NOT NULL');
        }

        if ($data['order']) {
            $select->order($orders[$data['order']]['sql']);
            switch ($data['order']) {
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

        $data['limit'] = $data['limit'] ? $data['limit'] : 1;

        $paginator
            ->setItemCountPerPage($data['limit'])
            ->setCurrentPageNumber($data['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields']
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

    /**
     * @param User\Row $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl(User\Row $user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('users/user', [
            'user_id' => $user->identity ? $user->identity : 'user' . $user->id
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    private function pictureUrl(DbTable\Picture\Row $picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('index', [], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]) . 'ng/moder/pictures/' . $picture->id;
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

        $data = (array)$this->processBodyContent($this->getRequest());
        $validationGroup = array_keys($data); // TODO: intersect with real keys
        $this->editInputFilter->setValidationGroup($validationGroup);
        $this->editInputFilter->setData($data);

        if (! $this->editInputFilter->isValid()) {
            return $this->inputFilterResponse($this->editInputFilter);
        }

        $data = $this->editInputFilter->getValues();
        
        if (array_key_exists('replace_picture_id', $data)) {
            if ($picture->replace_picture_id && ! $data['replace_picture_id']) {
                $replacePicture = $this->table->find($picture->replace_picture_id)->current();
                if (! $replacePicture) {
                    return $this->notFoundAction();
                }
                
                if (! $this->user()->isAllowed('picture', 'move')) {
                    return $this->forbiddenAction();
                }
                
                $picture->replace_picture_id = null;
                $picture->save();
                
                // log
                $this->log(sprintf(
                    'Замена %s на %s отклонена',
                    htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [$picture, $replacePicture]);
            }
        }
        
        if (isset($data['crop'])) {
            if (! $this->user()->isAllowed('picture', 'crop')) {
                return $this->forbiddenAction();
            }
            
            $left = round($data['crop']['left']);
            $top = round($data['crop']['top']);
            $width = round($data['crop']['width']);
            $height = round($data['crop']['height']);
            
            $left = max(0, $left);
            $left = min($picture->width, $left);
            $width = max(1, $width);
            $width = min($picture->width, $width);
            
            $top = max(0, $top);
            $top = min($picture->height, $top);
            $height = max(1, $height);
            $height = min($picture->height, $height);
            
            if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
                $picture->setFromArray([
                    'crop_left'   => $left,
                    'crop_top'    => $top,
                    'crop_width'  => $width,
                    'crop_height' => $height
                ]);
            } else {
                $picture->setFromArray([
                    'crop_left'   => null,
                    'crop_top'    => null,
                    'crop_width'  => null,
                    'crop_height' => null
                ]);
            }
            $picture->save();
            
            $this->imageStorage()->flush([
                'image' => $picture->image_id
            ]);
            
            $this->log(sprintf(
                'Выделение области на картинке %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [$picture]);
        }

        if (isset($data['special_name'])) {
            $picture->name = $data['special_name'];
            $picture->save();
        }

        if (isset($data['copyrights'])) {
            $text = $data['copyrights'];

            $user = $this->user()->get();

            if ($picture->copyrights_text_id) {
                $this->textStorage->setText($picture->copyrights_text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $this->textStorage->createText($text, $user->id);
                $picture->copyrights_text_id = $textId;
                $picture->save();
            }

            $this->log(sprintf(
                'Редактирование текста копирайтов изображения %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), $picture);

            if ($picture->copyrights_text_id) {
                $userIds = $this->textStorage->getTextUserIds($picture->copyrights_text_id);

                $userTable = new User();
                foreach ($userIds as $userId) {
                    if ($userId != $user->id) {
                        foreach ($userTable->find($userId) as $userRow) {
                            $uri = $this->hostManager->getUriByLanguage($userRow->language);

                            $message = sprintf(
                                $this->translate(
                                    'pm/user-%s-edited-picture-copyrights-%s-%s',
                                    'default',
                                    $userRow->language
                                ),
                                $this->userModerUrl($user, true, $uri),
                                $this->pic()->name($picture, $userRow->language),
                                $this->pictureUrl($picture, true, $uri)
                            );

                            $this->message->send(null, $userRow->id, $message);
                        }
                    }
                }
            }
        }

        if (isset($data['status'])) {

            $user = $this->user()->get();
            $previousStatusUserId = $picture->change_status_user_id;

            if ($data['status'] == DbTable\Picture::STATUS_ACCEPTED) {
                $canAccept = $this->canAccept($picture);

                if (! $canAccept) {
                    return $this->forbiddenAction();
                }

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

            if ($data['status'] == DbTable\Picture::STATUS_INBOX) {

                if ($picture['status'] == DbTable\Picture::STATUS_REMOVING) {

                    $canRestore = $this->user()->isAllowed('picture', 'restore');
                    if (! $canRestore) {
                        return $this->forbiddenAction();
                    }

                    $picture->setFromArray([
                        'status'                => DbTable\Picture::STATUS_INBOX,
                        'change_status_user_id' => $user->id
                    ]);
                    $picture->save();

                    $this->log(sprintf(
                        'Картинки `%s` восстановлена из очереди удаления',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), $picture);

                } elseif ($picture['status'] == DbTable\Picture::STATUS_ACCEPTED) {

                    $canUnaccept = $this->user()->isAllowed('picture', 'unaccept');
                    if (!$canUnaccept) {
                        return $this->forbiddenAction();
                    }

                    $picture->setFromArray([
                        'status'                => DbTable\Picture::STATUS_INBOX,
                        'change_status_user_id' => $user->id
                    ]);
                    $picture->save();

                    if ($picture->owner_id) {
                        $this->userPicture->refreshPicturesCount($picture->owner_id);
                    }

                    $this->log(sprintf(
                        'С картинки %s снят статус "принято"',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), $picture);


                    $pictureUrl = $this->pic()->url($picture->identity, true);
                    if ($previousStatusUserId != $user->id) {
                        $userTable = new User();
                        foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                            $message = sprintf(
                                'С картинки %s снят статус "принято"',
                                $pictureUrl
                            );
                            $this->message->send(null, $prevUser->id, $message);
                        }
                    }
                }
            }

            if ($data['status'] == DbTable\Picture::STATUS_REMOVING) {
                $canDelete = $this->pictureCanDelete($picture);
                if (! $canDelete) {
                    return $this->forbiddenAction();
                }

                $user = $this->user()->get();
                $picture->setFromArray([
                    'status'                => DbTable\Picture::STATUS_REMOVING,
                    'removing_date'         => new Zend_Db_Expr('CURDATE()'),
                    'change_status_user_id' => $user->id
                ]);
                $picture->save();

                if ($owner = $picture->findParentRow(User::class, 'Owner')) {
                    if ($owner->id != $user->id) {
                        $uri = $this->hostManager->getUriByLanguage($owner->language);

                        $requests = new DbTable\Picture\ModerVote();
                        $deleteRequests = $requests->fetchAll(
                            $requests->select()
                                ->where('picture_id = ?', $picture->id)
                                ->where('vote = 0')
                        );

                        $reasons = [];
                        if (count($deleteRequests)) {
                            foreach ($deleteRequests as $request) {
                                if ($user = $request->findParentRow(User::class)) {
                                    $reasons[] = $this->userModerUrl($user, true, $uri) . ' : ' . $request->reason;
                                }
                            }
                        }

                        $message = sprintf(
                            $this->translate('pm/your-picture-%s-enqueued-to-remove-%s', 'default', $owner->language),
                            $this->pic()->url($picture->identity, true, $uri),
                            implode("\n", $reasons)
                        );

                        $this->message->send(null, $owner->id, $message);
                    }
                }

                $this->log(sprintf(
                    'Картинка %s поставлена в очередь на удаление',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), $picture);
            }
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields']
        ]);

        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    private function pictureCanDelete($picture)
    {
        $canDelete = false;
        if ($picture->canDelete()) {
            $user = $this->user()->get();
            if ($this->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->user()->isAllowed('picture', 'remove_by_vote')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $db = $this->table->getAdapter();
                    $acceptVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote > 0')
                    );
                    $deleteVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote = 0')
                    );

                    $canDelete = ($deleteVotes > $acceptVotes);
                }
            }
        }

        return $canDelete;
    }

    private function pictureVoteExists($picture, $user)
    {
        $pictureTable = new DbTable\Picture();
        $db = $pictureTable->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from('pictures_moder_votes', new Zend_Db_Expr('COUNT(1)'))
                ->where('picture_id = ?', $picture->id)
                ->where('user_id = ?', $user->id)
        );
    }
    
    public function normalizeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }
        
        $canNormalize = $row['status'] == DbTable\Picture::STATUS_INBOX
                     && $this->user()->isAllowed('picture', 'normalize');
        
        if (! $canNormalize) {
            return $this->forbiddenAction();
        }
        
        if ($row->image_id) {
            $this->imageStorage()->normalize($row->image_id);
        }
        
        $this->log(sprintf(
            'К картинке %s применён normalize',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), $row);
        
        return $this->getResponse()->setStatusCode(200);
    }
    
    public function flopAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }
    
        $canFlop = $row['status'] == DbTable\Picture::STATUS_INBOX
                && $this->user()->isAllowed('picture', 'flop');
    
        if (! $canFlop) {
            return $this->forbiddenAction();
        }
    
        if ($row->image_id) {
            $this->imageStorage()->flop($row->image_id);
        }
    
        $this->log(sprintf(
            'К картинке %s применён flop',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), $row);
    
        return $this->getResponse()->setStatusCode(200);
    }
    
    public function repairAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }
    
        if ($row->image_id) {
            $this->imageStorage()->flush([
                'image' => $row->image_id
            ]);
        }
    
        return $this->getResponse()->setStatusCode(200);
    }
    
    public function correctFileNamesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }
    
        if ($row->image_id) {
            $this->imageStorage()->changeImageName($row->image_id, [
                'pattern' => $row->getFileNamePattern(),
            ]);
        }
    
        return $this->getResponse()->setStatusCode(200);
    }
    
    public function deleteSimilarAction()
    {
        $srcPicture = $this->table->find($this->params('id'))->current();
        $dstPicture = $this->table->find($this->params('similar_picture_id'))->current();
        
        if (! $srcPicture || ! $dstPicture) {
            return $this->notFoundAction();
        }
        
        $this->duplicateFinder->hideSimilar($srcPicture['id'], $dstPicture['id']);
        
        $this->log('Отменёно предупреждение о повторе', [$srcPicture, $dstPicture]);
        
        return $this->getResponse()->setStatusCode(204);
    }
    
    private function canReplace($picture, $replacedPicture)
    {
        $can1 = false;
        switch ($picture->status) {
            case DbTable\Picture::STATUS_ACCEPTED:
                $can1 = true;
                break;
    
            case DbTable\Picture::STATUS_INBOX:
                $can1 = $this->user()->isAllowed('picture', 'accept');
                break;
        }
    
        $can2 = false;
        switch ($replacedPicture->status) {
            case DbTable\Picture::STATUS_ACCEPTED:
                $can2 = $this->user()->isAllowed('picture', 'unaccept')
                     && $this->user()->isAllowed('picture', 'remove_by_vote');
                break;
    
            case DbTable\Picture::STATUS_INBOX:
                $can2 = $this->user()->isAllowed('picture', 'remove_by_vote');
                break;
    
            case DbTable\Picture::STATUS_REMOVING:
            case DbTable\Picture::STATUS_REMOVED:
                $can2 = true;
                break;
        }
    
        return $can1 && $can2 && $this->user()->isAllowed('picture', 'move');
    }
    
    public function acceptReplaceAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $picture = $this->table->find($this->params('id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }
        
        if (! $picture->replace_picture_id) {
            return $this->notFoundAction();
        }
        
        $replacePicture = $this->table->find($picture->replace_picture_id)->current();
        if (! $replacePicture) {
            return $this->notFoundAction();
        }
        
        if (! $this->canReplace($picture, $replacePicture)) {
            return $this->forbiddenAction();
        }
        
        $user = $this->user()->get();
        
        // statuses
        if ($picture->status != DbTable\Picture::STATUS_ACCEPTED) {
            $picture->setFromArray([
                'status'                => DbTable\Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user->id
            ]);
            if (! $picture->accept_datetime) {
                $picture->accept_datetime = new Zend_Db_Expr('NOW()');
            }
            $picture->save();
        
            if ($picture->owner_id) {
                $this->userPicture->refreshPicturesCount($picture->owner_id);
            }
        }
        
        if (! in_array($replacePicture->status, [DbTable\Picture::STATUS_REMOVING, DbTable\Picture::STATUS_REMOVED])) {
            $replacePicture->setFromArray([
                'status'                => DbTable\Picture::STATUS_REMOVING,
                'removing_date'         => new Zend_Db_Expr('now()'),
                'change_status_user_id' => $user->id
            ]);
            $replacePicture->save();
            if ($replacePicture->owner_id) {
                $this->userPicture->refreshPicturesCount($replacePicture->owner_id);
            }
        }
        
        // comments
        $this->comments->moveMessages(
            \Application\Comments::PICTURES_TYPE_ID,
            $replacePicture->id,
            \Application\Comments::PICTURES_TYPE_ID,
            $picture->id
        );
        
        // pms
        $owner = $picture->findParentRow(User::class, 'Owner');
        $replaceOwner = $replacePicture->findParentRow(User::class, 'Owner');
        $recepients = [];
        if ($owner) {
            $recepients[$owner->id] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner->id] = $replaceOwner;
        }
        unset($recepients[$user->id]);
        if ($recepients) {
            foreach ($recepients as $recepient) {
                $uri = $this->hostManager->getUriByLanguage($recepient->language);
        
                $url = $this->pic()->url($picture->identity, true, $uri);
                $replaceUrl = $this->pic()->url($replacePicture->identity, true, $uri);
        
                $moderUrl = $this->url()->fromRoute('users/user', [
                    'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                ], [
                    'force_canonical' => true,
                    'uri'             => $uri
                ]);
        
                $message = sprintf(
                    $this->translate('pm/user-%s-accept-replace-%s-%s', 'default', $recepient->language),
                    $moderUrl,
                    $replaceUrl,
                    $url
                );
        
                $this->message->send(null, $recepient->id, $message);
            }
        }
        
        // log
        $this->log(sprintf(
            'Замена %s на %s',
            htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [$picture, $replacePicture]);
        
        return $this->getResponse()->setStatusCode(200);
    }
}
