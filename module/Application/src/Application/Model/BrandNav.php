<?php

namespace Application\Model;

use Zend\Cache\Storage\StorageInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Router\Http\TreeRouteStack;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\DbTable\Picture;

use Zend_Db_Expr;

class BrandNav
{
    /**
     * @var StorageInterface
     */
    private $cache;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var TreeRouteStack
     */
    private $router;

    public function __construct(
        StorageInterface $cache,
        TranslatorInterface $translator,
        TreeRouteStack $router
    ) {

        $this->cache = $cache;
        $this->translator = $translator;
        $this->router = $router;
    }

    public function getMenu(array $params)
    {
        $defaults = [
            'brand_id'    => null,
            'car_id'      => null,
            'type'        => null,
            'is_concepts' => false,
            'is_engines'  => false,
            'language'    => 'en'
        ];
        $params = array_replace($defaults, $params);

        $brandModel = new BrandModel();
        $brand = $brandModel->getBrandById($params['brand_id'], $params['language']);
        if (! $brand) {
            return;
        }

        $carId = (int)$params['car_id'];
        $type = $params['type'];
        $type = strlen($type) ? (int)$type : null;
        $isConcepts = (bool)$params['is_concepts'];
        $isEngines = (bool)$params['is_engines'];

        return $this->brandSections($params['language'], $brand, $type, $carId, $isConcepts, $isEngines);
    }

    private function brandSections($language, $brand, $type, $carId, $isConcepts, $isEngines)
    {
        $conceptsSeparatly = ! in_array($brand['type_id'], [3, 4]);

        // create groups array
        $sections = $this->carSections($language, $brand, $conceptsSeparatly, $carId);

        $sections = array_merge(
            $sections,
            [
                [
                    'name'   => null,
                    'groups' => $this->subBrandGroups($language, $brand)
                ],
                [
                    'name'   => null,
                    'groups' => $this->otherGroups(
                        $language,
                        $brand,
                        $conceptsSeparatly,
                        $type,
                        $isConcepts,
                        $isEngines
                    )
                ]
            ]
        );

        return $sections;
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    private function url($route, array $params)
    {
        return $this->router->assemble($params, [
            'name' => $route
        ]);
    }

    private function otherGroups($language, $brand, $conceptsSeparatly, $type, $isConcepts, $isEngines)
    {
        $cacheKey = implode('_', [
            'SIDEBAR_OTHER',
            $brand['id'],
            $language,
            $conceptsSeparatly ? '1' : '0',
            '2'
        ]);

        $groups = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $groups = [];

            if ($conceptsSeparatly) {
                // concepts
                $carTable = new DbTable\Vehicle();

                $db = $carTable->getAdapter();
                $select = $db->select()
                    ->from('cars', new Zend_Db_Expr('1'))
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brand['id'])
                    ->where('cars.is_concept')
                    ->limit(1);
                if ($db->fetchOne($select) > 0) {
                    $groups['concepts'] = [
                        'url' => $this->url('catalogue', [
                            'action'        => 'concepts',
                            'brand_catname' => $brand['catname']
                        ]),
                        'name' => $this->translator->translate('concepts and prototypes'),
                    ];
                }
            }

            // engines
            $engineTable = new DbTable\Engine();
            $db = $engineTable->getAdapter();
            $enginesCount = $db->fetchOne(
                $db->select()
                    ->from($engineTable->info('name'), new Zend_Db_Expr('count(1)'))
                    ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $brand['id'])
            );
            if ($enginesCount > 0) {
                $groups['engines'] = [
                    'url' => $this->url('catalogue', [
                        'action'        => 'engines',
                        'brand_catname' => $brand['catname']
                    ]),
                    'name'  => $this->translator->translate('engines'),
                    'count' => $enginesCount
                ];
            }

            $picturesTable = new DbTable\Picture;
            $picturesAdapter = $picturesTable->getAdapter();

            // logotypes
            $logoPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('status in (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
                    ->where('type = ?', Picture::LOGO_TYPE_ID)
                    ->where('brand_id = ?', $brand['id'])
            );
            if ($logoPicturesCount > 0) {
                $groups['logo'] = [
                    'url' => $this->url('catalogue', [
                        'action'        => 'logotypes',
                        'brand_catname' => $brand['catname']
                    ]),
                    'name'  => $this->translator->translate('logotypes'),
                    'count' => $logoPicturesCount
                ];
            }

            // mixed
            $mixedPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                ->from('pictures', new Zend_Db_Expr('count(*)'))
                ->where('status in (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
                ->where('type = ?', Picture::MIXED_TYPE_ID)
                ->where('brand_id = ?', $brand['id'])
            );
            if ($mixedPicturesCount > 0) {
                $groups['mixed'] = [
                    'url' => $this->url('catalogue', [
                        'action' => 'mixed',
                        'brand_catname' => $brand['catname']
                    ]),
                    'name'  => $this->translator->translate('mixed'),
                    'count' => $mixedPicturesCount
                ];
            }

            // unsorted
            $unsortedPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('status in (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
                    ->where('type = ?', Picture::UNSORTED_TYPE_ID)
                    ->where('brand_id = ?', $brand['id'])
            );

            if ($unsortedPicturesCount > 0) {
                $groups['unsorted'] = [
                    'url'     => $this->url('catalogue', [
                        'action'        => 'other',
                        'brand_catname' => $brand['catname']
                    ]),
                    'name'  => $this->translator->translate('unsorted'),
                    'count' => $unsortedPicturesCount
                ];
            }

            $this->cache->setItem($cacheKey, $groups);
        }

        if (isset($groups['concepts'])) {
            $groups['concepts']['active'] = $isConcepts;
        }

        if (isset($groups['engines'])) {
            $groups['engines']['active'] = $isEngines;
        }

        if (isset($groups['logo'])) {
            $groups['logo']['active'] = isset($type) && $type == Picture::LOGO_TYPE_ID;
        }

        if (isset($groups['mixed'])) {
            $groups['mixed']['active'] = isset($type) && $type == Picture::MIXED_TYPE_ID;
        }

        if (isset($groups['unsorted'])) {
            $groups['unsorted']['active'] = isset($type) && $type == Picture::UNSORTED_TYPE_ID;
        }

        return array_values($groups);
    }

    private function getBrandAliases(array $brand)
    {
        $aliases = [$brand['name']];

        $brandAliasTable = new DbTable\BrandAlias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'brand_id = ?' => $brand['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $brandLangTable = new DbTable\BrandLanguage();
        $brandLangRows = $brandLangTable->fetchAll([
            'brand_id = ?' => $brand['id']
        ]);
        foreach ($brandLangRows as $brandLangRow) {
            $aliases[] = $brandLangRow->name;
        }

        usort($aliases, function ($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la == $lb) {
                return 0;
            }
            return ($la > $lb) ? -1 : 1;
        });

        return $aliases;
    }

    private function subBrandGroups($language, array $brand)
    {
        $brandModel = new BrandModel();

        $rows = $brandModel->getList($language, function ($select) use ($brand) {
            $select->where('parent_brand_id = ?', $brand['id']);
        });

        $groups = [];
        foreach ($rows as $subBrand) {
            $groups[] = [
                'url'  => $this->url('catalogue', [
                    'action'        => 'brand',
                    'brand_catname' => $subBrand['catname']
                ]),
                'name' => $subBrand['name'],
            ];
        }

        return $groups;
    }

    private function carSectionGroupsSelect($brandId, $carTypeId, $nullType, $conceptsSeparatly)
    {
        $brandCarTable = new DbTable\BrandCar();
        $db = $brandCarTable->getAdapter();

        $select = $db->select()
            ->from($brandCarTable->info('name'), [
                'brand_car_catname' => 'catname',
                'brand_id'
            ])
            ->join('cars', 'cars.id = brands_cars.car_id', [
                'car_id'   => 'id',
                'car_name' => 'cars.name',
            ])
            ->where('brands_cars.brand_id = ?', $brandId)
            ->group('cars.id');
        if ($conceptsSeparatly) {
            $select->where('NOT cars.is_concept');
        }

        if ($carTypeId) {
            $select
                ->join('vehicle_vehicle_type', 'cars.id = vehicle_vehicle_type.vehicle_id', null)
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $carTypeId);
        } else {
            if ($nullType) {
                $select
                    ->joinLeft(
                        'vehicle_vehicle_type',
                        'cars.id = vehicle_vehicle_type.vehicle_id',
                        null
                    )
                    ->where('vehicle_vehicle_type.vehicle_id is null');
            } else {
                $otherTypesIds = $db->fetchCol(
                    $db->select()
                        ->from('car_types_parents', 'id')
                        ->where('parent_id IN (?)', [43, 44, 17, 19])
                );

                $select->join(
                    'vehicle_vehicle_type',
                    'cars.id = vehicle_vehicle_type.vehicle_id',
                    null
                );

                if ($otherTypesIds) {
                    $select->where('vehicle_vehicle_type.vehicle_type_id not in (?)', $otherTypesIds);
                }
            }
        }

        return $select;
    }

    private function carSectionGroups($language, array $brand, array $section, $conceptsSeparatly, $carId)
    {
        $brandCarTable = new DbTable\BrandCar();
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();
        $db = $brandCarTable->getAdapter();

        $rows = [];
        if ($section['car_type_id']) {
            $select = $this->carSectionGroupsSelect($brand['id'], $section['car_type_id'], null, $conceptsSeparatly);
            $rows = $db->fetchAll($select);
        } else {
            $rows = [];
            $select = $this->carSectionGroupsSelect($brand['id'], null, false, $conceptsSeparatly);
            foreach ($db->fetchAll($select) as $row) {
                $rows[$row['car_id']] = $row;
            }
            $select = $this->carSectionGroupsSelect($brand['id'], null, true, $conceptsSeparatly);
            foreach ($db->fetchAll($select) as $row) {
                $rows[$row['car_id']] = $row;
            }
        }

        $aliases = $this->getBrandAliases($brand);

        $carLanguageTable = new DbTable\Vehicle\Language();

        $groups = [];
        foreach ($rows as $brandCarRow) {
            $url = $this->url('catalogue', [
                'action'        => 'brand-car',
                'brand_catname' => $brand['catname'],
                'car_catname'   => $brandCarRow['brand_car_catname']
            ]);

            $bvlRow = $brandVehicleLangaugeTable->fetchRow([
                'vehicle_id = ?' => $brandCarRow['car_id'],
                'brand_id = ?'   => $brandCarRow['brand_id'],
                'language = ?'   => $language
            ]);

            if ($bvlRow) {
                $name = $bvlRow->name;
            } else {
                $carLangRow = $carLanguageTable->fetchRow([
                    'car_id = ?'   => (int)$brandCarRow['car_id'],
                    'language = ?' => (string)$language
                ]);

                $name = $carLangRow ? $carLangRow->name : $brandCarRow['car_name'];
                foreach ($aliases as $alias) {
                    $name = str_ireplace('by The ' . $alias . ' Company', '', $name);
                    $name = str_ireplace('by '.$alias, '', $name);
                    $name = str_ireplace('di '.$alias, '', $name);
                    $name = str_ireplace('par '.$alias, '', $name);
                    $name = str_ireplace($alias.'-', '', $name);
                    $name = str_ireplace('-'.$alias, '', $name);

                    $name = preg_replace('/\b'.preg_quote($alias, '/').'\b/iu', '', $name);
                }

                $name = trim(preg_replace("|[[:space:]]+|", ' ', $name));
                $name = ltrim($name, '/');
                if (! $name) {
                    $name = $carLangRow ? $carLangRow->name : $brandCarRow['car_name'];
                }
            }

            $groups[] = [
                'car_id' => $brandCarRow['car_id'],
                'url'    => $url,
                'name'   => $name,
            ];
        }

        return $groups;
    }

    private function carSections($language, array $brand, $conceptsSeparatly, $carId)
    {
        $cacheKey = implode('_', [
            'SIDEBAR',
            $brand['id'],
            $language,
            '30'
        ]);

        $sections = $this->cache->getItem($cacheKey, $success);

        if (! $success) {
            $sectionsPresets = [
                'other' => [
                    'name'        => null,
                    'car_type_id' => null
                ],
                'moto' => [
                    'name'        => 'catalogue/section/moto',
                    'car_type_id' => 43
                ],
                'bus' => [
                    'name' => 'catalogue/section/buses',
                    'car_type_id' => 19
                ],
                'truck' => [
                    'name' => 'catalogue/section/trucks',
                    'car_type_id' => 17
                ],
                'tractor' => [
                    'name'        => 'catalogue/section/tractors',
                    'car_type_id' => 44
                ]
            ];

            $sections = [];
            foreach ($sectionsPresets as $sectionsPreset) {
                $sectionGroups = $this->carSectionGroups(
                    $language,
                    $brand,
                    $sectionsPreset,
                    $conceptsSeparatly,
                    $carId
                );

                usort($sectionGroups, function ($a, $b) {
                    return strnatcasecmp($a['name'], $b['name']);
                });

                $sections[] = [
                    'name'   => $sectionsPreset['name'],
                    'groups' => $sectionGroups
                ];
            }

            $this->cache->setItem($cacheKey, $sections);
        }

        $carTable = new DbTable\Vehicle();

        $selectedIds = [];
        if ($carId) {
            $db = $carTable->getAdapter();
            $selectedIds = $db->fetchCol(
                $db->select()
                    ->distinct()
                    ->from('car_parent_cache', 'parent_id')
                    ->where('car_id = ?', $carId)
            );
        }

        foreach ($sections as &$section) {
            foreach ($section['groups'] as &$group) {
                $group['active'] = in_array($group['car_id'], $selectedIds);
                unset($group['car_id']);
            }
        }

        return $sections;
    }
}
