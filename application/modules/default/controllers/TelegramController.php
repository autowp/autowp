<?php

class TelegramController extends Zend_Controller_Action
{
    public function webhookAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error');
        }

        $telegram = $this->getInvokeArg('bootstrap')->getResource('telegram');

        if (!$telegram->checkTokenMatch($this->getParam('token'))) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $telegram->commandsHandler(true);

        return $this->_helper->json(true);
    }
}