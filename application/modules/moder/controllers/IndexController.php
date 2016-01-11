<?php
class Moder_IndexController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $request = $this->getRequest();

        $addBrandForm = false;

        if ($this->_helper->user()->isAllowed('brand', 'add')) {
            $addBrandForm = new Application_Form_Moder_Brand_Add(array(
                'action' => $this->_helper->url->url(array('form' => 'add-brand')),
            ));

            if ($request->isPost() && $this->_getParam('form') == 'add-brand' && $addBrandForm->isValid($request->getPost())) {
                $values = $addBrandForm->getValues();

                $brands = new Brands();

                $id = $brands->insert($values);
                $brand = $brands->find($id)->current();

                $url = $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'brands',
                    'action'     => 'brand',
                    'brand_id'   => $brand->id
                ), 'default', true);

                $this->_helper->log(sprintf(
                    'Создан новый бренд %s', $this->view->htmlA($url, $brand->caption)
                ), $brand);

                return $this->_redirect($url);
            }
        }


        $menu = array();

        if ($this->_helper->user()->isAllowed('rights', 'edit')) {
            $menu[$this->_helper->url('index', 'rights')] = 'Права';
        }

        $menu[$this->_helper->url->url(array(
            'module' => 'moder',
            'controller' => 'comments',
            'action' => 'index'
        ), 'default', true)] = 'Комментарии';

        $menu['/moder/users/'] = 'Пользователи';
        if ($this->_helper->user()->isAllowed('museums', 'manage')) {
            $menu['/moder/museum/'] = 'Музеи';
        }

        $menu['/moder/pictures/'] = 'Картинки';

        /*if ($this->_helper->user()->inheritsRole('cars-moder')) {
            $menu[$this->_helper->url('alpha', 'cars')] = 'Алфавитный список автомобилей';
            //$menu[$this->_helper->url('by_types', 'cars')] = 'Список автомобилей по типам кузова';
        }*/

        $menu['/moder/perspectives/'] = 'Справка по ракурсам';

        $menu['/moder/index/stat'] = 'Статистика';
        $menu['/moder/hotlink'] = 'Hotlinks';

        $this->view->assign(array(
            'menu'          => $menu,
            'addBrandForm'  => $addBrandForm,
        ));
    }

    public function moderMenuAction()
    {
        $items = array();

        if ($this->_helper->user()->inheritsRole('moder')) {

            $urlHelper = $this->_helper->url;

            $pTable = $this->_helper->catalogue()->getPictureTable();
            $inboxCount = $pTable->getAdapter()->fetchOne(
                $pTable->getAdapter()->select()
                    ->from($pTable->info('name'), 'count(1)')
                    ->where('status = ?', Picture::STATUS_INBOX)
            );

            $items[] = array(
                'href'  => '/moder/pictures/index/order/1/status/inbox',
                'label' => 'Инбокс',
                'count' => $inboxCount,
                'icon'  => 'glyphicon glyphicon-th'
            );

            $cmTable = new Comment_Message();
            $attentionCount = $cmTable->getAdapter()->fetchOne(
                $cmTable->getAdapter()->select()
                    ->from($cmTable->info('name'), 'count(1)')
                    ->where('moderator_attention = ?', Comment_Message::MODERATOR_ATTENTION_REQUIRED)
            );

            $items[] = array(
                'href'  => $urlHelper->url(array(
                    'module'              => 'moder',
                    'controller'          => 'comments',
                    'action'              => 'index',
                    'moderator_attention' => Comment_Message::MODERATOR_ATTENTION_REQUIRED
                ), 'default', true),
                'label' => 'Комментарии',
                'count' => $attentionCount,
                'icon'  => 'glyphicon glyphicon-comment'
            );

            if ($this->_helper->user()->inheritsRole('pages-moder')) {

                $items[] = array(
                    'href'  => $urlHelper->url(array(
                        'module'     => 'moder',
                        'controller' => 'pages',
                        'action'     => 'index'
                    ), 'default', true),
                    'label' => 'Страницы сайта',
                    'icon'  => 'glyphicon glyphicon-book'
                );
            }

            $items[] = array(
                'href'  => $urlHelper->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'index'
                ), 'default', true),
                'label' => 'Автомобили',
                'icon'  => 'fa fa-car'
            );
        }

        $this->view->items = $items;

        $this->_helper->viewRenderer->setResponseSegment('moderatorMenu');
    }

    public function statAction()
    {
        $pictures = $this->_helper->catalogue()->getPictureTable();
        $cars = new Cars();

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

        $totalAttrs = $db->fetchOne('
            select count(1) from attrs_attributes
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

        $data = array(
            array(
                'name'    => 'Фотографий с копирайтами',
                'total'    => $totalPictures,
                'value'    => $db->fetchOne('
                    select count(1) from pictures where length(copyrights)
                ')
            ),
            array(
                'name'     => 'Автомобилей с 4 и более фото',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1) from (
                        select cars.id, count(pictures.id) as c
                        from cars
                            inner join pictures on cars.id=pictures.car_id
                        where pictures.type = ?
                        group by cars.id
                        having c >= 4
                    ) as T1
                ', Picture::CAR_TYPE_ID)
            ),
            array(
                'name'     => 'Заполненных значений ТТХ',
                'total'    => $totalCars * $totalCarAttrs,
                'value'    => $carAttrsValues,
            ),
            array(
                'name'     => 'Логотипов брендов',
                'total'    => $totalBrands,
                'value'    => $db->fetchOne('
                    select count(1)
                    from brands
                    where img is not null
                ')
            ),
            array(
                'name'    => 'Годы начала выпуска автомобилей',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
                    where begin_year
                ')
            ),
            array(
                'name'    => 'Годы начала и окончания выпуска автомобилей',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
                    where begin_year and end_year
                ')
            ),
            array(
                'name'    => 'Годы и месяцы начала и окончания выпуска автомобилей',
                'total'    => $totalCars,
                'value'    => $db->fetchOne('
                    select count(1)
                    from cars
                    where begin_year and end_year and begin_month and end_month
                ')
            ),
        );

        $this->view->assign(array(
            'data' => $data,
        ));
    }

    public function tooBigCarsAction()
    {
        $carTable = new Cars();

        $rows = $carTable->getAdapter()->fetchAll("
            SELECT cars.id, cars.caption, cars.body, (case car_parent.type when 0 then 'Stock' when 1 then 'Tuning' when 2 then 'Sport' else car_parent.type end) as t, count(1) as c
            from car_parent
            join cars on car_parent.parent_id=cars.id
            group by cars.id, car_parent.type
            order by c desc
                limit 100
        ");

        $this->view->rows = $rows;
    }
}