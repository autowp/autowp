<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\DbTable;

class StatController extends AbstractActionController
{
    public function globalSummaryAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $cars = new DbTable\Item();

        $db = $cars->getAdapter();

        $totalPictures = $db->fetchOne('
            select count(1) from pictures
        ');

        $totalBrands = $db->fetchOne('
            select count(1) from item where item_type_id = ?
        ', [DbTable\Item\Type::BRAND]);

        $totalCars = $db->fetchOne('
            select count(1) from item where item_type_id = ?
        ', [DbTable\Item\Type::VEHICLE]);

        $totalCarAttrs = $db->fetchOne('
            select count(1)
            from attrs_attributes
                join attrs_zone_attributes on attrs_attributes.id=attrs_zone_attributes.attribute_id
            where attrs_zone_attributes.zone_id = 1
        ');

        $carAttrsValues = $db->fetchOne('
            select count(1)
            from attrs_values
        ');

        $data = [
            [
                'name'    => 'moder/statistics/photos-with-copyrights',
                'total'    => $totalPictures,
                'value'    => $db->fetchOne('
                    select count(1) from pictures where copyrights_text_id
                ')
            ],
            [
                'name'     => 'moder/statistics/vehicles-with-4-or-more-photos',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1) from (
                        select item.id, count(pictures.id) as c
                        from item
                            inner join picture_item on item.id = picture_item.item_id
                            inner join pictures on picture_item.picture_id = pictures.id
                        group by item.id
                        having c >= 4
                    ) as T1
                ')
            ],
            [
                'name'     => 'moder/statistics/specifications-values',
                'total'    => $totalCars * $totalCarAttrs,
                'value'    => $carAttrsValues,
            ],
            [
                'name'     => 'moder/statistics/brand-logos',
                'total'    => $totalBrands,
                'value'    => $db->fetchOne('
                    select count(1)
                    from item
                    where logo_id is not null and item_type_id = ?
                ', [DbTable\Item\Type::BRAND])
            ],
            [
                'name'    => 'moder/statistics/from-years',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from item
                    where begin_year
                ')
            ],
            [
                'name'    => 'moder/statistics/from-and-to-years',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from item
                    where begin_year and end_year
                ')
            ],
            [
                'name'    => 'moder/statistics/from-and-to-years-and-months',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from item
                    where begin_year and end_year and begin_month and end_month
                ')
            ],
        ];

        return new JsonModel([
            'items' => $data,
        ]);
    }
}
