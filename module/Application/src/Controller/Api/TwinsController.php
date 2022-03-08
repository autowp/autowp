<?php

namespace Application\Controller\Api;

use Application\Model\Twins;
use Exception;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * @method string language()
 */
class TwinsController extends AbstractRestfulController
{
    private Twins $twins;

    private StorageInterface $cache;

    public function __construct(Twins $twins, StorageInterface $cache)
    {
        $this->twins = $twins;
        $this->cache = $cache;
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function getBrandsAction(): JsonModel
    {
        $language = $this->language();

        $key = 'API_TWINS_SIDEBAR_3_' . $language;

        $result = $this->cache->getItem($key, $success);
        if (! $success) {
            $arr = $this->twins->getBrands([
                'language' => $language,
            ]);

            $result = [];
            foreach ($arr as $brand) {
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
            'items' => $result,
        ]);
    }
}
