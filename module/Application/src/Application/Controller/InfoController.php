<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\TextStorage;
use Autowp\User\Model\User;

class InfoController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(TextStorage\Service $textStorage, User $userModel)
    {
        $this->textStorage = $textStorage;
        $this->userModel = $userModel;
    }

    public function textAction()
    {
        $textId = (int)$this->params('id');
        $revision = (int)$this->params('revision');

        $text = $this->textStorage->getTextInfo($textId);
        if ($text === null) {
            return $this->notFoundAction();
            return;
        }

        if ($revision) {
            $current = $this->textStorage->getRevisionInfo($textId, $revision);
        } else {
            $current = $this->textStorage->getRevisionInfo($textId, $text['revision']);
        }
        if ($current === null) {
            return $this->notFoundAction();
            return;
        }

        $prevText = $this->textStorage->getRevisionInfo($textId, $current['revision'] - 1);

        $nextUrl = null;
        if ($current['revision'] + 1 <= $text['revision']) {
            $nextUrl = $this->url()->fromRoute('info/text/revision', [
                'id'       => $textId,
                'revision' => $current['revision'] + 1
            ]);
        }

        $prevUrl = null;
        if ($current['revision'] - 1 > 0) {
            $prevUrl = $this->url()->fromRoute('info/text/revision', [
                'id'       => $textId,
                'revision' => $current['revision'] - 1
            ]);
        }

        $currentUser = $this->userModel->getRow((int)$current['user_id']);
        $prevUser = $this->userModel->getRow((int)$prevText['user_id']);

        return [
            'current'     => $current,
            'prev'        => $prevText,
            'prevUrl'     => $prevUrl,
            'nextUrl'     => $nextUrl,
            'currentUser' => $currentUser,
            'prevUser'    => $prevUser
        ];
    }
}
