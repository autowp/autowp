<?php

namespace Application\Model;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Zend_Db_Expr;

use Comments;
use Comment_Message;
use Comment_Topic;
use Project_Db_Table;

class Forums
{
    const TOPICS_PER_PAGE = 20;
    const MESSAGES_PER_PAGE = 20;

    const STATUS_NORMAL = 'normal';
    const STATUS_CLOSED = 'closed';
    const STATUS_DELETED = 'deleted';

    /**
     * @var Project_Db_Table
     */
    private $_themeTable;

    /**
     * @var Project_Db_Table
     */
    private $_topicTable;

    /**
     * @var Project_Db_Table
     */
    private $_subscriberTable;

    public function __construct()
    {
        $this->_themeTable = new Project_Db_Table([
            'name'    => 'forums_themes',
            'primary' => 'id'
        ]);
        $this->_topicTable = new Project_Db_Table([
            'name'    => 'forums_topics',
            'primary' => 'id'
        ]);
        $this->_subscriberTable = new Project_Db_Table([
            'name'    => 'forums_topics_subscribers',
            'primary' => ['user_id', 'topic_id']
        ]);
    }

    public function getThemeList($themeId, $isModerator)
    {
        $comments = new Comments();

        $select = $this->_themeTable->select(true)
            ->order('position');

        if ($themeId) {
            $select->where('parent_id = ?', (int)$themeId);
        } else {
            $select->where('parent_id IS NULL');
        }

        if (!$isModerator) {
            $select->where('not is_moderator');
        }

        $themes = array();

        foreach ($this->_themeTable->fetchAll($select) as $row) {
            $lastTopic = false;
            $lastMessage = false;
            $lastTopicRow = $this->_topicTable->fetchRow(
                $this->_topicTable->select(true)
                    ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', null)
                    ->where('forums_topics.status IN (?)', array(self::STATUS_NORMAL, self::STATUS_CLOSED))
                    ->where('forums_theme_parent.parent_id = ?', $row->id)
                    ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
                    ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                    ->order('comment_topic.last_update DESC')
            );
            if ($lastTopicRow) {
                $lastTopic = array(
                    'id'   => $lastTopicRow->id,
                    'name' => $lastTopicRow->caption
                );

                $lastMessageRow = $comments->getLastMessageRow(Comment_Message::FORUMS_TYPE_ID, $lastTopicRow->id);
                if ($lastMessageRow) {
                    $lastMessage = array(
                        'id'     => $lastMessageRow->id,
                        'date'   => $lastMessageRow->getDateTime('datetime'),
                        'author' => $lastMessageRow->findParentUsersByAuthor()
                    );
                }
            }

            $subthemes = array();

            $select = $this->_themeTable->select(true)
                ->where('parent_id = ?', $row->id)
                ->order('position');

            if (!$isModerator) {
                $select->where('not is_moderator');
            }

            foreach ($this->_themeTable->fetchAll($select) as $srow) {
                $subthemes[] = array(
                    'id'   => $srow->id,
                    'name' => $srow->caption
                );
            }

            $themes[] = array(
                'id'          => $row->id,
                'name'        => $row->caption,
                'description' => $row->description,
                'lastTopic'   => $lastTopic,
                'lastMessage' => $lastMessage,
                'topics'      => $row->topics,
                'messages'    => $row->messages,
                'subthemes'   => $subthemes
            );
        }

        return $themes;
    }

    public function userSubscribed($topicId, $userId)
    {
        return (bool)$this->_subscriberTable->fetchRow(
            $this->_subscriberTable->select(true)
                ->where('topic_id = ?', (int)$topicId)
                ->where('user_id = ?', (int)$userId)
        );
    }

    public function canSubscribe($topicId, $userId)
    {
        return !$this->userSubscribed($topicId, $userId);
    }

    public function canUnSubscribe($topicId, $userId)
    {
        return $this->userSubscribed($topicId, $userId);
    }

    public function subscribe($topicId, $userId)
    {
        if (!$this->canSubscribe($topicId, $userId)) {
            throw new \Exception('Пользователь уже подписан');
        }

        $this->_subscriberTable->insert(array(
            'topic_id' => (int)$topicId,
            'user_id'  => (int)$userId
        ));
    }

    public function unSubscribe($topicId, $userId)
    {
        if (!$this->canUnSubscribe($topicId, $userId)) {
            throw new \Exception('Пользователь не подписан');
        }

        $this->_subscriberTable->delete(array(
            'topic_id = ?' => (int)$topicId,
            'user_id = ?'  => (int)$userId
        ));
    }

    public function open($topicId)
    {
        $topic = $this->_topicTable->find($topicId)->current();
        if ($topic) {
            $topic->status = self::STATUS_NORMAL;
            $topic->save();
        }
    }

    public function close($topicId)
    {
        $topic = $this->_topicTable->find($topicId)->current();
        if ($topic) {
            $topic->status = self::STATUS_CLOSED;
            $topic->save();
        }
    }

    public function delete($topicId)
    {
        $topic = $this->_topicTable->find($topicId)->current();
        if (!$topic) {
            return false;
        }

        $theme = $this->_themeTable->find($topic->theme_id)->current();
        if (!$theme) {
            return false;
        }

        $comments = new Comments();
        $needAttention = $comments->topicHaveModeratorAttention(Comment_Message::FORUMS_TYPE_ID, $topic->id);

        if ($needAttention) {
            throw new \Exception("Cannot delete row when moderator attention not closed");
        }

        $topic->status = self::STATUS_DELETED;
        $topic->save();

        $this->updateThemeStat($theme->id);
    }

    public function updateThemeStat($themeId)
    {
        $theme = $this->_themeTable->find($themeId)->current();
        if (!$theme) {
            return false;
        }

        $db = $this->_topicTable->getAdapter();

        $theme->topics = $db->fetchOne(
            $db->select()
                ->from($this->_topicTable->info('name'), new Zend_Db_Expr('COUNT(1)'))
                ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', null)
                ->where('forums_theme_parent.parent_id = ?', $theme->id)
                ->where('forums_topics.status IN (?)', array(self::STATUS_NORMAL, self::STATUS_CLOSED))
        );

        $messages = new Comment_Message();

        $db = $messages->getAdapter();

        $theme->messages = $db->fetchOne(
            $db->select()
                ->from($messages->info('name'), new Zend_Db_Expr('COUNT(1)'))
                ->join('forums_topics', 'comments_messages.item_id = forums_topics.id', null)
                ->where('comments_messages.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', null)
                ->where('forums_theme_parent.parent_id = ?', $theme->id)
                ->where('forums_topics.status IN (?)', array(self::STATUS_NORMAL, self::STATUS_CLOSED))
        );

        $theme->save();
    }

    public function getTopicList($themeId, $page, $userId)
    {
        $select = $this->_topicTable->select(true)
            ->where('forums_topics.status IN (?)', array(self::STATUS_CLOSED, self::STATUS_NORMAL))
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
            ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
            ->order('comment_topic.last_update DESC');

        if ($themeId) {
            $select->where('forums_topics.theme_id = ?', (int)$themeId);
        } else {
            $select->where('forums_topics.theme_id IS NULL');
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(self::TOPICS_PER_PAGE)
            ->setCurrentPageNumber($page);

        $comments = new Comments();
        $commentTopicTable = new Comment_Topic();

        $topics = [];

        foreach ($paginator->getCurrentItems() as $topicRow) {
            $topicPaginator = $comments->getMessagePaginator(Comment_Message::FORUMS_TYPE_ID, $topicRow->id)
                ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
                ->setPageRange(10);

            $topicPaginator->setCurrentPageNumber($topicPaginator->count());

            if ($userId) {
                $stat = $commentTopicTable->getTopicStatForUser(
                    Comment_Message::FORUMS_TYPE_ID,
                    $topicRow->id,
                    $userId
                );
                $messages = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat = $commentTopicTable->getTopicStat(
                    Comment_Message::FORUMS_TYPE_ID,
                    $topicRow->id
                );
                $messages = $stat['messages'];
                $newMessages = 0;
            }

            $oldMessages = $messages - $newMessages;

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $comments->getLastMessageRow(Comment_Message::FORUMS_TYPE_ID, $topicRow->id);
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow->id,
                        'date'   => $lastMessageRow->getDateTime('datetime'),
                        'author' => $lastMessageRow->findParentUsersByAuthor()
                    ];
                }
            }

            $topics[] = [
                'id'          => $topicRow->id,
                'paginator'   => $topicPaginator,
                'name'        => $topicRow->caption,
                'messages'    => $messages,
                'oldMessages' => $oldMessages,
                'newMessages' => $newMessages,
                'addDatetime' => $topicRow->getDateTime('add_datetime'),
                'authorId'    => $topicRow->author_id,
                'lastMessage' => $lastMessage,
                'status'      => $topicRow->status
            ];
        }

        return [
            'topics'    => $topics,
            'paginator' => $paginator
        ];
    }

    public function getThemePage($themeId, $page, $userId, $isModerator)
    {
        $select = $this->_themeTable->select(true)
            ->where('id = ?', (int)$themeId);

        if (!$isModerator) {
            $select->where('not is_moderator');
        }

        $currentTheme = $this->_themeTable->fetchRow($select);

        $data = array(
            'topics'    => [],
            'paginator' => false
        );
        if ($currentTheme && !$currentTheme->disable_topics) {
            $data = $this->getTopicList($currentTheme->id, $page, $userId);
        }

        $themeData = null;
        if ($currentTheme) {
            $themeData = [
                'id'             => $currentTheme->id,
                'name'           => $currentTheme->caption,
                'topics'         => $currentTheme->topics,
                'messages'       => $currentTheme->messages,
                'disable_topics' => $currentTheme->disable_topics,
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
        if (!$userId) {
            throw new \Exception("User id not provided");
        }

        $theme = $this->getTheme($values['theme_id']);
        if (!$theme || $theme['disable_topics']) {
            return false;
        }

        $db = $this->_topicTable->getAdapter();

        $topic = $this->_topicTable->createRow([
            'theme_id'     => $theme['id'],
            'caption'      => $values['name'],
            'author_id'    => $userId,
            'author_ip'    => new Zend_Db_Expr($db->quoteInto('INET6_ATON(?)', $values['ip'])),
            'add_datetime' => new Zend_Db_Expr('NOW()'),
            'views'        => 0,
            'status'       => self::STATUS_NORMAL
        ]);
        $topic->save();

        $comments = new Comments();

        $comments->add(array(
            'typeId'             => Comment_Message::FORUMS_TYPE_ID,
            'itemId'             => $topic->id,
            'authorId'           => $userId,
            'datetime'           => new Zend_Db_Expr('NOW()'),
            'message'            => $values['text'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool)$values['moderator_attention']
        ));

        if ($values['subscribe']) {
            $this->subscribe($topic->id, $userId);
        }

        return $topic->id;
    }

    public function getTheme($themeId)
    {
        $theme = $this->_themeTable->find($themeId)->current();
        if (!$theme) {
            return false;
        }

        return [
            'id'             => $theme->id,
            'name'           => $theme->caption,
            'disable_topics' => $theme->disable_topics,
            'is_moderator'   => $theme->is_moderator
        ];
    }

    /**
     * @return array
     */
    public function getThemes()
    {
        $select = $this->_themeTable->select(true)
            ->order('position');

        $result = [];
        foreach ($this->_themeTable->fetchAll($select) as $row) {
            $result[] = [
                'id'   => $row->id,
                'name' => $row->caption
            ];
        }

        return $result;
    }

    public function getTopics($themeId)
    {
        $select = $this->_topicTable->select(true)
            ->where('forums_topics.status IN (?)', array(self::STATUS_CLOSED, self::STATUS_NORMAL))
            ->joinLeft('comment_topic', 'forums_topics.id = comment_topic.item_id and comment_topic.type_id = :type_id', null)
            ->order('comment_topic.last_update DESC')
            ->bind([
                'type_id' => Comment_Message::FORUMS_TYPE_ID
            ]);

        if ($themeId) {
            $select->where('forums_topics.theme_id = ?', (int)$themeId);
        } else {
            $select->where('forums_topics.theme_id IS NULL');
        }

        $result = [];
        foreach ($this->_topicTable->fetchAll($select) as $row) {
            $result[] = [
                'id'   => $row->id,
                'name' => $row->caption
            ];
        }

        return $result;
    }

    public function moveMessage($messageId, $topicId)
    {
        $topic = $this->_topicTable->find($topicId)->current();
        if (!$topic) {
            return false;
        }

        $comments = new Comments();
        $comments->moveMessage($messageId, Comment_Message::FORUMS_TYPE_ID, $topic->id);

        return true;
    }

    public function moveTopic($topicId, $themeId)
    {
        $topic = $this->_topicTable->find($topicId)->current();
        if (!$topic) {
            return false;
        }

        $theme = $this->_themeTable->find($themeId)->current();
        if (!$theme) {
            return false;
        }

        $oldThemeId = $topic->theme_id;

        $topic->theme_id = $theme['id'];
        $topic->save();

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

        $select = $this->_topicTable->select(true)
                ->where('forums_topics.id = ?', (int)$topicId);

        if ($options['isModerator'] !== null) {
            if (!$options['isModerator']) {
                $select
                    ->join('forums_themes', 'forums_topics.theme_id = forums_themes.id', null)
                    ->where('not forums_themes.is_moderator');
            }
        }

        if ($options['status']) {
            $select->where('status in (?)', $options['status']);
        }

        $topic = $this->_topicTable->fetchRow($select);
        if (!$topic) {
            return false;
        }

        return [
            'id'       => $topic->id,
            'name'     => $topic->caption,
            'theme_id' => $topic->theme_id,
            'status'   => $topic->status,
        ];
    }

    public function getMessagePage($messageId)
    {
        $comments = new Comments();

        $message = $comments->getMessageRow($messageId);
        if (!$message) {
            return false;
        }

        if ($message->type_id != Comment_Message::FORUMS_TYPE_ID) {
            return false;
        }

        $topic = $this->_topicTable->find($message->item_id)->current();
        if (!$topic) {
            return false;
        }

        $page = $comments->getMessagePage($message, self::MESSAGES_PER_PAGE);

        return [
            'page'     => $page,
            'topic_id' => $topic->id
        ];
    }

    public function registerTopicView($topicId, $userId)
    {
        $this->_topicTable->update([
            'views' => new Zend_Db_Expr('views+1')
        ], [
            'id = ?' => (int)$topicId
        ]);

        if ($userId) {
            $commentTopicTable = new Comment_Topic();
            $commentTopicTable->updateTopicView(
                    Comment_Message::FORUMS_TYPE_ID,
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
        if (!$topic) {
            return false;
        }

        $theme = $this->getTheme($topic['theme_id']);
        if (!$theme) {
            return false;
        }

        $this->registerTopicView($topic['id'], $userId);

        $comments = new Comments();

        $messages = $comments->get(
            Comment_Message::FORUMS_TYPE_ID,
            $topic['id'],
            $userId,
            self::TOPICS_PER_PAGE,
            $page
        );

        $paginator = $comments->getPaginator(
            Comment_Message::FORUMS_TYPE_ID,
            $topic['id'],
            self::TOPICS_PER_PAGE,
            $page
        );

        $canSubscribe = false;
        $canUnsubscribe = false;
        if ($userId) {
            $canSubscribe = $this->canSubscribe($topic['id'], $userId);
            $canUnsubscribe = $this->canUnSubscribe($topic['id'], $userId);
        }

        return [
            'topic'          => $topic,
            'theme'          => $theme,
            'paginator'      => $paginator,
            'messages'       => $messages,
            'canSubscribe'   => $canSubscribe,
            'canUnsubscribe' => $canUnsubscribe,
        ];
    }

    public function addMessage($values)
    {
        $comments = new Comments();

        $messageId = $comments->add(array(
            'typeId'             => Comment_Message::FORUMS_TYPE_ID,
            'itemId'             => $values['topic_id'],
            'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
            'authorId'           => $values['user_id'],
            'message'            => $values['message'],
            'ip'                 => $values['ip'],
            'moderatorAttention' => (bool)$values['moderator_attention']
        ));

        if (!$messageId) {
            throw new \Exception("Message add fails");
        }

        if ($values['resolve'] && $values['parent_id']) {
            $comments->completeMessage($values['parent_id']);
        }

        return $messageId;
    }

    public function getSubscribersIds($topicId)
    {
        $db = $this->_subscriberTable->getAdapter();

        return $db->fetchCol(
            $db->select()
                ->from($this->_subscriberTable->info('name'), 'user_id')
                ->where('topic_id = ?', (int)$topicId)
        );
    }

    public function getSubscribedTopics($userId)
    {
        $rows = $this->_topicTable->fetchAll(
            $this->_topicTable->select(true)
                ->join(
                    'forums_topics_subscribers',
                    'forums_topics.id = forums_topics_subscribers.topic_id',
                    null
                )
                ->where('forums_topics_subscribers.user_id = ?', (int)$userId)
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
                ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                ->order('comment_topic.last_update DESC')
        );

        $comments = new Comments();
        $commentTopicTable = new Comment_Topic();

        $topics = array();
        foreach ($rows as $row) {

            $stat = $commentTopicTable->getTopicStatForUser(
                    Comment_Message::FORUMS_TYPE_ID,
                    $row->id,
                    $userId
            );
            $messages = $stat['messages'];
            $newMessages = $stat['newMessages'];

            $oldMessages = $messages - $newMessages;

            $theme = $this->getTheme($row->theme_id);

            $lastMessage = false;
            if ($messages > 0) {
                $lastMessageRow = $comments->getLastMessageRow(
                        Comment_Message::FORUMS_TYPE_ID,
                        $row->id
                );
                if ($lastMessageRow) {
                    $lastMessage = [
                        'id'     => $lastMessageRow->id,
                        'date'   => $lastMessageRow->getDateTime('datetime'),
                        'author' => $lastMessageRow->findParentUsersByAuthor(),
                    ];
                }
            }

            $topics[] = array(
                'id'                => $row->id,
                'name'              => $row->caption,
                'messages'          => $messages,
                'oldMessages'       => $oldMessages,
                'newMessages'       => $newMessages,
                'theme'             => $theme,
                'authorId'          => $row->author_id,
                'lastMessage'       => $lastMessage,
            );
        }

        return $topics;
    }
}