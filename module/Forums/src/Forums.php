<?php

namespace Autowp\Forums;

use Application\Comments as AppComments;
use Autowp\Comments;
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
