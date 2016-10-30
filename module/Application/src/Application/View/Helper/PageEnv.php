<?php

namespace Application\View\Helper;

use Application\Language as AppLanguage;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable\Page as PageTable;

use Zend_Db_Table;

class PageEnv extends AbstractHelper
{
    /**
     * @var Zend_Db_Table
     */
    private $pageTable;

    /**
     * @var array
     */
    private $onPath = [];

    /**
     * @var int
     */
    private $language = 'en';

    public function __construct(AppLanguage $language)
    {
        $this->pageTable = new PageTable();

        $this->language = $language->getLanguage();
    }

    public function __invoke(array $options = [])
    {
        if ($options === []) {
            return $this;
        }

        $defaults = [
            'layout'             => [],
            'pageId'             => null,
            'pageTitle'          => null,
            'args'               => [],
            'breadcrumbsReplace' => null
        ];

        $options = array_merge($defaults, $options);

        $view = $this->view;

        $args = is_array($options['args']) ? $options['args'] : [];
        $preparedUrlArgs = [];
        $preparedNameArgs = [];
        foreach ($args as $key => $value) {
            $preparedUrlArgs['%' . $key . '%'] = urlencode($value);
            $preparedNameArgs['%' . $key . '%'] = $value;
        }

        if (is_array($options['layout'])) {
            $view->layout()->setVariables($options['layout']);
        }

        $pageTitle = null;
        if (isset($options['pageTitle'])) {
            $pageTitle = $options['pageTitle'];
        }

        $page = null;
        if (isset($options['pageId'])) {
            $page = $this->pageTable->find($options['pageId'])->current();
        }

        if ($page) {
            $name = $this->replaceArgs($view->page($page)->name, $preparedNameArgs);
            $title = $this->replaceArgs($view->page($page)->title, $preparedNameArgs);
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

                $this->onPath[] = $currentDoc->id;

                if (!$currentDoc->is_group_node) {
                    if ($replace && ($replace['pageId'] == $currentDoc->id)) {
                        foreach (array_reverse($replace['breadcrumbs']) as $breadcrumb) {
                            $view->breadcrumbs($breadcrumb['url'], $breadcrumb['name'], 'prepend');
                        }
                    } else {

                        $currentUrl = $this->replaceArgs(
                            $currentDoc->url,
                            $preparedUrlArgs
                        );

                        $currentName = $this->replaceArgs(
                            $currentDoc->breadcrumbs
                                ? $view->page($currentDoc)->breadcrumbs
                                : $view->page($currentDoc)->name,
                            $preparedNameArgs
                        );

                        $view->breadcrumbs($currentUrl, $currentName, 'prepend');
                    }
                }
                $currentDoc = $this->pageTable->find($currentDoc->parent_id)->current();
            } while($currentDoc);
        }

        if (isset($pageTitle)) {
            $view->pageTitle($pageTitle, 'SET');
        }
    }

    private function replaceArgs($str, $args)
    {
        foreach ($args as $key => $value) {
            $str = str_replace($key, $value, $str);
        }
        return $str;
    }

    public function isOnPath($id)
    {
        return in_array($id, $this->onPath);
    }

}