<?php

use Application\Model\Forums;
use Application\Model\Message;

class ForumsController extends Zend_Controller_Action
{
    private function prepareThemeList(&$themes)
    {
        foreach ($themes as &$theme) {
            if ($theme['lastMessage']) {
                $theme['lastMessage'] = array_replace($theme['lastMessage'], [
                    'message-url' => $this->topicMessageUrl($theme['lastMessage']['id']),
                    'url'         => $this->topicUrl($theme['lastTopic']['id'])
                ]);
            }

            foreach ($theme['subthemes'] as &$subtheme) {
                $subtheme['url'] = $this->themeUrl($subtheme['id']);
            }

            $theme['url'] = $this->themeUrl($theme['id']);
        }
    }

    public function indexAction()
    {
        $user = $this->_helper->user()->get();
        $userId = $user ? $user->id : null;

        $forumAdmin = $this->_helper->user()->isAllowed('forums', 'moderate');
        $isModearator = $this->_helper->user()->inheritsRole('moder');


        $model = new Forums();

        $data = $model->getThemePage(
                $this->getParam('theme_id'),
                $this->getParam('page'),
                $userId,
                $isModearator
        );

        if (!$data) {
            return $this->forward('notfound', 'error', 'default');
        }

        $userTable = new Users();

        foreach ($data['topics'] as &$topic) {
            $topic['author'] = $userTable->find($topic['authorId'])->current();

            $topic['url'] = $this->topicUrl($topic['id']);

            if ($topic['lastMessage']) {
                $topic['lastMessage']['url'] = $this->topicMessageUrl($topic['lastMessage']['id']);
            }

        }
        unset($topic);

        $this->prepareThemeList($data['themes']);

        $this->view->assign($data);

        $this->view->assign([
            'forumAdmin'  => $forumAdmin,
            'newTopicUrl' => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'new'
            )),
            'openUrl' => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'open'
            ), null, true),
            'closeUrl' => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'close'
            ), null, true),
            'deleteUrl' => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'delete'
            ), null, true)
        ]);
    }

    public function topicAction()
    {
        $model = new Forums();

        $forumAdmin = $this->_helper->user()->isAllowed('forums', 'moderate');
        $isModearator = $this->_helper->user()->inheritsRole('moder');

        $topic = $model->getTopic((int)$this->getParam('topic_id'), [
            'status'      => [Forums::STATUS_NORMAL, Forums::STATUS_CLOSED],
            'isModerator' => $isModearator
        ]);

        if (!$topic) {
            return $this->forward('notfound', 'error', 'default');
        }

        $user = $this->_helper->user()->get();

        $canAddComments = $user && ($topic['status'] == Forums::STATUS_NORMAL) || $forumAdmin;

        $needWait = $this->needWait();

        if ($canAddComments) {
            $form = new Application_Form_Comment(array(
                'action' => $this->_helper->url->url(),
                'method' => 'post',
                'canModeratorAttention' => $this->_helper->user()->isAllowed('comment', 'moderator-attention')
            ));

            $request = $this->getRequest();
            if ($request->isPost() && $form->isValid($request->getPost())) {
                if (!$needWait) {

                    $values = $form->getValues();

                    $values['topic_id'] = $topic['id'];
                    $values['user_id'] = $user->id;
                    $values['ip'] = $request->getServer('REMOTE_ADDR');
                    $values['resolve'] = $isModearator && $values['parent_id'] && $values['resolve'];
                    $messageId = $model->addMessage($values);

                    $user->forums_messages = new \Zend_Db_Expr('forums_messages + 1');
                    $user->last_message_time = new \Zend_Db_Expr('NOW()');
                    $user->save();

                    $messageUrl = $this->view->serverUrl($this->topicMessageUrl($messageId));

                    $userTable = new Users();

                    if ($values['parent_id']) {
                        $comments = new \Comments();
                        $authorId = $comments->getMessageAuthorId($values['parent_id']);
                        if ($authorId && ($authorId != $user->id)) {
                            $parentMessageAuthor = $userTable->fetchRow([
                                'id = ?' => $authorId,
                                'not deleted'
                            ]);
                            if ($parentMessageAuthor) {
                                $moderUrl = $this->view->serverUrl($this->_helper->url->url(array(
                                    'controller' => 'users',
                                    'action'     => 'user',
                                    'identity'   => $user->identity,
                                    'user_id'    => $user->id
                                ), 'users', true));
                                $message = sprintf(
                                        "%s ответил на ваше сообщение\n%s",
                                        $moderUrl, $messageUrl
                                        );

                                $mModel = new Message();
                                $mModel->send(null, $parentMessageAuthor->id, $message);
                            }
                        }
                    }

                    $ids = $model->getSubscribersIds($topic['id']);
                    $subscribers = $userTable->find($ids);

                    if (count($subscribers)) {

                        $subject = 'Уведомление о новом сообщении на форуме';
                        $message = sprintf(
                                "Здравствуйте.\n\n" .
                                "На форуме сайта http://www.autowp.ru/ в топике \"%s\" добавлено новое сообщение\n" .
                                "Для перехода к просмотру сообщения воспользуйтесь ссылкой %s\n\n" .
                                "Отписаться от получений уведомлений вы можете в личном кабинете\n\n" .
                                "С Уважением, робот www.autowp.ru\n",
                                $topic['name'],
                                $messageUrl
                                );


                        foreach ($subscribers as $subscriber) {
                            if ($subscriber->id == $user->id)
                                continue;

                                if ($subscriber->e_mail) {
                                    try {
                                        $mail = new Zend_Mail('utf-8');
                                        $mail->setBodyText($message)
                                        ->setFrom('no-reply@autowp.ru', 'Робот autowp.ru')
                                        ->addTo($subscriber->e_mail)
                                        ->setSubject($subject)
                                        ->send();
                                    } catch (Zend_Mail_Exception $e) {

                                    }
                                }
                        }
                    }

                    return $this->_redirect($this->topicMessageUrl($messageId));
                }
            }

            $this->view->formMessageNew = $form;
        }

        $data = $model->topicPage(
                $topic['id'],
                $user ? $user->id : null,
                $this->getParam('page'),
                $isModearator
                );

        if (!$data) {
            return $this->forward('notfound', 'error');
        }

        $this->view->assign($data);

        $canRemoveComments = $this->_helper->user()->isAllowed('comment', 'remove');
        $canViewIp = $this->_helper->user()->isAllowed('user', 'ip');

        $this->view->assign(array(
            'needWait'          => $needWait,
            'forumAdmin'        => $forumAdmin,
            'canAddComments'    => $canAddComments,
            'canRemoveComments' => $canRemoveComments,
            'canMoveMessage'    => $forumAdmin,
            'canViewIp'         => $canViewIp,
            'subscribeUrl'      => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'subscribe'
            )),
            'unsubscribeUrl'    => $this->_helper->url->url(array(
                'controller' => 'forums',
                'action'     => 'unsubscribe'
            )),
            'moveMessageUrl'    => [
                'controller' => 'forums',
                'action'     => 'move-message',
                'topic_id'   => null,
                'page'       => null
            ]
        ));
    }

    private function themeUrl($themeId)
    {
        return $this->_helper->url->url([
            'controller' => 'forums',
            'action'     => 'index',
            'theme_id'   => $themeId
        ], 'default', true);
    }

    private function topicUrl($topicId, $page = null)
    {
        return $this->_helper->url->url(array(
            'controller' => 'forums',
            'action'     => 'topic',
            'topic_id'   => $topicId,
            'page'       => $page
        ), 'default', true);
    }

    private function topicMessageUrl($messageId)
    {
        return $this->_helper->url->url(array(
            'controller' => 'forums',
            'action'     => 'topic-message',
            'message_id' => $messageId
        ), 'default', true);
    }

    public function subscribeAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $topicId = (int)$this->getParam('topic_id');

        $model = new Forums();
        $model->subscribe($topicId, $user->id);

        return $this->_redirect($this->topicUrl($topicId));
    }

    public function unsubscribeAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $topicId = (int)$this->getParam('topic_id');

        $model = new Forums();
        $model->unsubscribe($topicId, $user->id);

        return $this->_redirect($this->topicUrl($topicId));
    }

    private function needWait()
    {
        $user = $this->_helper->user()->get();
        if ($user) {
            if ($nextMessageTime = $user->nextMessageTime()) {
                return $nextMessageTime->isLater(Zend_Date::now());
            }
        }

        return false;
    }

    public function newAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $model = new Forums();
        $theme = $model->getTheme($this->getParam('theme_id'));

        if (!$theme || $theme['disable_topics']) {
            return $this->forward('notfound', 'error', 'default');
        }

        $needWait = $this->needWait();

        $user = $this->_helper->user()->get();
        if ($user) {
            $form = new Application_Form_Forums_Topic_New(array(
                'action' => $this->_helper->url->url()
            ));

            $request = $this->getRequest();

            if ($request->isPost() && $form->isValid($request->getPost())) {
                if (!$needWait) {
                    $values = $form->getValues();

                    $values['user_id'] = $user->id;
                    $values['theme_id'] = $theme['id'];
                    $values['ip'] = $request->getServer('REMOTE_ADDR');

                    $topicId = $model->addTopic($values);

                    $user->setFromArray(array(
                        'forums_topics'     => new Zend_Db_Expr('forums_topics + 1'),
                        'forums_messages'   => new Zend_Db_Expr('forums_messages + 1'),
                        'last_message_time' => new Zend_Db_Expr('NOW()')
                    ));
                    $user->save();

                    return $this->_redirect($this->topicUrl($topicId));
                }
            }
            $this->view->formTopicNew = $form;
        }

        $this->view->assign(array(
            'needWait' => $needWait,
            'theme'    => $theme
        ));
    }

    public function topicMessageAction()
    {
        $model = new Forums();

        $messageId = $this->getParam('message_id');
        $page = $model->getMessagePage($messageId);
        if (!$page) {
            return $this->forward('notfound', 'error', 'default');
        }

        return $this->_redirect($this->topicUrl($page['topic_id'], $page['page']) . '#msg' . $messageId);
    }

    private function authorizedForumModer(Callable $callback)
    {
        $forumAdmin = $this->_helper->user()->isAllowed('forums', 'moderate');
        if (!$forumAdmin) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $callback();
    }


    public function openAction()
    {
        return $this->authorizedForumModer(function() {

            $model = new Forums();
            $model->open($this->getParam('topic_id'));

            return $this->_helper->json(array(
                'ok' => true
            ));
        });
    }

    public function closeAction()
    {
        return $this->authorizedForumModer(function() {

            $model = new Forums();
            $model->close($this->getParam('topic_id'));

            return $this->_helper->json(array(
                'ok' => true
            ));
        });
    }

    public function deleteAction()
    {
        return $this->authorizedForumModer(function() {

            $model = new Forums();
            $model->delete($this->getParam('topic_id'));

            return $this->_helper->json(array(
                'ok' => true
            ));
        });
    }

    public function moveAction()
    {
        return $this->authorizedForumModer(function() {
            $model = new Forums();

            $topic = $model->getTopic($this->getParam('topic_id'));
            if (!$topic) {
                return $this->forward('notfound', 'error', 'default');
            }

            $theme = $model->getTheme($this->getParam('theme_id'));

            if ($theme) {
                $model->moveTopic($topic['id'], $theme['id']);

                return $this->_redirect($this->themeUrl($theme['id']));
            }

            $this->view->assign([
                'themes' => $model->getThemes(),
                'topic'  => $topic
            ]);
        });
    }

    public function subscribesAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->forward('forbidden', 'error');
        }

        $moder = new Forums();

        $topics = $moder->getSubscribedTopics($user->id);

        $userTable = new Users();

        foreach ($topics as &$topic) {
            $author = $userTable->find($topic['authorId'])->current();
            $topic['author'] = $author;
            $topic['url'] = $this->topicUrl($topic['id']);

            if ($topic['theme']) {
                $topic['theme']['url'] = $this->themeUrl($topic['theme']['id']);
            }

            if ($topic['lastMessage']) {
                $topic['lastMessage']['url'] = $this->topicMessageUrl($topic['lastMessage']['id']);
            }

            $topic['unsubscribeUrl'] = $this->_helper->url->url(array(
                'controller' => 'account',
                'action'     => 'forums-unsubscribe',
                'topic_id'   => $topic['id']
            ), 'account');
        }
        unset($topic);

        $this->view->topics = $topics;
    }

    public function moveMessageAction()
    {
        return $this->authorizedForumModer(function() {

            $messageId = $this->getParam('id');

            if (!$messageId) {
                return $this->forward('notfound', 'error', 'default');
            }

            $model = new Forums();

            $theme = $model->getTheme($this->getParam('theme_id'));
            if ($theme) {

                $topicId = (int)$this->getParam('topic_id');
                if ($topicId) {

                    $model->moveMessage($messageId, $topicId);

                    return $this->_redirect($this->topicMessageUrl($messageId));

                } else {
                    $this->view->topics = $model->getTopics($theme['id']);
                }

            } else {
                $this->view->themes = $model->getThemes();
            }

            $this->view->assign(array(
                'theme' => $theme
            ));
        });
    }
}