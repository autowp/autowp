<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\User;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrController extends AbstractRestfulController
{
    /**
     * @var Item
     */
    private $item;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var RestHydrator
     */
    private $conflictHydrator;

    /**
     * @var InputFilter
     */
    private $conflictListInputFilter;

    public function __construct(
        Item $item,
        SpecificationsService $specsService,
        User $userModel,
        RestHydrator $conflictHydrator,
        InputFilter $conflictListInputFilter
    ) {
        $this->item = $item;
        $this->specsService = $specsService;
        $this->userModel = $userModel;
        $this->conflictHydrator = $conflictHydrator;
        $this->conflictListInputFilter = $conflictListInputFilter;
    }

    public function conflictIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->conflictListInputFilter->setData($this->params()->fromQuery());

        if (! $this->conflictListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->conflictListInputFilter);
        }

        $values = $this->conflictListInputFilter->getValues();

        $data = $this->specsService->getConflicts($user['id'], $values['filter'], (int)$values['page'], 30);

        $this->conflictHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($data['conflicts'] as $conflict) {
            $items[] = $this->conflictHydrator->extract($conflict);
        }

        return new JsonModel([
            'items'     => $items,
            'paginator' => $data['paginator']->getPages()
        ]);
    }
}
