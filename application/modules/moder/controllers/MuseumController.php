<?php

class Moder_MuseumController extends Zend_Controller_Action
{
    private $_table;

    protected $_googleClient;
    const GOOGLE_URL = 'http://maps.googleapis.com/maps/api/geocode/json';

    public function init()
    {
        parent::init();

        $this->_table = new Museum();
    }

    /**
     * @param Models_Row $car
     * @return string
     */
    private function _museumEditUrl($museum)
    {
        return $this->_helper->url('edit', 'museum', 'moder', array(
            'museum_id' => $museum->id
        ));
    }

    public function indexAction()
    {
        if (!$this->_helper->user()->isAllowed('museums', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $select = $this->_table->select()
            ->order('name');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(30)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->assign(array(
            'paginator' => $paginator
        ));
    }

    public function editAction()
    {
        if (!$this->_helper->user()->isAllowed('museums', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $museum = $this->_table->find($this->_getParam('museum_id'))->current();
        if (!$museum) {
            return $this->_forward('notfound', 'error');
        }

        $form = new Application_Form_Museum(array(
            'action' => $this->_helper->url->url()
        ));

        $point = null;
        if ($museum->point) {
            $point = geoPHP::load(substr($museum->point, 4), 'wkb');
        }

        $form->populate(array(
            'name'        => $museum['name'],
            'lat'         => $point ? $point->y() : null,
            'lng'         => $point ? $point->x() : null,
            'url'         => $museum['url'],
            'description' => $museum['description'],
            'address'     => $museum['address']
        ));

        $request = $this->getRequest();
        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {
                $values = $form->getValues();

                if (strlen($values['lat']) && strlen($values['lng'])) {
                    $point = new Point($values['lng'], $values['lat']);
                    $point = new Zend_Db_Expr($this->_table->getAdapter()->quoteInto('GeomFromWKB(?)', $point->out('wkb')));
                } else {
                    $point = null;
                }

                $museum->setFromArray(array(
                    'name'        => $values['name'],
                    'url'         => $values['url'],
                    'address'     => $values['address'],
                    'point'       => $point,
                    'description' => $values['description']
                ));
                $museum->save();

                if ($values['photo']) {
                    $imageStorage = $this->getInvokeArg('bootstrap')
                        ->getResource('imagestorage');

                    $newImageId = null;
                    $tempFilepath = $form->photo->getFileName();
                    if ($tempFilepath && file_exists($tempFilepath)) {
                        $newImageId = $imageStorage->addImageFromFile($tempFilepath, 'museum');
                    }

                    if ($newImageId) {
                        $oldImageId = $museum->img;

                        $museum->img = $newImageId;
                        $museum->save();

                        if ($oldImageId) {
                            $imageStorage->removeImage($oldImageId);
                        }
                    }
                }

                return $this->_redirect($this->_museumEditUrl($museum));
            }
        }

        $this->view->assign(array(
            'form'   => $form,
            'museum' => $museum
        ));
    }

    public function newAction()
    {
        if (!$this->_helper->user()->isAllowed('museums', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $form = new Application_Form_Museum(array(
            'action' => $this->_helper->url->url()
        ));

        $request = $this->getRequest();
        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {
                $values = $form->getValues();

                if (strlen($values['lat']) && strlen($values['lng'])) {
                    $point = new Point($values['lng'], $values['lat']);
                    $point = new Zend_Db_Expr($this->_table->getAdapter()->quoteInto('GeomFromWKB(?)', $point->out('wkb')));
                } else {
                    $point = null;
                }

                $museum = $this->_table->fetchNew();
                $museum->setFromArray(array(
                    'name'        => $values['name'],
                    'url'         => $values['url'],
                    'address'     => $values['address'],
                    'point'       => $point,
                    'description' => $values['description']
                ));
                $museum->save();

                return $this->_redirect($this->_museumEditUrl($museum));
            }
        }

        $this->view->assign(array(
            'form' => $form
        ));
    }

    protected function _getGoogleClient()
    {
        if (!$this->_googleClient) {
            $this->_googleClient = new Zend_Http_Client();
        }
        return $this->_googleClient;
    }

    public function addressToLatLngAction()
    {
        if (!$this->_helper->user()->isAllowed('museums', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $address = $this->_getParam('address');

        $location = null;
        if ($address) {
            $client = $this->_getGoogleClient()
                ->setUri(self::GOOGLE_URL)
                ->setMethod(Zend_Http_Client::GET)
                ->setParameterGet(array(
                    'address' => $address,
                    'sensor'  => 'false'
                ));

            $response = $client->request();
            if ($response->isSuccessful()) {
                $result = Zend_Json::decode($response->getBody());

                if ($result['status'] == 'OK') {
                    if (isset($result['results'][0]["geometry"]['location'])) {
                        $location = $result['results'][0]["geometry"]['location'];
                    }
                }
            }
        }

        return $this->_helper->json($location);
    }
}