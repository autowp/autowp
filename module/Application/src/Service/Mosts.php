<?php

namespace Application\Service;

use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Most;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_merge;
use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function date;

class Mosts
{
    private array $ratings = [
        [
            'catName' => 'fastest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 47,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'slowest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 47,
                'order'     => 'ASC',
            ],
        ],
        /*[
            'catName'   => 'dynamic',
            'adapter'   => [
                'name'      => 'acceleration',
                'attributes' => [
                    'to100kmh' => 48,
                    'to60mph'  => 175,
                ],
                'order'     => 'ASC'
            ]
        ],
        [
            'catName'   => 'static',
            'adapter'   => [
                'name'      => 'attr',
                'attribute' => 48,
                'order'     => 'DESC'
            ]
        ],*/
        [
            'catName' => 'mighty',
            'adapter' => [
                'name'       => 'power',
                'attributes' => [
                    'power'            => 33,
                    'cylindersLayout'  => 26,
                    'cylindersCount'   => 25,
                    'valvePerCylinder' => 27,
                    'powerOnFrequency' => 34,
                    'turbo'            => 99,
                    'volume'           => 31,
                ],
                'order'      => 'DESC',
            ],
        ],
        [
            'catName' => 'weak',
            'adapter' => [
                'name'       => 'power',
                'attributes' => [
                    'power'            => 33,
                    'cylindersLayout'  => 26,
                    'cylindersCount'   => 25,
                    'valvePerCylinder' => 27,
                    'powerOnFrequency' => 34,
                    'turbo'            => 99,
                    'volume'           => 31,
                ],
                'order'      => 'ASC',
            ],
        ],
        [
            'catName' => 'big-engine',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 31,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'small-engine',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 31,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'nimblest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 11,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'economical',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 81,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'gluttonous',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 81,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'clenaly',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 82,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'dirty',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 82,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'heavy',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 72,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'lightest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 72,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'longest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 1,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'shortest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 1,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'widest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 2,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'narrow',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 2,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'highest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 3,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'lowest',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 3,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'air',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 64,
                'order'     => 'ASC',
            ],
        ],
        [
            'catName' => 'antiair',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 64,
                'order'     => 'DESC',
            ],
        ],
        //equipes.backaxis_tyrewidth*equipes.backaxis_tyreseries/100+equipes.backaxis_radius*25.4
        [
            'catName' => 'bigwheel',
            'adapter' => [
                'name'       => 'wheelsize',
                'order'      => 'DESC',
                'attributes' => [
                    'rear'  => [
                        'tyrewidth'  => 91,
                        'tyreseries' => 94,
                        'radius'     => 92,
                        'rimwidth'   => 93,
                    ],
                    'front' => [
                        'tyrewidth'  => 87,
                        'tyreseries' => 90,
                        'radius'     => 88,
                        'rimwidth'   => 89,
                    ],
                ],
            ],
        ],
        [
            'catName' => 'smallwheel',
            'adapter' => [
                'name'       => 'wheelsize',
                'order'      => 'ASC',
                'attributes' => [
                    'rear'  => [
                        'tyrewidth'  => 91,
                        'tyreseries' => 94,
                        'radius'     => 92,
                        'rimwidth'   => 93,
                    ],
                    'front' => [
                        'tyrewidth'  => 87,
                        'tyreseries' => 90,
                        'radius'     => 88,
                        'rimwidth'   => 89,
                    ],
                ],
            ],
        ],
        /*[
            'catName'   => 'bigbrakes',
            'adapter'   => [
                'name'       => 'brakes',
                'order'      => 'DESC',
                'attributes' => [
                    'rear' => [
                        'diameter'  => 147,
                        'thickness' => 149,
                    ],
                    'front' => [
                        'diameter'  => 146,
                        'thickness' => 148,
                    ],
                ]
            ]
        ],
        [
            'catName'   => 'smallbrakes',
            'adapter'   => [
                'name'       => 'brakes',
                'order'      => 'ASC',
                'attributes' => [
                    'rear' => [
                        'diameter'  => 147,
                        'thickness' => 149,
                    ],
                    'front' => [
                        'diameter'  => 146,
                        'thickness' => 148,
                    ],
                ]
            ]
        ],*/
        [
            'catName' => 'bigclearance',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 7,
                'order'     => 'DESC',
            ],
        ],
        [
            'catName' => 'smallclearance',
            'adapter' => [
                'name'      => 'attr',
                'attribute' => 7,
                'order'     => 'ASC',
            ],
        ],
    ];

    private array $years;

    private array $perspectiveGroups;

    private SpecificationsService $specs;

    private Perspective $perspective;

    private VehicleType $vehicleType;

    private Picture $picture;

    private TableGateway $attributeTable;

    private TableGateway $itemTable;

    public function __construct(
        SpecificationsService $specs,
        Perspective $perspective,
        VehicleType $vehicleType,
        Picture $picture,
        TableGateway $attributeTable,
        TableGateway $itemTable
    ) {
        $this->specs          = $specs;
        $this->perspective    = $perspective;
        $this->vehicleType    = $vehicleType;
        $this->picture        = $picture;
        $this->attributeTable = $attributeTable;
        $this->itemTable      = $itemTable;
    }

    private function betweenYearsExpr(int $from, int $to): string
    {
        return '(item.begin_order_cache between "' . $from . '-01-01" and "' . $to . '-12-31" or '
               . 'item.end_order_cache between "' . $from . '-01-01" and "' . $to . '-12-31" or '
               . '(item.begin_order_cache < "' . $from . '-01-01" and item.end_order_cache > "' . $to . '-12-31"))';
    }

    public function getYears(): array
    {
        if (! isset($this->years)) {
            $cy = (int) date('Y');

            $prevYear = $cy - 1;

            $this->years = [
                [
                    'name'   => 'mosts/period/before1920',
                    'folder' => 'before1920',
                    'where'  => '(item.begin_order_cache <= "1919-12-31" or '
                                . 'item.end_order_cache <= "1919-12-31")',
                ],
                [
                    'name'   => 'mosts/period/1920-29',
                    'folder' => '1920-29',
                    'where'  => $this->betweenYearsExpr(1920, 1929),
                ],
                [
                    'name'   => 'mosts/period/1930-39',
                    'folder' => '1930-39',
                    'where'  => $this->betweenYearsExpr(1930, 1939),
                ],
                [
                    'name'   => 'mosts/period/1940-49',
                    'folder' => '1940-49',
                    'where'  => $this->betweenYearsExpr(1940, 1949),
                ],
                [
                    'name'   => 'mosts/period/1950-59',
                    'folder' => '1950-59',
                    'where'  => $this->betweenYearsExpr(1950, 1959),
                ],
                [
                    'name'   => 'mosts/period/1960-69',
                    'folder' => '1960-69',
                    'where'  => $this->betweenYearsExpr(1960, 1969),
                ],
                [
                    'name'   => 'mosts/period/1970-79',
                    'folder' => '1970-79',
                    'where'  => $this->betweenYearsExpr(1970, 1979),
                ],
                [
                    'name'   => 'mosts/period/1980-89',
                    'folder' => '1980-89',
                    'where'  => $this->betweenYearsExpr(1980, 1989),
                ],
                [
                    'name'   => 'mosts/period/1990-99',
                    'folder' => '1990-99',
                    'where'  => $this->betweenYearsExpr(1990, 1999),
                ],
                [
                    'name'   => 'mosts/period/2000-09',
                    'folder' => '2000-09',
                    'where'  => $this->betweenYearsExpr(2000, 2009),
                ],
                [
                    'name'   => 'mosts/period/2010-' . ($prevYear % 100),
                    'folder' => '2010-' . ($prevYear % 100),
                    'where'  => $this->betweenYearsExpr(2010, $prevYear),
                ],
                [
                    'name'   => 'mosts/period/present',
                    'folder' => 'today',
                    'where'  => '(item.end_order_cache >="' . $cy . '-01-01" and item.end_order_cache<"2100-01-01" '
                                . 'or item.end_order_cache is null and item.today)',
                ],
            ];
        }

        return $this->years;
    }

    public function getRatings(): array
    {
        return $this->ratings;
    }

    public function getPrespectiveGroups(): array
    {
        if (! isset($this->perspectiveGroups)) {
            $ids = $this->perspective->getPageGroupIds(1);

            $this->perspectiveGroups = $ids;
        }

        return $this->perspectiveGroups;
    }

    public function getCarTypes(int $brandId): array
    {
        $carTypes = [];
        foreach ($this->vehicleType->getRows(0, $brandId) as $row) {
            $childs = [];

            foreach ($this->vehicleType->getRows($row['id'], $brandId) as $srow) {
                $childs[] = [
                    'id'      => (int) $srow['id'],
                    'catname' => $srow['catname'],
                    'name'    => $srow['name_rp'],
                ];
            }

            $carTypes[] = [
                'id'      => (int) $row['id'],
                'catname' => $row['catname'],
                'name'    => $row['name_rp'],
                'childs'  => $childs,
            ];
        }

        return $carTypes;
    }

    private function getCarTypeData(array $carType): array
    {
        return [
            'id'      => (int) $carType['id'],
            'catname' => $carType['catname'],
            'name'    => $carType['name'],
            'name_rp' => $carType['name_rp'],
        ];
    }

    /**
     * @throws Exception
     */
    private function getOrientedPictureList(int $carId, array $perspectiveGroupIds): array
    {
        $pictures = [];

        foreach ($perspectiveGroupIds as $groupId) {
            $picture = $this->picture->getRow([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'perspective'      => [
                        'group' => $groupId,
                    ],
                    'ancestor_or_self' => [
                        'link_type' => [ItemParent::TYPE_DEFAULT, ItemParent::TYPE_DESIGN],
                        'id'        => $carId,
                    ],
                ],
                'order'  => 'perspective_group',
            ]);

            $pictures[] = $picture ? $picture : null;
        }

        $ids = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $ids[] = $picture['id'];
            }
        }

        foreach ($pictures as $key => $picture) {
            if (! $picture) {
                $pic = $this->picture->getRow([
                    'id_exclude' => $ids,
                    'status'     => Picture::STATUS_ACCEPTED,
                    'item'       => [
                        'ancestor_or_self' => [
                            'link_type' => [ItemParent::TYPE_DEFAULT, ItemParent::TYPE_DESIGN],
                            'id'        => $carId,
                        ],
                    ],
                ]);

                if (! $pic) {
                    break;
                }

                $pictures[$key] = $pic;
                $ids[]          = $pic['id'];
            }
        }

        return $pictures;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     * @throws Exception
     */
    private function getCarsData(array $cMost, int $carTypeId, ?array $cYear, int $brandId, string $language): array
    {
        $select = new Sql\Select($this->itemTable->getTable());

        if ($carTypeId) {
            $ids = $this->vehicleType->getDescendantsAndSelfIds($carTypeId);

            if (! $ids) {
                throw new Exception("Failed fetch vehicle_type ids");
            }

            $select->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', []);

            if (count($ids) === 1) {
                $select->where(['vehicle_vehicle_type.vehicle_type_id' => $ids[0]]);
            } else {
                $select->where([new Sql\Predicate\In('vehicle_vehicle_type.vehicle_type_id', $ids)]);
            }
        }

        if ($cYear !== null) {
            $select->where($cYear['where']);
        }

        if ($brandId) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', [])
                ->where([
                    'not item_parent_cache.tuning',
                    'item_parent_cache.parent_id' => $brandId,
                ])
                ->group('item.id');
        }

        $most = new Most([
            'attributeTable' => $this->attributeTable,
            'itemTable'      => $this->itemTable,
            'specs'          => $this->specs,
            'carsSelect'     => $select,
            'adapter'        => $cMost['adapter'],
            'carsCount'      => 7,
        ]);

        $g = $this->getPrespectiveGroups();

        $data = $most->getData($language);
        foreach ($data['cars'] as &$car) {
            $car['pictures'] = $this->getOrientedPictureList($car['car']['id'], $g);
        }
        unset($car);

        return $data;
    }

    /**
     * @suppress PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     * @throws Exception
     */
    public function getData(array $options): array
    {
        $defaults = [
            'language' => null,
            'most'     => null,
            'years'    => null,
            'carType'  => null,
            'brandId'  => null,
        ];

        $options = array_merge($defaults, $options);

        $language = $options['language'];
        if (! $language) {
            throw new Exception('Language not provided');
        }

        $mostCatname    = $options['most'];
        $yearsCatname   = $options['years'];
        $carTypeCatname = $options['carType'];
        $brandId        = (int) $options['brandId'];

        $ratings = $this->getRatings();

        $mostId = 0;
        foreach ($ratings as $id => $most) {
            if ($mostCatname === $most['catName']) {
                $mostId = $id;
                break;
            }
        }

        $carType = null;
        if ($carTypeCatname) {
            $carType = $this->vehicleType->getRowByCatname($carTypeCatname);
        }

        $years = $this->getYears();

        if ($brandId) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select
                ->columns(['id'])
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', [])
                ->where([
                    'not item_parent_cache.tuning',
                    'item_parent_cache.parent_id' => $brandId,
                ])
                ->limit(1);

            foreach ($years as $idx => $year) {
                $cSelect = clone $select;
                $cSelect->where($year['where']);
                $rowExists = (bool) currentFromResultSetInterface($this->itemTable->selectWith($cSelect));
                if (! $rowExists) {
                    unset($years[$idx]);
                }
            }
        }

        $yearId = null;
        foreach ($years as $id => $year) {
            if ($yearsCatname === $year['folder']) {
                $yearId = $id;
                break;
            }
        }

        $cMost = $ratings[$mostId];
        $cYear = $yearId === null ? null : $years[$yearId];

        $carTypeData = false;
        if ($carType) {
            $carTypeData = $this->getCarTypeData($carType);
        }

        $data = $this->getCarsData($cMost, $carType ? $carType['id'] : 0, $cYear, $brandId, $language);

        // sidebar
        $carTypeCatname = $carTypeData ? $carTypeData['catname'] : null;
        $mosts          = [];
        foreach ($ratings as $id => $most) {
            $mosts[] = [
                'active' => $id === $mostId,
                'name'   => 'most/' . $most['catName'],
                'params' => [
                    'most_catname'  => $most['catName'],
                    'shape_catname' => $carTypeCatname,
                    'years_catname' => $cYear['folder'],
                ],
            ];
        }

        $carTypes = $this->getCarTypes($brandId);

        $sidebarCarTypes = [];
        foreach ($carTypes as $carType) {
            $sidebarCarType = [
                'active' => $carTypeData && $carType['id'] === $carTypeData['id'],
                'name'   => $carType['name'],
                'params' => [
                    'most_catname'  => $cMost['catName'],
                    'shape_catname' => $carType['catname'],
                    'years_catname' => $cYear['folder'],
                ],
                'childs' => [],
            ];

            $childActive = false;
            if ($carType['childs']) {
                foreach ($carType['childs'] as $child) {
                    $active = $carTypeData && $child['id'] === $carTypeData['id'];
                    if ($active) {
                        $childActive = true;
                    }
                    $sidebarCarType['childs'][] = [
                        'active' => $active,
                        'name'   => $child['name'],
                        'params' => [
                            'most_catname'  => $cMost['catName'],
                            'shape_catname' => $child['catname'],
                            'years_catname' => $cYear['folder'],
                        ],
                    ];
                }
            }

            if ($childActive) {
                $sidebarCarType['active'] = true;
            }

            $sidebarCarTypes[] = $sidebarCarType;
        }

        $sidebar = [
            'mosts'    => $mosts,
            'carTypes' => $sidebarCarTypes,
        ];

        $yearsMenu = [];
        foreach ($years as $id => $year) {
            $yearsMenu[] = [
                'active' => $yearId !== null && ($id === $yearId),
                'name'   => $year['name'],
                'params' => [
                    'most_catname'  => $cMost['catName'],
                    'shape_catname' => $carTypeCatname ? $carTypeCatname : 'car',
                    'years_catname' => $year['folder'],
                ],
            ];
        }
        $yearsMenu[] = [
            'active' => $yearId === null,
            'name'   => 'mosts/period/all-time',
            'params' => [
                'most_catname'  => $cMost['catName'],
                'shape_catname' => $carTypeCatname ? $carTypeCatname : null,
                'years_catname' => null,
            ],
        ];

        return [
            'carList' => $data,
            'carType' => $carTypeData,
            'years'   => $yearsMenu,
            'cYear'   => $cYear,
            'yearId'  => $yearId,
            'cMost'   => $cMost,
            'sidebar' => $sidebar,
        ];
    }

    public function getYearsMenu(): array
    {
        $yearsMenu = [];
        foreach ($this->getYears() as $year) {
            $yearsMenu[] = [
                'name'    => $year['name'],
                'catname' => $year['folder'],
            ];
        }
        $yearsMenu[] = [
            'name'    => 'mosts/period/all-time',
            'catname' => null,
        ];

        return $yearsMenu;
    }

    public function getRatingsMenu(): array
    {
        $result = [];
        foreach ($this->getRatings() as $most) {
            $result[] = [
                'name'    => 'most/' . $most['catName'],
                'catname' => $most['catName'],
            ];
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getItems(array $options): array
    {
        $defaults = [
            'language' => null,
            'most'     => null,
            'years'    => null,
            'carType'  => null,
            'brandId'  => null,
        ];

        $options = array_replace($defaults, $options);

        $language = $options['language'];
        if (! $language) {
            throw new Exception('Language not provided');
        }

        $mostCatname    = $options['most'];
        $yearsCatname   = $options['years'];
        $carTypeCatname = $options['carType'];
        $brandId        = (int) $options['brandId'];

        $ratings = $this->getRatings();

        $mostId = 0;
        foreach ($ratings as $id => $most) {
            if ($mostCatname === $most['catName']) {
                $mostId = $id;
                break;
            }
        }

        $carType = null;
        if ($carTypeCatname) {
            $carType = $this->vehicleType->getRowByCatname($carTypeCatname);
        }

        $years = $this->getYears();

        $yearId = null;
        foreach ($years as $id => $year) {
            if ($yearsCatname === $year['folder']) {
                $yearId = $id;
                break;
            }
        }

        $cMost = $ratings[$mostId];
        $cYear = $yearId === null ? null : $years[$yearId];

        return $this->getCarsData($cMost, $carType ? $carType['id'] : 0, $cYear, $brandId, $language);
    }
}
