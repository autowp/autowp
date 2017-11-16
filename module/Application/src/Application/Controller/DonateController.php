<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

class DonateController extends AbstractActionController
{
    public function successAction()
    {
        return $this->redirect()->toUrl('/ng/donate/success');
    }
}
