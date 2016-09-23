<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\DbTable\Museum;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use geoPHP;
use Point;

use Zend_Db_Expr;
use Zend_Http_Client;

class MuseumController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $form;

    const GOOGLE_URL = 'http://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    public function indexAction()
    {
        if (!$this->user()->isAllowed('museums', 'manage')) {
            return $this->forbiddenAction();
        }

        $table = new Museum();
        $select = $table->select(true)
            ->order('name');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(30)
            ->setCurrentPageNumber($this->params('page'));

        return [
            'paginator' => $paginator
        ];
    }

    public function itemAction()
    {
        if (!$this->user()->isAllowed('museums', 'manage')) {
            return $this->forbiddenAction();
        }

        $table = new Museum();
        $id = (int)$this->params('museum_id');
        if ($id) {
            $museum = $table->find($id)->current();
            if (!$museum) {
                return $this->notFoundAction();
            }
        } else {
            $museum = $table->createRow();
        }

        $this->form->setAttribute('action', $this->url()->fromRoute(null, [
            'action'    => 'item',
            'museum_id' => $museum->id
        ]));

        $point = null;
        if ($museum->point) {
            $point = geoPHP::load(substr($museum->point, 4), 'wkb');
        }

        $this->form->setData([
            'name'        => $museum['name'],
            'lat'         => $point ? $point->y() : null,
            'lng'         => $point ? $point->x() : null,
            'url'         => $museum['url'],
            'description' => $museum['description'],
            'address'     => $museum['address']
        ]);

        if ($this->getRequest()->isPost()) {
            $data = array_merge_recursive(
                $this->getRequest()->getPost()->toArray(),
                $this->getRequest()->getFiles()->toArray()
            );
            $this->form->setData($data);

            if ($this->form->isValid()) {
                $values = $this->form->getData();

                if (strlen($values['lat']) && strlen($values['lng'])) {
                    $point = new Point($values['lng'], $values['lat']);
                    $point = new Zend_Db_Expr($table->getAdapter()->quoteInto('GeomFromWKB(?)', $point->out('wkb')));
                } else {
                    $point = null;
                }

                $museum->setFromArray([
                    'name'        => $values['name'],
                    'url'         => $values['url'],
                    'address'     => $values['address'],
                    'point'       => $point,
                    'description' => $values['description']
                ]);
                $museum->save();

                if ($values['photo']) {
                    $imageStorage = $this->imageStorage();

                    $newImageId = null;
                    $tempFilepath = $data['photo']['tmp_name'];
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

                return $this->redirect()->toRoute('moder/museum/params', [
                    'action'    => 'item',
                    'museum_id' => $museum->id
                ]);
            }
        }

        return [
            'form'   => $this->form,
            'museum' => $museum
        ];
    }

    public function addressToLatLngAction()
    {
        if (!$this->user()->isAllowed('museums', 'manage')) {
            return $this->forbiddenAction();
        }

        $address = $this->params('address');

        $location = null;
        if ($address) {
            $client = new Zend_Http_Client();
            $client
                ->setUri(self::GOOGLE_URL)
                ->setMethod(Zend_Http_Client::GET)
                ->setParameterGet([
                    'address' => $address,
                    'sensor'  => 'false'
                ]);

            $response = $client->request();
            if ($response->isSuccessful()) {
                $result = \Zend\Json\Json::decode($response->getBody());

                if ($result['status'] == 'OK') {
                    if (isset($result['results'][0]["geometry"]['location'])) {
                        $location = $result['results'][0]["geometry"]['location'];
                    }
                }
            }
        }

        return new JsonModel($location);
    }
}