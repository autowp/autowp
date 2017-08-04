<?php

namespace Application\Controller;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator;

use Autowp\Commons\Db\Table;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Article;

class ArticlesController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;
    const PREVIEW_CAT_PATH = '/img/articles/preview/';

    public function __construct(TableGateway $table, TableGateway $htmlTable)
    {
        $this->table = $table;
        $this->htmlTable = $htmlTable;
    }

    public function indexAction()
    {
        $select = new Sql\Select($this->table->getTable());
        $select
            ->where('enabled')
            ->order(['ratio DESC', 'add_date DESC']);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::ARTICLES_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $userTable = new User();

        $articles = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $previewUrl = null;
            if ($row['preview_filename']) {
                $previewUrl = self::PREVIEW_CAT_PATH . $row['preview_filename'];
            }
            $articles[] = [
                'previewUrl'  => $previewUrl,
                'name'        => $row['name'],
                'description' => $row['description'],
                'author'      => $userTable->find($row['author_id'])->current(),
                'date'        => Table\Row::getDateTimeByColumnType('timestamp', $row['first_enabled_datetime']),
                'url'         => $this->url()->fromRoute('articles', [
                    'action'          => 'article',
                    'article_catname' => $row['catname']
                ])
            ];
        }

        return [
            'paginator' => $paginator,
            'articles'  => $articles,
            'urlParams' => [
                'action' => 'index'
            ]
        ];
    }

    public function articleAction()
    {
        $article = $this->table->select([
            'catname = ?' => (string)$this->params('article_catname')
        ])->current();

        if (! $article) {
            return $this->notFoundAction();
        }

        if (! $article['enabled']) {
            return $this->notFoundAction();
        }

        $htmlRow = $this->htmlTable->select([
            'id' => (int)$article['html_id']
        ])->current();

        return [
            'article' => $article,
            'html'    => $htmlRow ? $htmlRow['html'] : null
        ];
    }
}
