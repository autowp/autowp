<?php

namespace Application\Controller\Moder;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\Feature\RowGatewayFeature;
use Zend\Db\RowGateway\RowGateway;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\Page as PageForm;
use Application\Model\DbTable\Page;

class PagesController extends AbstractActionController
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(Adapter $adapter)
    {
        $this->table = new TableGateway('pages', $adapter, new RowGatewayFeature('id'));
    }

    private function getPagesList($parentId)
    {
        $result = [];

        $select = new Sql\Select($this->table->getTable());

        $select->order('position');
        if ($parentId) {
            $select->where(['parent_id' => $parentId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }
        $rows = $this->table->selectWith($select);
        foreach ($rows as $page) {
            $result[] = [
                'id'          => $page['id'],
                'name'        => $page['name'],
                'breadcrumbs' => $page['breadcrumbs'],
                'isGroupNode' => $page['is_group_node'],
                'childs' => $this->getPagesList($page['id']),
                'moveUpUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'move-up-page',
                    'page_id' => $page['id']
                ]),
                'moveDownUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'move-down-page',
                    'page_id' => $page['id']
                ]),
                'deleteUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'remove-page',
                    'page_id' => $page['id']
                ]),
                'addChildUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'    => 'item',
                    'parent_id' => $page['id']
                ]),
                'itemUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'item',
                    'page_id' => $page['id']
                ])
            ];
        }

        return $result;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return [
            'items' => $this->getPagesList(null)
        ];
    }

    private function getParentOptions($parentId = null)
    {
        $db = $this->table->getAdapter();
        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns(['id', 'name'])
            ->order('position');

        if ($parentId) {
            $select->where(['parent_id' => $parentId]);
        } else {
            $select->where(['parent_id is null']);
        }

        $result = [];

        foreach ($this->table->selectWith($select) as $row) {
            $result[$row['id']] = $row['name'];
            foreach ($this->getParentOptions($row['id']) as $childId => $childName) {
                $result[$childId] = '...' . $childName;
            }
        }

        return $result;
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->params('page_id');
        if ($id) {
            $page = $this->table->select(['id' => $id])->current();
            if (! $page) {
                return $this->notFoundAction();
            }
        } else {
            $page = new RowGateway('id', $this->table->getTable(), $this->table->getAdapter());
        }

        $form = new PageForm(null, [
            'parents' => $this->getParentOptions()
        ]);

        $form->setAttribute('action', $this->url()->fromRoute('moder/pages/params', [
            'action'  => 'item',
            'page_id' => $page->rowExistsInDatabase() ? $page['id'] : null
        ]));

        $values = $page->toArray();
        if (! $page->rowExistsInDatabase()) {
            $values['parent_id'] = $this->params('parent_id');
        }

        $form->populateValues($values);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                // check position
                if ($page->rowExistsInDatabase()) {
                    $position = $page['position'];

                    // look for same position
                    $filter = [
                        'id <> ?' => $page['id']
                    ];
                    if ($values['parent_id']) {
                        $filter['parent_id = ?'] = $values['parent_id'];
                    } else {
                        $filter[] = 'parent_id is null';
                    }
                    $samePositionRow = $this->table->select($filter)->current();
                    if ($samePositionRow) {
                        // get new position
                        $select = new Sql\Select($this->table->getTable());
                        $select->columns(['position' => new Sql\Expression('MAX(position)')]);
                        if ($values['parent_id']) {
                            $select->where(['parent_id' => $values['parent_id']]);
                        } else {
                            $select->where('parent_id is null');
                        }

                        $sql = new Sql\Sql($this->table->getAdapter());
                        $statement = $sql->prepareStatementForSqlObject($select);
                        $result = $statement->execute();
                        $row = $result->current();
                        $position = 1 + $row['position'];
                    }
                } else {
                    $select = new Sql\Select($this->table->getTable());
                    $select
                        ->columns(['position' => new Sql\Expression('MAX(position)')])
                        ->where(['parent_id' => $values['parent_id']]);

                    $sql = new Sql\Sql($this->table->getAdapter());
                    $statement = $sql->prepareStatementForSqlObject($select);
                    $result = $statement->execute();
                    $row = $result->current();
                    $position = 1 + $row['position'];
                }

                $page->populate([
                    'id'              => $page->rowExistsInDatabase() ? $page['id'] : null,
                    'parent_id'       => $values['parent_id'] ? $values['parent_id'] : null,
                    'name'            => $values['name'],
                    'title'           => $values['title'],
                    'breadcrumbs'     => $values['breadcrumbs'],
                    'url'             => $values['url'],
                    'class'           => $values['class'],
                    'is_group_node'   => (int)$values['is_group_node'],
                    'registered_only' => (int)$values['registered_only'],
                    'guest_only'      => (int)$values['guest_only'],
                    'position'        => $position
                ], $page->rowExistsInDatabase());
                $page->save();

                return $this->redirect()->toRoute('moder/pages/params', [
                    'action'  => 'item',
                    'page_id' => $page['id']
                ]);
            }
        }

        return [
            'form' => $form
        ];
    }

    public function moveUpPageAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $page = $this->table->select(['id' => (int)$this->params('page_id')])->current();
        if (! $page) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->table->getTable());
        $select
            ->where([
                'parent_id'    => $page['parent_id'],
                'position < ?' => $page['position']
            ])
            ->order('position DESC')
            ->limit(1);
        $prevPage = $this->table->selectWith($select)->current();

        if ($prevPage) {
            $prevPagePos = $prevPage['position'];

            $prevPage['position'] = 10000;
            $prevPage->save();

            $pagePos = $page['position'];
            $page['position'] = $prevPagePos;
            $page->save();

            $prevPage['position'] = $pagePos;
            $prevPage->save();
        }

        return $this->redirect()->toRoute('moder/pages');
    }

    public function moveDownPageAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $page = $this->table->select(['id' => (int)$this->params('page_id')])->current();
        if (! $page) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->table->getTable());
        $select
            ->where([
                'parent_id'    => $page['parent_id'],
                'position > ?' => $page['position']
            ])
            ->order('position DESC')
            ->limit(1);
        $nextPage = $this->table->selectWith($select)->current();

        if ($nextPage) {
            $nextPagePos = $nextPage['position'];

            $nextPage['position'] = 10000;
            $nextPage->save();

            $pagePos = $page['position'];
            $page['position'] = $nextPagePos;
            $page->save();

            $nextPage['position'] = $pagePos;
            $nextPage->save();
        }

        return $this->redirect()->toRoute('moder/pages');
    }

    public function removePageAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $page = $this->table->select(['id' => (int)$this->params('page_id')])->current();
        if (! $page) {
            return $this->notFoundAction();
        }

        $page->delete();

        return $this->redirect()->toRoute('moder/pages');
    }
}
