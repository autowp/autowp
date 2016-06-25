<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Articles;

class ArticlesController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;

    private function getBrandsMenu()
    {
        $brandModel = new Brand();

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
        $brandModel = new Brand();

        $brand = $brandModel->getBrandByCatname($this->params('brand_catname'), $this->language());

        $articles = new Articles();

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
                'author'      => $row->findParentUsers(),
                'date'        => $row->getDate('first_enabled_datetime'),
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
            'selectedBrandIds' => $brand ? [$brand['id']] : []
        ];
    }

    public function articleAction()
    {
        $articles = new Articles();

        $article = $articles->findRowByCatname($this->params('article_catname'));
        if (!$article) {
            return $this->getResponse()->setStatusCode(404);
        }

        if (!$article->enabled) {
            return $this->getResponse()->setStatusCode(404);
        }

        $links = array();
        foreach ($article->findBrandsViaArticles_Brands() as $brand)
            $links[] = [
                'url'  => $brand->getUrl(),
                'name' => $brand->caption
            ];

        foreach ($article->findCarsViaArticles_Cars() as $car) {
            $brands = $car->findBrandsViaBrands_Cars();
            if (count($brands) > 0) {
                foreach ($brands as $brand) {
                    $links[] = [
                        'url'  => $brand->getUrl() . 'car' . $car->id,
                        'name' => $car->getFullName()
                    ];
                }
            }
        }

        $selectedBrandIds = [];
        foreach ($article->findArticles_Brands_Cache() as $abc) {
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