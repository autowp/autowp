<?php

class Application_Service_Mosts
{
    protected $_ratings = array (
        array(
            'catName'   => 'fastest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 47,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'slowest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 47,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'dynamic',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 48,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'static',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 48,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),

        array(
            'catName'   => 'mighty',
            'adapter'   => array(
                'name'       => 'power',
                'attributes' => array(
                    'power'            => 33,
                    'cylindersLayout'  => 26,
                    'cylindersCount'   => 25,
                    'valvePerCylinder' => 27,
                    'powerOnFrequency' => 34,
                    'turbo'            => 99,
                    'volume'           => 31
                ),
                'order'      => 'DESC'
            )
        ),
        array(
            'catName'   => 'weak',
            'adapter'   => array(
                'name'       => 'power',
                'attributes' => array(
                    'power'            => 33,
                    'cylindersLayout'  => 26,
                    'cylindersCount'   => 25,
                    'valvePerCylinder' => 27,
                    'powerOnFrequency' => 34,
                    'turbo'            => 99,
                    'volume'           => 31
                ),
                'order'      => 'ASC'
            )
        ),

        array(
            'catName'   => 'big-engine',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 31,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'small-engine',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 31,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'nimblest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 11,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'economical',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 81,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'gluttonous',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 81,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),

        array(
            'catName'   => 'ecologicalclenaly',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 82,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'ecologicaldirty',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 82,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'heavy',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 72,
                'itemType'  => 2,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'lightest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 72,
                'itemType'  => 2,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'longest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 1,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'shortest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 1,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'widest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 2,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'narrow',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 2,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'highest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 3,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'lowest',
            'adapter'   => array(
                'name'     => 'attr',
                'attribute' => 3,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),

        array(
            'catName'   => 'air',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 64,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'antiair',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 64,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),


        //equipes.backaxis_tyrewidth*equipes.backaxis_tyreseries/100+equipes.backaxis_radius*25.4

        array(
            'catName'   => 'bigwheel',
            'adapter'   => array(
                'name'       => 'wheelsize',
                'order'      => 'DESC',
                'attributes' => array(
                    'rear' => array(
                        'tyrewidth'  => 91,
                        'tyreseries' => 94,
                        'radius'     => 92,
                        'rimwidth'   => 93
                    ),
                    'front' => array(
                        'tyrewidth'  => 87,
                        'tyreseries' => 90,
                        'radius'     => 88,
                        'rimwidth'   => 89
                    ),
                )
            )
        ),
        array(
            'catName'   => 'smallwheel',
            'adapter'   => array(
                'name'       => 'wheelsize',
                'order'      => 'ASC',
                'attributes' => array(
                    'rear' => array(
                        'tyrewidth'  => 91,
                        'tyreseries' => 94,
                        'radius'     => 92,
                        'rimwidth'   => 93
                    ),
                    'front' => array(
                        'tyrewidth'  => 87,
                        'tyreseries' => 90,
                        'radius'     => 88,
                        'rimwidth'   => 89
                    ),
                )
            )
        ),

        array(
            'catName'   => 'bigclearance',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 7,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'smallclearance',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 7,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),
    );

    protected $_years = null;

    /**
     * @var Car_Type_Language
     */
    protected $_carTypeLangTable = null;

    protected $_perspectiveGroups = null;

    public function __construct()
    {

    }

    /**
     * @return Car_Type_Language
     */
    protected function _getCarTypeLangTable()
    {
        return $this->_carTypeLangTable
            ? $this->_carTypeLangTable
            : $this->_carTypeLangTable = new Car_Type_Language();
    }

    protected function _betweenYearsExpr($from, $to)
    {
        return 'cars.begin_order_cache between "'.$from.'-01-01" and "'.$to.'-12-31" or ' .
               'cars.end_order_cache between "'.$from.'-01-01" and "'.$to.'-12-31" or ' .
               '(cars.begin_order_cache < "'.$from.'-01-01" and cars.end_order_cache > "'.$to.'-12-31")';
    }

    public function getYears()
    {
        if ($this->_years === null) {
            $cy = (int)date('Y');

            $prevYear = $cy-1;

            $this->_years = array(
                array(
                    'name'   => 'до 1920го',
                    'folder' => 'before1920',
                    'where'  => 'cars.begin_order_cache > 0 and cars.begin_order_cache < 1920'
                ),
                array(
                    'name'   => '1920-29ых',
                    'folder' => '1920-29',
                    'where'  => $this->_betweenYearsExpr(1920, 1929)
                ),
                array(
                    'name'   => '1930-39ых',
                    'folder' => '1930-39',
                    'where'  => $this->_betweenYearsExpr(1930, 1939)
                ),
                array(
                    'name'   => '1940-49ых',
                    'folder' => '1940-49',
                    'where'  => $this->_betweenYearsExpr(1940, 1949)
                ),
                array(
                    'name'   => '1950-59ых',
                    'folder' => '1950-59',
                    'where'  => $this->_betweenYearsExpr(1950, 1959)
                ),
                array(
                    'name'   => '1960-69ых',
                    'folder' => '1960-69',
                    'where'  => $this->_betweenYearsExpr(1960, 1969)
                ),
                array(
                    'name'   => '1970-79ых',
                    'folder' => '1970-79',
                    'where'  => $this->_betweenYearsExpr(1970, 1979)
                ),
                array(
                    'name'   => '1980-89ых',
                    'folder' => '1980-89',
                    'where'  => $this->_betweenYearsExpr(1980, 1989)
                ),
                array(
                    'name'   => '1990-99ых',
                    'folder' => '1990-99',
                    'where'  => $this->_betweenYearsExpr(1990, 1999)
                ),
                array(
                    'name'   => '2000-09ых',
                    'folder' => '2000-09',
                    'where'  => $this->_betweenYearsExpr(2000, 2009)
                ),
                array(
                    'name'   => '2010-'.($prevYear%100),
                    'folder' => '2010-'.($prevYear%100),
                    'where'  => $this->_betweenYearsExpr(2010, $prevYear)
                ),
                array(
                    'name'   => 'нашего времени',
                    'folder' => 'today',
                    'where'  => 'cars.end_order_cache >="'.$cy.'-01-01" or cars.end_order_cache is null and cars.today'
                )
            );
        }

        return $this->_years;
    }

    public function getRatings()
    {
        return $this->_ratings;
    }

    public function getPrespectiveGroups()
    {
        if ($this->_perspectiveGroups === null) {

            $pgTable = new Perspectives_Groups();
            $groups = $pgTable->fetchAll(
                $pgTable->select(true)
                    ->where('page_id = ?', 1)
                    ->order('position')
            );
            $g = array();
            foreach ($groups as $group) {
                $g[] = $group->id;
            }

            $this->_perspectiveGroups = $g;
        }

        return $this->_perspectiveGroups;
    }

    public function getCarTypes($language, $brandId)
    {
        $carTypesTable = new Car_Types();
        $carTypeLangTable = $this->_getCarTypeLangTable();
        $carTypes = array();
        $select = $carTypesTable->select(true)
            ->where('car_types.parent_id IS NULL')
            ->order('car_types.position');

        if ($brandId) {
            $select
                ->join('car_types_parents', 'car_types.id = car_types_parents.parent_id', null)
                ->join('cars', 'car_types_parents.id = cars.car_type_id', null)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId)
                ->group('car_types.id');
        }


        foreach ($carTypesTable->fetchAll($select) as $row) {

            $lang = $carTypeLangTable->fetchRow(array(
                'language = ?'    => $language,
                'car_type_id = ?' => $row->id
            ));

            $childs = array();

            $select = $select = $carTypesTable->select(true)
                ->where('car_types.parent_id = ?', $row->id)
                ->order('car_types.position');

            if ($brandId) {
                $select
                    ->join('car_types_parents', 'car_types.id = car_types_parents.parent_id', null)
                    ->join('cars', 'car_types_parents.id = cars.car_type_id', null)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brandId)
                    ->group('car_types.id');
            }

            foreach ($carTypesTable->fetchAll($select) as $srow) {

                $slang = $carTypeLangTable->fetchRow(array(
                    'language = ?'    => $language,
                    'car_type_id = ?' => $srow->id
                ));

                $childs[] = array(
                    'id'      => $srow->id,
                    'catname' => $srow->catname,
                    'name'    => $slang ? $slang->name_rp : $srow->name_rp
                );
            }

            $carTypes[] = array(
                'id'      => $row->id,
                'catname' => $row->catname,
                'name'    => $lang ? $lang->name_rp : $row->name_rp,
                'childs'  => $childs
            );
        }

        return $carTypes;
    }

    public function getCarTypeData($carType, $language)
    {
        $carTypeLangTable = $this->_getCarTypeLangTable();

        $carTypeLang = $carTypeLangTable->fetchRow(array(
            'language = ?'    => $language,
            'car_type_id = ?' => $carType->id
        ));

        return array(
            'id'      => $carType->id,
            'catname' => $carType->catname,
            'name'    => $carTypeLang ? $carTypeLang->name : $carType->name,
            'name_rp' => $carTypeLang ? $carTypeLang->name_rp : $carType->name_rp,
        );
    }

    public function getCarsData($cMost, $carType, $cYear, $brandId)
    {
        $carsTable = new Cars();

        $select = $carsTable->select(true);

        if ($carType) {
            $ids = $this->_getCarTypesIds($carType);
            if (count($ids) == 1) {
                $select->where('cars.car_type_id = ?', $ids[0]);
            } else {
                $select->where('cars.car_type_id IN (?)', $ids);
            }
        }

        if (!is_null($cYear)) {
            $select->where($cYear['where']);
        }

        if ($brandId) {
            $select
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId);
        }

        $most = new Project_Most(array(
            'carsSelect' => $select,
            'adapter'    => $cMost['adapter'],
            'carsCount'  => 7,
        ));

        $g = $this->getPrespectiveGroups();

        $data = $most->getData();
        foreach ($data['cars'] as &$car) {
            $car['pictures'] = $car['car']->getOrientedPictureList($g);
        }

        return $data;
    }

    protected function _getCarTypesIds($carType)
    {
        $result = array($carType->id);
        foreach ($carType->findCar_Types() as $child) {
            $result[] = $child->id;
            $result = array_merge($result, $this->_getCarTypesIds($child));
        }
        return $result;
    }

    public function getData($options)
    {
        $defaults = array(
            'language' => null,
            'most'     => null,
            'years'    => null,
            'carType'  => null,
            'brandId'  => null
        );

        $options = array_merge($defaults, $options);

        $language = $options['language'];
        if (!$language) {
            throw new Exception('Language not provided');
        }

        $mostCatname = $options['most'];
        $yearsCatname = $options['years'];
        $carTypeCatname = $options['carType'];
        $brandId = $options['brandId'];

        $ratings = $this->getRatings();

        $mostId = 0;
        foreach ($ratings as $id => $most) {
            if ($mostCatname == $most['catName']) {
                $mostId = $id;
                break;
            }
        }

        $carTypesTable = new Car_Types();
        $carType = $carTypesTable->fetchRow(array(
            'catname = ?' => (string)$carTypeCatname
        ));

        $years = $this->getYears();

        if ($brandId) {
            $carsTable = new Cars();
            $select = $carsTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId)
                ->limit(1);

            foreach ($years as $idx => $year) {
                $cSelect = clone $select;
                $cSelect->where($year['where']);
                //print $select;
                $rowExists = (bool)$carsTable->fetchRow($cSelect);
                //var_dump($year['where'], $rowExists);
                if (!$rowExists) {
                    unset($years[$idx]);
                }
            }
            //exit;
        }

        $yearId = null;
        foreach ($years as $id => $year) {
            if ($yearsCatname == $year['folder']) {
                $yearId = $id;
                break;
            }
        }

        $cMost = $ratings[$mostId];
        $cYear = is_null($yearId) ? null : $years[$yearId];

        $carTypeData = false;
        if ($carType) {
            $carTypeData = $this->getCarTypeData($carType, $language);
        }

        $data = $this->getCarsData($cMost, $carType, $cYear, $brandId);

        // sidebar
        $carTypeCatname = $carTypeData ? $carTypeData['catname'] : null;
        $mosts = array();
        foreach ($ratings as $id => $most) {
            $mosts[] = array(
                'active' => $id == $mostId,
                'name'   => 'most/'.$most['catName'],
                'params' => array(
                    'most_catname'  => $most['catName'],
                    'shape_catname' => $carTypeCatname,
                    'years_catname' => $cYear['folder']
                )
            );
        }

        $carTypes = $this->getCarTypes($language, $brandId);

        $sidebarCarTypes = array();
        foreach ($carTypes as $carType) {
            $sidebarCarType = array(
                'active' => $carTypeData && $carType['id'] == $carTypeData['id'],
                'name'   => $carType['name'],
                'params' => array(
                    'most_catname'  => $cMost['catName'],
                    'shape_catname' => $carType['catname'],
                    'years_catname' => $cYear['folder']
                ),
                'childs' => array()
            );

            if ($carType['childs']) {
                foreach ($carType['childs'] as $child) {
                    $sidebarCarType['childs'][] = array(
                        'active' => $carTypeData && $child['id'] == $carTypeData['id'],
                        'name'   => $child['name'],
                        'params' => array(
                            'most_catname'  => $cMost['catName'],
                            'shape_catname' => $child['catname'],
                            'years_catname' => $cYear['folder']
                        )
                    );
                }
            }

            $sidebarCarTypes[] = $sidebarCarType;
        }

        $sidebar = array(
            'mosts'    => $mosts,
            'carTypes' => $sidebarCarTypes
        );

        $yearsMenu = array();
        foreach ($years as $id => $year) {
            $yearsMenu[] = array(
                'active' => !is_null($yearId) && ($id == $yearId),
                'name'   => $year['name'],
                'params' => array(
                    'most_catname'  => $cMost['catName'],
                    'shape_catname' => $carTypeCatname ? $carTypeCatname : 'car',
                    'years_catname' => $year['folder']
                )
            );
        }
        $yearsMenu[] = array(
            'active' => is_null($yearId),
            'name'   => 'за всю историю',
            'params' => array(
                'most_catname'  => $cMost['catName'],
                'shape_catname' => $carTypeCatname ? $carTypeCatname : null,
                'years_catname' => null
            )
        );

        return array(
            'carList'  => $data,
            'carType'  => $carTypeData,
            'years'    => $yearsMenu,
            'cYear'    => $cYear,
            'yearId'   => $yearId,
            'cMost'    => $cMost,
            'sidebar'  => $sidebar
        );
    }
}