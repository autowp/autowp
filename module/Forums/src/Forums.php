<?php

namespace Autowp\Forums;

use Application\Comments as AppComments;
use Autowp\Comments;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator;

use function array_replace;

/**
 * @todo Remove \Application\Comments::FORUMS_TYPE_ID
 */
class Forums
{
    public const TOPICS_PER_PAGE    = 20;
    private const MESSAGES_PER_PAGE = 20;

    public const STATUS_NORMAL  = 'normal';
    public const STATUS_CLOSED  = 'closed';
    public const STATUS_DELETED = 'deleted';

    private TableGateway $themeTable;

    private TableGateway $topicTable;

    private Comments\CommentsService $comments;

    private User $userModel;

    public function __construct(
        Comments\CommentsService $comments,
        TableGateway $themeTable,
        TableGateway $topicTable,
        User $userModel
    ) {
        $this->comments = $comments;

        $this->themeTable = $themeTable;
        $this->topicTable = $topicTable;
        $this->userModel  = $userModel;
    }

    public function userSubscribed(int $topicId, int $userId): bool
    {
        return $this->comments->userSubscribed(AppComments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function canSubscribe(int $topicId, int $userId): bool
    {
        return $this->comments->canSubscribe(AppComments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function canUnSubscribe(int $topicId, int $userId): bool
    {
        return $this->comments->canUnSubscribe(AppComments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    /**
     * @throws Exception
     */
    public function subscribe(int $topicId, int $userId): void
    {
        $this->comments->subscribe(AppComments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    /**
     * @throws Exception
     */
    public function unsubscribe(int $topicId, int $userId): void
    {
        $this->comments->unSubscribe(AppComments::FORUMS_TYPE_ID, $topicId, $userId);
    }

    public function open(int $topicId): void
    {
        $this->topicTable->update([
            'status' => self::STATUS_NORMAL,
        ], [
            'id = ?' => $topicId,
        ]);
    }

    public function close(int $topicId): void
    {
        $this->topicTable->update([
            'status' => self::STATUS_CLOSED,
        ], [
            'id = ?' => $topicId,
        ]);
    }

    public function delete(int $topicId): bool
    {
        $topic = $this->topicTable->select(['id' => $topicId])->current();
        if (! $topic) {
            return false;
        }

        $needAttention = $this->comments->topicHaveModeratorAttention(
            AppComments::FORUMS_TYPE_ID,
            $topic['id']
        );

        if ($needAttention) {
            return false;
        }

        $this->topicTable->update([
            'status' => self::STATUS_DELETED,
        ], [
            'id = ?' => $topic['id'],
        ]);

        $this->updateThemeStat($topic['theme_id']);

        return true;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     */
    public function updateThemeStat(int $themeId): void
    {
        $theme = $this->themeTable->select([
            'id = ?' => (int) $themeId,
        ])->current();
        if (! $theme) {
            return;
        }

        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
            ->where([
                'forums_theme_parent.parent_id = ?' => $theme['id'],
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED]),
            ]);
        $topics = $this->topicTable->selectWith($select)->current();

        $messages = $this->comments->getTotalMessagesCount([
            'type' => AppComments::FORUMS_TYPE_ID,
            /**
                 * @suppress PhanPluginMixedKeyNoKey
                 */
            'callback'
                => function (Sql\Select $select) use ($theme): void {
                    $select
                        ->join('forums_topics', 'comment_message.item_id = forums_topics.id', [])
                        ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                        ->where([
                            'forums_theme_parent.parent_id = ?' => $theme['id'],
                            new Sql\Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED]),
                        ]);
                },
        ]);
        $this->themeTable->update([
            'topics'   => $topics['count'],
            'messages' => $messages,
        ], [
            'id = ?' => $theme['id'],
        ]);
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @throws Exception
     */
    public function getTopicList(int $themeId, int $page, int $userId): array
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
            ->where([
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_CLOSED, self::STATUS_NORMAL]),
                'comment_topic.type_id = ?' => AppComments::FORUMS_TYPE_ID,
            ])
            ->order('comment_topic.last_update DESC');

        if ($themeId) {
            $select->where(['forums_topics.theme_id = ?' => (int) $themeId]);
        } else {
            $select->where(['forums_topics.theme_id IS NULL']);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->topicTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::TOPICS_PER_PAGE)
            ->setCurrentPageNumber($page);

        $topics = [];

        foreach ($paginator->getCurrentItems() as $topicRow) {
            if ($userId) {
                $stat        = $this->comments->getTopicStatForUser(
                    AppComments::FORUMS_TYPE_ID,
                    $topicRow['id'],
                    $userId
                );
                $messages    = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat        = $this->comments->getTopicStat(
                    AppComments::FORUMS_TYPE_ID,
                    $topicRow['id']
                );
                $messages    = $stat['messages'];
                $newMessages = 0;
            }

            $oldMessages = $messages - $newMessages;

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $this->comments->getLastMessageRow(
                    AppComments::FORUMS_TYPE_ID,
                    $topicRow['id']
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow['id'],
                        'date'   => Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                        'author' => $this->userModel->getRow(['id' => (int) $lastMessageRow['author_id']]),
                    ];
                }
            }

            $topics[] = [
                'id'          => $topicRow['id'],
                'name'        => $topicRow['name'],
                'messages'    => $messages,
                'oldMessages' => $oldMessages,
                'newMessages' => $newMessages,
                'addDatetime' => Row::getDateTimeByColumnType('timestamp', $topicRow['add_datetime']),
                'authorId'    => $topicRow['author_id'],
                'lastMessage' => $lastMessage,
                'status'      => $topicRow['status'],
            ];
        }

        return [
            'topics'    => $topics,
            'paginator' => $paginator,
        ];
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    public function getThemePage(int $themeId, int $page, int $userId, bool $isModerator): array
    {
        $select = new Sql\Select($this->themeTable->getTable());
        $select->where(['id = ?' => $themeId]);

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $currentTheme = $this->themeTable->selectWith($select)->current();

        $data = [
            'topics'    => [],
            'paginator' => false,
        ];
        if ($currentTheme && ! $currentTheme['disable_topics']) {
            $data = $this->getTopicList($currentTheme['id'], $page, $userId);
        }

        $themeData = null;
        if ($currentTheme) {
            $themeData = [
                'id'             => $currentTheme['id'],
                'name'           => $currentTheme['name'],
                'topics'         => (int) $currentTheme['topics'],
                'messages'       => (int) $currentTheme['messages'],
                'disable_topics' => (bool) $currentTheme['disable_topics'],
            ];
        }

        return [
            'theme'     => $themeData,
            'topics'    => $data['topics'],
            'paginator' => $data['paginator']->getPages(),
        ];
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @throws Exception
     */
    public function addTopic(array $values): int
    {
        $userId = (int) $values['user_id'];
        if (! $userId) {
            throw new Exception("User id not provided");
        }

        $theme = $this->getTheme($values['theme_id']);
        if (! $theme || $theme['disable_topics']) {
            return 0;
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
            'status'       => self::STATUS_NORMAL,
        ]);
        $id = $this->topicTable->getLastInsertValue();

        $this->comments->add([
            'typeId'             => AppComments::FORUMS_TYPE_ID,
            'itemId'             => $id,
            'authorId'           => $userId,
            'datetime'           => new Sql\Expression('NOW()'),
            'message'            => $values['text'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool) $values['moderator_attention'],
        ]);

        if ($values['subscription']) {
            $this->subscribe($id, $userId);
        }

        $this->updateThemeStat($theme['id']);

        return $id;
    }

    public function getTheme(int $themeId): ?array
    {
        $theme = $this->themeTable->select([
            'id = ?' => $themeId,
        ])->current();
        if (! $theme) {
            return null;
        }

        return [
            'id'             => $theme['id'],
            'name'           => $theme['name'],
            'disable_topics' => $theme['disable_topics'],
            'is_moderator'   => $theme['is_moderator'],
        ];
    }

    public function getThemes(): array
    {
        $select = new Sql\Select($this->themeTable->getTable());
        $select->order('position');

        $result = [];
        foreach ($this->themeTable->selectWith($select) as $row) {
            $result[] = [
                'id'   => $row['id'],
                'name' => $row['name'],
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function getTopics(int $themeId): array
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->where([
                new Sql\Predicate\In('forums_topics.status', [self::STATUS_CLOSED, self::STATUS_NORMAL]),
            ])
            ->join(
                'comment_topic',
                new Sql\Expression(
                    'forums_topics.id = comment_topic.item_id and comment_topic.type_id = ?',
                    AppComments::FORUMS_TYPE_ID
                ),
                [],
                $select::JOIN_LEFT
            )
            ->order('comment_topic.last_update DESC');

        if ($themeId) {
            $select->where(['forums_topics.theme_id = ?' => (int) $themeId]);
        } else {
            $select->where(['forums_topics.theme_id IS NULL']);
        }

        $result = [];
        foreach ($this->topicTable->selectWith($select) as $row) {
            $result[] = [
                'id'   => $row['id'],
                'name' => $row['name'],
            ];
        }

        return $result;
    }

    public function moveMessage(int $messageId, int $topicId): bool
    {
        $topic = $this->topicTable->select([
            'id' => $topicId,
        ])->current();
        if (! $topic) {
            return false;
        }

        $this->comments->moveMessage($messageId, AppComments::FORUMS_TYPE_ID, $topic['id']);

        return true;
    }

    public function moveTopic(int $topicId, int $themeId): bool
    {
        $topic = $this->topicTable->select([
            'id' => $topicId,
        ])->current();
        if (! $topic) {
            return false;
        }

        $theme = $this->themeTable->select([
            'id' => $themeId,
        ])->current();
        if (! $theme) {
            return false;
        }

        $oldThemeId = $topic['theme_id'];

        $this->topicTable->update([
            'theme_id' => $theme['id'],
        ], [
            'id' => $topic['id'],
        ]);

        $this->updateThemeStat($theme['id']);
        $this->updateThemeStat($oldThemeId);

        return true;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function getTopic(int $topicId, array $options = []): ?array
    {
        $defaults = [
            'isModerator' => null,
            'status'      => null,
        ];
        $options  = array_replace($defaults, $options);

        $select = new Sql\Select($this->topicTable->getTable());
        $select->where(['forums_topics.id = ?' => $topicId]);

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
            return null;
        }

        return [
            'id'       => $topic['id'],
            'name'     => $topic['name'],
            'theme_id' => $topic['theme_id'],
            'status'   => $topic['status'],
        ];
    }

    public function getMessagePage(int $messageId): ?array
    {
        $message = $this->comments->getMessageRow($messageId);
        if (! $message) {
            return null;
        }

        if ((int) $message['type_id'] !== AppComments::FORUMS_TYPE_ID) {
            return null;
        }

        $topic = $this->topicTable->select([
            'id = ?' => $message['item_id'],
        ])->current();
        if (! $topic) {
            return null;
        }

        $page = $this->comments->getMessagePage($message, self::MESSAGES_PER_PAGE);

        return [
            'page'     => $page,
            'topic_id' => (int) $topic['id'],
        ];
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function registerTopicView(int $topicId, int $userId): void
    {
        $this->topicTable->update([
            'views' => new Sql\Expression('views+1'),
        ], [
            'id' => $topicId,
        ]);

        if ($userId) {
            $this->comments->updateTopicView(
                AppComments::FORUMS_TYPE_ID,
                $topicId,
                $userId
            );
        }
    }

    /**
     * @throws Exception
     */
    public function topicPage(int $topicId, int $userId, int $page, bool $isModerator): ?array
    {
        $topic = $this->getTopic($topicId, [
            'status'      => [self::STATUS_NORMAL, self::STATUS_CLOSED],
            'isModerator' => $isModerator,
        ]);
        if (! $topic) {
            return null;
        }

        $theme = $this->getTheme($topic['theme_id']);
        if (! $theme) {
            return null;
        }

        $this->registerTopicView($topic['id'], $userId);

        $messages = $this->comments->get(
            AppComments::FORUMS_TYPE_ID,
            $topic['id'],
            $userId ? $userId : 0,
            self::TOPICS_PER_PAGE,
            $page
        );

        $paginator = $this->comments->getPaginator(
            AppComments::FORUMS_TYPE_ID,
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
            'subscribed' => $subscribed,
        ];
    }

    /**
     * @throws Exception
     */
    public function addMessage(array $values): int
    {
        if (! $values['ip']) {
            $values['ip'] = '127.0.0.1';
        }

        $messageId = (int) $this->comments->add([
            'typeId'             => AppComments::FORUMS_TYPE_ID,
            'itemId'             => $values['topic_id'],
            'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
            'authorId'           => $values['user_id'],
            'message'            => $values['message'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool) $values['moderator_attention'],
        ]);

        if (! $messageId) {
            throw new Exception("Message add fails");
        }

        if ($values['resolve'] && $values['parent_id']) {
            $this->comments->completeMessage($values['parent_id']);
        }

        return $messageId;
    }

    public function getSubscribersIds(int $topicId): array
    {
        return $this->comments->getSubscribersIds(AppComments::FORUMS_TYPE_ID, $topicId);
    }

    public function getSubscribedTopics(int $userId): array
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
                'comment_topic_subscribe.user_id' => $userId,
                'comment_topic.type_id'           => AppComments::FORUMS_TYPE_ID,
                'comment_topic_subscribe.type_id' => AppComments::FORUMS_TYPE_ID,
            ])
            ->order('comment_topic.last_update DESC');
        $rows = $this->topicTable->selectWith($select);

        $topics = [];
        foreach ($rows as $row) {
            $stat        = $this->comments->getTopicStatForUser(
                AppComments::FORUMS_TYPE_ID,
                $row['id'],
                $userId
            );
            $messages    = $stat['messages'];
            $newMessages = $stat['newMessages'];

            $oldMessages = $messages - $newMessages;

            $theme = $this->getTheme($row['theme_id']);

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $this->comments->getLastMessageRow(
                    AppComments::FORUMS_TYPE_ID,
                    $row['id']
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow['id'],
                        'date'   => Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                        'author' => $this->userModel->getRow(['id' => (int) $lastMessageRow['author_id']]),
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

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     */
    public function getSubscribedTopicsCount(int $userId): int
    {
        $select = new Sql\Select($this->topicTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->join(
                'comment_topic_subscribe',
                'forums_topics.id = comment_topic_subscribe.item_id',
                []
            )
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
            ->where([
                'comment_topic_subscribe.user_id' => $userId,
                'comment_topic.type_id'           => AppComments::FORUMS_TYPE_ID,
                'comment_topic_subscribe.type_id' => AppComments::FORUMS_TYPE_ID,
            ]);
        $row = $this->topicTable->selectWith($select)->current();

        return $row ? (int) $row['count'] : 0;
    }

    public function getThemeTable(): TableGateway
    {
        return $this->themeTable;
    }

    public function getTopicTable(): TableGateway
    {
        return $this->topicTable;
    }
}
