<?php

namespace Autowp\Forums\Controller;

use DateTime;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;

use Application\Comments;

class FrontendController extends AbstractActionController
{
    private $commentForm;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Forums
     */
    private $model;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        Forums $model,
        $commentForm,
        MessageService $message,
        Comments $comments,
        User $userModel
    ) {
        $this->model = $model;
        $this->commentForm = $commentForm;
        $this->message = $message;
        $this->comments = $comments;
        $this->userModel = $userModel;
    }

    public function topicAction()
    {
        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');
        $isModerator = $this->user()->inheritsRole('moder');

        $topic = $this->model->getTopic((int)$this->params('topic_id'), [
            'status'      => [Forums::STATUS_NORMAL, Forums::STATUS_CLOSED],
            'isModerator' => $isModerator
        ]);

        if (! $topic) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $canAddComments = $user && ($topic['status'] == Forums::STATUS_NORMAL) || $forumAdmin;

        $needWait = $this->needWait();

        $formMessageNew = null;
        if ($canAddComments) {
            $this->commentForm->setAttribute('action', $this->url()->fromRoute('forums/topic', [
                'topic_id' => $topic['id']
            ]));
            // 'canModeratorAttention' => $this->user()->isAllowed('comment', 'moderator-attention')

            $request = $this->getRequest();
            if ($request->isPost()) {
                $this->commentForm->setData($request->getPost());
                if ($this->commentForm->isValid()) {
                    if (! $needWait) {
                        $values = $this->commentForm->getData();

                        $values['topic_id'] = $topic['id'];
                        $values['user_id'] = $user['id'];
                        $values['ip'] = $request->getServer('REMOTE_ADDR');
                        $values['resolve'] = $isModerator && $values['parent_id'] && $values['resolve'];
                        $messageId = $this->model->addMessage($values);

                        $this->userModel->getTable()->update([
                            'forums_messages'   => new Sql\Expression('forums_messages + 1'),
                            'last_message_time' => new Sql\Expression('NOW()')
                        ], [
                            'id' => $user['id']
                        ]);

                        $messageUrl = $this->topicMessageUrl($messageId, true);

                        if ($values['parent_id']) {
                            $authorId = $this->comments->service()->getMessageAuthorId($values['parent_id']);
                            if ($authorId && ($authorId != $user['id'])) {
                                $parentMessageAuthor = $this->userModel->getRow([
                                    'id'          => (int)$authorId,
                                    'not_deleted' => true
                                ]);

                                if ($parentMessageAuthor) {
                                    $moderUrl = $this->url()->fromRoute('users/user', [
                                        'user_id' => $user['identity'] ? $user['identity'] : 'user' . $user['id']
                                    ], [
                                        'force_canonical' => true
                                    ]);
                                    $message = sprintf(
                                        "%s ответил на ваше сообщение\n%s",
                                        $moderUrl,
                                        $messageUrl
                                    );

                                    $this->message->send(null, $parentMessageAuthor['id'], $message);
                                }
                            }
                        }

                        $this->comments->notifySubscribers($messageId);

                        return $this->redirect()->toUrl($this->topicMessageUrl($messageId));
                    }
                }
            }

            $formMessageNew = $this->commentForm;
        }

        $data = $this->model->topicPage(
            $topic['id'],
            $user ? $user['id'] : null,
            $this->params('page'),
            $isModerator
        );

        if (! $data) {
            return $this->notFoundAction();
        }

        if ($user) {
            $this->comments->service()->markSubscriptionAwaiting(Comments::FORUMS_TYPE_ID, $topic['id'], $user['id']);
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
            'subscribeUrl'      => $this->url()->fromRoute('api/comment/subscribe', [
                'item_id' => $topic['id'],
                'type_id' => Comments::FORUMS_TYPE_ID
            ]),
            'moveMessageRoute'  => 'forums/move-message',
            'moveMessageUrl'    => [
            ]
        ]);
    }

    private function themeUrl(int $themeId)
    {
        return '/ng/forums/'. $themeId;
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

        $this->model->subscribe($topicId, $user['id']);

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

        $this->model->unsubscribe($topicId, $user['id']);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl(
            $referer ? $referer : $this->topicUrl($topicId)
        );
    }

    private function needWait()
    {
        $user = $this->user()->get();
        if ($user) {
            $nextMessageTime = $this->userModel->getNextMessageTime($user['id']);
            if ($nextMessageTime) {
                return $nextMessageTime > new DateTime();
            }
        }

        return false;
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

        $topics = $this->model->getSubscribedTopics($user['id']);

        foreach ($topics as &$topic) {
            $author = $this->userModel->getRow($topic['authorId']);
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
