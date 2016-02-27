<?php

class Moder_PagesController extends Zend_Controller_Action
{
    /**
     * @var Pages
     */
    private $table;
    
    /**
     * @var Page_Language
     */
    private $langTable;

    public function init()
    {
        parent::init();

        $this->table = new Pages();
        $this->langTable = new Page_Language();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
    }

    public function newPageAction()
    {
        $form = new Application_Form_Moder_Page_New(array(
            'action' => $this->_helper->url->url()
        ));

        $form->populate($this->_getAllParams());

        if ($this->getRequest()->isPost() && $form->isValid($_POST)) {
            $values = $form->getValues();

            $values['position'] = 1 + (int)$this->table->getAdapter()->fetchOne(
                $this->table->select()
                    ->from($this->table, array(new Zend_Db_Expr('MAX(position)')))
                    ->where('parent_id = ?', $values['parent_id'])
            );

            $page_id = $this->table->insert($values);

            return $this->_redirect($this->_helper->url('edit-page', 'pages', 'moder', array(
                'page_id' => $page_id
            )));
        }

        $this->view->newPageForm = $form;
    }

    public function editPageAction()
    {
        $languages = array(
            'ru', 'en', 'fr'
        );

        $form = new Application_Form_Moder_Page_Edit(array(
            'action'  => $this->_helper->url->url(),
            'languages' => $languages
        ));

        $page = $this->table->find($this->_getParam('page_id'))->current();

        if (!$page)
            return $this->_forward('notfound', 'error');

        $values = $page->toArray();
        foreach ($languages as $lang) {
            $langPage = $this->langTable->fetchRow(array(
                'page_id = ?'  => $page->id,
                'language = ?' => $lang
            ));
            if ($langPage) {
                $values[$lang] = $langPage->toArray();
            }
        }

        $form->populate($values);

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            foreach ($languages as $lang) {
                $langValues = $values[$lang];
                unset($values[$lang]);

                $langPage = $this->langTable->fetchRow(array(
                    'page_id = ?'  => $page->id,
                    'language = ?' => $lang
                ));

                if (!$langPage) {
                    $langPage = $this->langTable->fetchNew();
                    $langPage->setFromArray(array(
                        'page_id'  => $page->id,
                        'language' => $lang
                    ));
                }

                $langPage->setFromArray($langValues);
                $langPage->save();
            }

            // check position
            // ищем строку с таким же position
            $filter = array(
                'id <> ?' => $page->id
            );
            if ($values['parent_id']) {
                $filter['parent_id = ?'] = $values['parent_id'];
            } else {
                $filter[] = 'parent_id is null';
            }
            $samePositionRow = $this->table->fetchRow($filter);
            if ($samePositionRow) {
                // get new position
                $select = $this->table
                    ->select()
                    ->from($this->table, array(new Zend_Db_Expr('MAX(position)')))
                    ->where('parent_id = ?', $values['parent_id']);
                if ($values['parent_id']) {
                    $select->where('parent_id = ?', $values['parent_id']);
                } else {
                    $select->where('parent_id is null');
                }
                $values['position'] = 1 + (int)$this->table->getAdapter()->fetchOne($select);
            }

            $page->setFromArray($values);
            $page->save();
        }

        $this->view->editPageForm = $form;
    }

    public function moveUpPageAction()
    {
        $page = $this->table->find($this->_getParam('page_id'))->current();
        if (!$page)
            return $this->_forward('notfound', 'error');

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

        $this->_redirect($this->_helper->url->url(array(
            'action' => 'index'
        )));
    }

    public function moveDownPageAction()
    {
        $page = $this->table->find($this->_getParam('page_id'))->current();
        if (!$page) {
            return $this->_forward('notfound', 'error');
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

        $this->_redirect($this->_helper->url->url(array(
            'action' => 'index'
        )));
    }

    public function removePageAction()
    {
        $page = $this->table->find($this->_getParam('page_id'))->current();
        if (!$page) {
            return $this->_forward('notfound', 'error');
        }

        $page->delete();

        $this->_redirect($this->_helper->url->url(array(
            'action' => 'index'
        )));
    }
}