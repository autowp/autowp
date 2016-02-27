<?php
class ArticlesController extends Zend_Controller_Action
{
    const ARTICLES_PER_PAGE = 10;

    function init()
    {
        parent::init();

        $this->view->selected_brand_ids = array();
    }

    public function loadBrands()
    {
        $brands = new Brands();
        $this->view->brandList = $brands->fetchAll(
            $brands->select(true)
                ->join(array('abc' => 'articles_brands_cache'), 'brands.id=abc.brand_id', null)
                ->join('articles', 'abc.article_id=articles.id', null)
                ->where('articles.enabled')
                ->group('brands.id')
                ->order(array('brands.position', 'brands.caption'))
        );
    }

    public function indexAction()
    {
        $brands = new Brands();
        $brand = $brands->findRowByCatname($this->_getParam('brand_catname'));

        $articles = new Articles();

        $select = $articles->select()
            ->from($articles)
            ->where('articles.enabled')
            ->order(array('articles.ratio DESC', 'articles.add_date DESC'));

        if ($brand) {
            $select->join(array('abc' => 'articles_brands_cache'), 'articles.id=abc.article_id', null)
                   ->where('abc.brand_id = ?', $brand->id);
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::ARTICLES_PER_PAGE)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->brand = $brand;
        $this->view->paginator = $paginator;

        // сайд бар
        $this->view->selected_brand_ids = $brand ? array($brand->id) : array();
        $this->loadBrands();
        $this->getResponse()->insert('sidebar', $this->view->render('articles/sidebar.phtml'));
    }

    public function articleAction()
    {
        $articles = new Articles();

        $article = $articles->findRowByCatname($this->_getParam('article_catname'));
        if (!$article)
            return $this->_forward('notfound', 'error');

        if (!$article->enabled)
            return $this->_forward('notfound', 'error');

        $this->view->article = $article;

        $links = array();
        foreach ($article->findBrandsViaArticles_Brands() as $brand)
            $links[] = array(
                'url' => $brand->getUrl(),
                'name' => $brand->caption
            );

        foreach ($article->findDesign_ProjectsViaArticles_Design_Projects() as $dp)
            $links[] = array(
                'url' => $dp->getUrl(),
                'name' => $dp->findParentBrands()->caption.' '.$dp->name
            );

        foreach ($article->findCarsViaArticles_Cars() as $car)
        {
            $brands = $car->findBrandsViaBrands_Cars();
            if (count($brands) > 0)
            {
                foreach ($brands as $brand)
                    $links[] = array(
                        'url' => $brand->getUrl().'car'.$car->id.'/',
                        'name' => $car->getFullName()
                    );
            }
        }

        $this->view->links = $links;




        // сайдбар
        foreach ($article->findBrandsViaArticles_Brands_Cache() as $brand)
            $this->view->selected_brand_ids[] = $brand->id;

        $this->loadBrands();
        $this->getResponse()->insert('sidebar', $this->view->render('articles/sidebar.phtml'));
    }
}