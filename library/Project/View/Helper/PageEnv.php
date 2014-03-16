<?php

class Project_View_Helper_PageEnv extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_Db_Table
     */
    protected $_pageTable;
    /**
     * @var Zend_Db_Table
     */
    protected $_pageLanguageTable;
    /**
     * @var int
     */
    protected $_language = 'en';

    public function __construct()
    {
        $this->_pageTable = new Pages();
        $this->_pageLanguageTable = new Page_Language();

        if (Zend_Registry::isRegistered('Zend_Locale')) {
            $locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
            $this->_language = $locale->getLanguage();
        }
    }

    public function pageEnv(array $options = array())
    {
        $defaults = array(
            'layout'    => array(),
            'pageId'    => null,
            'pageTitle' => null,
            'args'      => array(),
            'breadcrumbsReplace' => null
        );

        $options = array_merge($defaults, $options);

        $view = $this->view;

        $args = is_array($options['args']) ? $options['args'] : array();
        $preparedUrlArgs = array();
        $preparedNameArgs = array();
        foreach ($args as $key => $value) {
            $preparedUrlArgs['%' . $key . '%'] = urlencode($value);
            $preparedNameArgs['%' . $key . '%'] = $value;
        }

        if (is_array($options['layout'])) {
            $view->layout()->assign($options['layout']);
        }

        $pageTitle = null;
        if (isset($options['pageTitle'])) {
            $pageTitle = $options['pageTitle'];
        }

        $page = null;
        if (isset($options['pageId'])) {
            $page = $this->_pageTable->find($options['pageId'])->current();
        }

        if ($page) {
            $name = $this->_replaceArgs($view->page($page)->name, $preparedNameArgs);
            $title = $this->_replaceArgs($view->page($page)->title, $preparedNameArgs);
            $title = $title ? $title : $name;

            $view->headTitle($title, 'SET');
            if (!isset($pageTitle)) {
                $view->pageTitle($name, 'SET');
            }

            $view->breadcrumbs()->reset();

            if (isset($options['breadcrumbsReplace'])) {
                $replace = $options['breadcrumbsReplace'];
            } else {
                $replace = false;
            }

            $currentDoc = $page;
            do {
                if (!$currentDoc->is_group_node) {
                    if ($replace && ($replace['pageId'] == $currentDoc->id)) {
                        foreach (array_reverse($replace['breadcrumbs']) as $breadcrumb) {
                            $view->breadcrumbs($breadcrumb['url'], $breadcrumb['name'], 'prepend');
                        }
                    } else {

                        $currentUrl = $this->_replaceArgs(
                            $currentDoc->url,
                            $preparedUrlArgs
                        );

                        $currentName = $this->_replaceArgs(
                            $currentDoc->breadcrumbs
                                ? $view->page($currentDoc)->breadcrumbs
                                : $view->page($currentDoc)->name,
                            $preparedNameArgs
                        );

                        $view->breadcrumbs($currentUrl, $currentName, 'prepend');
                    }
                }
                $currentDoc = $this->_pageTable->find($currentDoc->parent_id)->current();
            } while($currentDoc);
        }

        if (isset($pageTitle)) {
            $view->pageTitle($pageTitle, 'SET');
        }
    }

    protected function _replaceArgs($str, $args)
    {
        foreach ($args as $key => $value) {
            $str = str_replace($key, $value, $str);
        }
        return $str;
    }
}