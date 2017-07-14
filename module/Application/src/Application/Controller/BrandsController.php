<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\Item;

class BrandsController extends AbstractActionController
{
    private $cache;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct($cache, Item $itemModel)
    {
        $this->cache = $cache;
        $this->itemModel = $itemModel;
    }

    public function indexAction()
    {
        $isHttps = (bool)$this->getRequest()->getServer('HTTPS');

        $language = $this->language();

        $cacheKey = 'brands_list_44_' . $language . '_' . ($isHttps ? 'HTTPS' : 'HTTP');

        $items = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $imageStorage = $this->imageStorage();

            $brandModel = new BrandModel();

            $items = $brandModel->getFullBrandsList($language);

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

        $itemTable = new DbTable\Item();

        $brand = $itemTable->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::BRAND,
            'id = ?'           => (int)$this->params('brand_id')
        ]);
        if (! $brand) {
            return $this->notFoundAction();
        }

        $language = $this->language();
        $brandLangTable = new DbTable\Item\Language();
        $brandLang = $brandLangTable->fetchRow([
            'item_id = ?' => $brand->id,
            'language = ?' => $language
        ]);


        $carList = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand->id)
                ->where('item.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)')
                ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                ->group('item.id')
                ->order(['item.add_datetime DESC'])
                ->limit(30)
        );

        $cars = [];
        foreach ($carList as $car) {
            $cars[] = $this->itemModel->getNameData($car, $language);
        }

        $viewModel = new ViewModel([
            'brand'     => $brand,
            'brandLang' => $brandLang,
            'carList'   => $cars
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
