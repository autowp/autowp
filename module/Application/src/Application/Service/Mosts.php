<?php

namespace Application\Service;

use Application\Service\SpecificationsService;

use Car_Types;
use Cars;
use Exception;
use Perspective_Group;
use Picture;
use Project_Most;
use Zend_Db_Expr;

class Mosts
{
    private $_ratings = array (
        array(
            'catName'   => 'fastest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 47,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'slowest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 47,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'dynamic',
            'adapter'   => array(
                'name'      => 'acceleration',
                'attributes' => array(
                    'to100kmh' => 48,
                    'to60mph'  => 175,
                ),
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'static',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 48,
                'itemType'  => 1,
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
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'small-engine',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 31,
                'itemType'  => 1,
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
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'gluttonous',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 81,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),

        array(
            'catName'   => 'clenaly',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 82,
                'itemType'  => 1,
                'order'     => 'ASC'
            )
        ),
        array(
            'catName'   => 'dirty',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 82,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'heavy',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 72,
                'itemType'  => 1,
                'order'     => 'DESC'
            )
        ),
        array(
            'catName'   => 'lightest',
            'adapter'   => array(
                'name'      => 'attr',
                'attribute' => 72,
                'itemType'  => 1,
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
            'catName'   => 'bigbrakes',
            'adapter'   => array(
                'name'       => 'brakes',
                'order'      => 'DESC',
                'attributes' => array(
                    'rear' => array(
                        'diameter'  => 147,
                        'thickness' => 149,
                    ),
                    'front' => array(
                        'diameter'  => 146,
                        'thickness' => 148,
                    ),
                )
            )
        ),
        array(
            'catName'   => 'smallbrakes',
            'adapter'   => array(
                'name'       => 'brakes',
                'order'      => 'ASC',
                'attributes' => array(
                    'rear' => array(
                        'diameter'  => 147,
                        'thickness' => 149,
                    ),
                    'front' => array(
                        'diameter'  => 146,
                        'thickness' => 148,
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

    private $_years = null;

    private $_perspectiveGroups = null;

    /**
     * @var SpecificationsService
     */
    private $_specs = null;

    public function __construct(array $options = array())
    {
        $this->_specs = $options['specs'];
    }

    private function _betweenYearsExpr($from, $to)
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
                    'name'   => 'mosts/period/before1920',
                    'folder' => 'before1920',
                    'where'  => 'cars.begin_order_cache <= "1919-12-31" or ' .
                                'cars.end_order_cache <= "1919-12-31"'
                ),
                array(
                    'name'   => 'mosts/period/1920-29',
                    'folder' => '1920-29',
                    'where'  => $this->_betweenYearsExpr(1920, 1929)
                ),
                array(
                    'name'   => 'mosts/period/1930-39',
                    'folder' => '1930-39',
                    'where'  => $this->_betweenYearsExpr(1930, 1939)
                ),
                array(
                    'name'   => 'mosts/period/1940-49',
                    'folder' => '1940-49',
                    'where'  => $this->_betweenYearsExpr(1940, 1949)
                ),
                array(
                    'name'   => 'mosts/period/1950-59',
                    'folder' => '1950-59',
                    'where'  => $this->_betweenYearsExpr(1950, 1959)
                ),
                array(
                    'name'   => 'mosts/period/1960-69',
                    'folder' => '1960-69',
                    'where'  => $this->_betweenYearsExpr(1960, 1969)
                ),
                array(
                    'name'   => 'mosts/period/1970-79',
                    'folder' => '1970-79',
                    'where'  => $this->_betweenYearsExpr(1970, 1979)
                ),
                array(
                    'name'   => 'mosts/period/1980-89',
                    'folder' => '1980-89',
                    'where'  => $this->_betweenYearsExpr(1980, 1989)
                ),
                array(
                    'name'   => 'mosts/period/1990-99',
                    'folder' => '1990-99',
                    'where'  => $this->_betweenYearsExpr(1990, 1999)
                ),
                array(
                    'name'   => 'mosts/period/2000-09',
                    'folder' => '2000-09',
                    'where'  => $this->_betweenYearsExpr(2000, 2009)
                ),
                array(
                    'name'   => 'mosts/period/2010-'.($prevYear%100),
                    'folder' => '2010-'.($prevYear%100),
                    'where'  => $this->_betweenYearsExpr(2010, $prevYear)
                ),
                array(
                    'name'   => 'mosts/period/present',
                    'folder' => 'today',
                    'where'  => 'cars.end_order_cache >="'.$cy.'-01-01" and cars.end_order_cache<"2100-01-01" or cars.end_order_cache is null and cars.today'
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

            $pgTable = new Perspective_Group();
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

                $childs[] = array(
                    'id'      => $srow->id,
                    'catname' => $srow->catname,
                    'name'    => $srow->name_rp
                );
            }

            $carTypes[] = array(
                'id'      => $row->id,
                'catname' => $row->catname,
                'name'    => $row->name_rp,
                'childs'  => $childs
            );
        }

        return $carTypes;
    }

    public function getCarTypeData($carType, $language)
    {
        return array(
            'id'      => $carType->id,
            'catname' => $carType->catname,
            'name'    => $carType->name,
            'name_rp' => $carType->name_rp,
        );
    }

    private function _getOrientedPictureList($carId, array $perspective_group_ids)
    {
        $pictureTable = new Picture();
        $pictures = array();
        $db = $pictureTable->getAdapter();

        foreach ($perspective_group_ids as $groupId) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join(array('mp' => 'perspectives_groups_perspectives'), 'pictures.perspective_id=mp.perspective_id', null)
                    ->where('mp.group_id = ?', $groupId)
                    ->where('car_parent_cache.parent_id = ?', $carId)
                    ->where('not car_parent_cache.sport and not car_parent_cache.tuning')
                    ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                    ->order(array(
                        'mp.position',
                        new Zend_Db_Expr($db->quoteInto('pictures.status=? DESC', Picture::STATUS_ACCEPTED)),
                        'pictures.width DESC', 'pictures.height DESC'
                    ))
                    ->limit(1)
            );

            if ($picture) {
                $pictures[] = $picture;
            } else {
                $pictures[] = null;
            }
        }

        $ids = array();
        foreach ($pictures as $picture) {
            if ($picture) {
                $ids[] = $picture->id;
            }
        }

        foreach ($pictures as $key => $picture) {
            if (!$picture) {
                $select = $pictureTable->select(true)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('pictures.type=?', Picture::CAR_TYPE_ID)
                    ->where('car_parent_cache.parent_id = ?', $carId)
                    ->where('not car_parent_cache.sport and not car_parent_cache.tuning')
                    ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                    ->limit(1);

                if (count($ids) > 0) {
                    $select->where('id NOT IN (?)', $ids);
                }

                $pic = $pictureTable->fetchAll($select)->current();

                if ($pic) {
                    $pictures[$key] = $pic;
                    $ids[] = $pic->id;
                } else {
                    break;
                }
            }
        }

        return $pictures;
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
                ->where('not car_parent_cache.tuning')
                ->where('brands_cars.brand_id = ?', $brandId)
                ->group('cars.id');
        }

        $most = new Project_Most(array(
            'specs'      => $this->_specs,
            'carsSelect' => $select,
            'adapter'    => $cMost['adapter'],
            'carsCount'  => 7,
        ));

        $g = $this->getPrespectiveGroups();

        $data = $most->getData();
        foreach ($data['cars'] as &$car) {
            $car['pictures'] = $this->_getOrientedPictureList($car['car']->id, $g);
        }

        return $data;
    }

    private function _getCarTypesIds($carType)
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
                ->where('not car_parent_cache.tuning')
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

            $childActive = false;
            if ($carType['childs']) {
                foreach ($carType['childs'] as $child) {
                    $active = $carTypeData && $child['id'] == $carTypeData['id'];
                    if ($active) {
                        $childActive = true;
                    }
                    $sidebarCarType['childs'][] = array(
                        'active' => $active,
                        'name'   => $child['name'],
                        'params' => array(
                            'most_catname'  => $cMost['catName'],
                            'shape_catname' => $child['catname'],
                            'years_catname' => $cYear['folder']
                        )
                    );
                }
            }

            if ($childActive) {
                $sidebarCarType['active'] = true;
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
            'name'   => 'mosts/period/all-time',
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