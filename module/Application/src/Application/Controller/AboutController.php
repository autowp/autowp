<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Permissions\Acl\Acl;

use Autowp\Comments;
use Autowp\User\Model\User;

use Application\Model\Item;
use Application\Model\Picture;

class AboutController extends AbstractActionController
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var Comments\CommentsService
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

    public function __construct(
        Acl $acl,
        Comments\CommentsService $comments,
        Picture $picture,
        Item $item,
        User $userModel
    ) {
        $this->acl = $acl;
        $this->comments = $comments;
        $this->picture = $picture;
        $this->item = $item;
        $this->userModel = $userModel;
    }

    public function indexAction()
    {
        $totalUsers = $this->userModel->getCount([]);
        $totalUsers = round($totalUsers, -3);

        $contributors = [];

        $greenUserRoles = [];
        foreach ($this->acl->getRoles() as $role) {
            if ($this->acl->isAllowed($role, 'status', 'be-green')) {
                $greenUserRoles[] = $role;
            }
        }

        if ($greenUserRoles) {
            $userTable = $this->userModel->getTable();

            $greenUsers = $userTable->select([
                'not deleted',
                new Sql\Predicate\In('role', $greenUserRoles),
                '(identity is null or identity <> "autowp")',
                'last_online > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'
            ]);

            foreach ($greenUsers as $greenUser) {
                $contributors[$greenUser['id']] = $greenUser;
            }
        }

        $picturesUsers = $this->userModel->getRows([
            'not_deleted' => true,
            'order'       => 'pictures_total desc',
            'limit'       => 20
        ]);

        foreach ($picturesUsers as $greenUser) {
            $contributors[$greenUser['id']] = $greenUser;
        }

        ksort($contributors, SORT_NUMERIC);

        $totalPictures = $this->picture->getCount([]);
        $totalPictures = round($totalPictures, -4);

        $totalCars = $this->item->getCount([]);
        $totalCars = round($totalCars, -3);

        $totalComments = $this->comments->getTotalMessagesCount();
        $totalComments = round($totalComments, -3);

        return [
            'developer'     => $this->userModel->getRow(1),
            'frTranslator'  => $this->userModel->getRow(3282),
            'zhTranslator'  => $this->userModel->getRow(25155),
            'beTranslator'  => $this->userModel->getRow(15603),
            'contributors'  => $contributors,
            'totalPictures' => $totalPictures,
            'picturesSize'  => $this->picture->getTotalPicturesSize(),
            'totalUsers'    => $totalUsers,
            'totalCars'     => $totalCars,
            'totalComments' => $totalComments
        ];
    }
}
