<?php

use Application\Service\Mosts;

class MostController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $specService = new Application_Service_Specifications();
        $service = new Mosts(array(
            'specs' => $specService
        ));

        $language = $this->_helper->language();
        $yearsCatname = $this->_getParam('years_catname');
        $carTypeCatname = $this->_getParam('shape_catname');
        $mostCatname = $this->_getParam('most_catname');

        $data = $service->getData(array(
            'language' => $language,
            'most'     => $mostCatname,
            'years'    => $yearsCatname,
            'carType'  => $carTypeCatname
        ));

        foreach ($data['sidebar']['mosts'] as &$most) {
            $most['url'] = $this->_helper->url->url($most['params'], 'most');
        }
        foreach ($data['sidebar']['carTypes'] as &$carType) {
            $carType['url'] = $this->_helper->url->url($carType['params'], 'most');
            foreach ($carType['childs'] as &$child) {
                $child['url'] = $this->_helper->url->url($child['params'], 'most');
            }
        }
        foreach ($data['years'] as &$year) {
            $year['url'] = $this->_helper->url->url($year['params'], 'most');
        }


        // images
        $formatRequests = array();
        $allPictures = array();
        $idx = 0;
        foreach ($data['carList']['cars'] as $car) {
            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $formatRequests[$idx++] = $picture->getFormatRequest();
                    $allPictures[] = $picture->toArray();
                }
            }
        }

        $imageStorage = $this->_helper->imageStorage();
        $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

        $pictureTable = new Picture();
        $names = $pictureTable->getNameData($allPictures, array(
            'language' => $language
        ));

        $carParentTable = new Car_Parent();
        $textStorage = $this->_helper->textStorage();

        $idx = 0;
        foreach ($data['carList']['cars'] as &$car) {
            
            $description = null;
            if ($car['car']['text_id']) {
                $description = $textStorage->getText($car['car']['text_id']);
            }
            $car['description'] = $description;
            
            $pictures = [];

            $paths = $carParentTable->getPaths($car['car']['id'], array(
                'breakOnFirst' => true
            ));

            foreach ($car['pictures'] as $picture) {
                if ($picture) {
                    $id = $picture->id;

                    $url = null;
                    foreach ($paths as $path) {
                        $url = $this->_helper->url->url(array(
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }

                    $pictures[] = array(
                        'name' => isset($names[$id]) ? $names[$id] : null,
                        'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                        'url'  => $url
                    );
                    $idx++;
                } else {
                    $pictures[] = null;
                }
            }

            $car['name'] = $car['car']->getNameData($language);
            $car['pictures'] = $pictures;
        }
        unset($car);

        $this->view->assign(
            $data
        );

        $this->getResponse()->insert('sidebar', $this->view->render('most/sidebar.phtml'));
    }
}