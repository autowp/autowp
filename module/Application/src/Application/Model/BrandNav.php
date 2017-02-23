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
            'item_id'     => null,
            'type'        => null,
            'is_concepts' => false,
            'language'    => 'en'
        ];
        $params = array_replace($defaults, $params);

        $brandModel = new BrandModel();
        $brand = $brandModel->getBrandById($params['brand_id'], $params['language']);
        if (! $brand) {
            return;
        }

        $carId = (int)$params['item_id'];
        $type = $params['type'];
        $type = strlen($type) ? (string)$type : null;
        $isConcepts = (bool)$params['is_concepts'];

        return $this->brandSections($params['language'], $brand, $type, $carId, $isConcepts);
    }

    private function brandSections($language, $brand, $type, $carId, $isConcepts)
    {
        $conceptsSeparatly = true;//! in_array($brand['type_id'], [3, 4]);

        // create groups array
        $sections = $this->carSections($language, $brand, $conceptsSeparatly, $carId);

        $sections = array_merge(
            $sections,
            [
                [
                    'name'   => null,
                    'groups' => $this->otherGroups(
                        $language,
                        $brand,
                        $conceptsSeparatly,
                        $type,
                        $isConcepts
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

    private function otherGroups($language, $brand, $conceptsSeparatly, $type, $isConcepts)
    {
        $cacheKey = implode('_', [
            'SIDEBAR_OTHER',
            $brand['id'],
            $language,
            $conceptsSeparatly ? '1' : '0',
            '9'
        ]);

        $picturesTable = new DbTable\Picture;
        $picturesAdapter = $picturesTable->getAdapter();

        $groups = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $groups = [];

            if ($conceptsSeparatly) {
                // concepts
                $itemTable = new DbTable\Item();

                $db = $itemTable->getAdapter();
                $select = $db->select()
                    ->from('item', new Zend_Db_Expr('1'))
                    ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $brand['id'])
                    ->where('item.is_concept')
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

            // logotypes
            $logoPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.perspective_id = ?', 22)
                    ->where('picture_item.item_id = ?', $brand['id'])
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
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.perspective_id = ?', 25)
                    ->where('picture_item.item_id = ?', $brand['id'])
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
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.perspective_id NOT IN (?) OR picture_item.perspective_id IS NULL', [22, 25])
                    ->where('picture_item.item_id = ?', $brand['id'])
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

        if (isset($groups['logo'])) {
            $groups['logo']['active'] = isset($type) && $type == 'logo';
        }

        if (isset($groups['mixed'])) {
            $groups['mixed']['active'] = isset($type) && $type == 'mixed';
        }

        if (isset($groups['unsorted'])) {
            $groups['unsorted']['active'] = isset($type) && $type == 'unsorted';
        }

        return array_values($groups);
    }

    private function getBrandAliases(array $brand)
    {
        $aliases = [$brand['name']];

        $brandAliasTable = new DbTable\Item\Alias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'item_id = ?' => $brand['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            if ($brandAliasRow->name) {
                $aliases[] = $brandAliasRow->name;
            }
        }

        $brandLangTable = new DbTable\Item\Language();
        $brandLangRows = $brandLangTable->fetchAll([
            'item_id = ?' => $brand['id']
        ]);
        foreach ($brandLangRows as $brandLangRow) {
            if ($brandLangRow->name) {
                $aliases[] = $brandLangRow->name;
            }
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

    private function carSectionGroupsSelect($brandId, $itemTypeId, $carTypeId, $nullType, $conceptsSeparatly)
    {
        $itemParentTable = new DbTable\Item\ParentTable();
        $db = $itemParentTable->getAdapter();

        $select = $db->select()
            ->from($itemParentTable->info('name'), [
                'brand_item_catname' => 'catname',
                'brand_id' => 'parent_id'
            ])
            ->join('item', 'item.id = item_parent.item_id', [
                'item_id'  => 'id',
                'car_name' => 'item.name',
            ])
            ->where('item_parent.parent_id = ?', $brandId)
            ->group('item.id');
        if ($conceptsSeparatly) {
            $select->where('NOT item.is_concept');
        }

        if ($itemTypeId == DbTable\Item\Type::VEHICLE) {
            $select->where('item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::BRAND]);
            if ($carTypeId) {
                $select
                    ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', null)
                    ->where('car_types_parents.parent_id = ?', $carTypeId);
            } else {
                if ($nullType) {
                    $select
                        ->joinLeft(
                            'vehicle_vehicle_type',
                            'item.id = vehicle_vehicle_type.vehicle_id',
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
                        'item.id = vehicle_vehicle_type.vehicle_id',
                        null
                    );

                    if ($otherTypesIds) {
                        $select->where('vehicle_vehicle_type.vehicle_type_id not in (?)', $otherTypesIds);
                    }
                }
            }
        } else {
            $select->where('item.item_type_id = ?', $itemTypeId);
        }

        return $select;
    }

    private function carSectionGroups($language, array $brand, array $section, $conceptsSeparatly, $carId)
    {
        $itemParentLangaugeTable = new DbTable\Item\ParentLanguage();
        $db = $itemParentLangaugeTable->getAdapter();

        $rows = [];
        if ($section['car_type_id']) {
            $select = $this->carSectionGroupsSelect(
                $brand['id'],
                $section['item_type_id'],
                $section['car_type_id'],
                null,
                $conceptsSeparatly
            );
            $rows = $db->fetchAll($select);
        } else {
            $rows = [];
            $select = $this->carSectionGroupsSelect(
                $brand['id'],
                $section['item_type_id'],
                null,
                false,
                $conceptsSeparatly
            );
            foreach ($db->fetchAll($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
            $select = $this->carSectionGroupsSelect(
                $brand['id'],
                $section['item_type_id'],
                null,
                true,
                $conceptsSeparatly
            );
            foreach ($db->fetchAll($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
        }

        $aliases = $this->getBrandAliases($brand);

        $carLanguageTable = new DbTable\Item\Language();

        $langSortExpr = new Zend_Db_Expr(
            $db->quoteInto('language = ? desc', $language)
        );

        $groups = [];
        foreach ($rows as $brandItemRow) {
            $url = $this->url('catalogue', [
                'action'        => 'brand-item',
                'brand_catname' => $brand['catname'],
                'car_catname'   => $brandItemRow['brand_item_catname']
            ]);

            $bvlRow = $itemParentLangaugeTable->fetchRow([
                'item_id = ?'   => $brandItemRow['item_id'],
                'parent_id = ?' => $brandItemRow['brand_id'],
                'length(name) > 0'
            ], $langSortExpr);

            if ($bvlRow) {
                $name = $bvlRow->name;
            } else {
                $carLangRow = $carLanguageTable->fetchRow([
                    'item_id = ?'  => (int)$brandItemRow['item_id'],
                    'language = ?' => (string)$language,
                    'length(name) > 0'
                ]);

                $name = $carLangRow ? $carLangRow->name : $brandItemRow['car_name'];
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
                    $name = $carLangRow ? $carLangRow->name : $brandItemRow['car_name'];
                }
            }

            $groups[] = [
                'item_id' => $brandItemRow['item_id'],
                'url'     => $url,
                'name'    => $name,
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
            '40'
        ]);

        $sections = $this->cache->getItem($cacheKey, $success);

        if (! $success) {
            $sectionsPresets = [
                'other' => [
                    'name'         => null,
                    'car_type_id'  => null,
                    'item_type_id' => DbTable\Item\Type::VEHICLE
                ],
                'moto' => [
                    'name'        => 'catalogue/section/moto',
                    'car_type_id' => 43,
                    'item_type_id' => DbTable\Item\Type::VEHICLE
                ],
                'bus' => [
                    'name' => 'catalogue/section/buses',
                    'car_type_id' => 19,
                    'item_type_id' => DbTable\Item\Type::VEHICLE
                ],
                'truck' => [
                    'name' => 'catalogue/section/trucks',
                    'car_type_id' => 17,
                    'item_type_id' => DbTable\Item\Type::VEHICLE
                ],
                'tractor' => [
                    'name'        => 'catalogue/section/tractors',
                    'car_type_id' => 44,
                    'item_type_id' => DbTable\Item\Type::VEHICLE
                ],
                'engine' => [
                    'name'        => 'catalogue/section/engines',
                    'car_type_id' => null,
                    'item_type_id' => DbTable\Item\Type::ENGINE,
                    'url'          => $this->router->assemble([
                        'brand_catname' => $brand['catname'],
                        'action'        => 'engines'
                    ], [
                        'name' => 'catalogue'
                    ])
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
                    'url'    => isset($sectionsPreset['url']) ? $sectionsPreset['url'] : null,
                    'groups' => $sectionGroups
                ];
            }

            $this->cache->setItem($cacheKey, $sections);
        }

        $itemTable = new DbTable\Item();

        $selectedIds = [];
        if ($carId) {
            $db = $itemTable->getAdapter();
            $selectedIds = $db->fetchCol(
                $db->select()
                    ->distinct()
                    ->from('item_parent_cache', 'parent_id')
                    ->where('item_id = ?', $carId)
            );
        }

        foreach ($sections as &$section) {
            foreach ($section['groups'] as &$group) {
                $group['active'] = in_array($group['item_id'], $selectedIds);
                unset($group['item_id']);
            }
        }

        return $sections;
    }
}
