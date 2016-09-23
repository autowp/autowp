<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\Page as PageForm;

use Pages;
use Page_Language;

use Zend_Db_Expr;

class PagesController extends AbstractActionController
{
    /**
     * @var Pages
     */
    private $table;

    /**
     * @var Page_Language
     */
    private $langTable;

    private $languages = [
        'ru', 'en', 'fr'
    ];

    public function __construct()
    {
        $this->table = new Pages();
        $this->langTable = new Page_Language();
    }

    private function getPagesList($parentId)
    {
        $result = [];

        $select = $this->table->select(true)
            ->order('position');
        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        } else {
            $select->where('parent_id IS NULL');
        }
        $rows = $this->table->fetchAll($select);
        foreach ($rows as $idx => $page) {
            $result[] = [
                'id'          => $page['id'],
                'name'        => $page['name'],
                'breadcrumbs' => $page->breadcrumbs,
                'isGroupNode' => $page->is_group_node,
                'childs' => $this->getPagesList($page->id),
                'moveUpUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'move-up-page',
                    'page_id' => $page->id
                ]),
                'moveDownUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'move-down-page',
                    'page_id' => $page->id
                ]),
                'deleteUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'remove-page',
                    'page_id' => $page->id
                ]),
                'addChildUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'    => 'item',
                    'parent_id' => $page->id
                ]),
                'itemUrl' => $this->url()->fromRoute('moder/pages/params', [
                    'action'  => 'item',
                    'page_id' => $page->id
                ])
            ];
        }

        return $result;
    }

    public function indexAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        return [
            'items' => $this->getPagesList(null)
        ];
    }

    private function getParentOptions($parentId = null)
    {
        $db = $this->table->getAdapter();
        $select = $db->select()
            ->from($this->table->info('name'), ['id', 'name'])
            ->order('position');

        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        } else {
            $select->where('parent_id is null');
        }

        $result = [];

        $pairs = $db->fetchPairs($select);

        foreach ($pairs as $id => $name) {
            $result[$id] = $name;
            foreach ($this->getParentOptions($id) as $childId => $childName) {
                $result[$childId] = '...' . $childName;
            }
        }

        return $result;
    }

    public function itemAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->params('page_id');
        if ($id) {
            $page = $this->table->find($id)->current();
            if (!$page) {
                return $this->notFoundAction();
            }
        } else {
            $page = $this->table->createRow();
        }

        $form = new PageForm(null, [
            'languages' => $this->languages,
            'parents'   => $this->getParentOptions()
        ]);

        $form->setAttribute('action', $this->url()->fromRoute('moder/pages/params', [
            'action'  => 'item',
            'page_id' => $page->id
        ]));

        $values = $page->toArray();
        if ($page->id) {
            foreach ($this->languages as $lang) {
                $langPage = $this->langTable->fetchRow([
                    'page_id = ?'  => $page->id,
                    'language = ?' => $lang
                ]);
                if ($langPage) {
                    $values[$lang] = $langPage->toArray();
                }
            }
        } else {
            $values['parent_id'] = $this->params('parent_id');
        }

        $form->populateValues($values);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                // check position
                if ($page->id) {
                    $position = $page->position;

                    // ищем строку с таким же position
                    $filter = [
                        'id <> ?' => $page->id
                    ];
                    if ($values['parent_id']) {
                        $filter['parent_id = ?'] = $values['parent_id'];
                    } else {
                        $filter[] = 'parent_id is null';
                    }
                    $samePositionRow = $this->table->fetchRow($filter);
                    if ($samePositionRow) {
                        // get new position
                        $select = $this->table->select()
                            ->from($this->table, new Zend_Db_Expr('MAX(position)'));
                        if ($values['parent_id']) {
                            $select->where('parent_id = ?', $values['parent_id']);
                        } else {
                            $select->where('parent_id is null');
                        }
                        $position = 1 + (int)$this->table->getAdapter()->fetchOne($select);
                    }
                } else {
                    $position = 1 + (int)$this->table->getAdapter()->fetchOne(
                        $this->table->select()
                            ->from($this->table, new Zend_Db_Expr('MAX(position)'))
                            ->where('parent_id = ?', $values['parent_id'])
                    );
                }

                $page->setFromArray([
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
                ]);
                $page->save();

                foreach ($this->languages as $lang) {
                    $langValues = $values[$lang];
                    unset($values[$lang]);

                    $langPage = $this->langTable->fetchRow([
                        'page_id = ?'  => $page->id,
                        'language = ?' => $lang
                    ]);

                    if (!$langPage) {
                        $langPage = $this->langTable->createRow([
                            'page_id'  => $page->id,
                            'language' => $lang
                        ]);
                    }

                    $langPage->setFromArray($langValues);
                    $langPage->save();
                }

                return $this->redirect()->toRoute('moder/pages/params', [
                    'action'  => 'item',
                    'page_id' => $page->id
                ]);
            }
        }

        return [
            'form' => $form
        ];
    }

    public function moveUpPageAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $page = $this->table->find($this->params('page_id'))->current();
        if (!$page) {
            return $this->notFoundAction();
        }

        $prevPage = $this->table->fetchRow(
            $this->table->select()
                ->where('parent_id = ?', $page->parent_id)
                ->where('position < ?', $page->position)
                ->order('position DESC')
                ->limit(1)
        );

        if ($prevPage) {
            $prevPagePos = $prevPage->position;

            $prevPage->position = 10000;
            $prevPage->save();

            $pagePos = $page->position;
            $page->position = $prevPagePos;
            $page->save();

            $prevPage->position = $pagePos;
            $prevPage->save();
        }

        return $this->redirect()->toRoute('moder/pages');
    }

    public function moveDownPageAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $page = $this->table->find($this->params('page_id'))->current();
        if (!$page) {
            return $this->notFoundAction();
        }

        $nextPage = $this->table->fetchRow(
            $this->table->select()
                ->where('parent_id = ?', $page->parent_id)
                ->where('position > ?', $page->position)
                ->order('position ASC')
                ->limit(1)
        );

        if ($nextPage) {
            $nextPagePos = $nextPage->position;

            $nextPage->position = 10000;
            $nextPage->save();

            $pagePos = $page->position;
            $page->position = $nextPagePos;
            $page->save();

            $nextPage->position = $pagePos;
            $nextPage->save();
        }

        return $this->redirect()->toRoute('moder/pages');
    }

    public function removePageAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $page = $this->table->find($this->params('page_id'))->current();
        if (!$page) {
            return $this->notFoundAction();
        }

        $page->delete();

        return $this->redirect()->toRoute('moder/pages');
    }
}