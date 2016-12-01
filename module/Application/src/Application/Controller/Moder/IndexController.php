<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle;

class IndexController extends AbstractActionController
{
    private $addBrandForm;

    public function __construct($addBrandForm)
    {
        $this->addBrandForm = $addBrandForm;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();

        if ($this->user()->isAllowed('brand', 'add')) {
            $this->addBrandForm->setAttribute(
                'action',
                $this->url()->fromRoute('moder/index/params', ['form' => 'add-brand'])
            );

            if ($request->isPost() && $this->params('form') == 'add-brand') {
                $this->addBrandForm->setData($request->getPost());
                if ($this->addBrandForm->isValid()) {
                    $values = $this->addBrandForm->getData();

                    $brands = new BrandTable();

                    $brand = $brands->createRow([
                        'name'    => $values['name'],
                        'type_id' => 1 // TODO: remove parameter
                    ]);
                    $brand->save();

                    $url = $this->url()->fromRoute('moder/brands/params', [
                        'action'   => 'brand',
                        'brand_id' => $brand->id
                    ]);

                    $this->log(sprintf(
                        'Создан новый бренд %s',
                        $brand->name
                    ), $brand);

                    return $this->redirect()->toUrl($url);
                }
            }
        }


        $menu = [];

        if ($this->user()->isAllowed('rights', 'edit')) {
            $menu[$this->url()->fromRoute('moder/rights')] = 'page/71/name';
        }

        $menu[$this->url()->fromRoute('moder/comments')] = 'page/110/name';

        $menu['/moder/users'] = 'page/203/name';
        if ($this->user()->isAllowed('museums', 'manage')) {
            $menu[$this->url()->fromRoute('moder/museum')] = 'page/115/name';
        }

        $menu['/moder/pictures'] = 'page/73/name';
        
        $menu[$this->url()->fromRoute('moder/category')] = 'page/125/name';

        $menu[$this->url()->fromRoute('moder/perspectives')] = 'page/202/name';

        $menu['/moder/index/stat'] = 'page/119/name';
        $menu[$this->url()->fromRoute('moder/hotlink')] = 'Hotlinks';

        return [
            'menu'         => $menu,
            'addBrandForm' => $this->addBrandForm,
        ];
    }

    public function statAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $cars = new Vehicle();

        $db = $cars->getAdapter();

        $totalPictures = $db->fetchOne('
            select count(1) from pictures
        ');

        $totalBrands = $db->fetchOne('
            select count(1) from brands
        ');

        $totalCars = $db->fetchOne('
            select count(1) from cars
        ');

        $totalCarAttrs = $db->fetchOne('
            select count(1)
            from attrs_attributes
                join attrs_zone_attributes on attrs_attributes.id=attrs_zone_attributes.attribute_id
            where attrs_zone_attributes.zone_id = 1
        ');

        $carAttrsValues = $db->fetchOne('
            select count(1)
            from attrs_values
            where item_type_id = 1
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
                        select cars.id, count(pictures.id) as c
                        from cars
                            inner join picture_item on cars.id = picture_item.item_id
                            inner join pictures on picture_item.picture_id = pictures.id
                        group by cars.id
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
                    from brands
                    where img is not null
                ')
            ],
            [
                'name'    => 'moder/statistics/from-years',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
                    where begin_year
                ')
            ],
            [
                'name'    => 'moder/statistics/from-and-to-years',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
                    where begin_year and end_year
                ')
            ],
            [
                'name'    => 'moder/statistics/from-and-to-years-and-months',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
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

        $carTable = new Vehicle();

        $rows = $carTable->getAdapter()->fetchAll("
            SELECT
                cars.id, cars.name, cars.body,
                (
                    case car_parent.type
                        when 0 then 'Stock'
                        when 1 then 'Related'
                        when 2 then 'Sport'
                        else car_parent.type
                    end
                ) as t,
                count(1) as c
            from car_parent
            join cars on car_parent.parent_id=cars.id
            group by cars.id, car_parent.type
            order by c desc
                limit 100
        ");

        return [
            'rows' => $rows
        ];
    }
}
