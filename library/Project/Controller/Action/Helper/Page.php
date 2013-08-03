<?php

class Project_Controller_Action_Helper_Page extends Zend_Controller_Action_Helper_Abstract
{
    protected $_page = null;

    protected $_pageTable = null;

    public function init()
    {
        $this->_pageTable = new Pages();
    }

    public function direct()
    {
        return $this;
    }

    public function page()
    {
        return $this->_page;
    }

    /**
     * initialize a page
     *
     * @param int $pageId
     */
    public function initPage($pageId)
    {
        $controller = $this->getActionController();
        $view = $controller->view;

        $this->_page = $this->_pageTable->find($pageId)->current();
        $title = $view->page($this->_page)->title;
        $name = $view->page($this->_page)->name;
        $view->headTitle($title ? $title : $name, 'SET');
        $view->pageTitle($name, 'SET');
        $view->page = $this->_page;
        $view->breadcrumbs()->reset();
        $view->page(null)->setCurrentPage($this->_page);

        $this->_addPage($this->_page);

        $parent_id = $this->_page->parent_id;
        while ($parent_id) {
            $page = $this->_pageTable->find($parent_id)->current();
            $parent_id = $page ? $page->parent_id : null;

            $this->_addPage($page);
        }
    }

    private function _addPage(Zend_Db_Table_Row $page)
    {
        if (!$page->is_group_node) {
            $view = $this->getActionController()->view;
            if ($page->breadcrumbs) {
                $view->breadcrumbs($page->url, $page->breadcrumbs, 'prepend');
            } else {
                $view->breadcrumbs($page->url, $view->page($page)->name, 'prepend');
            }
        }
    }
}