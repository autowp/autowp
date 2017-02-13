<?php

namespace Application;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Router\Http\TreeRouteStack;

use Autowp\Comments\CommentsService;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\DbTable;
use Application\StringUtils;

use Exception;
use InvalidArgumentException;

class Comments
{
    const PREVIEW_LENGTH = 60;

    const PICTURES_TYPE_ID = 1;
    const ITEM_TYPE_ID = 2;
    const VOTINGS_TYPE_ID = 3;
    const ARTICLES_TYPE_ID = 4;
    const FORUMS_TYPE_ID = 5;

    /**
     * @var CommentsService
     */
    private $service;

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @var Adapter
     */
    private $adapter;
    
    /**
     * @var HostManager
     */
    private $hostManager;
    
    /**
     * @var MessageService
     */
    private $message;
    
    private $translator;

    public function __construct(
        CommentsService $service,
        TreeRouteStack $router,
        Adapter $adapter,
        HostManager $hostManager,
        MessageService $message,
        $translator
    ) {
        $this->service = $service;
        $this->router = $router;
        $this->adapter = $adapter;
        $this->hostManager = $hostManager;
        $this->message = $message;
        $this->translator = $translator;
    }

    public function getMessageUrl($messageId, $canonical = false, $uri = null)
    {
        $message = $this->service->getMessageRow($messageId);

        if (! $message) {
            throw new InvalidArgumentException("Message `$messageId` not found");
        }

        return $this->getMessageRowUrl($message, $canonical, $uri);
    }

    public function getMessageRowUrl($message, $canonical = false, $uri = null)
    {
        $url = null;

        switch ($message['type_id']) {
            case self::PICTURES_TYPE_ID:
                $pictureTable = new DbTable\Picture();
                $picture = $pictureTable->find($message['item_id'])->current();
                if (! $picture) {
                    throw new Exception("Picture `{$message['item_id']}` not found");
                }

                $url = $this->router->assemble([
                    'picture_id' => $picture['identity']
                ], [
                    'name'            => 'picture/picture',
                    'force_canonical' => $canonical,
                    'uri'             => $uri
                ]);
                break;

            case self::ITEM_TYPE_ID:
                $itemTable = new DbTable\Item();
                $item = $itemTable->find($message['item_id'])->current();
                if (! $item) {
                    throw new Exception("Item `{$message['item_id']}` not found");
                }
                switch ($item['item_type_id']) {
                    case DbTable\Item\Type::TWINS:
                        $url = $this->router->assemble([
                            'id' => $item['id']
                        ], [
                            'name'            => 'twins/group',
                            'force_canonical' => $canonical,
                            'uri'             => $uri
                        ]);
                        break;
                    case DbTable\Item\Type::MUSEUM:
                        $url = $this->router->assemble([
                            'id' => $item['id']
                        ], [
                            'name'            => 'museums/museum',
                            'force_canonical' => $canonical,
                            'uri'             => $uri
                        ]);
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
                $url = $this->router->assemble([
                    'id' => $message['item_id']
                ], [
                    'name'            => 'votings/voting',
                    'force_canonical' => $canonical,
                    'uri'             => $uri
                ]);
                break;

            case self::ARTICLES_TYPE_ID:
                $articleTable = new TableGateway('articles', $this->adapter);
                $article = $articleTable->select([
                    'id = ?' => $message['item_id']
                ])->current();
                if (! $article) {
                    throw new Exception("Article `{$message['item_id']}` not found");
                }
                $url = $this->router->assemble([
                    'action'          => 'article',
                    'article_catname' => $article['catname']
                ], [
                    'name'            => 'articles',
                    'force_canonical' => $canonical,
                    'uri'             => $uri
                ]);
                break;

            case self::FORUMS_TYPE_ID:
                $url = $this->router->assemble([
                    'message_id' => $message['id']
                ], [
                    'name'            => 'forums/topic-message',
                    'force_canonical' => $canonical,
                    'uri'             => $uri
                ]);
                break;

            default:
                throw new Exception("Unknown type_id `{$message['type_id']}`");
        }

        return $url;
    }

    public function getMessagePreview($message)
    {
        return StringUtils::getTextPreview($message, [
            'maxlines'  => 1,
            'maxlength' => self::PREVIEW_LENGTH
        ]);
    }

    public function service()
    {
        return $this->service;
    }

    public function cleanBrokenMessages()
    {
        $affected = 0;

        // pictures
        $rows = $this->service()->getList([
            'type' => self::PICTURES_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('pictures', 'comment_message.item_id = pictures.id', [], $select::JOIN_LEFT)
                    ->where('pictures.id is null');
            }
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // item
        $rows = $this->service()->getList([
            'type' => self::ITEM_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('item', 'comment_message.item_id = item.id', [], $select::JOIN_LEFT)
                    ->where('item.id is null');
            }
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // votings
        $rows = $this->service()->getList([
            'type' => self::VOTINGS_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('voting', 'comment_message.item_id = voting.id', [], $select::JOIN_LEFT)
                    ->where('voting.id is null');
            }
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // articles
        $rows = $this->service()->getList([
            'type' => self::ARTICLES_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('articles', 'comment_message.item_id = articles.id', [], $select::JOIN_LEFT)
                    ->where('articles.id is null');
            }
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        // forums
        $rows = $this->service()->getList([
            'type' => self::FORUMS_TYPE_ID,
            'callback' => function (Sql\Select $select) {
                $select
                    ->join('forums_topics', 'comment_message.item_id = forums_topics.id', [], $select::JOIN_LEFT)
                    ->where('forums_topics.id is null');
            }
        ]);
        foreach ($rows as $row) {
            $affected += $this->service()->deleteMessage($row['id']);
        }

        return $affected;
    }
    
    public function notifySubscribers($messageId)
    {
        $comment = $this->service->getMessageRow($messageId);
        
        if (! $comment) {
            return false;
        }
        
        $userTable = new User();
        
        $author = $userTable->find($comment['author_id'])->current();
        
        if (! $author) {
            return false;
        }
    
        $ids = $this->service->getSubscribersIds($comment['type_id'], $comment['item_id'], true);
        $subscribers = $userTable->find($ids);
    
        foreach ($subscribers as $subscriber) {
            if ($subscriber->id == $author->id) {
                continue;
            }
    
            $uri = $this->hostManager->getUriByLanguage($subscriber->language);
    
            $url = $this->getMessageUrl($messageId, true, $uri) . '#msg' . $messageId;
            
            $userUrl = $this->router->assemble([
                'user_id' => $author->identity ? $author->identity : 'user' . $author->id
            ], [
                'name'            => 'users/user',
                'force_canonical' => true,
                'uri'             => $uri
            ]);
            
            $message = sprintf(
                $this->translator->translate('pm/user-%s-post-new-message-%s', 'default', $subscriber->language),
                $userUrl,
                $url
            );
            $this->message->send(null, $subscriber->id, $message);
            
            $this->service->markSubscriptionSent($comment['type_id'], $comment['item_id'], $subscriber->id);
        }
    }
}
