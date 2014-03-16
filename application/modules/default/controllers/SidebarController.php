<?php
class SidebarController extends Zend_Controller_Action
{
    protected function _getBrandAliases(Brands_Row $brand)
    {
        $aliases = array($brand->caption);

        $brandAliasTable = new Brand_Alias();
        $brandAliasRows = $brandAliasTable->fetchAll(array(
            'brand_id = ?' => $brand->id
        ));
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
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

    protected function _designProjectBrandGroups($brand)
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $select = $brandTable->select(true)
            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.car_id', null)
            ->join('cars', 'car_parent_cache.parent_id = cars.id', null)
            ->join('design_projects', 'cars.design_project_id = design_projects.id', null)
            ->where('design_projects.brand_id = ?', $brand->id)
            ->where('brands.id <> ?', $brand->id)
            ->where('brands.type_id IN (?)', array(1, 3))
            ->group('brands.id');

        $groups = array();
        foreach ($brandTable->fetchAll($select) as $ibrand) {
            $groups[] = array(
                'url'     => $this->_helper->url->url(array(
                    'action'          => 'design-project-brand',
                    'brand_catname'   => $brand->folder,
                    'dpbrand_catname' => $ibrand->folder
                ), 'catalogue', true),
                'caption' => $ibrand->caption,
            );
        }

        return $groups;
    }

    protected function _subBrandGroups($brand)
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $rows = $brandTable->fetchAll(array(
            'parent_brand_id = ?' => $brand->id
        ));

        $groups = array();
        foreach ($rows as $subBrand) {
            $groups[] = array(
                'url'     => $this->_helper->url->url(array(
                    'action'        => 'brand',
                    'brand_catname' => $subBrand->folder
                ), 'catalogue', true),
                'caption' => $subBrand->caption,
            );
        }

        return $groups;
    }

    protected function _carGroups($brand, $conceptsSeparatly)
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $brandCarTable = new Brand_Car();

        $select = $brandCarTable->select(true)
            ->join('cars', 'cars.id = brands_cars.car_id', null)
            ->where('brands_cars.brand_id = ?', $brand->id);
        if ($conceptsSeparatly) {
            $select->where('NOT cars.is_concept');
        }

        $brandCarRows = $brandCarTable->fetchAll($select);

        $aliases = $this->_getBrandAliases($brand);

        foreach ($brandCarRows as $brandCarRow) {

            $car = $carTable->find($brandCarRow->car_id)->current();
            if (!$car) {
                continue;
            }

            if ($brandCarRow->catname) {
                $url = $this->_helper->url->url(array(
                    'action'        => 'brand-car',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname
                ), 'catalogue', true);
            } else {
                $url = $this->_helper->url->url(array(
                    'action'        => 'car',
                    'brand_catname' => $brand->folder,
                    'car_id'        => $car->id
                ), 'catalogue', true);
            }

            $caption = $car->caption;
            foreach ($aliases as $alias) {
                $caption = str_ireplace('by '.$alias, '', $caption);
                $caption = str_ireplace($alias.'-', '', $caption);
                $caption = str_ireplace('-'.$alias, '', $caption);

                $caption = preg_replace('/\b'.preg_quote($alias, '/').'\b/u', '', $caption);
            }

            $caption = trim(preg_replace("|[[:space:]]+|", ' ', $caption));
            $caption = ltrim($caption, '/');
            if (!$caption) {
                $caption = $car->caption;
            }
            $groups[] = array(
                'url'     => $url,
                'caption' => $caption,
            );
        }

        return $groups;
    }

    public function _otherGroups($brand, $conceptsSeparatly)
    {
        $groups = array();

        if ($conceptsSeparatly) {
            // ссылка на страницу с концептами
            $carTable = $this->_helper->catalogue()->getCarTable();

            $db = $carTable->getAdapter();
            $select = $db->select()
                ->from('cars', array(new Zend_Db_Expr('1')))
                ->join('brands_cars_cache', 'cars.id=brands_cars_cache.car_id', null)
                ->where('brands_cars_cache.brand_id = ?', $brand->id)
                ->where('cars.is_concept')
                ->limit(1);
            if ($db->fetchOne($select) > 0) {
                $groups[] = array(
                    'url' => $this->_helper->url->url(array(
                        'action'        => 'concepts',
                        'brand_catname' => $brand->folder
                    ), 'catalogue', true),
                    'caption' => $this->view->translate('concepts and prototypes')
                );
            }
        }

        // ссылка на страницу с двигателями
        if ($brand->enginepictures_count > 0)
            $groups[] = array(
                'url' => $this->_helper->url->url(array(
                    'action'        => 'engines',
                    'brand_catname' => $brand->folder
                ), 'catalogue', true),
                'caption' => $this->view->translate('engines')
            );


        $picturesTable = $this->_helper->catalogue()->getPictureTable();
        $picturesAdapter = $picturesTable->getAdapter();

        // ссылка на страницу с логотипами
        $logoPicturesCount = $picturesAdapter->fetchOne(
            $select = $picturesAdapter->select()
                ->from('pictures', new Zend_Db_Expr('count(*)'))
                ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                ->where('type = ?', Picture::LOGO_TYPE_ID)
                ->where('brand_id = ?', $brand->id)
                ->limit(1)
        );
        if ($logoPicturesCount > 0)
            $groups[] = array(
                'url' => $this->_helper->url->url(array(
                    'action'        => 'logotypes',
                    'brand_catname' => $brand->folder
                ), 'catalogue', true),
                'caption' => $this->view->translate('logotypes'),
                'count'   => $logoPicturesCount,
            );

        // ссылка на страницу с разным
        $mixedPicturesCount = $picturesAdapter->fetchOne(
            $select = $picturesAdapter->select()
                ->from('pictures', new Zend_Db_Expr('count(*)'))
                ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                ->where('type = ?', Picture::MIXED_TYPE_ID)
                ->where('brand_id = ?', $brand->id)
                ->limit(1)
        );
        if ($mixedPicturesCount > 0)
            $groups[] = array(
                'url' => $this->_helper->url->url(array(
                    'action' => 'mixed',
                    'brand_catname' => $brand->folder
                ), 'catalogue', true),
                'caption' => $this->view->translate('mixed'),
                'count'   => $mixedPicturesCount,
            );

        // ссылка на страницу с несортированным
        $unsortedPicturesCount = $picturesAdapter->fetchOne(
            $select = $picturesAdapter->select()
                ->from('pictures', new Zend_Db_Expr('count(*)'))
                ->where('status in (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                ->where('type = ?', Picture::UNSORTED_TYPE_ID)
                ->where('brand_id = ?', $brand->id)
                ->limit(1)
        );
        if ($unsortedPicturesCount > 0) {
            $groups[] = array(
                'url' => $this->_helper->url->url(array(
                    'action'        => 'unsorted',
                    'brand_catname' => $brand->folder
                ), 'catalogue', true),
                'caption' => $this->view->translate('unsorted'),
                'count'   => $unsortedPicturesCount,
            );
        }

        return $groups;
    }

    protected function _getNamespace()
    {
        return new Zend_Session_Namespace(__CLASS__);
    }

    protected function _brandGroups($brand)
    {
        $conceptsSeparatly = !in_array($brand->type_id, array(3, 4));

        // создаем массив групп
        $groups = array_merge(
            $this->_carGroups($brand, $conceptsSeparatly),
            $this->_subBrandGroups($brand),
            $this->_designProjectBrandGroups($brand)
        );

        // сортируем группы
        $coll = new Collator($this->_helper->language());
        usort($groups, function($a, $b) use($coll) {
            return $coll->compare($a['caption'], $b['caption']);
        });

        $groups = array_merge(
            $groups,
            $this->_otherGroups($brand, $conceptsSeparatly)
        );

        return $groups;
    }

    public function brandAction()
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();
        $brand = $brandTable->find($this->_getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $namespace = $this->_getNamespace();
        $namespace->selected = $brand->id;

        $this->view->groups = $this->_brandGroups($brand);
        $this->_helper->viewRenderer->setResponseSegment('sidebar');
    }

    public function brandsAction()
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $namespace = $this->_getNamespace();
        $selected = null;
        if (isset($namespace->selected)) {
            $selected = (int)$namespace->selected;
        }

        $ids = (array)$this->_getParam('brand_id');

        $result = array();

        if ($ids) {
            $brands = $brandTable->fetchAll(array(
                'id in (?)' => $ids
            ), 'caption');

            foreach ($brands as $brand) {
                $result[] = array(
                    'brand'  => $brand,
                    'groups' => $this->_brandGroups($brand),
                    'active' => false
                );
            }
        }

        $found = false;
        foreach ($result as &$brand) {
            if ($brand['brand']->id == $selected) {
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