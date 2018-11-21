<?php

namespace Application\Service;

use Exception;

use Telegram\Bot\Api;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Router\Http\TreeRouteStack;

use Autowp\User\Model\User;

use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;
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
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var TableGateway
     */
    private $telegramItemTable;

    /**
     * @var TableGateway
     */
    private $telegramChatTable;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        array $options,
        TreeRouteStack $router,
        HostManager $hostManager,
        $serviceManager,
        Picture $picture,
        Item $item,
        TableGateway $telegramItemTable,
        TableGateway $telegramChatTable,
        User $userModel
    ) {

        $this->accessToken = isset($options['accessToken']) ? $options['accessToken'] : null;
        $this->webhook = isset($options['webhook']) ? $options['webhook'] : null;
        $this->token = isset($options['token']) ? $options['token'] : null;

        $this->router = $router;
        $this->hostManager = $hostManager;
        $this->serviceManager = $serviceManager;
        $this->picture = $picture;
        $this->item = $item;
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
        $this->userModel = $userModel;
    }

    /**
     * @return Api
     */
    private function getApi()
    {
        $api = new Api($this->accessToken);

        $api->addCommands([
            StartCommand::class,
            new MeCommand(
                $this->serviceManager->get(\Autowp\Message\MessageService::class),
                $this->telegramChatTable,
                $this->serviceManager->get(\Autowp\User\Model\User::class)
            ),
            new NewCommand($this->telegramItemTable, $this->telegramChatTable, $this->item->getTable()),
            new InboxCommand($this->telegramItemTable, $this->telegramChatTable, $this->item->getTable()),
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
            $message = $e->getMessage();

            if (strpos($message, 'deactivated') !== false) {
                $this->unsubscribeChat($params['chat_id']);
                return;
            }

            if (strpos($message, 'blocked') !== false) {
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

    public function notifyInbox(int $pictureId)
    {
        $picture = $this->picture->getRow(['id' => $pictureId]);
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

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function notifyPicture(int $pictureId)
    {
        $picture = $this->picture->getRow(['id' => $pictureId]);
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
        return $this->item->getIds([
            'item_type_id' => Item::BRAND,
            'descendant_or_self' => [
                'pictures' => [
                    'id' => $picture['id']
                ]
            ]
        ]);
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
        $chat = $this->telegramChatTable->select([
            'chat_id' => $chatId
        ])->current();

        if ($chat && $chat['user_id']) {
            $language = $this->userModel->getUserLanguage($chat['user_id']);

            if ($language) {
                return $this->hostManager->getUriByLanguage($language);
            }
        }

        return \Zend\Uri\UriFactory::factory('http://wheelsage.org');
    }

    public function notifyMessage($fromId, int $userId, $text)
    {
        $fromName = "New personal message";

        if ($fromId) {
            $userRow = $this->userModel->getRow((int)$fromId);
            if ($userRow) {
                $fromName = $userRow['name'];
            }
        }

        $chatRows = $this->telegramChatTable->select([
            'user_id' => $userId,
            'messages'
        ]);

        foreach ($chatRows as $chatRow) {
            $url = $this->router->assemble(['path' => ''], [
                'name'            => 'ng',
                'force_canonical' => true,
                'uri'             => $this->getUriByChatId($chatRow['chat_id'])
            ]) . 'account/messages' . ($fromId ? '' : '?folder=system');

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
