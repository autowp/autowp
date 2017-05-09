<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\DbTable;

class PerspectivePageController extends AbstractRestfulController
{
    /**
     * @var DbTable\Perspective
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    public function __construct(RestHydrator $hydrator, InputFilter $listInputFilter)
    {
        $this->table = new DbTable\Perspective\Page();
        $this->hydrator = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields']
        ]);

        $rows = $this->table->fetchAll([], 'id');
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}
