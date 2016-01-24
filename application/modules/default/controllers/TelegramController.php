<?php

class TelegramController extends Zend_Controller_Action
{
    public function webhookAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error');
        }

        try {

            $telegram = $this->getInvokeArg('bootstrap')->getResource('telegram');

            if (!$telegram->checkTokenMatch($this->getParam('token'))) {
                return $this->_forward('forbidden', 'error', 'default');
            }

            $updates = $telegram->commandsHandler(true);

        } catch (Exception $e) {
            $text = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            $filepath = APPLICATION_PATH . '/data/telegram.txt';
            file_put_contents($filepath, $text);
        }

        return $this->_helper->json(true);
    }
}