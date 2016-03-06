<?php

use Application\Model\Brand;

class SidebarController extends Zend_Controller_Action
{
    private function getBrandAliases(array $brand)
    {
        $aliases = [$brand['name']];

        $brandAliasTable = new Brand_Alias();
        $brandAliasRows = $brandAliasTable->fetchAll(array(
            'brand_id = ?' => $brand['id']
        ));
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $brandLangTable = new Brand_Language();
        $brandLangRows = $brandLangTable->fetchAll(array(
            'brand_id = ?' => $brand['id']
        ));
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
        $brandModel = new Brand();
        
        $language = $this->_helper->language();
        
        $rows = $brandModel->getList($language, function($select) use ($brand) {
            $select->where('parent_brand_id = ?', $brand['id']);
        });

        $groups = array();
        foreach ($rows as $subBrand) {
            $groups[] = array(
                'url'     => $this->_helper->url->url(array(
                    'action'        => 'brand',
                    'brand_catname' => $subBrand['catname']
                ), 'catalogue', true),
                'caption' => $subBrand['name'],
            );
        }

        return $groups;
    }

    private function carGroups(array $brand, $conceptsSeparatly, $carId)
    {
        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $language = $this->_helper->language();

        $cacheKey = 'SIDEBAR_' . $brand['id'] . '_' . $language . '_1';

        if (!($groups = $cache->load($cacheKey))) {

            $brandCarTable = new Brand_Car();
            $db = $brandCarTable->getAdapter();

            $select = $db->select()
                ->from($brandCarTable->info('name'), array(
                    'brand_car_catname' => 'catname'
                ))
                ->join('cars', 'cars.id = brands_cars.car_id', array(
                    'car_id'   => 'id',
                    'car_name' => 'cars.caption'
                ))
                ->where('brands_cars.brand_id = ?', $brand['id']);
            if ($conceptsSeparatly) {
                $select->where('NOT cars.is_concept');
            }

            $aliases = $this->getBrandAliases($brand);

            $carLanguageTable = new Car_Language();

            $groups = array();
            foreach ($db->fetchAll($select) as $brandCarRow) {

                if ($brandCarRow['brand_car_catname']) {
                    $url = $this->_helper->url->url(array(
                        'action'        => 'brand-car',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarRow['brand_car_catname']
                    ), 'catalogue', true);
                } else {
                    $url = $this->_helper->url->url(array(
                        'action'        => 'car',
                        'brand_catname' => $brand['catname'],
                        'car_id'        => $brandCarRow['car_id']
                    ), 'catalogue', true);
                }

                $carLangRow = $carLanguageTable->fetchRow(array(
                    'car_id = ?'   => (int)$brandCarRow['car_id'],
                    'language = ?' => (string)$language
                ));

                $caption = $carLangRow ? $carLangRow->name : $brandCarRow['car_name'];
                foreach ($aliases as $alias) {
                    $caption = str_ireplace('by The ' . $alias . ' Company', '', $caption);
                    $caption = str_ireplace('by '.$alias, '', $caption);
                    $caption = str_ireplace('par '.$alias, '', $caption);
                    $caption = str_ireplace($alias.'-', '', $caption);
                    $caption = str_ireplace('-'.$alias, '', $caption);

                    $caption = preg_replace('/\b'.preg_quote($alias, '/').'\b/iu', '', $caption);
                }

                $caption = trim(preg_replace("|[[:space:]]+|", ' ', $caption));
                $caption = ltrim($caption, '/');
                if (!$caption) {
                    $caption = $brandCarRow['car_name'];
                }
                $groups[] = array(
                    'car_id'  => $brandCarRow['car_id'],
                    'url'     => $url,
                    'caption' => $caption,
                );
            }

            $cache->save($groups, $cacheKey, array(), 300);
        }

        $carTable = $this->_helper->catalogue()->getCarTable();

        $selectedIds = array();
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
        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $language = $this->_helper->language();

        $cacheKey = 'SIDEBAR_OTHER_' . $brand['id'] . '_' . $language . '_' . ($conceptsSeparatly ? '1' : '0');

        if (!($groups = $cache->load($cacheKey))) {
            $groups = array();

            if ($conceptsSeparatly) {
                // ссылка на страницу с концептами
                $carTable = $this->_helper->catalogue()->getCarTable();

                $db = $carTable->getAdapter();
                $select = $db->select()
                    ->from('cars', array(new Zend_Db_Expr('1')))
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brand['id'])
                    ->where('cars.is_concept')
                    ->limit(1);
                if ($db->fetchOne($select) > 0) {
                    $groups['concepts'] = array(
                        'url' => $this->_helper->url->url(array(
                            'action'        => 'concepts',
                            'brand_catname' => $brand['catname']
                        ), 'catalogue', true),
                        'caption' => $this->view->translate('concepts and prototypes'),
                    );
                }
            }

            // ссылка на страницу с двигателями
            $engineTable = $this->_helper->catalogue()->getEngineTable();
            $db = $engineTable->getAdapter();
            $enginesCount = $db->fetchOne(
                $db->select()
                    ->from($engineTable->info('name'), new Zend_Db_Expr('count(1)'))
                    ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $brand['id'])
            );
            if ($enginesCount > 0)
                $groups['engines'] = array(
                    'url' => $this->_helper->url->url(array(
                        'action'        => 'engines',
                        'brand_catname' => $brand['catname']
                    ), 'catalogue', true),
                    'caption' => $this->view->translate('engines'),
                    'count'   => $enginesCount
                );


            $picturesTable = $this->_helper->catalogue()->getPictureTable();
            $picturesAdapter = $picturesTable->getAdapter();

            // ссылка на страницу с логотипами
            $logoPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                    ->where('type = ?', Picture::LOGO_TYPE_ID)
                    ->where('brand_id = ?', $brand['id'])
            );
            if ($logoPicturesCount > 0)
                $groups['logo'] = array(
                    'url' => $this->_helper->url->url(array(
                        'action'        => 'logotypes',
                        'brand_catname' => $brand['catname']
                    ), 'catalogue', true),
                    'caption' => $this->view->translate('logotypes'),
                    'count'   => $logoPicturesCount
                );

            // ссылка на страницу с разным
            $mixedPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                    ->where('type = ?', Picture::MIXED_TYPE_ID)
                    ->where('brand_id = ?', $brand['id'])
            );
            if ($mixedPicturesCount > 0)
                $groups['mixed'] = array(
                    'url' => $this->_helper->url->url(array(
                        'action' => 'mixed',
                        'brand_catname' => $brand['catname']
                    ), 'catalogue', true),
                    'caption' => $this->view->translate('mixed'),
                    'count'   => $mixedPicturesCount
                );

            // ссылка на страницу с несортированным
            $unsortedPicturesCount = $picturesAdapter->fetchOne(
                $select = $picturesAdapter->select()
                    ->from('pictures', new Zend_Db_Expr('count(*)'))
                    ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                    ->where('type = ?', Picture::UNSORTED_TYPE_ID)
                    ->where('brand_id = ?', $brand['id'])
            );

            if ($unsortedPicturesCount > 0) {
                $groups['unsorted'] = array(
                    'url'     => $this->_helper->url->url(array(
                        'action'        => 'other',
                        'brand_catname' => $brand['catname']
                    ), 'catalogue', true),
                    'caption' => $this->view->translate('unsorted'),
                    'count'   => $unsortedPicturesCount
                );
            }

            $cache->save($groups, $cacheKey, array(), 300);
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
        $conceptsSeparatly = !in_array($brand['type_id'], array(3, 4));

        // создаем массив групп
        $groups = array_merge(
            $this->carGroups($brand, $conceptsSeparatly, $carId),
            $this->subBrandGroups($brand)
        );

        // сортируем группы
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

    public function brandAction()
    {
        $language = $this->_helper->language();
        
        $brandModel = new Brand();
        $brand = $brandModel->getBrandById($this->getParam('brand_id'), $language);
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $carId = (int)$this->getParam('car_id');
        $type = $this->getParam('type');
        $type = strlen($type) ? (int)$type : null;
        $isConcepts = (bool)$this->getParam('is_concepts');
        $isEngines = (bool)$this->getParam('is_engines');

        $namespace = $this->getNamespace();
        $namespace->selected = $brand['id'];

        $this->view->groups = $this->brandGroups($brand, $type, $carId, $isConcepts, $isEngines);
        $this->_helper->viewRenderer->setResponseSegment('sidebar');
    }

    public function brandsAction()
    {
        $brandModel = new Brand();

        $namespace = $this->getNamespace();
        $selected = null;
        if (isset($namespace->selected)) {
            $selected = (int)$namespace->selected;
        }

        $ids = (array)$this->getParam('brand_id');

        $carId = (int)$this->getParam('car_id');
        $type = $this->getParam('type');
        $type = strlen($type) ? (int)$type : null;
        $isConcepts = (bool)$this->getParam('is_concepts');
        $isEngines = (bool)$this->getParam('is_engines');

        $result = array();

        if ($ids) {
            $language = $this->_helper->language();
            $brands = $brandModel->getList($language, function($select) use ($ids) {
                $select->where('id in (?)', $ids);
            });

            foreach ($brands as $brand) {
                $result[] = array(
                    'brand'  => $brand,
                    'groups' => $this->brandGroups($brand, $type, $carId, $isConcepts, $isEngines),
                    'active' => false
                );
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

        $this->view->brands = $result;

        $this->_helper->viewRenderer->setResponseSegment('sidebar');
    }
}