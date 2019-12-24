<?php

namespace Application\Controller;

use Exception;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Image\Storage;

use Application\Model\Brand;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Service\Mosts;

/**
 * Class CatalogueController
 * @package Application\Controller
 *
 * @method Storage imageStorage()
 * @method string language()
 * @method Catalogue catalogue()
 */
class CatalogueController extends AbstractActionController
{
    private $mostsMinCarsCount = 1;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Mosts
     */
    private $mosts;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct(
        $mostsMinCarsCount,
        Item $itemModel,
        Mosts $mosts,
        Picture $picture,
        Brand $brand
    ) {
        $this->mostsMinCarsCount = $mostsMinCarsCount;
        $this->itemModel = $itemModel;
        $this->mosts = $mosts;
        $this->picture = $picture;
        $this->brand = $brand;
    }

    /**
     * @param callable $callback
     * @return array|ViewModel
     * @throws Exception
     */
    private function doBrandAction(callable $callback)
    {
        $language = $this->language();

        $brand = $this->brand->getBrandByCatname($this->params('brand_catname'), $language);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $result = $callback($brand);
        if (is_array($result)) {
            $result = array_replace([
                'brand' => $brand
            ], $result);
        }

        return $result;
    }

    private function mostsActive(int $brandId)
    {
        $carsCount = $this->itemModel->getCount([
            'ancestor' => $brandId
        ]);

        return $carsCount >= $this->mostsMinCarsCount;
    }

    /**
     * @return array|ViewModel
     * @throws Exception
     */
    public function brandMostsAction()
    {
        return $this->doBrandAction(function ($brand) {

            if (! $this->mostsActive($brand['id'])) {
                return $this->notFoundAction();
            }

            $language = $this->language();
            $yearsCatname = $this->params('years_catname');
            $carTypeCatname = $this->params('shape_catname');
            $mostCatname = $this->params('most_catname');

            $data = $this->mosts->getData([
                'language' => $language,
                'most'     => $mostCatname,
                'years'    => $yearsCatname,
                'carType'  => $carTypeCatname,
                'brandId'  => $brand['id']
            ]);

            foreach ($data['sidebar']['mosts'] as &$most) {
                $most['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $most['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }
            foreach ($data['sidebar']['carTypes'] as &$carType) {
                $carType['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $carType['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
                foreach ($carType['childs'] as &$child) {
                    $child['url'] = $this->url()->fromRoute(
                        'catalogue',
                        array_merge(
                            $child['params'],
                            ['brand_catname' => $brand['catname']]
                        ),
                        [],
                        true
                    );
                }
            }
            foreach ($data['years'] as &$year) {
                $year['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $year['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }

            // images
            $formatRequests = [];
            $allPictures = [];
            $idx = 0;
            foreach ($data['carList']['cars'] as $car) {
                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $formatRequests[$idx++] = $picture['image_id'];
                        $allPictures[] = $picture;
                    }
                }
            }

            $imageStorage = $this->imageStorage();
            $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

            $names = $this->picture->getNameData($allPictures, [
                'language' => $language
            ]);

            $idx = 0;
            foreach ($data['carList']['cars'] as &$car) {
                $pictures = [];

                $paths = $this->catalogue()->getCataloguePaths($car['car']['id'], [
                    'breakOnFirst' => true
                ]);

                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $id = $picture['id'];

                        $url = null;
                        foreach ($paths as $path) {
                            $url = $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand-item-picture',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path'],
                                'picture_id'    => $picture['identity']
                            ]);
                        }

                        $pictures[] = [
                            'name' => isset($names[$id]) ? $names[$id] : null,
                            'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                            'url'  => $url
                        ];
                        $idx++;
                    } else {
                        $pictures[] = null;
                    }
                }

                $car['name'] = $this->itemModel->getNameData($car['car'], $language);
                $car['pictures'] = $pictures;
            }
            unset($car);

            $sideBarModel = new ViewModel($data);
            $sideBarModel->setTemplate('application/mosts/sidebar');
            $this->layout()->addChild($sideBarModel, 'sidebar');

            return $data;
        });
    }

}
