<?php

namespace Autowp\Forums\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;

use Application\Comments;

class FrontendController extends AbstractActionController
{
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
        MessageService $message,
        Comments $comments,
        User $userModel
    ) {
        $this->model = $model;
        $this->message = $message;
        $this->comments = $comments;
        $this->userModel = $userModel;
    }

    private function themeUrl(int $themeId)
    {
        return '/ng/forums/'. $themeId;
    }

    private function topicUrl($topicId, $page = null)
    {
        $url = '/ng/forums/topic/'. $topicId;

        if ($page) {
            $url .= '?page=' . $page;
        }

        return $url;
    }

    private function topicMessageUrl($messageId, $forceCanonical = false)
    {
        return $this->url()->fromRoute('forums/topic-message', [
            'message_id' => $messageId
        ], [
            'force_canonical' => $forceCanonical
        ]);
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
