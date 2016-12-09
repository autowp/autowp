<?php

namespace Application\Service;

use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Telegram\Brand as TelegramBrand;
use Application\Model\DbTable\Telegram\Chat as TelegramChat;
use Application\Telegram\Command\InboxCommand;
use Application\Telegram\Command\MeCommand;
use Application\Telegram\Command\NewCommand;
use Application\Telegram\Command\StartCommand;
use Application\Telegram\Command\MessagesCommand;

use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;

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

    /**
     * @var HostManager
     */
    private $hostManager;

    public function __construct(
        array $options,
        TreeRouteStack $router,
        HostManager $hostManager,
        $serviceManager
    ) {

        $this->accessToken = isset($options['accessToken']) ? $options['accessToken'] : null;
        $this->webhook = isset($options['webhook']) ? $options['webhook'] : null;
        $this->token = isset($options['token']) ? $options['token'] : null;

        $this->router = $router;
        $this->hostManager = $hostManager;
        $this->serviceManager = $serviceManager;
    }

    /**
     * @return Telegram\Bot\Api
     */
    private function getApi()
    {
        $api = new Api($this->accessToken);

        $api->addCommands([
            StartCommand::class,
            new MeCommand($this->serviceManager->get(\Application\Model\Message::class)),
            NewCommand::class,
            InboxCommand::class,
            MessagesCommand::class
        ]);

        return $api;
    }

    public function checkTokenMatch($token)
    {
        return $this->token == (string)$token;
    }

    public function registerWebhook()
    {
        $message = $this->getApi()->setWebhook([
            'url'         => $this->webhook,
            'certificate' => ''
        ]);
    }

    public function getWebhookUpdates()
    {
        return $this->getApi()->getWebhookUpdates();
    }

    public function sendMessage(array $params)
    {
        try {
            $this->getApi()->sendMessage($params);
        } catch (TelegramResponseException $e) {
            if ($e->getMessage() == 'Bot was blocked by the user') {
                if (isset($params['chat_id'])) {
                    $this->unsubscribeChat($params['chat_id']);
                    return;
                }
            }

            throw $e;
        }
    }

    private function unsubscribeChat($chatId)
    {
        $telegramBrandTable = new TelegramBrand();
        $telegramBrandTable->delete([
            'chat_id = ?' => (int)$chatId
        ]);

        $telegramChatTable = new TelegramChat();
        $telegramChatTable->delete([
            'chat_id = ?' => (int)$chatId
        ]);
    }

    public function commandsHandler($webhook = false)
    {
        return $this->getApi()->commandsHandler($webhook);
    }

    public function notifyInbox($pictureId)
    {
        $pictureTable = new Picture();

        $picture = $pictureTable->find($pictureId)->current();
        if (! $picture) {
            return;
        }

        $brandIds = $this->getPictureBrandIds($picture);

        if (count($brandIds)) {
            $telegramBrandTable = new TelegramBrand();

            $db = $telegramBrandTable->getAdapter();

            $rows = $db->fetchAll(
                $db->select()
                    ->from($telegramBrandTable->info('name'), ['chat_id'])
                    ->where('telegram_brand.brand_id in (?)', $brandIds)
                    ->where('telegram_brand.inbox')
                    ->join('telegram_chat', 'telegram_brand.chat_id = telegram_chat.chat_id', null)
                    ->join('users', 'telegram_chat.user_id = users.id', null)
                    ->where('users.id <> ?', (int)$picture->owner_id)
                    ->where('not users.deleted')
            );

            foreach ($rows as $row) {
                $url = $this->getPictureUrl($row['chat_id'], $picture);

                $this->sendMessage([
                    'text'    => $url,
                    'chat_id' => $row['chat_id']
                ]);
            }
        }
    }

    public function notifyPicture($pictureId)
    {
        $pictureTable = new Picture();

        $picture = $pictureTable->find($pictureId)->current();
        if (! $picture) {
            return;
        }

        $brandIds = $this->getPictureBrandIds($picture);

        if (count($brandIds)) {
            $telegramBrandTable = new TelegramBrand();

            $db = $telegramBrandTable->getAdapter();

            $authorChatId = $db->fetchOne(
                $db->select()
                    ->from('telegram_chat', 'chat_id')
                    ->where('user_id = ?', (int)$picture->owner_id)
            );

            $select = $db->select()
                ->distinct()
                ->from('telegram_brand', ['chat_id'])
                ->where('telegram_brand.brand_id in (?)', $brandIds)
                ->where('telegram_brand.new');

            if ($authorChatId) {
                $select->where('telegram_brand.chat_id <> ?', $authorChatId);
            }

            $rows = $db->fetchAll($select);

            foreach ($rows as $row) {
                $url = $this->getPictureUrl($row['chat_id'], $picture);

                $this->sendMessage([
                    'text'    => $url,
                    'chat_id' => $row['chat_id']
                ]);
            }
        }
    }

    private function getPictureBrandIds($picture)
    {
        $db = $picture->getTable()->getAdapter();

        $brandIds = [];

        switch ($picture->type) {
            case Picture::VEHICLE_TYPE_ID:
                $brandIds = $db->fetchCol(
                    $db->select()
                        ->from('brand_item', 'brand_id')
                        ->join('item_parent_cache', 'brand_item.car_id = item_parent_cache.parent_id', null)
                        ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                        ->where('picture_item.picture_id = ?', $picture->id)
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

        return $brandIds;
    }

    private function getPictureUrl($chatId, $picture)
    {
        $uri = $this->getUriByChatId($chatId);

        return $this->router->assemble([
            'picture_id' => $picture->identity ? $picture->identity : $picture->id,
        ], [
            'name'            => 'picture/picture',
            'force_canonical' => true,
            'uri'             => $uri
        ]);
    }

    private function getUriByChatId($chatId)
    {
        $userTable = new User();

        $userRow = $userTable->fetchRow(
            $userTable->select(true)
                ->join('telegram_chat', 'users.id = telegram_chat.user_id', null)
                ->where('telegram_chat.chat_id = ?', $chatId)
        );

        if ($userRow && $userRow->language) {
            return $this->hostManager->getUriByLanguage($userRow->language);
        }

        return \Zend\Uri\UriFactory::factory('http://wheelsage.org');
    }

    public function notifyMessage($fromId, $userId, $text)
    {
        $fromName = "New personal message";

        if ($fromId) {
            $userTable = new User();
            $userRow = $userTable->find($fromId)->current();
            if ($userRow) {
                $fromName = $userRow->name;
            }
        }

        $chatTable = new TelegramChat();

        $chatRows = $chatTable->fetchAll([
            'user_id = ?' => (int)$userId,
            'messages'
        ]);

        foreach ($chatRows as $chatRow) {
            $url = $this->router->assemble([], [
                'name'            => $fromId ? 'account/personal-messages' : 'account/personal-messages/system',
                'force_canonical' => true,
                'uri'             => $this->getUriByChatId($chatRow->chat_id)
            ]);

            $telegramMessage = sprintf(
                "%s: \n%s\n\n%s",
                $fromName,
                $text,
                $url
            );

            $this->sendMessage([
                'text'    => $telegramMessage,
                'chat_id' => $chatRow->chat_id
            ]);
        }
    }
}
