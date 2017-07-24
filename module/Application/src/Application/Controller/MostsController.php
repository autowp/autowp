<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\TextStorage;

use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\Perspective;
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
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        TextStorage\Service $textStorage,
        Item $itemModel,
        Perspective $perspective,
        Mosts $mosts,
        DbTable\Picture $pictureTable
    ) {

        $this->textStorage = $textStorage;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->mosts = $mosts;
        $this->pictureTable = $pictureTable;
    }

    public function indexAction()
    {
        $language = $this->language();
        $yearsCatname = $this->params('years_catname');
        $carTypeCatname = $this->params('shape_catname');
        $mostCatname = $this->params('most_catname');

        $data = $this->mosts->getData([
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

        $names = $this->pictureTable->getNameData($allPictures, [
            'language' => $language
        ]);

        $idx = 0;
        foreach ($data['carList']['cars'] as &$car) {
            $car['description'] = $this->itemModel->getTextOfItem($car['car']['id'], $this->language());

            $pictures = [];

            $paths = $this->catalogue()->getCataloguePaths($car['car']['id'], [
                'breakOnFirst' => true
            ]);

            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $id = $picture->id;

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
    }
}
