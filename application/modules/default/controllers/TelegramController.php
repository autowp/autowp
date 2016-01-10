<?php

class TelegramController extends Zend_Controller_Action
{
    public function webhookAction()
    {
        /*if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error');
        }*/

        try {
            $telegram = $this->getInvokeArg('bootstrap')->getResource('telegram');

            $updates = $telegram->commandsHandler(true);

            /*$filepath = APPLICATION_PATH . '/data/telegram.txt';
            file_put_contents($filepath, print_r($updates, true));*/


            /*$message = $updates->get('message');
            if ($message) {
                $chat = $message->get('chat');
                if (!$chat) {
                    throw new Exception("Is not chat");
                }

                $chatId = $chat->get('id');
                if (!$chatId) {
                    throw new Exception("Is not chat id");
                }

                $text = $message->get('text');
                if ($text) {
                    $telegram->sendMessage(array(
                        'chat_id' => $chatId,
                        'text'    => 'Re: ' . $text
                    ));
                }
            }*/


        } catch (Exception $e) {
            $text = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            $filepath = APPLICATION_PATH . '/data/telegram.txt';
            file_put_contents($filepath, $text);
        }

        return $this->_helper->json(true);
    }
}