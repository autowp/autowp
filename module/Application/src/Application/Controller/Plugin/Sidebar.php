<?php

namespace Application\Controller\Plugin;

use Zend\Cache\Storage\StorageInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\View\Model\ViewModel;

use Application\Model\Brand as BrandModel;

use Brand_Alias;
use Brand_Car;
use Brand_Language;
use Car_Language;
use Picture;

use Zend_Db_Expr;
use Zend_Session_Namespace;

class Sidebar extends AbstractPlugin
{
    /**
     * @var StorageInterface
     */
    private $cache;

    private $translator;

    public function __construct(StorageInterface $cache, $translator)
    {
        $this->cache = $cache;
        $this->translator = $translator;
    }

    private function getBrandAliases(array $brand)
    {
        $aliases = [$brand['name']];

        $brandAliasTable = new Brand_Alias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'brand_id = ?' => $brand['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $brandLangTable = new Brand_Language();
        $brandLangRows = $brandLangTable->fetchAll([
            'brand_id = ?' => $brand['id']
        ]);
        foreach ($brandLangRows as $brandLangRow) {
            $aliases[] = $brandLangRow->name;
        }

        usort($aliases, function($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la == $lb) {
                return 0;
            }
            return ($la > $lb) ? -1 : 1;
        });

        return $aliases;
    }

    private function subBrandGroups(array $brand)
    {
        $brandModel = new BrandModel();

        $language = $this->getController()->language();

        $rows = $brandModel->getList($language, function($select) use ($brand) {
            $select->where('parent_brand_id = ?', $brand['id']);
        });

        $groups = [];
        foreach ($rows as $subBrand) {
            $groups[] = [
                'url'     => $this->getController()->url()->fromRoute('catalogue', [
                    'action'        => 'brand',
                    'brand_catname' => $subBrand['catname']
                ]),
                'caption' => $subBrand['name'],
            ];
        }

        return $groups;
    }

    private function carGroups(array $brand, $conceptsSeparatly, $carId)
    {
        $language = $this->getController()->language();

        $cacheKey = 'SIDEBAR_' . $brand['id'] . '_' . $language . '_5';

        $groups = $this->cache->getItem($cacheKey, $success);

        if (!$success) {

            $brandCarTable = new Brand_Car();
            $db = $brandCarTable->getAdapter();

            $select = $db->select()
                ->from($brandCarTable->info('name'), [
                    'brand_car_catname' => 'catname'
                ])
                ->join('cars', 'cars.id = brands_cars.car_id', [
                    'car_id'   => 'id',
                    'car_name' => 'cars.caption'
                ])
                ->where('brands_cars.brand_id = ?', $brand['id']);
            if ($conceptsSeparatly) {
                $select->where('NOT cars.is_concept');
            }

            $aliases = $this->getBrandAliases($brand);

            $carLanguageTable = new Car_Language();

            $groups = [];
            foreach ($db->fetchAll($select) as $brandCarRow) {

                if ($brandCarRow['brand_car_catname']) {
                    $url = $this->getController()->url()->fromRoute('catalogue', [
                        'action'        => 'brand-car',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarRow['brand_car_catname']
                    ]);
                } else {
                    $url = $this->getController()->url()->fromRoute('catalogue', [
                        'action'        => 'car',
                        'brand_catname' => $brand['catname'],
                        'car_id'        => $brandCarRow['car_id']
                    ]);
                }

                $carLangRow = $carLanguageTable->fetchRow([
                    'car_id = ?'   => (int)$brandCarRow['car_id'],
                    'language = ?' => (string)$language
                ]);

                $caption = $carLangRow ? $carLangRow->name : $brandCarRow['car_name'];
                foreach ($aliases as $alias) {
                    $caption = str_ireplace('by The ' . $alias . ' Company', '', $caption);
                    $caption = str_ireplace('by '.$alias, '', $caption);
                    $caption = str_ireplace('di '.$alias, '', $caption);
                    $caption = str_ireplace('par '.$alias, '', $caption);
                    $caption = str_ireplace($alias.'-', '', $caption);
                    $caption = str_ireplace('-'.$alias, '', $caption);

                    $caption = preg_replace('/\b'.preg_quote($alias, '/').'\b/iu', '', $caption);
                }

                $caption = trim(preg_replace("|[[:space:]]+|", ' ', $caption));
                $caption = ltrim($caption, '/');
                if (!$caption) {
                    $caption = $carLangRow ? $carLangRow->name : $brandCarRow['car_name'];
                }
                $groups[] = [
                    'car_id'  => $brandCarRow['car_id'],
                    'url'     => $url,
                    'caption' => $caption,
                ];
            }

            $this->cache->setItem($cacheKey, $groups);
        }

        $carTable = $this->getController()->catalogue()->getCarTable();

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

        foreach ($groups as &$group) {
            $group['active'] = in_array($group['car_id'], $selectedIds);
            unset($group['car_id']);
        }

        return $groups;
    }

    private function otherGroups($brand, $conceptsSeparatly, $type, $isConcepts, $isEngines)
    {
        $language = $this->getController()->language();

        $cacheKey = 'SIDEBAR_OTHER_' . $brand['id'] . '_' . $language . '_1_' . ($conceptsSeparatly ? '1' : '0');

        $groups = $this->cache->getItem($cacheKey, $success);
        if (!$success) {
            $groups = [];

            if ($conceptsSeparatly) {
                // concepts
                $carTable = $this->getController()->catalogue()->getCarTable();

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
                        'url' => $this->getController()->url()->fromRoute('catalogue', [
                            'action'        => 'concepts',
                            'brand_catname' => $brand['catname']
                        ]),
                        'caption' => $this->translator->translate('concepts and prototypes'),
                    ];
                }
            }

            // engines
            $engineTable = $this->getController()->catalogue()->getEngineTable();
            $db = $engineTable->getAdapter();
            $enginesCount = $db->fetchOne(
                $db->select()
                    ->from($engineTable->info('name'), new Zend_Db_Expr('count(1)'))
                    ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $brand['id'])
            );
            if ($enginesCount > 0) {
                $groups['engines'] = [
                    'url' => $this->getController()->url()->fromRoute('catalogue', [
                        'action'        => 'engines',
                        'brand_catname' => $brand['catname']
                    ]),
                    'caption' => $this->translator->translate('engines'),
                    'count'   => $enginesCount
                ];
            }

            $picturesTable = $this->getController()->catalogue()->getPictureTable();
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
                    'url' => $this->getController()->url()->fromRoute('catalogue', [
                        'action'        => 'logotypes',
                        'brand_catname' => $brand['catname']
                    ]),
                    'caption' => $this->translator->translate('logotypes'),
                    'count'   => $logoPicturesCount
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
                    'url' => $this->getController()->url()->fromRoute('catalogue', [
                        'action' => 'mixed',
                        'brand_catname' => $brand['catname']
                    ]),
                    'caption' => $this->translator->translate('mixed'),
                    'count'   => $mixedPicturesCount
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
                    'url'     => $this->getController()->url()->fromRoute('catalogue', [
                        'action'        => 'other',
                        'brand_catname' => $brand['catname']
                    ]),
                    'caption' => $this->translator->translate('unsorted'),
                    'count'   => $unsortedPicturesCount
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

    private function getNamespace()
    {
        return new Zend_Session_Namespace(__CLASS__);
    }

    private function brandGroups($brand, $type, $carId, $isConcepts, $isEngines)
    {
        $conceptsSeparatly = !in_array($brand['type_id'], [3, 4]);

        // create groups array
        $groups = array_merge(
            $this->carGroups($brand, $conceptsSeparatly, $carId),
            $this->subBrandGroups($brand)
        );

        // create groups
        /*$coll = new Collator($this->_helper->language());
         usort($groups, function($a, $b) use($coll) {
         return $coll->compare($a['caption'], $b['caption']);
         });*/

        usort($groups, function($a, $b) {
            return strnatcasecmp($a['caption'], $b['caption']);
        });

        $groups = array_merge(
            $groups,
            $this->otherGroups($brand, $conceptsSeparatly, $type, $isConcepts, $isEngines)
        );

        return $groups;
    }

    public function brand(array $params)
    {
        $defaults = [
            'brand_id'    => null,
            'car_id'      => null,
            'type'        => null,
            'is_concepts' => false,
            'is_engines'  => false
        ];
        $params = array_replace($defaults, $params);

        $language = $this->getController()->language();

        $brandModel = new BrandModel();
        $brand = $brandModel->getBrandById($params['brand_id'], $language);
        if (!$brand) {
            return;
        }

        $carId = (int)$params['car_id'];
        $type = $params['type'];
        $type = strlen($type) ? (int)$type : null;
        $isConcepts = (bool)$params['is_concepts'];
        $isEngines = (bool)$params['is_engines'];

        $namespace = $this->getNamespace();
        $namespace->selected = $brand['id'];

        $sideBarModel = new ViewModel([
            'groups' => $this->brandGroups($brand, $type, $carId, $isConcepts, $isEngines)
        ]);
        $sideBarModel->setTemplate('application/sidebar/brand');
        $this->getController()->layout()->addChild($sideBarModel, 'sidebar');
    }

    public function brands(array $params)
    {
        $defaults = [
            'brand_id'    => null,
            'car_id'      => null,
            'type'        => null,
            'is_concepts' => false,
            'is_engines'  => false
        ];
        $params = array_replace($defaults, $params);

        $brandModel = new BrandModel();

        $namespace = $this->getNamespace();
        $selected = null;
        if (isset($namespace->selected)) {
            $selected = (int)$namespace->selected;
        }

        $ids = (array)$params['brand_id'];

        $carId = (int)$params['car_id'];
        $type = $params['type'];
        $type = strlen($type) ? (int)$type : null;
        $isConcepts = (bool)$params['is_concepts'];
        $isEngines = (bool)$params['is_engines'];

        $result = [];

        if ($ids) {
            $language = $this->getController()->language();
            $brands = $brandModel->getList($language, function($select) use ($ids) {
                $select->where('id in (?)', $ids);
            });

            foreach ($brands as $brand) {
                $result[] = [
                    'brand'  => $brand,
                    'groups' => $this->brandGroups($brand, $type, $carId, $isConcepts, $isEngines),
                    'active' => false
                ];
            }
        }

        $found = false;
        foreach ($result as &$brand) {
            if ($brand['brand']['id'] == $selected) {
                $brand['active'] = true;
                $found = true;
                break;
            }
        }

        if (!$found && $result) {
            $result[0]['active'] = true;
        }

        $sideBarModel = new ViewModel([
            'brands' => $result
        ]);
        $sideBarModel->setTemplate('application/sidebar/brands');
        $this->getController()->layout()->addChild($sideBarModel, 'sidebar');
    }
}
