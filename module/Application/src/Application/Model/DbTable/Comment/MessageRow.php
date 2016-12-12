<?php

namespace Application\Model\DbTable\Comment;

use Application\Db\Table\Row;
use Application\Model\DbTable\Article;
use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Comment\Vote as CommentVote;
use Application\Model\DbTable\Picture;

use Zend_Db_Expr;

class MessageRow extends Row
{
    /**
     * @deprecated
     * @param bool $absolute
     * @return string
     */
    public function getUrl()
    {
        switch ($this->type_id) {
            case CommentMessage::PICTURES_TYPE_ID:
                $pictures = new Picture();
                $picture = $pictures->find($this->item_id)->current();
                if ($picture) {
                    return '/picture/'.($picture->identity ? $picture->identity : $picture->id);
                }
                return false;

            case CommentMessage::VOTINGS_TYPE_ID:
                return '/voting/voting/id/'.(int)$this->item_id.'/';

            case CommentMessage::TWINS_TYPE_ID:
                return '/twins/group'.(int)$this->item_id;

            case CommentMessage::ARTICLES_TYPE_ID:
                $articles = new Article();
                $article = $articles->find($this->item_id)->current();
                if ($article) {
                    return '/articles/'.$article->catname.'/';
                }
                return false;

            case CommentMessage::FORUMS_TYPE_ID:
                return '/forums/topic-message/message_id/'.(int)$this->id;

            case CommentMessage::MUSEUMS_TYPE_ID:
                return '/museums/museum/id/'.(int)$this->item_id;
        }
        return null;
    }

    public function getMessagePreview()
    {
        $Msg = str_replace("\r", '', $this->message);
        $Msg = explode("\n", $Msg);
        $Msg = trim($Msg[0]);
        if (mb_strlen($Msg) > 60) {
            $Msg = mb_substr($Msg, 0, 60).'...';
        }
        return $Msg;
    }

    public function updateVote()
    {
        $voteTable = new CommentVote();

        $this->vote = $voteTable->getAdapter()->fetchOne(
            $voteTable->getAdapter()->select()
                ->from($voteTable->info('name'), new Zend_Db_Expr('sum(vote)'))
                ->where('comment_id = ?', $this->id)
        );
        $this->save();
    }

    public function isNew($userId)
    {
        $db = $this->getTable()->getAdapter();

        $viewTime = $db->fetchRow(
            $db->select()
                ->from('comment_topic_view', 'timestamp')
                ->where('comment_topic_view.item_id = ?', $this->item_id)
                ->where('comment_topic_view.type_id = ?', $this->type_id)
                ->where('comment_topic_view.user_id = ?', $userId)
        );

        return $viewTime ? $this->datetime > $viewTime : true;
    }
}
