<?php

namespace Application\Service;

use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Telegram\Command\InboxCommand;
use Application\Telegram\Command\MeCommand;
use Application\Telegram\Command\MessagesCommand;
use Application\Telegram\Command\NewCommand;
use Application\Telegram\Command\StartCommand;
use ArrayAccess;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Uri\UriFactory;
use Psr\Container\ContainerInterface;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;

use function count;
use function sprintf;
use function strpos;
use function urlencode;

class TelegramService
{
    private string $accessToken;

    private string $webhook;

    private string $token;

    private TreeRouteStack $router;

    private HostManager $hostManager;

    private Picture $picture;

    private Item $item;

    private TableGateway $telegramItemTable;

    private TableGateway $telegramChatTable;

    private User $userModel;

    private ContainerInterface $serviceManager;

    public function __construct(
        array $options,
        TreeRouteStack $router,
        HostManager $hostManager,
        ContainerInterface $serviceManager,
        Picture $picture,
        Item $item,
        TableGateway $telegramItemTable,
        TableGateway $telegramChatTable,
        User $userModel
    ) {
        $this->accessToken = $options['accessToken'] ?? null;
        $this->webhook     = $options['webhook'] ?? null;
        $this->token       = $options['token'] ?? null;

        $this->router            = $router;
        $this->hostManager       = $hostManager;
        $this->serviceManager    = $serviceManager;
        $this->picture           = $picture;
        $this->item              = $item;
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
        $this->userModel         = $userModel;
    }

    /**
     * @throws TelegramSDKException
     */
    private function getApi(): Api
    {
        $api = new Api($this->accessToken);

        $api->addCommands([
            StartCommand::class,
            new MeCommand(
                $this->serviceManager->get(MessageService::class),
                $this->telegramChatTable,
                $this->serviceManager->get(User::class)
            ),
            new NewCommand($this->telegramItemTable, $this->telegramChatTable, $this->item->getTable()),
            new InboxCommand($this->telegramItemTable, $this->telegramChatTable, $this->item->getTable()),
            new MessagesCommand($this->telegramChatTable),
        ]);

        return $api;
    }

    public function checkTokenMatch(string $token): bool
    {
        return $this->token === $token;
    }

    public function registerWebhook(): void
    {
        $this->getApi()->setWebhook([
            'url' => $this->webhook,
            //'certificate' => ''
        ]);
    }

    public function getWebhookUpdates()
    {
        return $this->getApi()->getWebhookUpdates();
    }

    /**
     * @throws Exception
     */
    public function sendMessage(array $params): void
    {
        if (! isset($params['chat_id'])) {
            throw new Exception("`chat_id` not provided");
        }

        try {
            $this->getApi()->sendMessage($params);
        } catch (Exception $e) {
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

    private function unsubscribeChat($chatId): void
    {
        $chatId = (int) $chatId;
        if (! $chatId) {
            throw new Exception("`chat_id` is invalid");
        }

        $this->telegramItemTable->delete([
            'chat_id = ?' => (int) $chatId,
        ]);

        $this->telegramChatTable->delete([
            'chat_id = ?' => (int) $chatId,
        ]);
    }

    /**
     * @return Update|Update[]
     * @throws TelegramSDKException
     */
    public function commandsHandler(bool $webhook = false)
    {
        return $this->getApi()->commandsHandler($webhook);
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @throws Exception
     */
    public function notifyInbox(int $pictureId): void
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
                    'users.id <> ?' => (int) $picture['owner_id'],
                    'not users.deleted',
                ])
                ->join('telegram_chat', 'telegram_brand.chat_id = telegram_chat.chat_id', [])
                ->join('users', 'telegram_chat.user_id = users.id', []);

            foreach ($this->telegramItemTable->selectWith($select) as $row) {
                $url = $this->getPictureUrl($row['chat_id'], $picture);

                $this->sendMessage([
                    'text'    => $url,
                    'chat_id' => $row['chat_id'],
                ]);
            }
        }
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    public function notifyPicture(int $pictureId): void
    {
        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return;
        }

        $brandIds = $this->getPictureBrandIds($picture);

        if (count($brandIds)) {
            $select = new Sql\Select($this->telegramChatTable->getTable());
            $select->columns(['chat_id'])
                ->where(['user_id' => (int) $picture['owner_id']]);

            $row = $this->telegramChatTable->selectWith($select)->current();

            $authorChatId = $row ? $row['chat_id'] : null;

            $select = new Sql\Select($this->telegramItemTable->getTable());
            $select->columns(['chat_id'])
                ->quantifier($select::QUANTIFIER_DISTINCT)
                ->where([
                    new Sql\Predicate\In('telegram_brand.item_id', $brandIds),
                    'telegram_brand.new',
                ]);

            if ($authorChatId) {
                $select->where(['telegram_brand.chat_id != ?' => $authorChatId]);
            }

            $rows = $this->telegramItemTable->selectWith($select);

            foreach ($rows as $row) {
                $url = $this->getPictureUrl($row['chat_id'], $picture);

                $this->sendMessage([
                    'text'    => $url,
                    'chat_id' => $row['chat_id'],
                ]);
            }
        }
    }

    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    private function getPictureBrandIds($picture): array
    {
        return $this->item->getIds([
            'item_type_id'       => Item::BRAND,
            'descendant_or_self' => [
                'pictures' => [
                    'id' => $picture['id'],
                ],
            ],
        ]);
    }

    private function getPictureUrl($chatId, $picture)
    {
        $uri = $this->getUriByChatId($chatId);
        $uri->setPath('/picture/' . urlencode($picture['identity']));

        return $uri->toString();
    }

    private function getUriByChatId($chatId)
    {
        $chat = $this->telegramChatTable->select([
            'chat_id' => $chatId,
        ])->current();

        if ($chat && $chat['user_id']) {
            $language = $this->userModel->getUserLanguage($chat['user_id']);

            if ($language) {
                return $this->hostManager->getUriByLanguage($language);
            }
        }

        return UriFactory::factory('http://wheelsage.org');
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @throws Exception
     */
    public function notifyMessage(?int $fromId, int $userId, string $text): void
    {
        $fromName = "New personal message";

        if ($fromId) {
            $userRow = $this->userModel->getRow($fromId);
            if ($userRow) {
                $fromName = $userRow['name'];
            }
        }

        $chatRows = $this->telegramChatTable->select([
            'user_id' => $userId,
            'messages',
        ]);

        foreach ($chatRows as $chatRow) {
            $uri = $this->getUriByChatId($chatRow['chat_id']);
            $uri->setPath('/account/messages');
            if (! $fromId) {
                $uri->setQuery(['folder' => 'system']);
            }

            $telegramMessage = sprintf(
                "%s: \n%s\n\n%s",
                $fromName,
                $text,
                $uri->toString()
            );

            $this->sendMessage([
                'text'    => $telegramMessage,
                'chat_id' => $chatRow['chat_id'],
            ]);
        }
    }
}
