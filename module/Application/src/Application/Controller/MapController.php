<?php

namespace Application\Controller;

use geoPHP;
use LineString;
use Point;
use Polygon;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\ItemNameFormatter;
use Application\Model\DbTable;

class MapController extends AbstractActionController
{
    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    public function __construct(
        ItemNameFormatter $itemNameFormatter
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
    }

    public function indexAction()
    {
    }

    public function index2Action()
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

        $pointsOnly = (bool)$this->params()->fromQuery('points-only', 14);

        $pictureTable = new DbTable\Picture();

        $language = $this->language();

        $imageStorage = $this->imageStorage();

        $itemTable = new DbTable\Item();
        $db = $itemTable->getAdapter();

        $factories = $db->fetchAll(
            $db->select()
                ->from(
                    'item',
                    $pointsOnly
                        ? []
                        : ['id', 'name', 'begin_year', 'end_year', 'item_type_id']
                )
                ->join('item_point', 'item.id = item_point.item_id', 'point')
                ->where('ST_Contains(GeomFromText(?), item_point.point)', $polygon->out('wkt'))
                ->order('item.name')
        );

        $data = [];
        foreach ($factories as $item) {
            $point = null;
            if ($item['point']) {
                $point = geoPHP::load(substr($item['point'], 4), 'wkb');
            }

            $row = [
                'location' => [
                    'lat'  => $point ? $point->y() : null,
                    'lng'  => $point ? $point->x() : null,
                ],
            ];

            if (! $pointsOnly) {
                $url = null;
                switch ($item['item_type_id']) {
                    case DbTable\Item\Type::FACTORY:
                        $url = $this->url()->fromRoute('factories/factory', [
                            'id' => $item['id']
                        ]);
                        break;

                    case DbTable\Item\Type::MUSEUM:
                        $url = $this->url()->fromRoute('museums/museum', [
                            'id' => $item['id']
                        ]);
                        break;
                }


                $row = array_replace($row, [
                    'id'   => 'factory' . $item['id'],
                    'name' => $this->itemNameFormatter->format(
                        $item,
                        $language
                    ),
                    'url'  => $url,
                ]);

                $picture = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->where('picture_item.item_id = ?', $item['id'])
                        ->limit(1)
                );

                if ($picture) {
                    $image = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'format9');
                    if ($image) {
                        $row['image'] = $image->getSrc();
                    }
                }
            }

            $data[] = $row;
        }

        return new JsonModel($data);
    }
}
