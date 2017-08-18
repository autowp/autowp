<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Model\Contact;

class ContactsController extends AbstractRestfulController
{
    /**
     * @var Contact
     */
    private $contact;

    /**
     * @var TableGateway
     */
    private $userTable;

    public function __construct(Contact $contact, TableGateway $userTable)
    {
        $this->contact = $contact;
        $this->userTable = $userTable;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return mixed
     */
    public function update($id, $data)
    {
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->notFoundAction();
        }

        if ($currentUser['id'] == $id) {
            return $this->notFoundAction();
        }

        $user = $this->userTable->select([
            'id = ?' => (int)$id,
            'not deleted'
        ])->current();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->contact->create($currentUser['id'], $user['id']);

        $this->getResponse()->setStatusCode(200);

        return new JsonModel([
            'status' => true
        ]);
    }

    /**
     * Delete an existing resource
     *
     * @param  mixed $id
     * @return mixed
     */
    public function delete($id)
    {
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->notFoundAction();
        }

        if ($currentUser['id'] == $id) {
            return $this->notFoundAction();
        }

        $user = $this->userTable->select([
            'id' => (int)$id
        ])->current();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->contact->delete($currentUser['id'], $user['id']);

        $this->getResponse()->setStatusCode(204);

        return new JsonModel([
            'status' => true
        ]);
    }
}
