<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;

class SpecController extends AbstractRestfulController
{
    /**
     * @var DbTable\Spec
     */
    private $table;

    public function __construct()
    {
        $this->table = new DbTable\Spec();
    }

    private function getSpecOptions($parentId = null)
    {
        if ($parentId) {
            $filter = [
                'parent_id = ?' => $parentId
            ];
        } else {
            $filter = 'parent_id is null';
        }

        $rows = $this->table->fetchAll($filter, 'name');
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'         => (int)$row->id,
                'name'       => $row->name,
                'short_name' => $row->short_name,
                'childs'     => $this->getSpecOptions($row->id)
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
            'items' => $this->getSpecOptions(null),
        ]);
    }
}
