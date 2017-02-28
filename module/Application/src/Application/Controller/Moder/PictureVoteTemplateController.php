<?php

namespace Application\Controller\Moder;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

class PictureVoteTemplateController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $form;
    
    /**
     * @var TableGateway
     */
    private $table;
    
    public function __construct(Adapter $adapter, Form $form)
    {
        $this->table = new TableGateway('picture_moder_vote_template', $adapter);
        $this->form = $form;
    }
    
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $user = $this->user()->get();
        
        $select = new Sql\Select($this->table->getTable());
        
        $select->where(['user_id' => $user->id])
            ->order(['vote', 'reason']);
        
        $rows = $this->table->selectWith($select);
        
        $this->form->setAttribute('action', $this->url()->fromRoute());
        
        if ($this->getRequest()->isPost()) {
            
            $this->form->setData($this->params()->fromPost());
            if ($this->form->isValid()) {
                
                $values = $this->form->getData();
                
                $this->table->insert([
                    'user_id' => $user->id,
                    'reason'  => $values['reason'],
                    'vote'    => $values['vote']
                ]);
                
                return $this->redirect()->toRoute(null);
            }
        }
        
        return [
            'reasons' => $rows,
            'form'    => $this->form
        ];
    }
    
    public function deleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $this->table->delete([
            'user_id' => $this->user()->get()->id,
            'id'      => (int)$this->params()->fromPost('id')
        ]);
        
        return $this->redirect()->toRoute(null, [
            'action' => 'index'
        ]);
    }
}