<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\InputFilter\InputFilter;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\DuplicateFinder;
use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\CarOfDay;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Application\Service\TelegramService;

class PictureController extends AbstractRestfulController
{
    /**
     * @var CarOfDay
     */
    private $carOfDay;

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

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        RestHydrator $hydrator,
        PictureItem $pictureItem,
        DuplicateFinder $duplicateFinder,
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
        \Autowp\Comments\CommentsService $comments,
        PictureModerVote $pictureModerVote,
        Item $item,
        Picture $picture
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
        $this->pictureModerVote = $pictureModerVote;
        $this->picture = $picture;
        $this->item = $item;
    }

    public function randomPictureAction()
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'random'
        ]);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
    }


    public function newPictureAction()
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'accept_datetime_desc'
        ]);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
    }


    public function carOfDayPictureAction()
    {
        $itemOfDay = $this->carOfDay->getCurrent();

        $pictureRow = null;

        if ($itemOfDay) {
            $carRow = $this->item->getRow(['id' => (int)$itemOfDay['item_id']]);
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $filter = [
                        'status' => Picture::STATUS_ACCEPTED,
                        'item'   => [
                            'ancestor_or_self' => $carRow['id']
                        ],
                        'order'  => 'resolution_desc'
                    ];

                    if ($groupId) {
                        $filter['item']['perspective'] = [
                            'group' => $groupId
                        ];
                        $filter['order'] = 'perspective_group';
                    }

                    $pictureRow = $this->picture->getRow($filter);
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
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
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

        $filter = [];

        if (strlen($data['status'])) {
            switch ($data['status']) {
                case Picture::STATUS_INBOX:
                case Picture::STATUS_ACCEPTED:
                case Picture::STATUS_REMOVING:
                    $filter['status'] = $data['status'];
                    break;
                case 'custom1':
                    $filter['status'] = [
                        Picture::STATUS_INBOX,
                        Picture::STATUS_ACCEPTED
                    ];
                    break;
            }
        }

        if ($data['exact_item_id']) {
            $filter['item']['id'] = $data['exact_item_id'];
        }

        if ($data['item_id']) {
            $filter['item']['ancestor_or_self'] = $data['item_id'];
        }

        if ($data['perspective_id']) {
            if ($data['perspective_id'] == 'null') {
                $filter['item']['perspective_is_null'] = true;
            } else {
                $filter['item']['perspective'] = $data['perspective_id'];
            }
        }

        if (strlen($data['comments'])) {
            if ($data['comments'] == '1') {
                $filter['has_comments'] = true;
            } elseif ($data['comments'] == '0') {
                $filter['has_comments'] = false;
            }
        }

        if ($data['owner_id']) {
            $filter['user'] = $data['owner_id'];
        }

        if ($data['car_type_id']) {
            $filter['item']['vehicle_type'] = $data['car_type_id'];
        }

        if ($data['special_name']) {
            $filter['has_special_name'] = true;
        }

        if ($data['similar']) {
            $filter['has_similar'] = true;
            $data['order'] = 10;
        }

        if (strlen($data['requests'])) {
            switch ($data['requests']) {
                case '0':
                    $filter['has_moder_votes'] = false;
                    break;

                case '1':
                    $filter['has_accept_votes'] = true;
                    break;

                case '2':
                    $filter['has_delete_votes'] = true;
                    break;

                case '3':
                    $filter['has_moder_votes'] = true;
                    break;
            }
        }

        if (strlen($data['replace'])) {
            if ($data['replace'] == '1') {
                $filter['is_replace'] = true;
            } elseif ($data['replace'] == '0') {
                $filter['is_replace'] = false;
            }
        }

        if ($data['lost']) {
            $filter['is_lost'] = true;
        }

        if ($data['gps']) {
            $filter['has_point'] = true;
        }

        $orders = [
            1 => 'add_date_desc',
            2 => 'add_date_asc',
            3 => 'resolution_desc',
            4 => 'resolution_asc',
            5 => 'filesize_desc',
            6 => 'filesize_asc',
            7 => 'comments',
            8 => 'views',
            9 => 'moder_votes',
            10 => 'similarity',
            11 => 'removing_date',
            12 => 'likes',
            13 => 'dislikes',
            14 => 'status'
        ];

        if ($data['order']) {
            $filter['order'] = $orders[$data['order']];
        } else {
            $filter['order'] = $orders[1];
        }

        $paginator = $this->picture->getPaginator($filter);

        $data['limit'] = $data['limit'] ? $data['limit'] : 1;

        $paginator
            ->setItemCountPerPage($data['limit'])
            ->setCurrentPageNumber($data['page']);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields']
        ]);

        $pictures = [];
        foreach ($paginator->getCurrentItems() as $pictureRow) {
            $pictures[] = $this->hydrator->extract($pictureRow);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'pictures'  => $pictures,
        ]);
    }

    private function canAccept($picture)
    {
        return $this->picture->canAccept($picture)
            && $this->user()->isAllowed('picture', 'accept');
    }

    /**
     * @param \Zend_Db_Table_Row_Abstract $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl(\Zend_Db_Table_Row_Abstract $user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('users/user', [
            'user_id' => $user['identity'] ? $user['identity'] : 'user' . $user['id']
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    private function pictureUrl($picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('index', [], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]) . 'ng/moder/pictures/' . $picture['id'];
    }

    public function updateAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$this->params('id')]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        $userTable = new User();

        $data = (array)$this->processBodyContent($this->getRequest());
        $validationGroup = array_keys($data); // TODO: intersect with real keys
        $this->editInputFilter->setValidationGroup($validationGroup);
        $this->editInputFilter->setData($data);

        if (! $this->editInputFilter->isValid()) {
            return $this->inputFilterResponse($this->editInputFilter);
        }

        $data = $this->editInputFilter->getValues();

        $set = [];

        if (array_key_exists('replace_picture_id', $data)) {
            if ($picture['replace_picture_id'] && ! $data['replace_picture_id']) {
                $replacePicture = $this->picture->getRow(['id' => (int)$picture['replace_picture_id']]);
                if (! $replacePicture) {
                    return $this->notFoundAction();
                }

                if (! $this->user()->isAllowed('picture', 'move')) {
                    return $this->forbiddenAction();
                }

                $set['replace_picture_id'] = null;

                // log
                $this->log(sprintf(
                    'Замена %s на %s отклонена',
                    htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [
                    'pictures' => [$picture['id'], $replacePicture['id']]
                ]);
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
            $left = min($picture['width'], $left);
            $width = max(1, $width);
            $width = min($picture['width'], $width);

            $top = max(0, $top);
            $top = min($picture['height'], $top);
            $height = max(1, $height);
            $height = min($picture['height'], $height);

            if ($left > 0 || $top > 0 || $width < $picture['width'] || $height < $picture['height']) {
                $set = array_replace($set, [
                    'crop_left'   => $left,
                    'crop_top'    => $top,
                    'crop_width'  => $width,
                    'crop_height' => $height
                ]);
            } else {
                $set = array_replace($set, [
                    'crop_left'   => null,
                    'crop_top'    => null,
                    'crop_width'  => null,
                    'crop_height' => null
                ]);
            }

            $this->imageStorage()->flush([
                'image' => $picture['image_id']
            ]);

            $this->log(sprintf(
                'Выделение области на картинке %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id']
            ]);
        }

        if (isset($data['special_name'])) {
            $set['name'] = $data['special_name'];
        }

        if (isset($data['copyrights'])) {
            $text = $data['copyrights'];

            $user = $this->user()->get();

            if ($picture['copyrights_text_id']) {
                $this->textStorage->setText($picture['copyrights_text_id'], $text, $user['id']);
            } elseif ($text) {
                $textId = $this->textStorage->createText($text, $user['id']);
                $set['copyrights_text_id'] = $textId;
            }

            $this->log(sprintf(
                'Редактирование текста копирайтов изображения %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id']
            ]);

            if ($picture['copyrights_text_id']) {
                $userIds = $this->textStorage->getTextUserIds($picture['copyrights_text_id']);

                foreach ($userIds as $userId) {
                    if ($userId != $user['id']) {
                        foreach ($userTable->find($userId) as $userRow) {
                            $uri = $this->hostManager->getUriByLanguage($userRow['language']);

                            $message = sprintf(
                                $this->translate(
                                    'pm/user-%s-edited-picture-copyrights-%s-%s',
                                    'default',
                                    $userRow['language']
                                ),
                                $this->userModerUrl($user, true, $uri),
                                $this->pic()->name($picture, $userRow['language']),
                                $this->pictureUrl($picture, true, $uri)
                            );

                            $this->message->send(null, $userRow['id'], $message);
                        }
                    }
                }
            }
        }

        if (isset($data['status'])) {
            $user = $this->user()->get();
            $previousStatusUserId = $picture['change_status_user_id'];

            if ($data['status'] == Picture::STATUS_ACCEPTED) {
                $canAccept = $this->canAccept($picture);

                if (! $canAccept) {
                    return $this->forbiddenAction();
                }

                $success = $this->picture->accept($picture['id'], $user['id'], $isFirstTimeAccepted);
                if ($success) {
                    $userTable = new User();
                    $owner = $userTable->find((int)$picture['owner_id'])->current();

                    if ($owner) {
                        $this->userPicture->refreshPicturesCount($owner['id']);
                    }

                    if ($isFirstTimeAccepted) {
                        if ($owner && ($owner['id'] != $user['id'])) {
                            $uri = $this->hostManager->getUriByLanguage($owner['language']);

                            $message = sprintf(
                                $this->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                                $this->pic()->url($picture['identity'], true, $uri)
                            );

                            $this->message->send(null, $owner['id'], $message);
                        }

                        $this->telegram->notifyPicture($picture['id']);
                    }
                }


                if ($previousStatusUserId != $user['id']) {
                    foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                        $message = sprintf(
                            'Принята картинка %s',
                            $this->pic()->url($picture['identity'], true)
                        );
                        $this->message->send(null, $prevUser['id'], $message);
                    }
                }

                $this->log(sprintf(
                    'Картинка %s принята',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [
                    'pictures' => $picture['id']
                ]);
            }

            if ($data['status'] == Picture::STATUS_INBOX) {
                if ($picture['status'] == Picture::STATUS_REMOVING) {
                    $canRestore = $this->user()->isAllowed('picture', 'restore');
                    if (! $canRestore) {
                        return $this->forbiddenAction();
                    }

                    $set = array_replace($set, [
                        'status'                => Picture::STATUS_INBOX,
                        'change_status_user_id' => $user['id']
                    ]);

                    $this->log(sprintf(
                        'Картинки `%s` восстановлена из очереди удаления',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id']
                    ]);
                } elseif ($picture['status'] == Picture::STATUS_ACCEPTED) {
                    $canUnaccept = $this->user()->isAllowed('picture', 'unaccept');
                    if (! $canUnaccept) {
                        return $this->forbiddenAction();
                    }

                    $this->picture->getTable()->update([
                        'status'                => Picture::STATUS_INBOX,
                        'change_status_user_id' => $user['id']
                    ], [
                        'id' => $picture['id']
                    ]);

                    if ($picture['owner_id']) {
                        $this->userPicture->refreshPicturesCount($picture['owner_id']);
                    }

                    $this->log(sprintf(
                        'С картинки %s снят статус "принято"',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id']
                    ]);


                    $pictureUrl = $this->pic()->url($picture['identity'], true);
                    if ($previousStatusUserId != $user['id']) {
                        foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                            $message = sprintf(
                                'С картинки %s снят статус "принято"',
                                $pictureUrl
                            );
                            $this->message->send(null, $prevUser['id'], $message);
                        }
                    }
                }
            }

            if ($data['status'] == Picture::STATUS_REMOVING) {
                $canDelete = $this->pictureCanDelete($picture);
                if (! $canDelete) {
                    return $this->forbiddenAction();
                }

                $user = $this->user()->get();
                $set = array_replace($set, [
                    'status'                => Picture::STATUS_REMOVING,
                    'removing_date'         => new Sql\Expression('CURDATE()'),
                    'change_status_user_id' => $user['id']
                ]);

                $userTable = new User();
                $owner = $userTable->find((int)$picture['owner_id'])->current();
                if ($owner && $owner['id'] != $user['id']) {
                    $uri = $this->hostManager->getUriByLanguage($owner['language']);

                    $deleteRequests = $this->pictureModerVote->getNegativeVotes($picture['id']);

                    $reasons = [];
                    foreach ($deleteRequests as $request) {
                        $user = $userTable->find($request['user_id'])->current();
                        if ($user) {
                            $reasons[] = $this->userModerUrl($user, true, $uri) . ' : ' . $request['reason'];
                        }
                    }

                    $message = sprintf(
                        $this->translate('pm/your-picture-%s-enqueued-to-remove-%s', 'default', $owner['language']),
                        $this->pic()->url($picture['identity'], true, $uri),
                        implode("\n", $reasons)
                    );

                    $this->message->send(null, $owner['id'], $message);
                }

                $this->log(sprintf(
                    'Картинка %s поставлена в очередь на удаление',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [
                    'pictures' => $picture['id']
                ]);
            }
        }
        if ($set) {
            $this->picture->getTable()->update($set, [
                'id' => $picture['id']
            ]);
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

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    private function pictureCanDelete($picture)
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $canDelete = false;
        $user = $this->user()->get();
        if ($this->user()->isAllowed('picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $canDelete = true;
            }
        } elseif ($this->user()->isAllowed('picture', 'remove_by_vote')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
                $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

                $canDelete = ($deleteVotes > $acceptVotes);
            }
        }

        return $canDelete;
    }

    public function normalizeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        $canNormalize = $row['status'] == Picture::STATUS_INBOX
                     && $this->user()->isAllowed('picture', 'normalize');

        if (! $canNormalize) {
            return $this->forbiddenAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->normalize($row['image_id']);
        }

        $this->log(sprintf(
            'К картинке %s применён normalize',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), [
            'pictures' => $row['id']
        ]);

        return $this->getResponse()->setStatusCode(200);
    }

    public function flopAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        $canFlop = $row['status'] == Picture::STATUS_INBOX
                && $this->user()->isAllowed('picture', 'flop');

        if (! $canFlop) {
            return $this->forbiddenAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->flop($row['image_id']);
        }

        $this->log(sprintf(
            'К картинке %s применён flop',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), [
            'pictures' => $row['id']
        ]);

        return $this->getResponse()->setStatusCode(200);
    }

    public function repairAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->flush([
                'image' => $row['image_id']
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function correctFileNamesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->changeImageName($row['image_id'], [
                'pattern' => $this->picture->getFileNamePattern($row)
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function deleteSimilarAction()
    {
        $srcPicture = $this->picture->getRow(['id' => (int)$this->params('id')]);
        $dstPicture = $this->picture->getRow(['id' => (int)$this->params('similar_picture_id')]);

        if (! $srcPicture || ! $dstPicture) {
            return $this->notFoundAction();
        }

        $this->duplicateFinder->hideSimilar($srcPicture['id'], $dstPicture['id']);

        $this->log('Отменёно предупреждение о повторе', [
            'pictures' => [$srcPicture['id'], $dstPicture['id']]
        ]);

        return $this->getResponse()->setStatusCode(204);
    }

    private function canReplace($picture, $replacedPicture)
    {
        $can1 = false;
        switch ($picture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can1 = true;
                break;

            case Picture::STATUS_INBOX:
                $can1 = $this->user()->isAllowed('picture', 'accept');
                break;
        }

        $can2 = false;
        switch ($replacedPicture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can2 = $this->user()->isAllowed('picture', 'unaccept')
                     && $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_INBOX:
                $can2 = $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_REMOVING:
            case Picture::STATUS_REMOVED:
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

        $picture = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $picture['replace_picture_id']) {
            return $this->notFoundAction();
        }

        $replacePicture = $this->picture->getRow(['id' => (int)$picture['replace_picture_id']]);
        if (! $replacePicture) {
            return $this->notFoundAction();
        }

        if (! $this->canReplace($picture, $replacePicture)) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        // statuses
        if ($picture['status'] != Picture::STATUS_ACCEPTED) {
            $set = [
                'status'                => Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user['id']
            ];
            if (! $picture['accept_datetime']) {
                $set['accept_datetime'] = new Sql\Expression('NOW()');
            }

            $this->picture->getTable()->update($set, [
                'id' => $picture['id']
            ]);

            if ($picture['owner_id']) {
                $this->userPicture->refreshPicturesCount($picture['owner_id']);
            }
        }

        if (! in_array($replacePicture['status'], [Picture::STATUS_REMOVING, Picture::STATUS_REMOVED])) {
            $this->picture->getTable()->update([
                'status'                => Picture::STATUS_REMOVING,
                'removing_date'         => new Sql\Expression('now()'),
                'change_status_user_id' => $user['id']
            ], [
                'id' => $picture['id']
            ]);
            if ($replacePicture['owner_id']) {
                $this->userPicture->refreshPicturesCount($replacePicture['owner_id']);
            }
        }

        // comments
        $this->comments->moveMessages(
            \Application\Comments::PICTURES_TYPE_ID,
            $replacePicture['id'],
            \Application\Comments::PICTURES_TYPE_ID,
            $picture['id']
        );

        // pms
        $userTable = new User();
        $owner = $userTable->find($picture['owner_id'])->current();
        $replaceOwner = $userTable->find($replacePicture['owner_id'])->current();
        $recepients = [];
        if ($owner) {
            $recepients[$owner['id']] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner['id']] = $replaceOwner;
        }
        unset($recepients[$user['id']]);
        if ($recepients) {
            foreach ($recepients as $recepient) {
                $uri = $this->hostManager->getUriByLanguage($recepient['language']);

                $url = $this->pic()->url($picture['identity'], true, $uri);
                $replaceUrl = $this->pic()->url($replacePicture['identity'], true, $uri);

                $moderUrl = $this->url()->fromRoute('users/user', [
                    'user_id' => $user['identity'] ? $user['identity'] : 'user' . $user['id']
                ], [
                    'force_canonical' => true,
                    'uri'             => $uri
                ]);

                $message = sprintf(
                    $this->translate('pm/user-%s-accept-replace-%s-%s', 'default', $recepient['language']),
                    $moderUrl,
                    $replaceUrl,
                    $url
                );

                $this->message->send(null, $recepient['id'], $message);
            }
        }

        // log
        $this->log(sprintf(
            'Замена %s на %s',
            htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => [$picture['id'], $replacePicture['id']]
        ]);

        return $this->getResponse()->setStatusCode(200);
    }
}
