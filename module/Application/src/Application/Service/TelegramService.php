<?php

namespace Application\Service;

use Picture;
use Project\Telegram\Command\InboxCommand;
use Project\Telegram\Command\MeCommand;
use Project\Telegram\Command\StartCommand;
use Telegram\Bot\Api;
use Telegram_Brand;

use Zend\Router\Http\TreeRouteStack;

class TelegramService
{
    private $accessToken;

    private $webhook;

    private $token;

    /**
     * @var TreeRouteStack
     */
    private $router;

    public function __construct(array $options = [], TreeRouteStack $router)
    {
        $this->accessToken = isset($options['accessToken']) ? $options['accessToken'] : null;
        $this->webhook = isset($options['webhook']) ? $options['webhook'] : null;
        $this->token = isset($options['token']) ? $options['token'] : null;

        $this->router = $router;
    }

    /**
     * @return Telegram\Bot\Api
     */
    private function getApi()
    {
        $api = new Api($this->accessToken);

        $api->addCommands([
            StartCommand::class,
            MeCommand::class,
            InboxCommand::class,
        ]);

        return $api;
    }

    public function checkTokenMatch($token)
    {
        return $this->token == (string)$token;
    }

    public function registerWebhook()
    {
        $message = $this->getApi()->setWebhook(array(
            'url'         => $this->webhook,
            'certificate' => ''
        ));
    }

    public function getWebhookUpdates()
    {
        return $this->getApi()->getWebhookUpdates();
    }

    public function sendMessage(array $params)
    {
        return $this->getApi()->sendMessage($params);
    }

    public function commandsHandler($webhook = false)
    {
        return $this->getApi()->commandsHandler($webhook);
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
                        ->where('engine_id = ?', $picture->engine_id)
                );
                break;
        }

        if (count($brandIds)) {
            $telegramBrandTable = new Telegram_Brand();

            $filter = [
                'brand_id in (?)' => $brandIds
            ];

            $authorChatId = $db->fetchOne(
                $db->select()
                    ->from('telegram_chat', 'chat_id')
                    ->where('user_id = ?', (int)$picture->owner_id)
            );

            if ($authorChatId) {
                $filter['chat_id <> ?'] = $authorChatId;
            }

            $rows = $telegramBrandTable->fetchAll($filter);

            foreach ($rows as $row) {
                $url = $this->router->assemble([
                    'picture_id' => $picture->identity ? $picture->identity : $picture->id,
                ], [
                    'name' => 'picture',
                    'force_canonical' => true
                ]);

                $this->sendMessage([
                    'text'    => $url,
                    'chat_id' => $row['chat_id']
                ]);
            }
        }
    }
}