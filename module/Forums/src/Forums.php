<?php

namespace Autowp\Forums;

use Application\Comments as AppComments;
use Autowp\Comments;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\TableGateway\TableGateway;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;

/**
 * @todo Remove \Application\Comments::FORUMS_TYPE_ID
 */
class Forums
{
    public const TOPICS_PER_PAGE = 20;

    public const STATUS_NORMAL  = 'normal';
    public const STATUS_CLOSED  = 'closed';
    public const STATUS_DELETED = 'deleted';

    private TableGateway $themeTable;

    private TableGateway $topicTable;

    private Comments\CommentsService $comments;

    public function __construct(
        Comments\CommentsService $comments,
        TableGateway $themeTable,
        TableGateway $topicTable
    ) {
        $this->comments   = $comments;
        $this->themeTable = $themeTable;
        $this->topicTable = $topicTable;
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
        $topic = currentFromResultSetInterface($this->topicTable->select(['id' => $topicId]));
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
     * @throws Exception
     */
    public function updateThemeStat(int $themeId): void
    {
        $theme = currentFromResultSetInterface($this->themeTable->select(['id' => (int) $themeId]));
        if (! $theme) {
            return;
        }

        $select = new Sql\Select($this->topicTable->getTable());
        $select
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
            ->where([
                'forums_theme_parent.parent_id = ?' => $theme['id'],
                new Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED]),
            ]);
        $topics = currentFromResultSetInterface($this->topicTable->selectWith($select));

        $messages = $this->comments->getTotalMessagesCount([
            'type' => AppComments::FORUMS_TYPE_ID,
            'callback'
                => function (Sql\Select $select) use ($theme): void {
                    $select
                        ->join('forums_topics', 'comment_message.item_id = forums_topics.id', [])
                        ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                        ->where([
                            'forums_theme_parent.parent_id = ?' => $theme['id'],
                            new Predicate\In('forums_topics.status', [self::STATUS_NORMAL, self::STATUS_CLOSED]),
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
        $theme = currentFromResultSetInterface($this->themeTable->select(['id' => $themeId]));
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

    public function moveTopic(int $topicId, int $themeId): bool
    {
        $topic = currentFromResultSetInterface($this->topicTable->select(['id' => $topicId]));
        if (! $topic) {
            return false;
        }

        $theme = currentFromResultSetInterface($this->themeTable->select(['id' => $themeId]));
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
            $select->where([new Predicate\In('status', $options['status'])]);
        }

        $topic = currentFromResultSetInterface($this->topicTable->selectWith($select));
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

    public function getThemeTable(): TableGateway
    {
        return $this->themeTable;
    }

    public function getTopicTable(): TableGateway
    {
        return $this->topicTable;
    }
}
