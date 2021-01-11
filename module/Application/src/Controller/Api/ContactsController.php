<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class ContactsController extends AbstractRestfulController
{
    private User $userModel;

    private InputFilter $listInputFilter;

    private AbstractRestHydrator $hydrator;

    public function __construct(
        User $userModel,
        InputFilter $listInputFilter,
        AbstractRestHydrator $hydrator
    ) {
        $this->userModel       = $userModel;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator        = $hydrator;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
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
}
