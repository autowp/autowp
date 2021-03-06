<?php

namespace Application\Controller\Api;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Image\Storage;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_replace;
use function Autowp\Commons\parsePointWkb;
use function count;
use function explode;
use function sprintf;

/**
 * @method string language()
 */
class MapController extends AbstractActionController
{
    private ItemNameFormatter $itemNameFormatter;

    private Picture $picture;

    private TableGateway $itemTable;

    private Storage $imageStorage;

    public function __construct(
        ItemNameFormatter $itemNameFormatter,
        Picture $picture,
        TableGateway $itemTable,
        Storage $imageStorage
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->picture           = $picture;
        $this->itemTable         = $itemTable;
        $this->imageStorage      = $imageStorage;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function dataAction()
    {
        $bounds = $this->params()->fromQuery('bounds');
        $bounds = explode(',', (string) $bounds);

        if (count($bounds) < 4) {
            return $this->notfoundAction();
        }

        $lngLo = (float) $bounds[0];
        $latLo = (float) $bounds[1];
        $lngHi = (float) $bounds[2];
        $latHi = (float) $bounds[3];

        $pointsOnly = (bool) $this->params()->fromQuery('points-only', 14);

        $language = $this->language();

        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(
            $pointsOnly
                    ? []
                    : ['id', 'name', 'begin_year', 'end_year', 'item_type_id']
        )
            ->join('item_point', 'item.id = item_point.item_id', ['point'])
            ->where([
                'ST_Contains(ST_GeomFromText(?), item_point.point)' => sprintf(
                    'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
                    $lngLo,
                    $latLo,
                    $lngLo,
                    $latHi,
                    $lngHi,
                    $latHi,
                    $lngHi,
                    $latLo,
                    $lngLo,
                    $latLo,
                ),
            ])
            ->order('item.name');

        $factories = $this->itemTable->selectWith($select);

        $data = [];
        foreach ($factories as $item) {
            $point = null;
            if ($item['point']) {
                $point = parsePointWkb($item['point']);
            }

            $row = [
                'location' => [
                    'lat' => $point ? $point->getLat() : null,
                    'lng' => $point ? $point->getLng() : null,
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
                    'item'   => $item['id'],
                ]);

                if ($picture) {
                    $image = $this->imageStorage->getFormatedImage(
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
