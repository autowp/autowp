<?php

namespace Application\Service;

use Picture;
use Project\Telegram\Command\InboxCommand;
use Project\Telegram\Command\MeCommand;
use Project\Telegram\Command\StartCommand;
use Telegram\Bot\Api;
use Telegram_Brand;
use Zend_Controller_Front;

class Telegram
{
    private $_accessToken;

    private $_webhook;

    private $_token;

    public function __construct(array $options = array())
    {
        $this->_accessToken = isset($options['accessToken']) ? $options['accessToken'] : null;
        $this->_webhook = isset($options['webhook']) ? $options['webhook'] : null;
        $this->_token = isset($options['token']) ? $options['token'] : null;
    }

    /**
     * @return Telegram\Bot\Api
     */
    private function _getApi()
    {
        $api = new Api($this->_accessToken);

        $api->addCommands([
            StartCommand::class,
            MeCommand::class,
            InboxCommand::class,
        ]);

        return $api;
    }

    public function checkTokenMatch($token)
    {
        return $this->_token == (string)$token;
    }

    public function registerWebhook()
    {
        $message = $this->_getApi()->setWebhook(array(
            'url'         => $this->_webhook,
            'certificate' => ''
        ));
    }

    public function getWebhookUpdates()
    {
        return $this->_getApi()->getWebhookUpdates();
    }

    public function sendMessage(array $params)
    {
        return $this->_getApi()->sendMessage($params);
    }

    public function commandsHandler($webhook = false)
    {
        return $this->_getApi()->commandsHandler($webhook);
    }

    public function notifyInbox($pictureId)
    {
        $pictureTable = new Picture();

        $picture = $pictureTable->find($pictureId)->current();
        if (!$picture) {
            return;
        }

        $db = $pictureTable->getAdapter();

        switch ($picture->type) {
            case Picture::CAR_TYPE_ID:
                $brandIds = $db->fetchCol(
                    $db->select()
                        ->from('brands_cars', 'brand_id')
                        ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                        ->where('car_parent_cache.car_id = ?', $picture->car_id)
                );
                break;
            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
                $brandIds = [$picture->brand_id];
                break;
            case Picture::ENGINE_TYPE_ID:
                $brandIds = $db->fetchCol(
                    $db->select()
                        ->from('brand_engine', 'brand_id')
                        ->where('engine_id = ?', $engine->id)
                );
                break;
        }

        if (count($brandIds)) {
            $telegramBrandTable = new Telegram_Brand();

            $filter = array(
                'brand_id in (?)' => $brandIds
            );

            $authorChatId = $db->fetchOne(
                $db->select()
                    ->from('telegram_chat', 'chat_id')
                    ->where('user_id = ?', $picture->owner_id)
            );

            if ($authorChatId) {
                $filter['chat_id <> ?'] = $authorChatId;
            }

            $rows = $telegramBrandTable->fetchAll($filter);

            $router = Zend_Controller_Front::getInstance()->getRouter();

            foreach ($rows as $row) {
                $url = $router->assemble(array(
                    'picture_id' => $picture->identity ? $picture->identity : $picture->id,
                ), 'picture', true);

                $this->sendMessage(array(
                    'text'    => 'http://autowp.ru' . $url,
                    'chat_id' => $row['chat_id']
                ));
            }
        }
    }
}