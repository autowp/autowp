<?php

use Application\Model\Brand;

class BrandsController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $language = $this->_helper->language();

        $cache_key = 'brands_list_4_' . $language;

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        if (!($items = $cache->load($cache_key))) {

            $imageStorage = $this->_helper->imageStorage();
            
            $brandModel = new Brand();
            
            $items = $brandModel->getFullBrandsList($language);
            
            foreach ($items as &$char) {
                foreach ($char['brands'] as &$item) { 
                    $item['url'] = $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand',
                        'brand_catname' => $item['catname']
                    ), 'catalogue', true);
                    $item['newCarsUrl'] = $this->_helper->url->url(array(
                        'brand_id' => $item['id'],
                    ), 'brand_new_cars', true);
                    
                    $img = false;
                    if ($item['img']) {
                        $imageInfo = $imageStorage->getFormatedImage($item['img'], 'brandicon');
                        if ($imageInfo) {
                            $img = $imageInfo->getSrc();
                        }
                    }
                    
                    $item['logo'] = $img;
                }
            }
            unset($char, $item);

            $cache->save($items);
        }

        $this->view->assign(array(
            'brandList' => $items
        ));
    }

    public function newcarsAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->_forward('notfound', 'error');
        }

        $brand_id = $this->getRequest()->getParam('brand_id');
        $brands = new Brands();

        $brand = $brands->find($brand_id)->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $language = $this->_helper->language();
        $brandLangTable = new Brand_Language();
        $brandLang = $brandLangTable->fetchRow(array(
            'brand_id = ?' => $brand->id,
            'language = ?' => $language
        ));

        $cars = new Cars();
        $carList = $cars->fetchAll(
            $cars->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand_id)
                ->where('cars.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)')
                ->group('cars.id')
                ->order(array('cars.add_datetime DESC'))
                ->limit(30)
        );
        $this->view->assign(array(
            'brand'     => $brand,
            'brandLang' => $brandLang,
            'carList'   => $carList
        ));
    }

}