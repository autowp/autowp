<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\TextStorage;
use Autowp\User\Model\DbTable\User;

class InfoController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var TableGateway
     */
    private $specTable;

    public function __construct(TextStorage\Service $textStorage, TableGateway $specTable)
    {
        $this->textStorage = $textStorage;
        $this->specTable = $specTable;
    }

    private function loadSpecs(int $parentId): array
    {
        if ($parentId) {
            $filter = ['parent_id?' => $parentId];
        } else {
            $filter = ['parent_id is null'];
        }

        $select = new Sql\Select($this->specTable->getTable());
        $select->where($filter)
            ->order('short_name');

        $result = [];
        foreach ($this->specTable->selectWith($select) as $row) {
            $result[] = [
                'id'         => (int)$row['id'],
                'short_name' => $row['short_name'],
                'name'       => $row['name'],
                'childs'     => $this->loadSpecs($row['id'])
            ];
        }

        return $result;
    }

    public function specAction()
    {
        return [
            'items' => $this->loadSpecs(0)
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

        $userTable = new User();
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
