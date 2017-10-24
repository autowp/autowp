<?php

namespace Application\Controller;

use DateTime;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator\Paginator;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRename;

use Application\Comments;
use Application\Model\Brand;
use Application\Model\Contact;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\UserAccount;

class UsersController extends AbstractActionController
{
    private $cache;

    /**
     * @var TrafficControl
     */
    private $trafficControl;

    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Contact
     */
    private $contact;

    /**
     * @var UserRename
     */
    private $userRename;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var UserAccount
     */
    private $userAccount;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Brand
     */
    private $brand;

    private $userModel;

    public function __construct(
        $cache,
        TrafficControl $trafficControl,
        Comments $comments,
        Contact $contact,
        UserRename $userRename,
        Perspective $perspective,
        UserAccount $userAccount,
        Picture $picture,
        Item $item,
        Brand $brand,
        User $userModel
    ) {
        $this->cache = $cache;
        $this->trafficControl = $trafficControl;
        $this->comments = $comments;
        $this->contact = $contact;
        $this->userRename = $userRename;
        $this->perspective = $perspective;
        $this->userAccount = $userAccount;
        $this->picture = $picture;
        $this->item = $item;
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

    private function getLastComments($user)
    {
        $paginator = $this->comments->service()->getMessagesPaginator([
            'user'            => $user['id'],
            'exclude_type'    => \Application\Comments::FORUMS_TYPE_ID,
            'exclude_deleted' => true,
            'order'           => 'datetime DESC'
        ]);

        $paginator->setItemCountPerPage(15);

        $lastComments = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $lastComments[] = [
                'url'     => $this->comments->getMessageRowUrl($row),
                'message' => $this->comments->getMessagePreview($row['message'])
            ];
        }

        return $lastComments;
    }

    public function userAction()
    {
        $user = $this->getUser();

        if (! $user) {
            return $this->notFoundAction();
        }

        $picturesExists = $this->picture->getCount([
            'user'   => $user['id'],
            'status' => Picture::STATUS_ACCEPTED
        ]);

        $lastPictureRows = $this->picture->getRows([
            'user'  => $user['id'],
            'limit' => 12,
            'order' => 'add_date_desc'
        ]);

        $names = $this->picture->getNameData($lastPictureRows, [
            'language' => $this->language(),
            'large'    => true
        ]);

        $lastPictures = [];
        foreach ($lastPictureRows as $lastPictureRow) {
            $lastPictures[] = [
                'url'  => $this->pic()->url($lastPictureRow['identity']),
                'name' => $names[$lastPictureRow['id']]
            ];
        }

        $renames = $this->userRename->getRenames($user['id']);

        $canRemovePhoto = $ban = $canBan = $canViewIp = $canDeleteUser = false;
        if ($this->user()->logedIn()) {
            if ($this->user()->get()['id'] != $user['id']) {
                $canBan = $this->user()->isAllowed('user', 'ban');
                $canDeleteUser = $this->user()->isAllowed('user', 'delete');
            }
            $canRemovePhoto = $this->user()->isAllowed('user', 'ban');
            $canViewIp = $this->user()->isAllowed('user', 'ip');
        }

        if ($canBan && $user['last_ip'] !== null) {
            if ($user['last_ip']) {
                $ban = $this->trafficControl->getBanInfo(inet_ntop($user['last_ip']));
                if ($ban) {
                    $ban['user'] = $this->userModel->getRow((int)$ban['user_id']);
                }
            }
        }

        $currentUser = $this->user()->get();
        $isMe = $currentUser && ($currentUser['id'] == $user['id']);
        $inContacts = $currentUser && ! $isMe && $this->contact->exists($currentUser['id'], $user['id']);
        $canBeInContacts = $currentUser && ! $currentUser['deleted'] && ! $isMe ;

        return [
            'currentUser'     => $user,
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canRemovePhoto'  => $canRemovePhoto,
            'canViewIp'       => $canViewIp,
            'canDeleteUser'   => $canDeleteUser,
            'accounts'        => $this->userAccount->getAccounts($user['id']),
            'inContacts'      => $inContacts,
            'canBeInContacts' => $canBeInContacts,
            'contactApiUrl'   => sprintf('/api/contacts/%d', $user['id']),
            'picturesExists'  => $picturesExists,
            'lastPictures'    => $lastPictures,
            'lastComments'    => $this->getLastComments($user),
            'renames'         => $renames
        ];
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
