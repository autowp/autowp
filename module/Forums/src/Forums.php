<?php

namespace Autowp\Forums;

use Autowp\Comments;
use Autowp\Commons\Db\Table;
use Autowp\User\Model\DbTable\User;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

/**
 * @todo Remove \Application\Comments::FORUMS_TYPE_ID
 */
class Forums
{
    const TOPICS_PER_PAGE = 20;
    const MESSAGES_PER_PAGE = 20;

    const STATUS_NORMAL = 'normal';
    const STATUS_CLOSED = 'closed';
    const STATUS_DELETED = 'deleted';

    /**
     * @var TableGateway
     */
    private $themeTable;

    /**
     * @var TableGateway
     */
    private $topicTable;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    public function __construct(
        Comments\CommentsService $comments,
        TableGateway $themeTable,
        TableGateway $topicTable
    ) {
        $this->comments = $comments;

        $this->themeTable = $themeTable;
        $this->topicTable = $topicTable;
    }

    public function getThemeList($themeId, $isModerator)
    {
        $select = new Sql\Select($this->themeTable->getTable());

        $select->order('position');

        if ($themeId) {
            $select->where(['parent_id = ?' => (int)$themeId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $userTable = new User();

        $themes = [];

        foreach ($this->themeTable->selectWith($select) as $row) {
            $lastTopic = false;
            $lastMessage = false;

            $select = new Sql\Select($this->topicTable->getTable());
            $select
                ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
                ->where([
                    new Sql\Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED]),
                    'forums_theme_parent.parent_id = ?' => $row['id'],
                    'comment_topic.type_id = ?'         => \Application\Comments::FORUMS_TYPE_ID
                ])
                ->order('comment_topic.last_update DESC')
                ->limit(1);

            $lastTopicRow = $this->topicTable->selectWith($select)->current();
            if ($lastTopicRow) {
                $lastTopic = [
                    'id'   => $lastTopicRow['id'],
                    'name' => $lastTopicRow['name']
                ];

                $lastMessageRow = $this->comments->getLastMessageRow(
                    \Application\Comments::FORUMS_TYPE_ID,
                    $lastTopicRow['id']
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow['id'],
                        'date'   => Table\Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                        'author' => $userTable->find($lastMessageRow['author_id'])->current(),
                    ];
                }
            }

            $subthemes = [];

            $select = new Sql\Select($this->themeTable->getTable());
            $select
                ->where(['parent_id = ?' => $row['id']])
                ->order('position');

            if (! $isModerator) {
                $select->where(['not is_moderator']);
            }

            foreach ($this->themeTable->selectWith($select) as $srow) {
                $subthemes[] = [
                    'id'   => $srow['id'],
                    'name' => $srow['name']
                ];
            }

            $themes[] = [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'lastTopic'   => $lastTopic,
                'lastMessage' => $lastMessage,
                'topics'      => $row['topics'],
                'messages'    => $row['messages'],
                'subthemes'   => $subthemes
            ];
        }

        return $themes;
    }

    public function userSubscribed($topicId, $userId)
    {
        return $this->comments->userSubscribed(\Application\Comments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function canSubscribe($topicId, $userId)
    {
        return $this->comments->canSubscribe(\Application\Comments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function canUnSubscribe($topicId, $userId)
    {
        return $this->comments->canUnSubscribe(\Application\Comments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function subscribe($topicId, $userId)
    {
        return $this->comments->subscribe(\Application\Comments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function unSubscribe($topicId, $userId)
    {
        return $this->comments->unSubscribe(\Application\Comments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function open($topicId)
    {
        $this->topicTable->update([
            'status' => self::STATUS_NORMAL
        ], [
            'id = ?' => (int)$topicId
        ]);
    }

    public function close($topicId)
    {
        $this->topicTable->update([
            'status' => self::STATUS_CLOSED
        ], [
            'id = ?' => (int)$topicId
        ]);
    }

    public function delete($topicId)
    {
        $topic = $this->topicTable->select(['id = ?' => (int)$topicId])->current();
        if (! $topic) {
            return false;
        }

        $needAttention = $this->comments->topicHaveModeratorAttention(
            \Application\Comments::FORUMS_TYPE_ID,
            $topic['id']
        );

        if ($needAttention) {
            throw new \Exception("Cannot delete row when moderator attention not closed");
        }

        $this->topicTable->update([
            'status' => self::STATUS_DELETED
        ], [
            'id = ?' => $topic['id']
        ]);

        $this->updateThemeStat($topic['theme_id']);
    }

    public function updateThemeStat($themeId)
    {
        $theme = $this->themeTable->select([
            'id = ?' => (int)$themeId
        ])->current();
        if (! $theme) {
            return false;
        }

        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
            ->where([
                'forums_theme_parent.parent_id = ?' => $theme['id'],
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED])
            ]);
        $topics = $this->topicTable->selectWith($select)->current();

        $messages = $this->comments->getTotalMessagesCount([
            'type'     => \Application\Comments::FORUMS_TYPE_ID,
            'callback' => function ($select) use ($theme) {
                $select
                    ->join('forums_topics', 'comment_message.item_id = forums_topics.id', [])
                    ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                    ->where([
                        'forums_theme_parent.parent_id = ?' => $theme['id'],
                        new Sql\Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED])
                    ]);
            }
        ]);
        $theme = $this->themeTable->update([
            'topics'   => $topics['count'],
            'messages' => $messages
        ], [
            'id = ?' => $theme['id']
        ]);
    }

    public function getTopicList($themeId, $page, $userId)
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
            ->where([
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_CLOSED, self::STATUS_NORMAL]),
                'comment_topic.type_id = ?' => \Application\Comments::FORUMS_TYPE_ID
            ])
            ->order('comment_topic.last_update DESC');

        if ($themeId) {
            $select->where(['forums_topics.theme_id = ?' => (int)$themeId]);
        } else {
            $select->where(['forums_topics.theme_id IS NULL']);
        }

        $paginator = new \Zend\Paginator\Paginator(
            new \Zend\Paginator\Adapter\DbSelect($select, $this->topicTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::TOPICS_PER_PAGE)
            ->setCurrentPageNumber($page);

        $userTable = new User();

        $topics = [];

        foreach ($paginator->getCurrentItems() as $topicRow) {
            $topicPaginator = $this->comments->getMessagePaginator(
                \Application\Comments::FORUMS_TYPE_ID,
                $topicRow['id']
            )
                ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
                ->setPageRange(10);

            $topicPaginator->setCurrentPageNumber($topicPaginator->count());

            if ($userId) {
                $stat = $this->comments->getTopicStatForUser(
                    \Application\Comments::FORUMS_TYPE_ID,
                    $topicRow['id'],
                    $userId
                );
                $messages = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat = $this->comments->getTopicStat(
                    \Application\Comments::FORUMS_TYPE_ID,
                    $topicRow['id']
                );
                $messages = $stat['messages'];
                $newMessages = 0;
            }

            $oldMessages = $messages - $newMessages;

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $this->comments->getLastMessageRow(
                    \Application\Comments::FORUMS_TYPE_ID,
                    $topicRow['id']
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow['id'],
                        'date'   => Table\Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                        'author' => $userTable->find($lastMessageRow['author_id'])->current(),
                    ];
                }
            }

            $topics[] = [
                'id'          => $topicRow['id'],
                'paginator'   => $topicPaginator,
                'name'        => $topicRow['name'],
                'messages'    => $messages,
                'oldMessages' => $oldMessages,
                'newMessages' => $newMessages,
                'addDatetime' => Table\Row::getDateTimeByColumnType('timestamp', $topicRow['add_datetime']),
                'authorId'    => $topicRow['author_id'],
                'lastMessage' => $lastMessage,
                'status'      => $topicRow['status']
            ];
        }

        return [
            'topics'    => $topics,
            'paginator' => $paginator
        ];
    }

    public function getThemePage($themeId, $page, $userId, $isModerator)
    {
        $select = new Sql\Select($this->themeTable->getTable());
        $select->where(['id = ?' => (int)$themeId]);

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $currentTheme = $this->themeTable->selectWith($select)->current();

        $data = [
            'topics'    => [],
            'paginator' => false
        ];
        if ($currentTheme && ! $currentTheme['disable_topics']) {
            $data = $this->getTopicList($currentTheme['id'], $page, $userId);
        }

        $themeData = null;
        if ($currentTheme) {
            $themeData = [
                'id'             => $currentTheme['id'],
                'name'           => $currentTheme['name'],
                'topics'         => $currentTheme['topics'],
                'messages'       => $currentTheme['messages'],
                'disable_topics' => $currentTheme['disable_topics'],
            ];
        }

        return [
            'theme'     => $themeData,
            'topics'    => $data['topics'],
            'paginator' => $data['paginator'],
            'themes'    => $this->getThemeList($themeId, $isModerator)
        ];
    }

    /**
     *
     * @param array $values
     * @throws \Exception
     */
    public function addTopic($values)
    {
        $userId = (int)$values['user_id'];
        if (! $userId) {
            throw new \Exception("User id not provided");
        }

        $theme = $this->getTheme($values['theme_id']);
        if (! $theme || $theme['disable_topics']) {
            return false;
        }

        if (! $values['ip']) {
            $values['ip'] = '127.0.0.1';
        }

        $this->topicTable->insert([
            'theme_id'     => $theme['id'],
            'name'         => $values['name'],
            'author_id'    => $userId,
            'author_ip'    => new Sql\Expression('INET6_ATON(?)', $values['ip']),
            'add_datetime' => new Sql\Expression('NOW()'),
            'views'        => 0,
            'status'       => self::STATUS_NORMAL
        ]);
        $id = $this->topicTable->getLastInsertValue();

        $this->comments->add([
            'typeId'             => \Application\Comments::FORUMS_TYPE_ID,
            'itemId'             => $id,
            'authorId'           => $userId,
            'datetime'           => new Sql\Expression('NOW()'),
            'message'            => $values['text'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool)$values['moderator_attention']
        ]);

        if ($values['subscribe']) {
            $this->subscribe($id, $userId);
        }

        return $id;
    }

    public function getTheme($themeId)
    {
        $theme = $this->themeTable->select([
            'id = ?' => (int)$themeId
        ])->current();
        if (! $theme) {
            return false;
        }

        return [
            'id'             => $theme['id'],
            'name'           => $theme['name'],
            'disable_topics' => $theme['disable_topics'],
            'is_moderator'   => $theme['is_moderator']
        ];
    }

    /**
     * @return array
     */
    public function getThemes()
    {
        $select = new Sql\Select($this->themeTable->getTable());
        $select->order('position');

        $result = [];
        foreach ($this->themeTable->selectWith($select) as $row) {
            $result[] = [
                'id'   => $row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }

    public function getTopics($themeId)
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->where([
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_CLOSED, self::STATUS_NORMAL])
            ])
            ->join(
                'comment_topic',
                new Sql\Expression(
                    'forums_topics.id = comment_topic.item_id and comment_topic.type_id = ?',
                    \Application\Comments::FORUMS_TYPE_ID
                ),
                [],
                $select::JOIN_LEFT
            )
            ->order('comment_topic.last_update DESC');

        if ($themeId) {
            $select->where(['forums_topics.theme_id = ?' => (int)$themeId]);
        } else {
            $select->where(['forums_topics.theme_id IS NULL']);
        }

        $result = [];
        foreach ($this->topicTable->selectWith($select) as $row) {
            $result[] = [
                'id'   => $row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }

    public function moveMessage($messageId, $topicId)
    {
        $topic = $this->topicTable->select([
            'id = ?' => (int)$topicId
        ])->current();
        if (! $topic) {
            return false;
        }

        $this->comments->moveMessage($messageId, \Application\Comments::FORUMS_TYPE_ID, $topic['id']);

        return true;
    }

    public function moveTopic($topicId, $themeId)
    {
        $topic = $this->topicTable->select([
            'id = ?' => (int)$topicId
        ])->current();
        if (! $topic) {
            return false;
        }

        $theme = $this->themeTable->select([
            'id = ?' => (int)$themeId
        ])->current();
        if (! $theme) {
            return false;
        }

        $oldThemeId = $topic['theme_id'];

        $this->topicTable->update([
            'theme_id' => $theme['id']
        ], [
            'id = ?' => $topic['id']
        ]);

        $this->updateThemeStat($theme['id']);
        $this->updateThemeStat($oldThemeId);

        return true;
    }

    public function getTopic($topicId, array $options = [])
    {
        $defaults = [
            'isModerator' => null,
            'status'      => null
        ];
        $options = array_replace($defaults, $options);

        $select = new Sql\Select($this->topicTable->getTable());
        $select->where(['forums_topics.id = ?' => (int)$topicId]);

        if ($options['isModerator'] !== null) {
            if (! $options['isModerator']) {
                $select
                    ->join('forums_themes', 'forums_topics.theme_id = forums_themes.id', [])
                    ->where(['not forums_themes.is_moderator']);
            }
        }

        if ($options['status']) {
            $select->where([new Sql\Predicate\In('status', $options['status'])]);
        }

        $topic = $this->topicTable->selectWith($select)->current();
        if (! $topic) {
            return false;
        }

        return [
            'id'       => $topic['id'],
            'name'     => $topic['name'],
            'theme_id' => $topic['theme_id'],
            'status'   => $topic['status'],
        ];
    }

    public function getMessagePage($messageId)
    {
        $message = $this->comments->getMessageRow($messageId);
        if (! $message) {
            return false;
        }

        if ($message['type_id'] != \Application\Comments::FORUMS_TYPE_ID) {
            return false;
        }

        $topic = $this->topicTable->select([
            'id = ?' => $message['item_id']
        ])->current();
        if (! $topic) {
            return false;
        }

        $page = $this->comments->getMessagePage($message, self::MESSAGES_PER_PAGE);

        return [
            'page'     => $page,
            'topic_id' => $topic['id']
        ];
    }

    public function registerTopicView($topicId, $userId)
    {
        $this->topicTable->update([
            'views' => new Sql\Expression('views+1')
        ], [
            'id = ?' => (int)$topicId
        ]);

        if ($userId) {
            $this->comments->updateTopicView(
                \Application\Comments::FORUMS_TYPE_ID,
                $topicId,
                $userId
            );
        }
    }

    public function topicPage($topicId, $userId, $page, $isModearator)
    {
        $topic = $this->getTopic($topicId, [
            'status'      => [self::STATUS_NORMAL, self::STATUS_CLOSED],
            'isModerator' => $isModearator
        ]);
        if (! $topic) {
            return false;
        }

        $theme = $this->getTheme($topic['theme_id']);
        if (! $theme) {
            return false;
        }

        $this->registerTopicView($topic['id'], $userId);

        $messages = $this->comments->get(
            \Application\Comments::FORUMS_TYPE_ID,
            $topic['id'],
            $userId,
            self::TOPICS_PER_PAGE,
            $page
        );

        $paginator = $this->comments->getPaginator(
            \Application\Comments::FORUMS_TYPE_ID,
            $topic['id'],
            self::TOPICS_PER_PAGE,
            $page
        );

        $subscribed = false;
        if ($userId) {
            $subscribed = $this->userSubscribed($topic['id'], $userId);
        }

        return [
            'topic'      => $topic,
            'theme'      => $theme,
            'paginator'  => $paginator,
            'messages'   => $messages,
            'subscribed' => $subscribed
        ];
    }

    public function addMessage($values)
    {
        if (! $values['ip']) {
            $values['ip'] = '127.0.0.1';
        }

        $messageId = $this->comments->add([
            'typeId'             => \Application\Comments::FORUMS_TYPE_ID,
            'itemId'             => $values['topic_id'],
            'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
            'authorId'           => $values['user_id'],
            'message'            => $values['message'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool)$values['moderator_attention']
        ]);

        if (! $messageId) {
            throw new \Exception("Message add fails");
        }

        if ($values['resolve'] && $values['parent_id']) {
            $this->comments->completeMessage($values['parent_id']);
        }

        return $messageId;
    }

    public function getSubscribersIds($topicId)
    {
        return $this->comments->getSubscribersIds(\Application\Comments::FORUMS_TYPE_ID, $topicId);
    }

    public function getSubscribedTopics($userId)
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->join(
                'comment_topic_subscribe',
                'forums_topics.id = comment_topic_subscribe.item_id',
                []
            )
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
            ->where([
                'comment_topic_subscribe.user_id' => (int)$userId,
                'comment_topic.type_id'           => \Application\Comments::FORUMS_TYPE_ID,
                'comment_topic_subscribe.type_id' => \Application\Comments::FORUMS_TYPE_ID,
            ])
            ->order('comment_topic.last_update DESC');
        $rows = $this->topicTable->selectWith($select);

        $userTable = new User();

        $topics = [];
        foreach ($rows as $row) {
            $stat = $this->comments->getTopicStatForUser(
                \Application\Comments::FORUMS_TYPE_ID,
                $row['id'],
                $userId
            );
            $messages = $stat['messages'];
            $newMessages = $stat['newMessages'];

            $oldMessages = $messages - $newMessages;

            $theme = $this->getTheme($row['theme_id']);

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $this->comments->getLastMessageRow(
                    \Application\Comments::FORUMS_TYPE_ID,
                    $row['id']
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow['id'],
                        'date'   => Table\Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                        'author' => $userTable->find($lastMessageRow['author_id'])->current(),
                    ];
                }
            }

            $topics[] = [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'messages'    => $messages,
                'oldMessages' => $oldMessages,
                'newMessages' => $newMessages,
                'theme'       => $theme,
                'authorId'    => $row['author_id'],
                'lastMessage' => $lastMessage,
            ];
        }

        return $topics;
    }
}
