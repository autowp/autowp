<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Article;
use Application\Model\DbTable\Article\BrandCache;
use Application\Model\DbTable\User;
use Application\Paginator\Adapter\Zend1DbTableSelect;

class ArticlesController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;

    private function getBrandsMenu()
    {
        $brandModel = new BrandModel();

        $language = $this->language();

        return $brandModel->getList($language, function($select) {
            $select
                ->join(['abc' => 'articles_brands_cache'], 'brands.id = abc.brand_id', null)
                ->join('articles', 'abc.article_id = articles.id', null)
                ->where('articles.enabled')
                ->group('brands.id');
        });
    }

    public function indexAction()
    {
        $brandModel = new BrandModel();

        $brand = $brandModel->getBrandByCatname($this->params('brand_catname'), $this->language());

        $articles = new Article();

        $select = $articles->select(true)
            ->where('articles.enabled')
            ->order(['articles.ratio DESC', 'articles.add_date DESC']);

        if ($brand) {
            $select
                ->join(['abc' => 'articles_brands_cache'], 'articles.id=abc.article_id', null)
                ->where('abc.brand_id = ?', $brand['id']);
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(self::ARTICLES_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $articles = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $articles[] = [
                'previewUrl'  => $row->getPreviewUrl(),
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
            'menu'             => $this->getBrandsMenu(),
            'brand'            => $brand,
            'paginator'        => $paginator,
            'articles'         => $articles,
            'selectedBrandIds' => $brand ? [$brand['id']] : [],
            'urlParams'        => [
                'action'        => 'index',
                'brand_catname' => $brand ? $brand['catname'] : null
            ]
        ];
    }

    public function articleAction()
    {
        $articles = new Article();

        $article = $articles->findRowByCatname($this->params('article_catname'));
        if (!$article) {
            return $this->notFoundAction();
        }

        if (!$article->enabled) {
            return $this->notFoundAction();
        }

        $links = [];

        $brandModel = new BrandModel();
        $brands = $brandModel->getList($this->language(), function($select) use ($article) {
            $select
                ->join('articles_brands', 'brands.id = articles_brands.brand_id', null)
                ->where('articles_brands.article_id = ?', $article->id);
        });
        foreach ($brands as $brand) {
            $links[] = [
                'url'  => $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand',
                    'brand_catname' => $brand['catname']
                ]),
                'name' => $brand['name']
            ];
        }

        $selectedBrandIds = [];
        foreach ($article->findDependentRowset(BrandCache::class) as $abc) {
            $selectedBrandIds[] = $abc->brand_id;
        }

        return [
            'article'          => $article,
            'menu'             => $this->getBrandsMenu(),
            'selectedBrandIds' => $selectedBrandIds,
            'links'            => $links
        ];
    }
}