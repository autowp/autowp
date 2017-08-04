<?php

namespace Application\Service;

use Telegram\Bot\Api;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Router\Http\TreeRouteStack;

use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\DbTable;
use Application\Model\Item;
use Application\Telegram\Command\InboxCommand;
use Application\Telegram\Command\MeCommand;
use Application\Telegram\Command\NewCommand;
use Application\Telegram\Command\StartCommand;
use Application\Telegram\Command\MessagesCommand;

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

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var TableGateway
     */
    private $telegramItemTable;

    /**
     * @var TableGateway
     */
    private $telegramChatTable;

    /**
     * @var TableGateway
     */
    private $itemTable;

    public function __construct(
        array $options,
        TreeRouteStack $router,
        HostManager $hostManager,
        $serviceManager,
        DbTable\Picture $pictureTable,
        TableGateway $telegramItemTable,
        TableGateway $telegramChatTable,
        TableGateway $itemTable
    ) {

        $this->accessToken = isset($options['accessToken']) ? $options['accessToken'] : null;
        $this->webhook = isset($options['webhook']) ? $options['webhook'] : null;
        $this->token = isset($options['token']) ? $options['token'] : null;

        $this->router = $router;
        $this->hostManager = $hostManager;
        $this->serviceManager = $serviceManager;
        $this->pictureTable = $pictureTable;
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
        $this->itemTable = $itemTable;
    }

    /**
     * @return Telegram\Bot\Api
     */
    private function getApi()
    {
        $api = new Api($this->accessToken);

        $api->addCommands([
            StartCommand::class,
            new MeCommand($this->serviceManager->get(\Autowp\Message\MessageService::class), $this->telegramChatTable),
            new NewCommand($this->telegramItemTable, $this->telegramChatTable, $this->itemTable),
            new InboxCommand($this->telegramItemTable, $this->telegramChatTable, $this->itemTable),
            new MessagesCommand($this->telegramChatTable)
        ]);

        return $api;
    }

    public function checkTokenMatch($token)
    {
        return $this->token == (string)$token;
    }

    public function registerWebhook()
    {
        $this->getApi()->setWebhook([
            'url'         => $this->webhook,
            //'certificate' => ''
        ]);
    }

    public function getWebhookUpdates()
    {
        return $this->getApi()->getWebhookUpdates();
    }

    public function sendMessage(array $params)
    {
        if (! isset($params['chat_id'])) {
            throw new \Exception("`chat_id` not provided");
        }

        try {
            $this->getApi()->sendMessage($params);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'blocked') !== false) {
                $this->unsubscribeChat($params['chat_id']);
                return;
            }
            throw $e;
        }
    }

    private function unsubscribeChat($chatId)
    {
        $chatId = (int) $chatId;
        if (! $chatId) {
            throw new Exception("`chat_id` is invalid");
        }

        $this->telegramItemTable->delete([
            'chat_id = ?' => (int)$chatId
        ]);

        $this->telegramChatTable->delete([
            'chat_id = ?' => (int)$chatId
        ]);
    }

    public function commandsHandler($webhook = false)
    {
        return $this->getApi()->commandsHandler($webhook);
    }

    public function notifyInbox($pictureId)
    {
        $picture = $this->pictureTable->find($pictureId)->current();
        if (! $picture) {
            return;
        }

        $brandIds = $this->getPictureBrandIds($picture);

        if (count($brandIds)) {
            $select = new Sql\Select($this->telegramItemTable->getTable());
            $select->columns(['chat_id'])
                ->where([
                    new Sql\Predicate\In('telegram_brand.item_id', $brandIds),
                    'telegram_brand.inbox',
                    'users.id <> ?' => (int)$picture['owner_id'],
                    'not users.deleted'
                ])
                ->join('telegram_chat', 'telegram_brand.chat_id = telegram_chat.chat_id', [])
                ->join('users', 'telegram_chat.user_id = users.id', []);

            foreach ($this->telegramItemTable->selectWith($select) as $row) {
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
        $picture = $this->pictureTable->find($pictureId)->current();
        if (! $picture) {
            return;
        }

        $brandIds = $this->getPictureBrandIds($picture);

        if (count($brandIds)) {
            $select = new Sql\Select($this->telegramChatTable->getTable());
            $select->columns(['chat_id'])
                ->where(['user_id' => (int)$picture['owner_id']]);

            $row = $this->telegramChatTable->selectWith($select)->current();

            $authorChatId = $row ? $row['chat_id'] : null;

            $select = new Sql\Select($this->telegramItemTable->getTable());
            $select->columns(['chat_id'])
                ->quantifier($select::QUANTIFIER_DISTINCT)
                ->where([
                    new Sql\Predicate\In('telegram_brand.item_id', $brandIds),
                    'telegram_brand.new'
                ]);

            if ($authorChatId) {
                $select->where(['telegram_brand.chat_id != ?' => $authorChatId]);
            }

            $rows = $this->telegramItemTable->selectWith($select);

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

        $brandIds = $db->fetchCol(
            $db->select()
                ->from('item', 'id')
                ->where('item.item_type_id = ?', Item::BRAND)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                ->where('picture_item.picture_id = ?', $picture['id'])
        );

        return $brandIds;
    }

    private function getPictureUrl($chatId, $picture)
    {
        $uri = $this->getUriByChatId($chatId);

        return $this->router->assemble([
            'picture_id' => $picture['identity']
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

        if ($userRow && $userRow['language']) {
            return $this->hostManager->getUriByLanguage($userRow['language']);
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
                $fromName = $userRow['name'];
            }
        }

        $chatRows = $this->telegramChatTable->select([
            'user_id' => (int)$userId,
            'messages'
        ]);

        foreach ($chatRows as $chatRow) {
            $url = $this->router->assemble([], [
                'name'            => $fromId ? 'account/personal-messages' : 'account/personal-messages/system',
                'force_canonical' => true,
                'uri'             => $this->getUriByChatId($chatRow['chat_id'])
            ]);

            $telegramMessage = sprintf(
                "%s: \n%s\n\n%s",
                $fromName,
                $text,
                $url
            );

            $this->sendMessage([
                'text'    => $telegramMessage,
                'chat_id' => $chatRow['chat_id']
            ]);
        }
    }
}
