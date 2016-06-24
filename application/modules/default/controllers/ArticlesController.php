<?php

use Application\Model\Brand;

class ArticlesController extends Zend_Controller_Action
{
    const ARTICLES_PER_PAGE = 10;

    function init()
    {
        parent::init();

        $this->view->selected_brand_ids = [];
    }

    public function loadBrands()
    {
        $brandModel = new Brand();

        $language = $this->_helper->language();

        $this->view->brandList = $brandModel->getList($language, function($select) {
            $select
                ->join(['abc' => 'articles_brands_cache'], 'brands.id = abc.brand_id', null)
                ->join('articles', 'abc.article_id = articles.id', null)
                ->where('articles.enabled')
                ->group('brands.id');
        });
    }

    public function indexAction()
    {
        $language = $this->_helper->language();

        $brandModel = new Brand();

        $brand = $brandModel->getBrandByCatname($this->getParam('brand_catname'), $language);

        $articles = new Articles();

        $select = $articles->select(true)
            ->where('articles.enabled')
            ->order(['articles.ratio DESC', 'articles.add_date DESC']);

        if ($brand) {
            $select
                ->join(['abc' => 'articles_brands_cache'], 'articles.id=abc.article_id', null)
                ->where('abc.brand_id = ?', $brand['id']);
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::ARTICLES_PER_PAGE)
            ->setCurrentPageNumber($this->getParam('page'));

        $this->view->brand = $brand;
        $this->view->paginator = $paginator;

        // сайд бар
        $this->view->selected_brand_ids = $brand ? [$brand['id']] : [];
        $this->loadBrands();
        $this->getResponse()->insert('sidebar', $this->view->render('articles/sidebar.phtml'));
    }

    public function articleAction()
    {
        $articles = new Articles();

        $article = $articles->findRowByCatname($this->getParam('article_catname'));
        if (!$article) {
            return $this->forward('notfound', 'error');
        }

        if (!$article->enabled) {
            return $this->forward('notfound', 'error');
        }

        $this->view->article = $article;

        $links = array();
        foreach ($article->findBrandsViaArticles_Brands() as $brand)
            $links[] = array(
                'url' => $brand->getUrl(),
                'name' => $brand->caption
            );

        foreach ($article->findCarsViaArticles_Cars() as $car) {
            $brands = $car->findBrandsViaBrands_Cars();
            if (count($brands) > 0) {
                foreach ($brands as $brand) {
                    $links[] = [
                        'url' => $brand->getUrl().'car'.$car->id.'/',
                        'name' => $car->getFullName()
                    ];
                }
            }
        }

        $this->view->links = $links;

        // сайдбар
        foreach ($article->findArticles_Brands_Cache() as $abc) {
            $this->view->selected_brand_ids[] = $abc->brand_id;
        }

        $this->loadBrands();
        $this->getResponse()->insert('sidebar', $this->view->render('articles/sidebar.phtml'));
    }
}