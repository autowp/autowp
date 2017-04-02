<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\RestHydrator;

class UserController extends AbstractRestfulController
{
    /**
     * @var User
     */
    private $table;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;
    
    public function __construct(RestHydrator $hydrator)
    {
        $this->hydrator = $hydrator;
        
        $this->table = new User();
    }
    
    public function indexAction()
    {
        $perPage = 24;
        
        $select = $this->table->select(true)
            ->where('not deleted');
        
        $search = $this->params()->fromQuery('search');
        if ($search) {
            $select->where('name like ?', $search . '%');
        }
        
        $id = (int)$this->params()->fromQuery('id');
        if ($id) {
            $select->where('id = ?', $id);
        }
        
        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
        
        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));
        
        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());
        
        $this->hydrator->setOptions([
            'language' => $this->language(),
            //'user_id'  => $user ? $user['id'] : null
        ]);
        
        $items = [];
        foreach ($this->table->fetchAll($select) as $row) {
            $items[] = $this->hydrator->extract($row);
        }
        
        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items
        ]);
    }
}
