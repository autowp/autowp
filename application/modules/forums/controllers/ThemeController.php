<?php
class Forums_ThemeController extends Zend_Controller_Action
{
    const TOPICS_PER_PAGE = 20;
    const MESSAGES_PER_PAGE = 20;

    public function themeAction()
    {
        // определяем является ли пользователь администратором форума
        $forumAdmin = $this->_helper->user()->isAllowed('forums', 'moderate');
        $moder = $this->_helper->user()->inheritsRole('moder');

        $themeTable = new Forums_Themes();
        $topicsTable = new Forums_Topics();
        $comments = new Comments();
        $commentTopicTable = new Comment_Topic();

        $select = $themeTable->select(true)
            ->where('id = ?', (int)$this->_getParam('theme_id'));

        if (!$moder) {
            $select->where('not is_moderator');
        }

        $theme = $themeTable->fetchRow($select);

        if (!$theme) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $paginator = false;
        $topics = array();

        if (!$theme->disable_topics) {

            $select = $topicsTable->select(true)
                ->where('forums_topics.theme_id = ?', $theme->id)
                ->where('forums_topics.status IN (?)', array(Forums_Topics::STATUS_CLOSED, Forums_Topics::STATUS_NORMAL))
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
                ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                ->order('comment_topic.last_update DESC');

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage(self::TOPICS_PER_PAGE)
                ->setCurrentPageNumber($this->_getParam('page'));

            foreach ($paginator->getCurrentItems() as $topicRow) {
                $topicPaginator = $comments->getMessagePaginator(Comment_Message::FORUMS_TYPE_ID, $topicRow->id)
                    ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
                    ->setPageRange(10);

                $topicPaginator->setCurrentPageNumber(count($topicPaginator));

                if ($this->_helper->user()->logedIn()) {
                    $stat = $commentTopicTable->getTopicStatForUser(
                        Comment_Message::FORUMS_TYPE_ID,
                        $topicRow->id,
                        $this->_helper->user()->get()->id
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
                $lastMessageDate = false;
                $lastMessageAuthor = false;
                $lastMessageUrl = false;
                if ($messages > 0) {
                    $lastMessage = $comments->getLastMessageRow(Comment_Message::FORUMS_TYPE_ID, $topicRow->id);
                    if ($lastMessage) {
                        $lastMessageDate = $lastMessage->getDate('datetime');
                        $lastMessageAuthor = $lastMessage->findParentUsersByAuthor();
                        $lastMessageUrl = $this->_helper->url->url(array(
                            'module'     => 'forums',
                            'controller' => 'topic',
                            'action'     => 'topic-message',
                            'message_id' => $lastMessage->id
                        ), 'default', true);
                    }
                }

                $topics[] = array(
                    'id'                => $topicRow->id,
                    'paginator'         => $topicPaginator,
                    'url'               => $this->_helper->url->url(array(
                        'module'     => 'forums',
                        'controller' => 'topic',
                        'action'     => 'topic',
                        'topic_id'   => $topicRow->id,
                        'page'       => null
                    ), 'default', true),
                    'name'              => $topicRow->caption,
                    'messages'          => $messages,
                    'oldMessages'       => $oldMessages,
                    'newMessages'       => $newMessages,
                    'addDatetime'       => $topicRow->getDate('add_datetime'),
                    'author'            => $topicRow->findParentUsersByAuthor(),
                    'lastMessage'       => $lastMessage,
                    'lastMessageUrl'    => $lastMessageUrl,
                    'lastMessageDate'   => $lastMessageDate,
                    'lastMessageAuthor' => $lastMessageAuthor,
                    'status'            => $topicRow->status
                );
            }
        }


        // Themes
        $select = $themeTable->select()
            ->where('parent_id = ?', $theme->id)
            ->order('position');

        if (!$moder) {
            $select->where('not is_moderator');
        }

        $themes = array();

        foreach ($themeTable->fetchAll($select) as $row) {
            $lastMessage = false;
            $lastTopic = $topicsTable->fetchRow(
                $topicsTable->select(true)
                    ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', null)
                    ->where('forums_topics.status IN (?)', array(Forums_Topics::STATUS_NORMAL, Forums_Topics::STATUS_CLOSED))
                    ->where('forums_theme_parent.parent_id = ?', $row->id)
                    ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', null)
                    ->where('comment_topic.type_id = ?', Comment_Message::FORUMS_TYPE_ID)
                    ->order('comment_topic.last_update DESC')
            );
            if ($lastTopic) {
                $lastMessage = $comments->getLastMessageRow(Comment_Message::FORUMS_TYPE_ID, $lastTopic->id);
            }

            $themes[] = array(
                'url'         => $this->_helper->url->url(array(
                    'controller' => 'theme',
                    'action'     => 'theme',
                    'theme_id'   => $row->id
                )),
                'name'        => $row->caption,
                'description' => $row->description,
                'lastTopic'   => $lastTopic,
                'lastMessage' => $lastMessage,
                'topics'      => $row->topics,
                'messages'    => $row->messages
            );
        }

        $this->view->assign(array(
            'topics'     => $topics,
            'paginator'  => $paginator,
            'theme'      => $theme,
            'forumAdmin' => $forumAdmin,
            'themes'     => $themes
        ));
    }
}