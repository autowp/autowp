<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;
use Application\Model\Item;
use Zend\View\Model\JsonModel;

class BrandsController extends AbstractActionController
{
    private $cache;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct($cache, Brand $brand)
    {
        $this->cache = $cache;
        $this->brand = $brand;
    }

    public function indexAction()
    {
        $isHttps = (bool)$this->getRequest()->getServer('HTTPS');

        $language = $this->language();

        $cacheKey = 'brands_list_45_' . $language . '_' . ($isHttps ? 'HTTPS' : 'HTTP');

        $items = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $items = $this->brand->getFullBrandsList($language);

            foreach ($items as &$line) {
                foreach ($line as &$char) {
                    foreach ($char['brands'] as &$item) {
                        $item['url'] = $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand',
                            'brand_catname' => $item['catname']
                        ]);
                        $item['new_cars_url'] = $this->url()->fromRoute('brands/newcars', [
                            'brand_id' => $item['id'],
                        ]);
                    }
                }
            }
            unset($line, $char, $item);

            $this->cache->setItem($cacheKey, $items);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}
