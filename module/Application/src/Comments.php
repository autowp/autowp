<?php

namespace Application;

use Application\Model\Item;
use Application\Model\Picture;
use ArrayAccess;
use Autowp\Comments\CommentsService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Exception;
use InvalidArgumentException;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Uri\Uri;

use function array_walk;
use function implode;
use function ltrim;
use function sprintf;
use function urlencode;

class Comments
{
    private const PREVIEW_LENGTH = 60;

    public const PICTURES_TYPE_ID = 1;
    public const ITEM_TYPE_ID     = 2;
    public const VOTINGS_TYPE_ID  = 3;
    public const ARTICLES_TYPE_ID = 4;
    public const FORUMS_TYPE_ID   = 5;

    private CommentsService $service;

    private HostManager $hostManager;

    private MessageService $message;

    private TranslatorInterface $translator;

    private Picture $picture;

    private TableGateway $articleTable;

    private TableGateway $itemTable;

    private User $userModel;

    public function __construct(
        CommentsService $service,
        HostManager $hostManager,
        MessageService $message,
        TranslatorInterface $translator,
        Picture $picture,
        TableGateway $articleTable,
        TableGateway $itemTable,
        User $userModel
    ) {
        $this->service      = $service;
        $this->hostManager  = $hostManager;
        $this->message      = $message;
        $this->translator   = $translator;
        $this->picture      = $picture;
        $this->articleTable = $articleTable;
        $this->itemTable    = $itemTable;
        $this->userModel    = $userModel;
    }

    /**
     * @throws Exception
     */
    public function getMessageUrl(int $messageId, Uri $uri): string
    {
        $message = $this->service->getMessageRow($messageId);

        if (! $message) {
            throw new InvalidArgumentException("Message `$messageId` not found");
        }

        $route = $this->getMessageRowRoute($message);

        if ($route) {
            $route[0] = ltrim($route[0], '/');
        }

        array_walk($route, function (&$item) {
            $item = urlencode($item);
        });

        $uri->setPath('/' . implode('/', $route));
        $uri->setFragment('msg' . $messageId);

        return $uri->toString();
    }

    /**
     * @param array|ArrayAccess $message
     * @throws Exception
     */
    public function getMessageRowRoute($message): array
    {
        switch ($message['type_id']) {
            case self::PICTURES_TYPE_ID:
                $picture = $this->picture->getRow(['id' => (int) $message['item_id']]);
                if (! $picture) {
                    throw new Exception("Picture `{$message['item_id']}` not found");
                }

                $url = ['/picture', $picture['identity']];
                break;

            case self::ITEM_TYPE_ID:
                $item = $this->itemTable->select(['id' => (int) $message['item_id']])->current();
                if (! $item) {
                    throw new Exception("Item `{$message['item_id']}` not found");
                }
                switch ($item['item_type_id']) {
                    case Item::TWINS:
                        $url = ['/twins', 'group', $item['id']];
                        break;
                    case Item::MUSEUM:
                        $url = ['/museums', $item['id']];
                        break;
                    default:
                        throw new Exception(sprintf(
                            "Failed to build url form message `%s` item_type `%s`",
                            $message['item_id'],
                            $item['item_type_id']
                        ));
                }
                break;

            case self::VOTINGS_TYPE_ID:
                $url = ['/voting', $message['item_id']];
                break;

            case self::ARTICLES_TYPE_ID:
                $article = $this->articleTable->select([
                    'id = ?' => $message['item_id'],
                ])->current();
                if (! $article) {
                    throw new Exception("Article `{$message['item_id']}` not found");
                }
                $url = ['/articles', $article['catname']];
                break;

            case self::FORUMS_TYPE_ID:
                $url = ['/forums', 'message', $message['id']];
                break;

            default:
                throw new Exception("Unknown type_id `{$message['type_id']}`");
        }

        return $url;
    }

    public function getMessagePreview(string $message): string
    {
        return StringUtils::getTextPreview($message, [
            'maxlines'  => 1,
            'maxlength' => self::PREVIEW_LENGTH,
        ]);
    }

    public function service(): CommentsService
    {
        return $this->service;
    }

    public function cleanBrokenMessages(): int
    {
        $affected = 0;

        // pictures
        $rows = $this->service()->getList([
            'type'     => self::PICTURES_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('pictures', 'comment_message.item_id = pictures.id', [], $select::JOIN_LEFT)
                    ->where('pictures.id is null');
            },
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // item
        $rows = $this->service()->getList([
            'type'     => self::ITEM_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('item', 'comment_message.item_id = item.id', [], $select::JOIN_LEFT)
                    ->where('item.id is null');
            },
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // votings
        $rows = $this->service()->getList([
            'type'     => self::VOTINGS_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('voting', 'comment_message.item_id = voting.id', [], $select::JOIN_LEFT)
                    ->where('voting.id is null');
            },
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // articles
        $rows = $this->service()->getList([
            'type'     => self::ARTICLES_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('articles', 'comment_message.item_id = articles.id', [], $select::JOIN_LEFT)
                    ->where('articles.id is null');
            },
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // forums
        $rows = $this->service()->getList([
            'type'     => self::FORUMS_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('forums_topics', 'comment_message.item_id = forums_topics.id', [], $select::JOIN_LEFT)
                    ->where('forums_topics.id is null');
            },
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        return $affected;
    }

    /**
     * @throws Exception
     */
    public function notifySubscribers(int $messageId): void
    {
        $comment = $this->service->getMessageRow($messageId);

        if (! $comment) {
            return;
        }

        $author = $this->userModel->getRow(['id' => (int) $comment['author_id']]);

        if (! $author) {
            return;
        }

        $ids         = $this->service->getSubscribersIds($comment['type_id'], $comment['item_id'], true);
        $subscribers = $this->userModel->getRows(['id' => $ids]);

        foreach ($subscribers as $subscriber) {
            if ((int) $subscriber['id'] === (int) $author['id']) {
                continue;
            }

            $uri = $this->hostManager->getUriByLanguage($subscriber['language']);
            $uri->setPath('/users/' . ($author['identity'] ? $author['identity'] : 'user' . $author['id']));
            $userUrl = $uri->toString();

            $url = $this->getMessageUrl($messageId, $uri);

            $message = sprintf(
                $this->translator->translate('pm/user-%s-post-new-message-%s', 'default', $subscriber['language']),
                $userUrl,
                $url
            );
            $this->message->send(null, $subscriber['id'], $message);

            $this->service->setSubscriptionSent($comment['type_id'], $comment['item_id'], $subscriber['id'], true);
        }
    }
}
