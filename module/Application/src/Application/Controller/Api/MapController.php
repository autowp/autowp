<?php

namespace Application\Controller\Api;

use geoPHP;
use LineString;
use Point;
use Polygon;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;

class MapController extends AbstractActionController
{
    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var TableGateway
     */
    private $itemTable;

    public function __construct(
        ItemNameFormatter $itemNameFormatter,
        Picture $picture,
        TableGateway $itemTable
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->picture = $picture;
        $this->itemTable = $itemTable;
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

        $language = $this->language();

        $imageStorage = $this->imageStorage();

        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(
            $pointsOnly
                    ? []
                    : ['id', 'name', 'begin_year', 'end_year', 'item_type_id']
        )
            ->join('item_point', 'item.id = item_point.item_id', ['point'])
            ->where(['ST_Contains(ST_GeomFromText(?), item_point.point)' => $polygon->out('wkt')])
            ->order('item.name');

        $factories = $this->itemTable->selectWith($select);

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
                    case Item::FACTORY:
                        $url = ['/factories', $item['id']];
                        break;

                    case Item::MUSEUM:
                        $url = ['/museums', $item['id']];
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

                $picture = $this->picture->getRow([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => $item['id']
                ]);

                if ($picture) {
                    $image = $imageStorage->getFormatedImage(
                        $picture['image_id'],
                        'format9'
                    );
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
