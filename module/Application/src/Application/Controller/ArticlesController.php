<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Article;

use Zend_Db_Table;

class ArticlesController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;
    const PREVIEW_CAT_PATH = 'img/articles/preview/';

    private function getTable()
    {
        return new Zend_Db_Table([
            'name' => 'articles',
            'referenceMap' => [
                'Author' => [
                    'columns'       => ['author_id'],
                    'refTableClass' => \Autowp\User\Model\DbTable\User::class,
                    'refColumns'    => ['id']
                ],
                'Html' => [
                    'columns'       => ['html_id'],
                    'refTableClass' => \Application\Model\DbTable\Html::class,
                    'refColumns'    => ['id']
                ]
            ]
        ]);
    }

    public function indexAction()
    {
        $table = $this->getTable();

        $select = $table->select(true)
            ->where('articles.enabled')
            ->order(['articles.ratio DESC', 'articles.add_date DESC']);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(self::ARTICLES_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $articles = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $previewUrl = null;
            if ($row->preview_filename) {
                $previewUrl = '/' . self::PREVIEW_CAT_PATH . $row->preview_filename;
            }
            $articles[] = [
                'previewUrl'  => $previewUrl,
                'name'        => $row->name,
                'description' => $row->description,
                'author'      => $row->findParentRow(User::class),
                'date'        => $row->getDateTime('first_enabled_datetime'),
                'url'         => $this->url()->fromRoute('articles', [
                    'action'          => 'article',
                    'article_catname' => $row->catname
                ])
            ];
        }

        return [
            'paginator'        => $paginator,
            'articles'         => $articles,
            'urlParams'        => [
                'action' => 'index'
            ]
        ];
    }

    public function articleAction()
    {
        $table = $this->getTable();

        $article = $table->fetchRow([
            'catname = ?' => (string)$this->params('article_catname')
        ]);
        if (! $article) {
            return $this->notFoundAction();
        }

        if (! $article->enabled) {
            return $this->notFoundAction();
        }

        $htmlRow = $article->findParentRow(\Application\Model\DbTable\Html::class);

        return [
            'article' => $article,
            'html'    => $htmlRow ? $htmlRow->html : null
        ];
    }
}
