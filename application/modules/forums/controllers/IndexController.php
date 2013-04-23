<?php
class Forums_IndexController extends My_Controller_Action
{
    public function indexAction()
    {
        $this->view->blankPage = false;
        $this->view->needLeft = false;
        $this->view->needRight = true;
        $this->initPage(42);
        
        $themeTable = new Forums_Themes();
        $topicsTable = new Forums_Topics();
        $msgTable = new Forums_Messages();
        
        $isModearator = $this->user && 
            $this->_helper->acl()->inheritsRole($this->user->role, 'moder');
    
        $select = $themeTable->select(true)
            ->where('parent_id IS NULL')
            ->order('position');
            
        if (!$isModearator) {
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
                    ->order('forums_topics.update_datetime DESC')
            );
            if ($lastTopic) {
                $lastMessage = $msgTable->fetchRow(array(
                    'topic_id = ?'    => $lastTopic->id
                ), 'add_datetime DESC');
            }
            
            $subthemes = array();
            
            $select = $themeTable->select()
                ->where('parent_id = ?', $row->id)
                ->order('position');
                
            if (!$isModearator) {
                $select->where('not is_moderator');
            }
            
            foreach ($themeTable->fetchAll($select) as $srow) {
                $subthemes[] = array(
                    'name' => $srow->caption,
                    'url'  => $this->_helper->url->url(array(
                        'controller' => 'theme',
                        'action'     => 'theme',
                        'theme_id'   => $srow->id
                    )),
                );
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
                'messages'    => $row->messages,
                'subthemes'   => $subthemes
            );
        }
            
        $this->view->themes = $themes;
    }
}