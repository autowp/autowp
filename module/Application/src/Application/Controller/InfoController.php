<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Spec;
use Users;

use Autowp\TextStorage;

class InfoController extends AbstractActionController
{
    private $textStorage;

    public function __construct(TextStorage\Service $textStorage)
    {
        $this->textStorage = $textStorage;
    }

    private function loadSpecs($table, $parentId)
    {
        if ($parentId) {
            $filter = ['parent_id = ?' => $parentId];
        } else {
            $filter = ['parent_id is null'];
        }

        $result = [];
        foreach ($table->fetchAll($filter, 'short_name') as $row) {
            $result[] = [
                'id'         => $row->id,
                'short_name' => $row->short_name,
                'name'       => $row->name,
                'childs'     => $this->loadSpecs($table, $row->id)
            ];
        }

        return $result;
    }

    public function specAction()
    {
        $table = new Spec();

        return [
            'items' => $this->loadSpecs($table, null)
        ];
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

        $prevText = $this->textStorage->getRevisionInfo($textId, $current['revision']-1);

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

        $userTable = new Users();
        $currentUser = $userTable->find($current['user_id'])->current();
        $prevUser = $userTable->find($prevText['user_id'])->current();

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