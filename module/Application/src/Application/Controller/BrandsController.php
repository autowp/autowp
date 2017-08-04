<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Brand;
use Application\Model\Item;

class BrandsController extends AbstractActionController
{
    private $cache;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct($cache, Item $itemModel, Brand $brand)
    {
        $this->cache = $cache;
        $this->itemModel = $itemModel;
        $this->brand = $brand;
    }

    public function indexAction()
    {
        $isHttps = (bool)$this->getRequest()->getServer('HTTPS');

        $language = $this->language();

        $cacheKey = 'brands_list_44_' . $language . '_' . ($isHttps ? 'HTTPS' : 'HTTP');

        $items = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $imageStorage = $this->imageStorage();

            $items = $this->brand->getFullBrandsList($language);

            foreach ($items as &$line) {
                foreach ($line as &$char) {
                    foreach ($char['brands'] as &$item) {
                        $item['url'] = $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand',
                            'brand_catname' => $item['catname']
                        ]);
                        $item['newCarsUrl'] = $this->url()->fromRoute('brands/newcars', [
                            'brand_id' => $item['id'],
                        ]);

                        $img = false;
                        if ($item['logo_id']) {
                            $imageInfo = $imageStorage->getFormatedImage($item['logo_id'], 'brandicon');
                            if ($imageInfo) {
                                $img = $imageInfo->getSrc();
                            }
                        }

                        $item['logo'] = $img;
                    }
                }
            }
            unset($line, $char, $item);

            $this->cache->setItem($cacheKey, $items);
        }

        return [
            'brandList' => $items
        ];
    }

    public function newcarsAction()
    {
        /*if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->notFoundAction();
        }*/

        $brand = $this->itemModel->getRow([
            'item_type_id' => Item::BRAND,
            'id'           => (int)$this->params('brand_id')
        ]);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $langName = $this->itemModel->getName($brand['id'], $language);

        $carList = $this->itemModel->getRows([
            'ancestor'        => $brand['id'],
            'created_in_days' => 7,
            'limit'           => 30,
            'order'           => 'item.add_datetime DESC'
        ]);

        $cars = [];
        foreach ($carList as $car) {
            $cars[] = $this->itemModel->getNameData($car, $language);
        }

        $viewModel = new ViewModel([
            'brand'     => $brand,
            'carList'   => $cars,
            'name'      => $langName ? $langName : $brand['name']
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
