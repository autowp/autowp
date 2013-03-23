<?php
class Project_View_Helper_PersonalMessages extends Zend_View_Helper_Abstract
{
    private $_cache = array();
    
    private $_defaults = array(
        'allMessagesLink' => true
    );
    
    public function personalMessages(Zend_Db_Table_Rowset $messages, $options = array())
    {
        $options = array_merge($this->_defaults, $options);
        
        $bb = new BBDocument($this->view);
        $user = $this->view->user;
        $db = $messages->getTable()->getAdapter();
        
        $idx = 0;
        $rows = array();
        foreach ($messages as $message)
        {
            $parity = (++$idx % 2) ? 'even' : 'odd';
            $author = $message->findParentUsersByFrom();
            
            $date_text = '';
            if ($date = $message->getDate('add_datetime')) {
            	$date_text = '<i class="icon-time"></i> ' . 
            				 $this->view->escape($this->view->humanTime($date));
            }
            
            
            
            if ($author)
                $authorHtml = $this->view->user($author);
            else
                $authorHtml = 'Системное уведомление';
        
            $bb->load($message->contents);
            $contents = $bb->getHtml();
        
            $links = array();
            if ($author && !$author->deleted && $author->id != $user->id)
            {
                $links[] = $this->view->htmlA(array(
                    'href'	=> '#',
                    'onclick' => 'showPMWindow('.$author->id.', this);return false'
                ), '<i class="icon-share-alt"></i> ответить', false); 

                if ($options['allMessagesLink'] && $author && $author->id != $user->id)
                {
                    if (isset($this->_cache[$author->id])) {
                        $c = $this->_cache[$author->id];
                    } else {
                        $sql =  'SELECT COUNT(1) FROM personal_messages '.
                                'WHERE '.
                                    'from_user_id=? AND to_user_id=? AND NOT deleted_by_from OR '.
                                    'from_user_id=? AND to_user_id=? AND NOT deleted_by_to';
                        $c = $db->fetchOne($sql, array($user->id, $author->id, $author->id, $user->id));
                        
                        $this->_cache[$author->id] = $c;
                    }
                    if ($c)
                    {
                        $url = $this->view->url(array(
                            'controller' => 'account',
                            'action'     => 'personal-messages-user',
                            'user_id'    => $author->id
                        ));
                        $links[] = $this->view->htmlA($url, '<i class="icon-align-justify"></i> вся переписка', false) . ' <small>('.$c.')</small>';
                    }
                }
            }
            
            $avatar = '';
            if ($author && !$author->deleted && $author->photo)
                $avatar = $this->view->htmlA($author->getAboutUrl(), $this->view->image($author, 'photo', array(
                    //'class'		=>	'avatar',
                	'format'	=>	15
                )), false);
                
            $is_new = $message->to_user_id == $user->id && !$message->readen;
            $canDelete = $message->from_user_id == $user->id || $message->to_user_id == $user->id;
        
            $html = '<div class="row message">' .
						'<div class="span9">' . $authorHtml . '</div>' .
                        '<div class="span1">' .
                            $avatar .
                        '</div>' .
                        '<div class="span8">'.
                            '<div class="text">' .
	                            '<p>'.$contents.'</p>'.
                            '</div>'.
                            '<div>' .
	                            ($is_new ? ' <span class="label label-info">новое</span>' : '') .
	                            (
	                                $canDelete
	                                ?
	                                	$this->view->htmlA(array(
	                                		'href'		=>	'#',
	                                		'class'		=>	'pull-right',
	                                		'onclick'	=>	'deletePM('.$message->id.', this.parentNode.parentNode.parentNode);return false;'
	                                	), '<i class="icon-trash"></i> Удалить', false)
									:
	                                    ''
	                            ).
	                            ($links ? implode(' ', $links) : ''). ' ' .
	                            $date_text .
                            '</div>'.
                        '</div>'.
                    '</div>';
        
            $rows[] = $html;
        }
        
        return implode($rows);
    }
}