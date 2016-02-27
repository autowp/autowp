<?php

class AboutController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $userTable = new Users();
        $userTableAdapter = $userTable->getAdapter();
        $totalUsers = $userTableAdapter->fetchOne(
            $userTableAdapter->select()
            ->from($userTable->info('name'), 'count(1)')
        );
        $totalUsers = round($totalUsers, -3);

        $contributors = array();

        $greenUserRoles = array();
        $acl = $this->getInvokeArg('bootstrap')->getResource('acl');
        foreach ($acl->getRoles() as $role) {
            if ($acl->isAllowed($role, 'status', 'be-green')) {
                $greenUserRoles[] = $role;
            }
        }

        $greenUsers = $userTable->fetchAll(array(
            'not deleted',
            'role in (?)' => $greenUserRoles,
            'identity is null or identity <> "autowp"',
            'last_online > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'
        ));

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

        $pictureTable = $this->_helper->catalogue()->getPictureTable();
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

        $this->view->assign(array(
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
        ));
    }
}