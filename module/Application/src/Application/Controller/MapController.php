<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\DbTable;
use Application\Model\DbTable\Museum;
use Application\Model\DbTable\Picture;

use geoPHP;
use LineString;
use Point;
use Polygon;

class MapController extends AbstractActionController
{
    public function indexAction()
    {
    }

    public function dataAction()
    {
        geoPHP::version(); // for autoload classes

        $bounds = $this->params()->fromQuery('bounds');
        $bounds = explode(',', (string)$bounds);

        if (count($bounds) < 4) {
            return $this->notfoundAction();
        }

        $lngLo = (float)$bounds[0];
        $latLo = (float)$bounds[1];
        $lngHi = (float)$bounds[2];
        $latHi = (float)$bounds[3];

        $line = new LineString([
            new Point($lngLo, $latLo),
            new Point($lngLo, $latHi),
            new Point($lngHi, $latHi),
            new Point($lngHi, $latLo),
            new Point($lngLo, $latLo),
        ]);
        $polygon = new Polygon([$line]);

        $coordsFilter = [
            
        ];
        $pictureTable = new Picture();

        $imageStorage = $this->imageStorage();

        $itemTable = new DbTable\Item();
        $db = $itemTable->getAdapter();

        $factories = $db->fetchAll(
            $db->select()
                ->from('item', ['id', 'name'])
                ->join('item_point', 'item.id = item_point.item_id', 'point')
                ->where('ST_Contains(GeomFromText(?), item_point.point)', $polygon->out('wkt'))
                ->order('item.name')
        );

        $data = [];
        foreach ($factories as $factory) {
            $point = null;
            if ($factory['point']) {
                $point = geoPHP::load(substr($factory['point'], 4), 'wkb');
            }

            $row = [
                'id'   => 'factory' . $factory['id'],
                'name' => $factory['name'],
                'location' => [
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ],
                'url'  => $this->url()->fromRoute('factories/factory', [
                    'id' => $factory['id']
                ])
            ];

            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.item_id = ?', $factory['id'])
                    ->limit(1)
            );

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
