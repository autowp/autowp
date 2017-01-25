<?php

namespace Application\Controller;

use Zend\Mail;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Autowp\Comments;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\Model\Forums;

use DateTime;

use Zend_Db_Expr;

class ForumsController extends AbstractActionController
{
    private $transport;

    private $newTopicForm;

    private $commentForm;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var Forums
     */
    private $model;

    public function __construct(
        Forums $model,
        $newTopicForm,
        $commentForm,
        $transport,
        MessageService $message,
        Comments\CommentsService $comments
    ) {
        $this->model = $model;
        $this->newTopicForm = $newTopicForm;
        $this->commentForm = $commentForm;
        $this->transport = $transport;
        $this->message = $message;
        $this->comments = $comments;
    }

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
        $user = $this->user()->get();
        $userId = $user ? $user->id : null;

        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');
        $isModearator = $this->user()->inheritsRole('moder');

        $data = $this->model->getThemePage(
            $this->params('theme_id'),
            $this->params('page'),
            $userId,
            $isModearator
        );

        if (! $data) {
            return $this->notFoundAction();
        }

        $userTable = new User();

        foreach ($data['topics'] as &$topic) {
            $topic['author'] = $userTable->find($topic['authorId'])->current();

            $topic['url'] = $this->topicUrl($topic['id']);

            if ($topic['lastMessage']) {
                $topic['lastMessage']['url'] = $this->topicMessageUrl($topic['lastMessage']['id']);
            }
        }
        unset($topic);

        $this->prepareThemeList($data['themes']);

        $newTopicUrl = null;
        if ($data['theme']) {
            $newTopicUrl = $this->url()->fromRoute('forums/new', [
                'theme_id' => $data['theme']['id']
            ]);
        }

        return array_replace($data, [
            'forumAdmin'  => $forumAdmin,
            'newTopicUrl' => $newTopicUrl,
            'openUrl' => $this->url()->fromRoute('forums/open', [
                'action' => 'open'
            ]),
            'closeUrl' => $this->url()->fromRoute('forums/close', [
                'action' => 'close'
            ]),
            'deleteUrl' => $this->url()->fromRoute('forums/delete', [
                'action' => 'delete'
            ]),
        ]);
    }

    private function sendMessageEmailNotification($email, $topicName, $url)
    {
        $subject = $this->translate('forums/notification-mail/subject');

        $message = sprintf(
            $this->translate('forums/notification-mail/body'),
            $topicName,
            $url
        );

        $mail = new Mail\Message();
        $mail
            ->setEncoding('utf-8')
            ->setBody($message)
            ->setFrom('no-reply@autowp.ru', $this->translate('forums/notification-mail/from'))
            ->addTo($email)
            ->setSubject($subject);

        $this->transport->send($mail);
    }

    public function topicAction()
    {
        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');
        $isModearator = $this->user()->inheritsRole('moder');

        $topic = $this->model->getTopic((int)$this->params('topic_id'), [
            'status'      => [Forums::STATUS_NORMAL, Forums::STATUS_CLOSED],
            'isModerator' => $isModearator
        ]);

        if (! $topic) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $canAddComments = $user && ($topic['status'] == Forums::STATUS_NORMAL) || $forumAdmin;

        $needWait = $this->needWait();

        $formMessageNew = null;
        if ($canAddComments) {
            $form = $this->commentForm;
            $this->commentForm->setAttribute('action', $this->url()->fromRoute('forums/topic', [
                'topic_id' => $topic['id']
            ]));
            // 'canModeratorAttention' => $this->user()->isAllowed('comment', 'moderator-attention')

            $request = $this->getRequest();
            if ($request->isPost()) {
                $form->setData($request->getPost());
                if ($form->isValid()) {
                    if (! $needWait) {
                        $values = $form->getData();

                        $values['topic_id'] = $topic['id'];
                        $values['user_id'] = $user->id;
                        $values['ip'] = $request->getServer('REMOTE_ADDR');
                        $values['resolve'] = $isModearator && $values['parent_id'] && $values['resolve'];
                        $messageId = $this->model->addMessage($values);

                        $user->forums_messages = new Zend_Db_Expr('forums_messages + 1');
                        $user->last_message_time = new Zend_Db_Expr('NOW()');
                        $user->save();

                        $messageUrl = $this->topicMessageUrl($messageId, true);

                        $userTable = new User();

                        if ($values['parent_id']) {
                            $authorId = $this->comments->getMessageAuthorId($values['parent_id']);
                            if ($authorId && ($authorId != $user->id)) {
                                $parentMessageAuthor = $userTable->fetchRow([
                                    'id = ?' => $authorId,
                                    'not deleted'
                                ]);
                                if ($parentMessageAuthor) {
                                    $moderUrl = $this->url()->fromRoute('users/user', [
                                        'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                                    ], [
                                        'force_canonical' => true
                                    ]);
                                    $message = sprintf(
                                        "%s ответил на ваше сообщение\n%s",
                                        $moderUrl,
                                        $messageUrl
                                    );

                                    $this->message->send(null, $parentMessageAuthor->id, $message);
                                }
                            }
                        }

                        $ids = $this->model->getSubscribersIds($topic['id']);
                        $subscribers = $userTable->find($ids);

                        foreach ($subscribers as $subscriber) {
                            if ($subscriber->id == $user->id) {
                                continue;
                            }

                            if ($subscriber->e_mail) {
                                $this->sendMessageEmailNotification($subscriber->e_mail, $topic['name'], $messageUrl);
                            }
                        }

                        return $this->redirect()->toUrl($this->topicMessageUrl($messageId));
                    }
                }
            }

            $formMessageNew = $form;
        }

        $data = $this->model->topicPage(
            $topic['id'],
            $user ? $user->id : null,
            $this->params('page'),
            $isModearator
        );

        if (! $data) {
            return $this->notFoundAction();
        }

        $canRemoveComments = $this->user()->isAllowed('comment', 'remove');
        $canViewIp = $this->user()->isAllowed('user', 'ip');

        return array_replace($data, [
            'formMessageNew'    => $formMessageNew,
            'needWait'          => $needWait,
            'forumAdmin'        => $forumAdmin,
            'canAddComments'    => $canAddComments,
            'canRemoveComments' => $canRemoveComments,
            'canMoveMessage'    => $forumAdmin,
            'canViewIp'         => $canViewIp,
            'subscribeUrl'      => $this->url()->fromRoute('forums/subscribe', [
                'topic_id' => $topic['id']
            ]),
            'unsubscribeUrl'    => $this->url()->fromRoute('forums/unsubscribe', [
                'topic_id' => $topic['id']
            ]),
            'moveMessageRoute'  => 'forums/move-message',
            'moveMessageUrl'    => [
            ]
        ]);
    }

    private function themeUrl($themeId)
    {
        return $this->url()->fromRoute('forums/index', [
            'theme_id' => $themeId
        ]);
    }

    private function topicUrl($topicId, $page = null)
    {
        return $this->url()->fromRoute('forums/topic', [
            'topic_id' => $topicId,
            'page'     => $page
        ]);
    }

    private function topicMessageUrl($messageId, $forceCanonical = false)
    {
        return $this->url()->fromRoute('forums/topic-message', [
            'message_id' => $messageId
        ], [
            'force_canonical' => $forceCanonical
        ]);
    }

    public function subscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $topicId = (int)$this->params('topic_id');

        $this->model->subscribe($topicId, $user->id);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl(
            $referer ? $referer : $this->topicUrl($topicId)
        );
    }

    public function unsubscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $topicId = (int)$this->params('topic_id');

        $this->model->unsubscribe($topicId, $user->id);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl(
            $referer ? $referer : $this->topicUrl($topicId)
        );
    }

    private function needWait()
    {
        $user = $this->user()->get();
        if ($user) {
            if ($nextMessageTime = $user->nextMessageTime()) {
                return $nextMessageTime > new DateTime();
            }
        }

        return false;
    }

    public function newAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forbiddenAction();
        }

        $theme = $this->model->getTheme($this->params('theme_id'));

        if (! $theme || $theme['disable_topics']) {
            return $this->notFoundAction();
        }

        $needWait = $this->needWait();

        $user = $this->user()->get();
        if ($user) {
            $form = $this->newTopicForm;
            $this->newTopicForm->setAttribute('action', $this->url()->fromRoute('forums/new', [
                'theme_id' => $theme['id']
            ]));

            $request = $this->getRequest();

            if ($request->isPost()) {
                $this->newTopicForm->setData($request->getPost());
                if ($this->newTopicForm->isValid()) {
                    if (! $needWait) {
                        $values = $this->newTopicForm->getData();

                        $values['user_id'] = $user->id;
                        $values['theme_id'] = $theme['id'];
                        $values['ip'] = $request->getServer('REMOTE_ADDR');

                        $topicId = $this->model->addTopic($values);

                        $user->setFromArray([
                            'forums_topics'     => new Zend_Db_Expr('forums_topics + 1'),
                            'forums_messages'   => new Zend_Db_Expr('forums_messages + 1'),
                            'last_message_time' => new Zend_Db_Expr('NOW()')
                        ]);
                        $user->save();

                        return $this->redirect()->toUrl($this->topicUrl($topicId));
                    }
                }
            }
        }

        return [
            'formTopicNew' => $this->newTopicForm,
            'needWait'     => $needWait,
            'theme'        => $theme
        ];
    }

    public function topicMessageAction()
    {
        $messageId = $this->params('message_id');
        $page = $this->model->getMessagePage($messageId);
        if (! $page) {
            return $this->notFoundAction();
        }

        return $this->redirect()->toUrl($this->topicUrl($page['topic_id'], $page['page']) . '#msg' . $messageId);
    }

    private function authorizedForumModer(callable $callback)
    {
        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');
        if (! $forumAdmin) {
            return $this->forbiddenAction();
        }

        return $callback();
    }


    public function openAction()
    {
        return $this->authorizedForumModer(function () {

            $this->model->open($this->params()->fromPost('topic_id'));

            return new JsonModel([
                'ok' => true
            ]);
        });
    }

    public function closeAction()
    {
        return $this->authorizedForumModer(function () {

            $this->model->close($this->params()->fromPost('topic_id'));

            return new JsonModel([
                'ok' => true
            ]);
        });
    }

    public function deleteAction()
    {
        return $this->authorizedForumModer(function () {

            $this->model->delete($this->params()->fromPost('topic_id'));

            return new JsonModel([
                'ok' => true
            ]);
        });
    }

    public function moveAction()
    {
        return $this->authorizedForumModer(function () {
            $topic = $this->model->getTopic($this->params('topic_id'));
            if (! $topic) {
                return $this->notFoundAction();
            }

            $theme = $this->model->getTheme($this->params()->fromPost('theme_id'));

            if ($theme) {
                $this->model->moveTopic($topic['id'], $theme['id']);

                return $this->redirect()->toUrl($this->themeUrl($theme['id']));
            }

            return [
                'themes' => $this->model->getThemes(),
                'topic'  => $topic
            ];
        });
    }

    public function subscribesAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $moder = new Forums();

        $topics = $moder->getSubscribedTopics($user->id);

        $userTable = new User();

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

            $topic['unsubscribeUrl'] = $this->url()->fromRoute('forums/unsubscribe', [
                'topic_id' => $topic['id']
            ]);
        }
        unset($topic);

        return [
            'topics' => $topics
        ];
    }

    public function moveMessageAction()
    {
        return $this->authorizedForumModer(function () {

            $messageId = $this->params('id');

            if (! $messageId) {
                return $this->notFoundAction();
            }

            $themes = [];
            $topics = [];

            $theme = $this->model->getTheme($this->params()->fromQuery('theme_id'));
            if ($theme) {
                $topicId = (int)$this->params()->fromQuery('topic_id');
                if ($topicId) {
                    $this->model->moveMessage($messageId, $topicId);

                    return $this->redirect()->toUrl($this->topicMessageUrl($messageId));
                } else {
                    $topics = $this->model->getTopics($theme['id']);
                }
            } else {
                $themes = $this->model->getThemes();
            }

            return [
                'id'     => $messageId,
                'theme'  => $theme,
                'themes' => $themes,
                'topics' => $topics
            ];
        });
    }
}
