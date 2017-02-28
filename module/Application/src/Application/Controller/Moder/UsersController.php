<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\Service\UsersService;

class UsersController extends AbstractActionController
{
    /**
     * @var User
     */
    private $table;

    /**
     * @var UsersService
     */
    private $userService;

    public function __construct(UsersService $userService)
    {
        $this->userService = $userService;

        $this->table = new User();
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $select = $this->table->select(true)
            ->order('id');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(30)
            ->setCurrentPageNumber($this->params('page'));

        return [
            'paginator' => $paginator
        ];
    }

    public function removeUserPhotoAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $can = $this->user()->isAllowed('user', 'ban');
        if (! $can) {
            return $this->forbiddenAction();
        }

        $row = $this->table->find($this->params('id'))->current();

        if (! $row) {
            return $this->notFoundAction();
        }

        $oldImageId = $row->img;
        if ($oldImageId) {
            $row->img = null;
            $row->save();
            $this->imageStorage()->removeImage($oldImageId);
        }

        $this->log(sprintf(
            'Удаление фотографии пользователя №%s',
            $row->id
        ), [$row]);

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id' => $row->identity ? $row->identity : 'user' . $row->id
        ]));
    }

    public function deleteUserAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $can = $this->user()->isAllowed('user', 'delete');
        if (! $can) {
            return $this->forbiddenAction();
        }

        $row = $this->table->find($this->params('id'))->current();
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->userService->markDeleted($row['id']);

        $this->log(sprintf(
            'Удаление пользователя №%s',
            $row->id
        ), [$row]);

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id' => $row->identity ? $row->identity : 'user' . $row->id
        ]));
    }
}
