<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\DbTable;

class PerspectiveController extends AbstractRestfulController
{
    /**
     * @var DbTable\Perspective
     */
    private $table;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;
    
    public function __construct(RestHydrator $hydrator) 
    {
        $this->hydrator = $hydrator;
        $this->table = new DbTable\Perspective();
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);
        
        $rows = $this->table->fetchAll([], 'position');
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}
