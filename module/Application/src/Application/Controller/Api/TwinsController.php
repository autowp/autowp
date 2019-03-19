<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Model\Twins;

class TwinsController extends AbstractRestfulController
{
    /**
     * @var Twins
     */
    private $twins;

    private $cache;

    public function __construct(Twins $twins, $cache)
    {
        $this->twins = $twins;
        $this->cache = $cache;
    }

    public function getBrandsAction()
    {
        $language = $this->language();

        $key = 'API_TWINS_SIDEBAR_3_' . $language;

        $result = $this->cache->getItem($key, $success);
        if (! $success) {
            $arr = $this->twins->getBrands([
                'language' => $language
            ]);

            $result = [];
            foreach ($arr as &$brand) {
                $result[] = [
                    'catname'   => $brand['catname'],
                    'name'      => $brand['name'],
                    'count'     => (int) $brand['count'],
                    'new_count' => (int) $brand['new_count'],
                ];
            }
            unset($brand);

            $this->cache->setItem($key, $result);
        }

        return new JsonModel([
            'items' => $result
        ]);
    }
}
