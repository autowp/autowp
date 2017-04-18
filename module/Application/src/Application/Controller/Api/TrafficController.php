<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Traffic\TrafficControl;

use Application\Hydrator\Api\RestHydrator;

class TrafficController extends AbstractRestfulController
{
    /**
     * @var TrafficControl
     */
    private $service;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;
    
    public function __construct(TrafficControl $service, RestHydrator $hydrator)
    {
        $this->service = $service;
        $this->hydrator = $hydrator;
    }
    
    public function listAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getTopData();
        
        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
            //'user_id'  => $user ? $user['id'] : null
        ]);
        
        $result = [];
        foreach ($data as $row) {
            $result[] = $this->hydrator->extract($row);
        }
        
        return new JsonModel([
            'items' => $result
        ]);
    }
}
