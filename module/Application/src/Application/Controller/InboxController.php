<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

class InboxController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toUrl('/ng/inbox');
    }
}
