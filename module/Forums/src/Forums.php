<?php

namespace Autowp\Forums;

use Application\Comments as AppComments;
use Autowp\Comments;
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
