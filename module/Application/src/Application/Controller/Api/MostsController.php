<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;

use Autowp\TextStorage;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Service\Mosts;

class MostsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Mosts
     */
    private $mosts;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var RestHydrator
     */
    private $itemHydrator;

    public function __construct(
        TextStorage\Service $textStorage,
        Item $itemModel,
        Perspective $perspective,
        Mosts $mosts,
        Picture $picture,
        RestHydrator $itemHydrator
    ) {
        $this->itemHydrator = $itemHydrator;
        $this->textStorage = $textStorage;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->mosts = $mosts;
        $this->picture = $picture;
    }

    public function getItemsAction()
    {
        $user = $this->user()->get();

        $language = $this->language();
        $yearsCatname = $this->params()->fromQuery('years_catname');
        $carTypeCatname = $this->params()->fromQuery('shape_catname');
        $mostCatname = $this->params()->fromQuery('most_catname');

        $list = $this->mosts->getItems([
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname
        ]);

        // images
        $formatRequests = [];
        $allPictures = [];
        $idx = 0;
        foreach ($list['cars'] as $car) {
            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $formatRequests[$idx++] = $this->picture->getFormatRequest($picture);
                    $allPictures[] = $picture;
                }
            }
        }

        $imageStorage = $this->imageStorage();
        $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

        $names = $this->picture->getNameData($allPictures, [
            'language' => $language
        ]);

        $this->itemHydrator->setOptions([
            'language' => $language,
            'fields'   => ['name_html' => true, 'description' => true],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $idx = 0;
        $result = [];
        foreach ($list['cars'] as $car) {
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

            $unit = null;
            if (isset($car['unit'])) {
                $unit = [
                    'abbr' => $car['unit']['abbr'],
                    'name' => $car['unit']['name'],
                ];
            }

            $result[] = [
                'item'       => $this->itemHydrator->extract($car['car']),
                'pictures'   => $pictures,
                'value_text' => isset($car['valueText']) ? $car['valueText'] : null,
                'value_html' => isset($car['valueHtml']) ? $car['valueHtml'] : null,
                'unit'       => $unit
            ];
        }


        return new JsonModel([
            'items' => $result
        ]);
    }

    public function getMenuAction()
    {
        /*$data = $this->mosts->getData([
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname
        ]);*/

        return new JsonModel([
            'years'         => $this->mosts->getYearsMenu(),
            'ratings'       => $this->mosts->getRatingsMenu(),
            'vehilce_types' => $this->mosts->getCarTypes(0)
        ]);
    }
}
