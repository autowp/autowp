<?php

namespace Autowp\Forums\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Forums\Forums;
use Autowp\User\Model\User;

class FrontendController extends AbstractActionController
{
    /**
     * @var Forums
     */
    private $model;

    public function __construct(
        Forums $model
    ) {
        $this->model = $model;
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
