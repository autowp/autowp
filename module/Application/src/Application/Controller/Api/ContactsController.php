<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\User;

use Application\Hydrator\Api\RestHydrator;
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

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    public function __construct(
        Contact $contact,
        TableGateway $userTable,
        User $userModel,
        InputFilter $listInputFilter,
        RestHydrator $hydrator
    ) {
        $this->contact = $contact;
        $this->userTable = $userTable;
        $this->userModel = $userModel;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator = $hydrator;
    }

    public function indexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $params = $this->listInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $params['fields'],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $userRows = $this->userModel->getRows([
            'in_contacts' => $user['id'],
            'order'       => ['users.deleted', 'users.name']
        ]);

        $items = [];
        foreach ($userRows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function putAction()
    {
        $id = (int)$this->params('id');

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

    public function deleteAction()
    {
        $id = (int)$this->params('id');

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
