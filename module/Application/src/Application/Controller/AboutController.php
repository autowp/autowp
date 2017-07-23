<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Permissions\Acl\Acl;

use Autowp\Comments;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;

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
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        Acl $acl,
        Comments\CommentsService $comments,
        DbTable\Picture $pictureTable
    ) {
        $this->acl = $acl;
        $this->comments = $comments;
        $this->pictureTable = $pictureTable;
    }

    public function indexAction()
    {
        $userTable = new User();
        $userTableAdapter = $userTable->getAdapter();
        $totalUsers = $userTableAdapter->fetchOne(
            $userTableAdapter->select()
                ->from($userTable->info('name'), 'count(1)')
        );
        $totalUsers = round($totalUsers, -3);

        $contributors = [];

        $greenUserRoles = [];
        foreach ($this->acl->getRoles() as $role) {
            if ($this->acl->isAllowed($role, 'status', 'be-green')) {
                $greenUserRoles[] = $role;
            }
        }

        if ($greenUserRoles) {
            $greenUsers = $userTable->fetchAll([
                'not deleted',
                'role in (?)' => $greenUserRoles,
                'identity is null or identity <> "autowp"',
                'last_online > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'
            ]);

            foreach ($greenUsers as $greenUser) {
                $contributors[$greenUser->id] = $greenUser;
            }
        }

        $picturesUsers = $userTable->fetchAll([
            'not deleted',
        ], 'pictures_total desc', 20);

        foreach ($picturesUsers as $greenUser) {
            $contributors[$greenUser->id] = $greenUser;
        }

        ksort($contributors, SORT_NUMERIC);

        $pictureTableAdapter = $this->pictureTable->getAdapter();
        $pictureTableName = $this->pictureTable->info('name');

        $totalPictures = $pictureTableAdapter->fetchOne(
            $pictureTableAdapter->select()
                ->from($pictureTableName, 'count(1)')
        );
        $totalPictures = round($totalPictures, -4);

        $itemTable = new DbTable\Item();
        $itemTableAdapter = $itemTable->getAdapter();
        $totalCars = $itemTableAdapter->fetchOne(
            $itemTableAdapter->select()
                ->from($itemTable->info('name'), 'count(1)')
        );
        $totalCars = round($totalCars, -3);

        $totalComments = $this->comments->getTotalMessagesCount();
        $totalComments = round($totalComments, -3);

        return [
            'developer'     => $userTable->find(1)->current(),
            'frTranslator'  => $userTable->find(3282)->current(),
            'zhTranslator'  => $userTable->find(25155)->current(),
            'contributors'  => $contributors,
            'totalPictures' => $totalPictures,
            'picturesSize'  => $pictureTableAdapter->fetchOne(
                $pictureTableAdapter->select()
                    ->from($pictureTableName, 'sum(filesize)')
            ),
            'totalUsers'    => $totalUsers,
            'totalCars'     => $totalCars,
            'totalComments' => $totalComments
        ];
    }
}
