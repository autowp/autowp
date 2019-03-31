<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\TextStorage;

class TextController extends AbstractRestfulController
{
    /**
     * @var TextStorage\Service
     */
    private $textStorage;

    public function __construct(TextStorage\Service $textStorage)
    {
        $this->textStorage = $textStorage;
    }

    public function itemAction()
    {
        $textId = (int)$this->params('id');
        $revision = (int)$this->params()->fromQuery('revision');

        $text = $this->textStorage->getTextInfo($textId);
        if ($text === null) {
            return $this->notFoundAction();
        }

        if ($revision) {
            $current = $this->textStorage->getRevisionInfo($textId, $revision);
        } else {
            $current = $this->textStorage->getRevisionInfo($textId, $text['revision']);
        }
        if ($current === null) {
            return $this->notFoundAction();
        }

        $prevText = $this->textStorage->getRevisionInfo($textId, $current['revision'] - 1);

        $nextRevision = null;
        if ($current['revision'] + 1 <= $text['revision']) {
            $nextRevision = $current['revision'] + 1;
        }

        $prevRevision = null;
        if ($current['revision'] - 1 > 0) {
            $prevRevision = $current['revision'] - 1;
        }

        return new JsonModel([
            'current' => [
                'text'     => $current['text'],
                'revision' => (int)$current['revision'],
                'user_id'  => (int)$current['user_id']
            ],
            'prev' => [
                'text'     => $prevText['text'],
                'revision' => $prevRevision,
                'user_id'  => (int)$prevText['user_id']
            ],
            'next' => [
                'revision' => $nextRevision
            ]
        ]);
    }
}
