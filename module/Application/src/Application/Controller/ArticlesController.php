<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Article;
use Application\Paginator\Adapter\Zend1DbTableSelect;

class ArticlesController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;

    public function indexAction()
    {
        $articles = new Article();

        $select = $articles->select(true)
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
            if ($row->previewExists()) {
                $previewUrl = '/' . Article::PREVIEW_CAT_PATH . $row->preview_filename;
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
        $articles = new Article();

        $article = $articles->findRowByCatname($this->params('article_catname'));
        if (! $article) {
            return $this->notFoundAction();
        }

        if (! $article->enabled) {
            return $this->notFoundAction();
        }

        return [
            'article' => $article,
        ];
    }
}
