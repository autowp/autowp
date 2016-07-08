<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Zend\Permissions\Acl\Acl;

use Cars;
use Comment_Message;
use Picture;
use Users;

class AboutController extends AbstractActionController
{
    /**
     * @var Acl
     */
    private $acl;

    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    public function indexAction()
    {
        $userTable = new Users();
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

        $greenUsers = $userTable->fetchAll([
            'not deleted',
            'role in (?)' => $greenUserRoles,
            'identity is null or identity <> "autowp"',
            'last_online > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'
        ]);

        foreach ($greenUsers as $greenUser) {
            $contributors[$greenUser->id] = $greenUser;
        }

        $picturesUsers = $userTable->fetchAll(array(
            'not deleted',
        ), 'pictures_added desc', 20);

        foreach ($picturesUsers as $greenUser) {
            $contributors[$greenUser->id] = $greenUser;
        }

        ksort($contributors, SORT_NUMERIC);

        $pictureTable = new Picture();
        $pictureTableAdapter = $pictureTable->getAdapter();
        $pictureTableName = $pictureTable->info('name');

        $totalPictures = $pictureTableAdapter->fetchOne(
            $pictureTableAdapter->select()
                ->from($pictureTableName, 'count(1)')
        );
        $totalPictures = round($totalPictures, -4);

        $carTable = new Cars();
        $carTableAdapter = $carTable->getAdapter();
        $totalCars = $carTableAdapter->fetchOne(
            $carTableAdapter->select()
                ->from($carTable->info('name'), 'count(1)')
        );
        $totalCars = round($totalCars, -3);

        $commentMessageTable = new Comment_Message();
        $commentMessageTableAdapter = $commentMessageTable->getAdapter();
        $totalComments = $commentMessageTableAdapter->fetchOne(
            $commentMessageTableAdapter->select()
                ->from($commentMessageTable->info('name'), 'count(1)')
        );
        $totalComments = round($totalComments, -3);

        return [
            'developer'     => $userTable->find(1)->current(),
            'contributors'  => $contributors,
            'totalPictures' => $totalPictures,
            'picturesSize'  => $pictureTableAdapter->fetchOne(
                $pictureTableAdapter->select()
                    ->from($pictureTableName, 'sum(filesize)')
            ),
            'totalUsers'    => $totalUsers,
            'totalCars'     => $totalCars,
            'totalComments' =>  $totalComments
        ];
    }
}