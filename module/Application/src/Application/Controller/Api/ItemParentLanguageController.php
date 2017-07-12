<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\BrandVehicle;

class ItemParentLanguageController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    /**
     * @var InputFilter
     */
    private $putInputFilter;

    public function __construct(
        TableGateway $table,
        RestHydrator $hydrator,
        BrandVehicle $brandVehicle,
        InputFilter $putInputFilter
    ) {
        $this->table = $table;
        $this->hydrator = $hydrator;
        $this->brandVehicle = $brandVehicle;
        $this->putInputFilter = $putInputFilter;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $rows = $this->table->select([
            'item_id'       => (int)$this->params('item_id'),
            'parent_id'     => (int)$this->params('parent_id')
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function getAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->table->select([
            'item_id'   => (int)$this->params('item_id'),
            'parent_id' => (int)$this->params('parent_id'),
            'language'  => (string)$this->params('language')
        ])->current();

        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    public function putAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        $this->putInputFilter->setValidationGroup($fields);

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid request'));
        }

        $this->putInputFilter->setData($data);

        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $data = $this->putInputFilter->getValues();

        $language = (string)$this->params('language');

        $row = $this->table->select([
            'item_id'   => (int)$this->params('item_id'),
            'parent_id' => (int)$this->params('parent_id'),
            'language'  => $language
        ])->current();

        if (! $row) {
            return $this->notFoundAction();
        }

        if (array_key_exists('name', $data)) {
            $this->brandVehicle->setItemParentLanguage($row['parent_id'], $row['item_id'], $language, [
                'name' => $data['name']
            ], false);
        }

        return $this->getResponse()->setStatusCode(200);
    }
}
