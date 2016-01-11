<?php

class UploadController extends Zend_Controller_Action
{
    /**
     * @var Car_Parent
     */
    private $_carParentTable;

    private function _getCarParentTable()
    {
        return $this->_carParentTable
            ? $this->_carParentTable
            : $this->_carParentTable = new Car_Parent();
    }

    public function onlyRegisteredAction()
    {

    }

    public function indexAction()
    {
        $user = $this->_helper->user()->get();

        if (!$user || $user->deleted) {
            return $this->_forward('only-registered');
        }

        $isSimple = (bool)$this->getParam('simple');

        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $replace = $this->getParam('replace');
        $replacePicture = false;
        if ($replace) {
            $replacePicture = $pictureTable->fetchRow(array(
                'identity = ?' => $replace
            ));
            if (!$replacePicture) {
                $replacePicture = $pictureTable->fetchRow(array(
                    'id = ?' => $replace
                ));
            }
        }

        if ($replacePicture) {

            $type =  $replacePicture->type;
            $brandId = $replacePicture->brand_id;
            $carId = $replacePicture->car_id;
            $engineId = $replacePicture->engine_id;

        } else {

            $type = (int)$this->getParam('type');
            $brandId = (int)$this->getParam('brand_id');
            $carId = (int)$this->getParam('car_id');
            $engineId = (int)$this->getParam('engine_id');

        }

        $selected = false;
        $selectedName = null;
        switch ($type) {
            case Picture::UNSORTED_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
                $brands = new Brands();
                $brand = $brands->find($brandId)->current();
                if ($brand) {
                    $selected = true;
                    switch ($type) {
                        case Picture::UNSORTED_TYPE_ID:
                            $selectedName = $brand->caption . ' / Несортировано';
                            break;
                        case Picture::MIXED_TYPE_ID:
                            $selectedName = $brand->caption . ' / Разное';
                            break;
                        case Picture::LOGO_TYPE_ID:
                            $selectedName = $brand->caption . ' / Логотипы';
                            break;
                    }
                }
                break;

            case Picture::CAR_TYPE_ID:
                $cars = new Cars();
                $car = $cars->find($carId)->current();
                if ($car) {
                    $selected = true;
                    $selectedName = $car->getFullName();
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engines = new Engines();
                $engine = $engines->find($engineId)->current();
                if ($engine) {
                    $selected = true;
                    $selectedName = $engine->getMetaCaption();
                }
                break;
        }

        $this->view->selected = $selected;
        $this->view->selectedName = $selectedName;
        $this->view->isSimple = $isSimple;

        if ($selected) {

            $form = new Application_Form_Upload(array(
                'multipleFiles' => !$replacePicture,
                'class'         => $isSimple ? 'disable-ajax' : ''
            ));

            $request = $this->getRequest();

            if ($request->isPost()) {
                if ($form->isValid($request->getPost())) {

                    $pictures = $this->_saveUpload($form, $type, $brandId, $engineId, $carId, $replacePicture);

                    if ($request->isXmlHttpRequest()) {
                        /*$urls = array();
                        foreach ($pictures as $picture) {
                            $identity = $picture->identity ? $picture->identity : $picture->id;

                            $urls[] = $this->view->serverUrl($this->_helper->url->url(array(
                                'module'     => 'default',
                                'controller' => 'picture',
                                'action'     => 'index',
                                'picture_id' => $identity
                            ), 'picture', true));
                        }*/

                        $imageStorage = $this->getInvokeArg('bootstrap')
                            ->getResource('imagestorage');

                        $result = array();
                        foreach ($pictures as $picture) {

                            $image = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-gallery-full');

                            if ($image) {

                                $picturesData = $this->_helper->pic->listData(array($picture));

                                $html = $this->view->partial('picture.phtml', array_replace(
                                    $picturesData['items'][0],
                                    array(
                                        'disableBehaviour' => false,
                                        'isModer'          => false
                                    )
                                ));

                                $result[] = array(
                                    'id'     => $picture->id,
                                    'html'   => $html,
                                    'width'  => $picture->width,
                                    'height' => $picture->height,
                                    'src'    => $image->getSrc()
                                );
                            }
                        }

                        $this->getResponse()->setHttpResponseCode(200);
                        return $this->_helper->json($result);
                    } else {
                        return $this->_forward('success');
                    }
                } else {
                    if ($request->isXmlHttpRequest()) {
                        $this->getResponse()->setHttpResponseCode(400);
                        return $this->_helper->json($form->getMessages());
                    }
                }
            }

            $this->view->form = $form;
        }
    }

    private function _saveUpload($form, $type, $brandId, $engineId, $carId, $replacePicture)
    {
        $user = $this->_helper->user()->get();

        $values = $form->getValues();

        switch ($type) {
            case Picture::UNSORTED_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
                $brands = new Brands();
                $brand = $brands->find($brandId)->current();
                if ($brand) {
                    $brandId = $brand->id;
                }
                break;

            case Picture::CAR_TYPE_ID:
                $cars = new Cars();
                $car = $cars->find($carId)->current();
                if ($car) {
                    $carId = $car->id;
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engines = new Engines();
                $engine = $engines->find($engineId)->current();
                if ($engine) {
                    $engineId = $engine->id;
                }
                break;

            default:
                throw new Exception("Unexpected type");
        }

        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $tempFilePaths = (array)$form->picture->getFileName();

        $result = array();
        foreach ($tempFilePaths as $tempFilePath) {

            list ($width, $height, $imageType) = getimagesize($tempFilePath);
            $width = (int)$width;
            $height = (int)$height;
            if ($width <= 0)
                throw new Exception("Width <= 0");

            if ($height <= 0)
                throw new Exception("Height <= 0");

            // подбираем имя для файла
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                case IMAGETYPE_PNG:
                    break;
                default:
                    throw new Exception("Unsupported image type");
            }
            $ext = image_type_to_extension($imageType, false);

            $imageId = $imageStorage->addImageFromFile($tempFilePath, 'picture', array(
                'extension' => $ext,
                'pattern'   => 'autowp_' . rand()
            ));

            $image = $imageStorage->getImage($imageId);
            $fileSize = $image->getFileSize();

            // добавляем запись о картинке в БД
            $picture = $pictureTable->createRow(array(
                'image_id'      => $imageId,
                'width'         => $width,
                'height'        => $height,
                'owner_id'      => $user ? $user->id : null,
                'add_date'      => new Zend_Db_Expr('NOW()'),
                //'note'          => $values['note'],
                'views'         => 0,
                'filesize'      => $fileSize,
                'crc'           => 0,
                'status'        => Picture::STATUS_INBOX,
                'type'          => $type,
                'removing_date' => null,
                'brand_id'      => $brandId ? $brandId : null,
                'engine_id'     => $engineId ? $engineId : null,
                'car_id'        => $carId ? $carId : null,
                'ip'            => inet_pton($this->getRequest()->getServer('REMOTE_ADDR')),
                'identity'      => $pictureTable->generateIdentity(),
                'replace_picture_id' => $replacePicture ? $replacePicture->id : null,
            ));
            $picture->save();


            // инкрементируем счётчик добавленных картинок
            if ($user) {
                $user->pictures_added = new Zend_Db_Expr('pictures_added+1');
                $user->save();
            }

            // переименовываем файл под автомобиль
            $imageStorage->changeImageName($picture->image_id, array(
                'pattern' => $picture->getFileNamePattern(),
            ));

            // пересчитываем цифры
            switch ($picture->type) {
                case Picture::UNSORTED_TYPE_ID:
                case Picture::LOGO_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                    $brand = $picture->findParentBrands();
                    if ($brand) {
                        $brand->updatePicturesCache();
                        $brand->refreshPicturesCount();
                    }
                    break;
                case Picture::CAR_TYPE_ID:
                    $car = $picture->findParentCars();
                    if ($car) {
                        $car->refreshPicturesCount();
                        foreach ($car->findBrandsViaBrands_Cars() as $brand) {
                            $brand->updatePicturesCache();
                            $brand->refreshPicturesCount();
                        }
                    }
                    break;
            }

            // добавляем комментарий
            if ($values['note']) {
                $commentTable = new Comments();
                $commentTable->add(array(
                    'typeId'             => Comment_Message::PICTURES_TYPE_ID,
                    'itemId'             => $picture->id,
                    'parentId'           => null,
                    'authorId'           => $user->id,
                    'message'            => $values['note'],
                    'ip'                 => $this->getRequest()->getServer('REMOTE_ADDR'),
                    'moderatorAttention' => Comment_Message::MODERATOR_ATTENTION_NONE
                ));
            }

            $formatRequest = $picture->getFormatRequest();
            $imageStorage->getFormatedImage($formatRequest, 'picture-thumb');
            $imageStorage->getFormatedImage($formatRequest, 'picture-medium');
            $imageStorage->getFormatedImage($formatRequest, 'picture-gallery-full');

            $telegram = $this->getInvokeArg('bootstrap')->getResource('telegram');
            $telegram->notifyInbox($picture->id);

            $result[] = $picture;
        }

        return $result;
    }

    public function selectBrandAction()
    {
        $brandTable = new Brands();
        $brand = $brandTable->find($this->getParam('brand_id'))->current();

        if ($brand) {
            return $this->_forward('select-in-brand');
        }

        $db = $brandTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from('brands', ['id', 'name' => 'IFNULL(brand_language.name, brands.caption)'])
                ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
                ->order(['brands.position', 'name'])
                ->bind(array(
                    'language' => $this->_helper->language()
                ))
        );

        $this->view->brands = $rows;
    }

    public function selectInBrandAction()
    {
        $brands = new Brands();
        $brand = $brands->find($this->getParam('brand_id'))->current();

        if (!$brand) {
            return $this->_forward('select-brand');
        }

        $this->view->brand = $brand;

        $carTable = new Cars();
        $carParentTable = $this->_getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();

        $haveConcepts = (bool)$carTable->fetchRow(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->where('cars.is_concept')
        );

        $rows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->where('NOT cars.is_concept')
                ->order(array('cars.caption', 'cars.begin_year', 'cars.end_year'))
        );
        $cars = $this->_prepareCars($rows);

        $engineTable = new Engines();
        $haveEngines = (bool)$engineTable->fetchRow(
            $engineTable->select(true)
                ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                ->join('brand_engine', 'engine_parent_cache.parent_id = brand_engine.engine_id', null)
                ->where('brand_engine.brand_id = ?', $brand->id)
        );

        $this->view->assign(array(
            'cars'         => $cars,
            'haveConcepts' => $haveConcepts,
            'conceptsUrl'  => $this->_helper->url->url(array(
                'action' => 'concepts',
            )),
            'haveEngines'  => $haveEngines,
            'enginesUrl'   => $this->_helper->url->url(array(
                'action' => 'engines',
            )),
        ));
    }


    public function successAction()
    {
        $this->view->moreUrl = $this->_helper->url->url(array(
            'action' => 'index'
        ));
    }

    private function _prepareCars(Cars_Rowset $rows)
    {
        $carParentTable = $this->_getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();

        $cars = array();
        foreach ($rows as $row) {
            $haveChilds = (bool)$carParentAdapter->fetchOne(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                    ->where('parent_id = ?', $row->id)
            );
            $cars[] = array(
                'name' => $row->getFullName(),
                'url'  => $this->_helper->url->url(array(
                    'action' => 'index',
                    'type'   => Picture::CAR_TYPE_ID,
                    'car_id' => $row['id']
                )),
                'haveChilds' => $haveChilds,
                'isGroup'    => $row->is_group,
                'type'       => null,
                'loadUrl'    => $this->_helper->url->url(array(
                    'action' => 'car-childs',
                    'car_id' => $row['id']
                )),
            );
        }

        return $cars;
    }

    private function _prepareCarParentRows($rows)
    {
        $carParentTable = $this->_getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();
        $carTable = new Cars();

        $items = array();
        foreach ($rows as $carParentRow) {
            $car = $carTable->find($carParentRow->car_id)->current();
            if ($car) {
                $haveChilds = (bool)$carParentAdapter->fetchOne(
                    $carParentAdapter->select()
                        ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                        ->where('parent_id = ?', $car->id)
                );
                $items[] = array(
                    'name' => $car->getFullName(),
                    'url'  => $this->_helper->url->url(array(
                        'action' => 'index',
                        'type'   => Picture::CAR_TYPE_ID,
                        'car_id' => $car['id']
                    )),
                    'haveChilds' => $haveChilds,
                    'isGroup'    => $car['is_group'],
                    'type'       => $carParentRow->type,
                    'loadUrl'    => $this->_helper->url->url(array(
                        'action' => 'car-childs',
                        'car_id' => $car['id']
                    )),
                );
            }
        }

        return $items;
    }

    public function carChildsAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->_forward('only-registered');
        }

        $carTable = new Cars();
        $carParentTable = $this->_getCarParentTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $rows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->join('cars', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $car->id)
                ->order(array('car_parent.type', 'cars.caption', 'cars.begin_year', 'cars.end_year'))
        );

        $this->view->assign(array(
            'cars' => $this->_prepareCarParentRows($rows)
        ));
    }


    public function enginesAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->_forward('only-registered');
        }

        $brandTable = new Brands();
        $brand = $brandTable->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $engineTable = new Engines();
        $rows = $engineTable->fetchAll(
            $engineTable->select(true)
                ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                ->join('brand_engine', 'engine_parent_cache.parent_id = brand_engine.engine_id', null)
                ->where('brand_engine.brand_id = ?', $brand->id)
                ->order('engines.caption')
        );
        $engines = array();
        foreach ($rows as $row) {
            $engines[] = array(
                'name' => $row->getMetaCaption(),
                'url'  => $this->_helper->url->url(array(
                    'action'    => 'index',
                    'type'      => Picture::ENGINE_TYPE_ID,
                    'engine_id' => $row->id
                ))
            );
        }

        $this->view->assign(array(
            'engines' => $engines,
        ));
    }


    public function conceptsAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->_forward('only-registered');
        }

        $brandTable = new Brands();
        $brand = $brandTable->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $carTable = new Cars();

        $rows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->where('cars.is_concept')
                ->order(array('cars.caption', 'cars.begin_year', 'cars.end_year'))
                ->group('cars.id')
        );
        $concepts = $this->_prepareCars($rows);

        $this->view->assign(array(
            'concepts' => $concepts,
        ));
    }


    public function cropSaveAction()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $picture = $pictureTable->find($this->getParam('id'))->current();
        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->_forward('forbidden', 'error');
        }

        if ($picture->owner_id != $user->id) {
            return $this->_forward('forbidden', 'error');
        }

        if ($picture->status != Picture::STATUS_INBOX) {
            return $this->_forward('forbidden', 'error');
        }

        $left = round($this->getParam('x'));
        $top = round($this->getParam('y'));
        $width = round($this->getParam('w'));
        $height = round($this->getParam('h'));

        $left = max(0, $left);
        $left = min($picture->width, $left);
        $width = max(400, $width);
        $width = min($picture->width, $width);

        $top = max(0, $top);
        $top = min($picture->height, $top);
        $height = max(300, $height);
        $height = min($picture->height, $height);

        if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
            $picture->setFromArray(array(
                'crop_left'   => $left,
                'crop_top'    => $top,
                'crop_width'  => $width,
                'crop_height' => $height
            ));
        } else {
            $picture->setFromArray(array(
                'crop_left'   => null,
                'crop_top'    => null,
                'crop_width'  => null,
                'crop_height' => null
            ));
        }
        $picture->save();

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $imageStorage->flush(array(
            'image' => $picture->image_id
        ));

        $this->_helper->log(sprintf(
            'Выделение области на картинке %s',
            $this->view->escape($picture->getCaption())
        ), array($picture));

        $image = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-thumb');

        $this->_helper->json(array(
            'ok'  => true,
            'src' => $image->getSrc()
        ));
    }
}