<?php

namespace Application\View\Helper;

use Application\Language as AppLanguage;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\View\Helper\AbstractHelper;

use function array_replace;
use function array_reverse;
use function in_array;
use function is_array;
use function str_replace;
use function urlencode;

class PageEnv extends AbstractHelper
{
    /** @var TableGateway */
    private $pageTable;

    /** @var array */
    private $onPath = [];

    /** @var int */
    private $language = 'en';

    public function __construct(AppLanguage $language, TableGateway $pageTable)
    {
        $this->pageTable = $pageTable;

        $this->language = $language->getLanguage();
    }

    public function __invoke(array $options = []): self
    {
        if ($options === []) {
            return $this;
        }

        $defaults = [
            'layout'             => [],
            'pageId'             => null,
            'pageTitle'          => null,
            'args'               => [],
            'breadcrumbsReplace' => null,
            'encodeUrl'          => true,
        ];

        $options = array_replace($defaults, $options);

        $view = $this->view;

        $args             = is_array($options['args']) ? $options['args'] : [];
        $preparedUrlArgs  = [];
        $preparedNameArgs = [];
        foreach ($args as $key => $value) {
            $preparedUrlArgs['%' . $key . '%']  = $options['encodeUrl'] ? urlencode($value) : $value;
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
            $page = $this->pageTable->select([
                'id' => $options['pageId'],
            ])->current();
        }

        if ($page) {
            $name  = $this->replaceArgs($view->page($page)->name, $preparedNameArgs);
            $title = $this->replaceArgs($view->page($page)->title, $preparedNameArgs);
            $title = $title ? $title : $name;

            $view->headTitle($title, 'SET');
            if (! isset($pageTitle)) {
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
                $this->onPath[] = $currentDoc['id'];

                if (! $currentDoc['is_group_node']) {
                    if ($replace && ($replace['pageId'] === $currentDoc['id'])) {
                        foreach (array_reverse($replace['breadcrumbs']) as $breadcrumb) {
                            $view->breadcrumbs($breadcrumb['url'], $breadcrumb['name'], 'prepend');
                        }
                    } else {
                        $currentUrl = $this->replaceArgs(
                            $currentDoc['url'],
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
                $currentDoc = $this->pageTable->select([
                    'id' => $currentDoc['parent_id'],
                ])->current();
            } while ($currentDoc);
        }

        if (isset($pageTitle)) {
            $view->pageTitle($pageTitle, 'SET');
        }

        return $this;
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
