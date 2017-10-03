<?php

namespace Autowp\Forums\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Forums\Forums;

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

    public function topicMessageAction()
    {
        $messageId = $this->params('message_id');
        $page = $this->model->getMessagePage($messageId);
        if (! $page) {
            return $this->notFoundAction();
        }

        return $this->redirect()->toUrl($this->topicUrl($page['topic_id'], $page['page']) . '#msg' . $messageId);
    }
}
