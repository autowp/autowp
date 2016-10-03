<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Museum;
use Application\Model\DbTable\Picture;

use geoPHP;
use LineString;
use Point;
use Polygon;

class MapController extends AbstractActionController
{
    private $googleClient;

    const GOOGLE_URL = 'http://maps.googleapis.com/maps/api/geocode/json';

    private function _getGoogleClient()
    {
        if (!$this->googleClient) {
            $this->googleClient = new Zend_Http_Client();
        }
        return $this->googleClient;
    }

    public function indexAction()
    {

    }

    public function dataAction()
    {
        geoPHP::version(); // for autoload classes

        $bounds = $this->params()->fromQuery('bounds');
        $bounds = explode(',', $bounds);

        if (count($bounds) < 4) {
            return $this->_forward('notfound', 'error');
        }

        $latLo = (float)$bounds[0];
        $lngLo = (float)$bounds[1];
        $latHi = (float)$bounds[2];
        $lngHi = (float)$bounds[3];

        $line = new LineString([
            new Point($lngLo, $latLo),
            new Point($lngLo, $latHi),
            new Point($lngHi, $latHi),
            new Point($lngHi, $latLo),
            new Point($lngLo, $latLo),
        ]);
        $polygon = new Polygon([$line]);

        $coordsFilter = [
            'ST_Contains(GeomFromText(?), point)' => $polygon->out('wkt'),
        ];

        $pictureTable = new Picture();

        $imageStorage = $this->imageStorage();

        $factoryTable = new Factory();

        $factories = $factoryTable->fetchAll($coordsFilter, 'name');

        $data = [];
        foreach ($factories as $factory) {

            $point = null;
            if ($factory->point) {
                $point = geoPHP::load(substr($factory->point, 4), 'wkb');
            }

            $row = [
                'id'   => 'factory' . $factory->id,
                'name' => $factory->name,
                'location' => [
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ],
                'url'  => $this->url()->fromRoute('factories/factory', [
                    'id' => $factory->id
                ])
            ];

            $picture = $pictureTable->fetchRow([
                'type = ?'       => Picture::FACTORY_TYPE_ID,
                'factory_id = ?' => $factory->id
            ]);

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

            $row = [
                'id'   => 'museum' . $museum->id,
                'name' => $museum->name,
                'location' => [
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ],
                'url'  => $this->url()->fromRoute('museums/museum', [
                    'id' => $museum->id
                ])
            ];

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

        return new JsonModel($data);
    }
}