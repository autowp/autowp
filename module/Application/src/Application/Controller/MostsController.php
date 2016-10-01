<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Service\Mosts;
use Application\Service\SpecificationsService;

use Autowp\TextStorage;

use Car_Parent;
use Picture;

class MostsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    public function __construct(
        TextStorage\Service $textStorage,
        SpecificationsService $specsService)
    {
        $this->textStorage = $textStorage;
        $this->specsService = $specsService;
    }

    public function indexAction()
    {
        $service = new Mosts([
            'specs' => $this->specsService
        ]);

        $language = $this->language();
        $yearsCatname = $this->params('years_catname');
        $carTypeCatname = $this->params('shape_catname');
        $mostCatname = $this->params('most_catname');

        $data = $service->getData([
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname
        ]);

        foreach ($data['sidebar']['mosts'] as &$most) {
            $most['url'] = $this->url()->fromRoute('mosts', $most['params']);
        }
        foreach ($data['sidebar']['carTypes'] as &$carType) {
            $carType['url'] = $this->url()->fromRoute('mosts', $carType['params']);
            foreach ($carType['childs'] as &$child) {
                $child['url'] = $this->url()->fromRoute('mosts', $child['params']);
            }
        }
        foreach ($data['years'] as &$year) {
            $year['url'] = $this->url()->fromRoute('mosts', $year['params']);
        }


        // images
        $formatRequests = [];
        $allPictures = [];
        $idx = 0;
        foreach ($data['carList']['cars'] as $car) {
            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $formatRequests[$idx++] = $picture->getFormatRequest();
                    $allPictures[] = $picture->toArray();
                }
            }
        }

        $imageStorage = $this->imageStorage();
        $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

        $pictureTable = new Picture();
        $names = $pictureTable->getNameData($allPictures, [
            'language' => $language
        ]);

        $carParentTable = new Car_Parent();

        $idx = 0;
        foreach ($data['carList']['cars'] as &$car) {

            $description = null;
            if ($car['car']['text_id']) {
                $description = $this->textStorage->getText($car['car']['text_id']);
            }
            $car['description'] = $description;

            $pictures = [];

            $paths = $carParentTable->getPaths($car['car']['id'], [
                'breakOnFirst' => true
            ]);

            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $id = $picture->id;

                    $url = null;
                    foreach ($paths as $path) {
                        $url = $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
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

            $car['name'] = $car['car']->getNameData($language);
            $car['pictures'] = $pictures;
        }
        unset($car);

        $sideBarModel = new ViewModel($data);
        $sideBarModel->setTemplate('application/mosts/sidebar');
        $this->layout()->addChild($sideBarModel, 'sidebar');

        return $data;
    }
}