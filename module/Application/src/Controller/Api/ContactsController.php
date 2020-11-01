<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Contact;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function Autowp\Commons\currentFromResultSetInterface;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class ContactsController extends AbstractRestfulController
{
    private Contact $contact;

    private TableGateway $userTable;

    private User $userModel;

    private InputFilter $listInputFilter;

    private AbstractRestHydrator $hydrator;

    public function __construct(
        Contact $contact,
        TableGateway $userTable,
        User $userModel,
        InputFilter $listInputFilter,
        AbstractRestHydrator $hydrator
    ) {
        $this->contact         = $contact;
        $this->userTable       = $userTable;
        $this->userModel       = $userModel;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator        = $hydrator;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
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
            'user_id'  => $user['id'],
        ]);

        $userRows = $this->userModel->getRows([
            'in_contacts' => $user['id'],
            'order'       => ['users.deleted', 'users.name'],
        ]);

        $items = [];
        foreach ($userRows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getAction()
    {
        $id = (int) $this->params('id');

        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->notFoundAction();
        }

        if ((int) $currentUser['id'] === $id) {
            return $this->notFoundAction();
        }

        if (! $this->contact->exists($currentUser['id'], $id)) {
            return $this->notFoundAction();
        }

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_200);

        return new JsonModel([
            'contact_user_id' => $id,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function putAction()
    {
        $id = (int) $this->params('id');

        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        if ((int) $currentUser['id'] === $id) {
            return $this->notFoundAction();
        }

        $user = currentFromResultSetInterface($this->userTable->select([
            'id = ?' => (int) $id,
            'not deleted',
        ]));

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->contact->create($currentUser['id'], $user['id']);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_200);

        return new JsonModel([
            'status' => true,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        $id = (int) $this->params('id');

        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->notFoundAction();
        }

        if ((int) $currentUser['id'] === $id) {
            return $this->notFoundAction();
        }

        $user = currentFromResultSetInterface($this->userTable->select(['id' => (int) $id]));

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->contact->delete($currentUser['id'], $user['id']);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_204);

        return new JsonModel([
            'status' => true,
        ]);
    }
}
