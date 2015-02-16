<?php

require_once APPLICATION_PATH . '/../vendor/phayes/geoPHP/geoPHP.inc';

class MapController extends Zend_Controller_Action
{
    protected $_googleClient;

    const GOOGLE_URL = 'http://maps.googleapis.com/maps/api/geocode/json';

    protected function _getGoogleClient()
    {
        if (!$this->_googleClient) {
            $this->_googleClient = new Zend_Http_Client();
        }
        return $this->_googleClient;
    }

    public function indexAction()
    {

    }

    public function dataAction()
    {
        $bounds = $this->_getParam('bounds');
        $bounds = explode(',', $bounds);

        if (count($bounds) < 4) {
            return $this->_forward('notfound', 'error');
        }

        $latLo = (float)$bounds[0];
        $lngLo = (float)$bounds[1];
        $latHi = (float)$bounds[2];
        $lngHi = (float)$bounds[3];

        $line = new LineString(array(
            new Point($lngLo, $latLo),
            new Point($lngLo, $latHi),
            new Point($lngHi, $latHi),
            new Point($lngHi, $latLo),
            new Point($lngLo, $latLo),
        ));
        $polygon = new Polygon(array($line));

        $coordsFilter = array(
            'ST_Contains(GeomFromText(?), point)' => $polygon->out('wkt'),
        );

        $pictureTable = new Picture();

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $factoryTable = new Factory();

        $factories = $factoryTable->fetchAll($coordsFilter, 'name');

        $data = array();
        foreach ($factories as $factory) {

            $point = null;
            if ($factory->point) {
                $point = geoPHP::load(substr($factory->point, 4), 'wkb');
            }

            $row = array(
                'id'   => 'factory' . $factory->id,
                'name' => $factory->name,
                'location' => array(
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ),
                'url'  => $this->_helper->url->url(array(
                    'controller' => 'factory',
                    'action'     => 'factory',
                    'id'         => $factory->id
                ))
            );



            $picture = $pictureTable->fetchRow(array(
                'type = ?'       => Picture::FACTORY_TYPE_ID,
                'factory_id = ?' => $factory->id
            ));

            if ($picture) {
                $image = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'format9');
                if ($image) {
                    $row['image'] = $image->getSrc();
                }
            }

            $data[] = $row;
        }

        $museumTable = new Museum();

        $museums = $museumTable->fetchAll($coordsFilter, 'name');

        foreach ($museums as $museum) {

            $point = null;
            if ($museum->point) {
                $point = geoPHP::load(substr($museum->point, 4), 'wkb');
            }

            $row = array(
                'id'   => 'museum' . $museum->id,
                'name' => $museum->name,
                'location' => array(
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ),
                'url'  => $this->_helper->url->url(array(
                    'controller' => 'museums',
                    'action'     => 'museum',
                    'id'         => $museum->id
                ))
            );

            /*if ($museum->url) {
                $row['url'] = $museum->url;
            }*/
            if ($museum->address) {
                $row['address'] = $museum->address;
            }
            if ($museum->description) {
                $row['desc'] = $museum->description;
            }

            if ($museum->img) {
                $image = $imageStorage->getFormatedImage($museum->img, 'format9');
                if ($image) {
                    $row['image'] = $image->getSrc();
                }
            }

            $data[] = $row;
        }

        $this->_helper->json($data);
    }
}