<?php

namespace Application\Controller\Api;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

class PictureModerVoteTemplateController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;
    
    public function __construct(Adapter $adapter) 
    {
        $this->table = new TableGateway('picture_moder_vote_template', $adapter);
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $user = $this->user()->get();
        
        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns(['id', 'reason', 'vote'])
            ->where(['user_id' => $user['id']])
            ->order('reason');
        
        $items = [];
        foreach ($this->table->selectWith($select) as $row) {
            $items[] = [
                'id'   => (int)$row['id'],
                'name' => $row['reason'],
                'vote' => (int)$row['vote']
            ];
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}
