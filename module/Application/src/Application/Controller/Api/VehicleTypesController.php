<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;

class VehicleTypesController extends AbstractRestfulController
{
    /**
     * @var DbTable\Vehicle\Type
     */
    private $table;
    
    public function __construct() 
    {
        $this->table = new DbTable\Vehicle\Type();
    }
    
    private function getCarTypeOptions($parentId = null)
    {
        if ($parentId) {
            $filter = [
                'parent_id = ?' => $parentId
            ];
        } else {
            $filter = 'parent_id is null';
        }
    
        $rows = $this->table->fetchAll($filter, 'position');
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'     => (int)$row->id,
                'name'   => $row->name,
                'childs' => $this->getCarTypeOptions($row->id)
            ];
        }
    
        return $result;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'items' => $this->getCarTypeOptions(null),
        ]);
    }
}
