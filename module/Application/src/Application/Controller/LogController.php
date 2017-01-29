<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Log;

class LogController extends AbstractActionController
{
    /**
     * @var Log
     */
    private $log;
    
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $data = $this->log->getList([
            'article_id' => $this->params('article_id'),
            'item_id'    => $this->params('item_id'),
            'picture_id' => $this->params('picture_id'),
            'user_id'    => $this->params('user_id'),
            'page'       => $this->params('page'),
            'language'   => $this->language()
        ]);

        return array_replace($data, [
            'urlParams' => $this->params()->fromRoute()
        ]);
    }
}
