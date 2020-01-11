<?php

namespace Application\Controller\Plugin;

use ArrayObject;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\PictureNameFormatter;

class Pic extends AbstractPlugin
{
    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Catalogue
     */
    private $catalogue;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        PictureItem $pictureItem,
        Catalogue $catalogue,
        Item $itemModel,
        Picture $picture
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->pictureItem = $pictureItem;
        $this->catalogue = $catalogue;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
    }

    public function route($row, $catalogueOnly = false): ?array
    {
        $route = null;
        if (! $catalogueOnly) {
            ['/picture', $row['identity']];
        }

        $itemIds = $this->pictureItem->getPictureItems($row['id'], PictureItem::PICTURE_CONTENT);
        if ($itemIds) {
            $itemIds = $this->itemModel->getIds([
                'id'           => $itemIds,
                'item_type_id' => [Item::BRAND, Item::VEHICLE, Item::ENGINE]
            ]);

            if ($itemIds) {
                $carId = $itemIds[0];

                $paths = $this->catalogue->getCataloguePaths($carId, [
                    'breakOnFirst' => true,
                    'stockFirst'   => true
                ]);

                if (count($paths) > 0) {
                    $path = $paths[0];

                    if ($path['car_catname']) {
                        $route = array_merge(
                            ['/', $path['brand_catname'], $path['car_catname']],
                            $path['path'],
                            ['pictures', $row['identity']]
                        );
                    } else {
                        $perspectiveId = $this->pictureItem->getPerspective($row['id'], $carId);

                        switch ($perspectiveId) {
                            case 22:
                                $action = 'logotypes';
                                break;
                            case 25:
                                $action = 'mixed';
                                break;
                            default:
                                $action = 'other';
                                break;
                        }

                        $route = ['/', $path['brand_catname'], $action, $row['identity']];
                    }
                }
            }
        }

        return $route;
    }

    public function name($pictureRow, $language)
    {
        if ($pictureRow instanceof ArrayObject) {
            $pictureRow = (array)$pictureRow;
        }

        $names = $this->picture->getNameData([$pictureRow], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
