<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\Contact;

class ContactsController extends AbstractRestfulController
{
    /**
     * @var Contact
     */
    private $contact;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
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

        $userTable = new User();
        $user = $userTable->fetchRow([
            'id = ?' => (int)$id,
            'not deleted'
        ]);

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

        $userTable = new User();
        $user = $userTable->fetchRow([
            'id = ?' => (int)$id
        ]);

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
