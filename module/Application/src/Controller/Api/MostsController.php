<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Catalogue;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\Mosts;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User;
use Exception;
use ImagickException;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

use function array_merge;

/**
 * @method User user($user = null)
 * @method string language()
 * @method Catalogue catalogue()
 */
class MostsController extends AbstractActionController
{
    private Mosts $mosts;

    private Picture $picture;

    private AbstractRestHydrator $itemHydrator;

    private PictureNameFormatter $pictureNameFormatter;

    private Storage $imageStorage;

    public function __construct(
        Mosts $mosts,
        Picture $picture,
        AbstractRestHydrator $itemHydrator,
        PictureNameFormatter $pictureNameFormatter,
        Storage $imageStorage
    ) {
        $this->itemHydrator         = $itemHydrator;
        $this->mosts                = $mosts;
        $this->picture              = $picture;
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->imageStorage         = $imageStorage;
    }

    /**
     * @throws Storage\Exception
     * @throws ImagickException
     * @throws Exception
     */
    public function getItemsAction(): JsonModel
    {
        $user = $this->user()->get();

        $language       = $this->language();
        $yearsCatname   = (string) $this->params()->fromQuery('years_catname');
        $carTypeCatname = (string) $this->params()->fromQuery('type_catname');
        $mostCatname    = (string) $this->params()->fromQuery('rating_catname');
        $brandId        = (int) $this->params()->fromQuery('brand_id');

        $list = $this->mosts->getItems([
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname,
            'brandId'  => $brandId,
        ]);

        // images
        $formatRequests = [];
        $allPictures    = [];
        $idx            = 0;
        foreach ($list['cars'] as $car) {
            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $formatRequests[$idx++] = $picture['image_id'];
                    $allPictures[]          = $picture;
                }
            }
        }

        $imagesInfo = $this->imageStorage->getFormatedImages($formatRequests, 'picture-thumb-medium');

        $names = $this->picture->getNameData($allPictures, [
            'language' => $language,
        ]);

        $this->itemHydrator->setOptions([
            'language' => $language,
            'fields'   => ['name_html' => true, 'description' => true],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $unit = null;
        if (isset($list['unit'])) {
            $unit = [
                'id'   => $list['unit']['id'],
                'abbr' => $list['unit']['abbr'],
                'name' => $list['unit']['name'],
            ];
        }

        $idx    = 0;
        $result = [];
        foreach ($list['cars'] as $car) {
            $pictures = [];

            $paths = $this->catalogue()->getCataloguePaths($car['car']['id'], [
                'breakOnFirst' => true,
            ]);

            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $id = $picture['id'];

                    $route = null;
                    foreach ($paths as $path) {
                        $route = array_merge(
                            ['/', $path['brand_catname'], $path['car_catname']],
                            $path['path'],
                            ['pictures', $picture['identity']]
                        );
                        break;
                    }

                    $pictures[] = [
                        'name'  => isset($names[$id])
                            ? $this->pictureNameFormatter->format($names[$id], $language)
                            : null,
                        'src'   => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                        'route' => $route,
                    ];
                    $idx++;
                } else {
                    $pictures[] = null;
                }
            }

            $result[] = [
                'item'       => $this->itemHydrator->extract($car['car']),
                'pictures'   => $pictures,
                'value_text' => $car['valueText'] ?? null,
                'value_html' => $car['valueHtml'] ?? null,
                'unit'       => $unit,
            ];
        }

        return new JsonModel([
            'items' => $result,
        ]);
    }

    public function getMenuAction(): JsonModel
    {
        $brandId = (int) $this->params()->fromQuery('brand_id');

        return new JsonModel([
            'years'         => $this->mosts->getYearsMenu(),
            'ratings'       => $this->mosts->getRatingsMenu(),
            'vehilce_types' => $this->mosts->getCarTypes($brandId),
        ]);
    }
}
