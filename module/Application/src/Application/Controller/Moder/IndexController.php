<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();

        $menu = [];

        if ($this->user()->isAllowed('rights', 'edit')) {
            $menu[$this->url()->fromRoute('moder/rights')] = 'page/71/name';
        }

        $menu[$this->url()->fromRoute('moder/comments')] = 'page/110/name';

        $menu['/moder/users'] = 'page/203/name';

        $menu['/moder/pictures'] = 'page/73/name';

        $menu[$this->url()->fromRoute('moder/perspectives')] = 'page/202/name';

        $menu['/moder/index/stat'] = 'page/119/name';
        $menu[$this->url()->fromRoute('moder/hotlink')] = 'Hotlinks';
        
        $menu[$this->url()->fromRoute('moder/picture-vote-template')] = 'page/212/name';

        return [
            'menu' => $menu,
        ];
    }

    public function statAction()
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

        return [
            'data' => $data,
        ];
    }

    public function tooBigCarsAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = new DbTable\Item();

        $rows = $itemTable->getAdapter()->fetchAll("
            SELECT
                item.id, item.name, item.body,
                (
                    case item_parent.type
                        when 0 then 'Stock'
                        when 1 then 'Related'
                        when 2 then 'Sport'
                        else item_parent.type
                    end
                ) as t,
                count(1) as c
            from item_parent
            join item on item_parent.parent_id=item.id
            group by item.id, item_parent.type
            order by c desc
                limit 100
        ");

        return [
            'rows' => $rows
        ];
    }
}
