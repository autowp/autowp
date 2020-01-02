<?php

namespace Application\Controller\Api;

use Exception;
use ImagickException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User;
use Autowp\TextStorage;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\Mosts;

/**
 * Class MostsController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method string language()
 * @method Storage imageStorage()
 * @method Catalogue catalogue()
 */
class MostsController extends AbstractActionController
{
    /**
     * @var TextStorage\Service
     */
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

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(
        TextStorage\Service $textStorage,
        Item $itemModel,
        Perspective $perspective,
        Mosts $mosts,
        Picture $picture,
        RestHydrator $itemHydrator,
        PictureNameFormatter $pictureNameFormatter
    ) {
        $this->itemHydrator = $itemHydrator;
        $this->textStorage = $textStorage;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->mosts = $mosts;
        $this->picture = $picture;
        $this->pictureNameFormatter = $pictureNameFormatter;
    }

    /**
     * @return JsonModel
     * @throws Storage\Exception
     * @throws ImagickException
     * @throws Exception
     */
    public function getItemsAction()
    {
        $user = $this->user()->get();

        $language = $this->language();
        $yearsCatname = (string)$this->params()->fromQuery('years_catname');
        $carTypeCatname = (string)$this->params()->fromQuery('type_catname');
        $mostCatname = (string)$this->params()->fromQuery('rating_catname');
        $brandID = (int)$this->params()->fromQuery('brand_id');

        $list = $this->mosts->getItems([
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname,
            'brandId'  => $brandID
        ]);

        // images
        $formatRequests = [];
        $allPictures = [];
        $idx = 0;
        foreach ($list['cars'] as $car) {
            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $formatRequests[$idx++] = $picture['image_id'];
                    $allPictures[] = $picture;
                }
            }
        }

        $imageStorage = $this->imageStorage();
        $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb-medium');

        $names = $this->picture->getNameData($allPictures, [
            'language' => $language
        ]);

        $this->itemHydrator->setOptions([
            'language' => $language,
            'fields'   => ['name_html' => true, 'description' => true],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $unit = null;
        if (isset($list['unit'])) {
            $unit = [
                'abbr' => $list['unit']['abbr'],
                'name' => $list['unit']['name'],
            ];
        }

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
                        'name' => isset($names[$id])
                            ? $this->pictureNameFormatter->format($names[$id], $language)
                            : null,
                        'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                        'url'  => $url
                    ];
                    $idx++;
                } else {
                    $pictures[] = null;
                }
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
        $brandID = (int)$this->params()->fromQuery('brand_id');

        return new JsonModel([
            'years'         => $this->mosts->getYearsMenu(),
            'ratings'       => $this->mosts->getRatingsMenu(),
            'vehilce_types' => $this->mosts->getCarTypes($brandID)
        ]);
    }
}
