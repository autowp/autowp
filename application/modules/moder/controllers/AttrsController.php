<?php

class Moder_AttrsController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->isAllowed('attrs', 'edit')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $itemTypes = new Attrs_Item_Types();
        $attributes = new Attrs_Attributes();

        $this->view->assign(array(
            'itemTypes'     => $itemTypes->fetchAll(),
            'attributes'    => $attributes->fetchAll(
                $attributes->select()->where('parent_id IS NULL')->order('position')
            )
        ));
    }

    private function attributeUrl($attribute)
    {
        return $this->_helper->url->url(array(
            'module'        => 'moder',
            'controller'    => 'attrs',
            'action'        => 'attribute',
            'attribute_id'  => $attribute->id
        ), 'default', true);
    }

    public function attributeAction()
    {
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->getParam('attribute_id'))->current();
        if (!$attribute)
            return $this->_forward('notfound', 'error');

        $formAttributeEdit = new Application_Form_Moder_Attrs_Attribute(array(
            'elementsBelongTo' => 'edit',
            'action'           => $this->_helper->url->url(array(
                'form' => 'edit'
            ))
        ));
        $formAttributeEdit->populate($attribute->toArray());

        $formAttributeNew = new Application_Form_Moder_Attrs_Attribute(array(
            'elementsBelongTo' => 'new',
            'action'           => $this->_helper->url->url(array(
                'form' => 'new'
            ))
        ));

        $formListOption = new Application_Form_Moder_Attrs_List_Option(array(
            'elementsBelongTo' => 'option',
            'attribute'        => $attribute,
            'action'           => $this->_helper->url->url(array(
                'form' => 'option'
            )),
        ));

        $options = new Attrs_List_Options();

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch ($this->getParam('form')) {
                case 'new':
                    if ($formAttributeNew->isValid($request->getPost('new'))) {
                        $values = $formAttributeNew->getValues();
                        $values = $values['new'];

                        $position = $attributes->getAdapter()->fetchOne(
                            $attributes->getAdapter()->select()
                                ->from($attributes->info('name'), 'max(position)')
                                ->where('parent_id = ?', $attribute->id)
                        ) + 1;

                        $new = $attributes->createRow(array(
                            'name'        => $values['name'],
                            'description' => $values['description'],
                            'type_id'     => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'     => $values['unit_id'] ? $values['unit_id'] : null,
                            'parent_id'   => $attribute->id,
                            'precision'   => $values['precision'] ? $values['precision'] : null,
                            'position'    => $position
                        ));
                        $new->save();

                        return $this->_redirect($this->attributeUrl($attribute));
                    }
                    break;

                case 'edit':
                    if ($formAttributeEdit->isValid($request->getPost('edit'))) {
                        $values = $formAttributeEdit->getValues();
                        $values = $values['edit'];

                        $attribute->setFromArray(array(
                            'name'          => $values['name'],
                            'description'   => $values['description'],
                            'type_id'       => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'       => $values['unit_id'] ? $values['unit_id'] : null,
                            'precision'     => $values['precision'] ? $values['precision'] : null
                        ));
                        $attribute->save();

                        return $this->_redirect($this->attributeUrl($attribute));
                    }
                    break;

                case 'option':
                    if ($formListOption->isValid($request->getPost('option'))) {
                        $values = $formListOption->getValues();
                        $values = $values['option'];

                        $new = $options->fetchNew();
                        $new->setFromArray(array(
                            'name'          => $values['name'],
                            'attribute_id'  => $attribute->id,
                            'parent_id'     => $values['parent_id'] ? $values['parent_id'] : null,
                            'position'      =>  1 + (int)$options->getAdapter()->fetchOne(
                                $options->select()
                                    ->from($options, array('MAX(position)'))
                                    ->where('attribute_id = ?', $attribute->id)
                            )
                        ));
                        $new->save();

                        return $this->_redirect($this->attributeUrl($attribute));
                    }
                    break;
            }

        }

        $this->view->assign(array(
            //'itemType'      => $itemType,
            'attribute'         => $attribute,
            'formAttributeEdit' => $formAttributeEdit,
            'formAttributeNew'  => $formAttributeNew,
            'attributes'        => $attributes->fetchAll(array(
                'parent_id = ?' => $attribute->id
            ), 'position'),
            'options'           => $options->fetchAll(
                $options->select()
                    ->where('attribute_id = ?', $attribute->id)
                    ->where('parent_id IS NULL')
                    ->order('position')
            ),
            'formListOption'    => $formListOption
        ));
    }

    private function zoneUrl($zone)
    {
        return $this->_helper->url->url(array(
            'module'     => 'moder',
            'controller' => 'attrs',
            'action'     => 'zone',
            'zone_id'    => $zone->id
        ), 'default', true);
    }

    public function zoneAction()
    {
        $zones = new Attrs_Zones();

        $zone = $zones->find($this->getParam('zone_id'))->current();
        if (!$zone)
            return $this->_forward('notfound', 'error');

        $itemType = $zone->findParentAttrs_Item_Types();

        $attributes = new Attrs_Attributes();

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch($this->getParam('form')) {
                case 'attributes':

                    $zoneAttributes = new Attrs_Zone_Attributes();
                    $ids = (array)$request->getPost('attribute_id');
                    if (count($ids)) {
                        $select = $attributes->select()
                            ->where('id IN (?)', $ids);
                        foreach ($attributes->fetchAll($select) as $attribute) {
                            $exists = (bool)$zoneAttributes->fetchRow(
                                $zoneAttributes->select()
                                    ->where('zone_id = ?', $zone->id)
                                    ->where('attribute_id = ?', $attribute->id)
                            );
                            if (!$exists) {
                                $zoneAttributes->insert(array(
                                    'zone_id'       => $zone->id,
                                    'attribute_id'  => $attribute->id,
                                    'position'      =>  1 + $zoneAttributes->getAdapter()->fetchOne(
                                        $zoneAttributes->select()
                                            ->from($zoneAttributes, array('MAX(position)'))
                                            ->where('zone_id = ?', $zone->id)
                                        )
                                ));
                            }
                        }
                        $zoneAttributes->delete(array(
                            $zoneAttributes->getAdapter()->quoteInto('zone_id = ?', $zone->id),
                            $zoneAttributes->getAdapter()->quoteInto('attribute_id NOT IN (?)', $ids)
                        ));
                    } else {
                        $zoneAttributes->delete(array(
                            $zoneAttributes->getAdapter()->quoteInto('zone_id = ?', $zone->id)
                        ));
                    }
                    break;
            }
            return $this->_redirect($this->zoneUrl($zone));
        }

        $this->view->assign(array(
            'itemType'   => $itemType,
            'zone'       => $zone,
            'attributes' => $attributes->fetchAll(
                $attributes->select()->where('parent_id IS NULL')
            )
            /*'formAttribute' => $formAttribute,
            'attributes' => $group->findAttrs_Attributes()*/
        ));
    }

    public function attributeUpAction()
    {
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->getParam('attribute_id'))->current();
        if (!$attribute)
            return $this->_forward('notfound', 'error');

        $select = $attributes->select()
            ->from($attributes)
            ->where('attrs_attributes.position < ?', $attribute->position)
            ->order('attrs_attributes.position DESC')
            ->limit(1);
        if ($attribute->parent_id)
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        else
            $select->where('attrs_attributes.parent_id IS NULL');
        $prev = $attributes->fetchRow($select);

        if ($prev) {
            $prevPos = $prev->position;

            $prev->position = 10000;
            $prev->save();

            $pagePos = $attribute->position;
            $attribute->position = $prevPos;
            $attribute->save();

            $prev->position = $pagePos;
            $prev->save();
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index'
        )));
    }

    public function attributeDownAction()
    {
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->getParam('attribute_id'))->current();
        if (!$attribute)
            return $this->_forward('notfound', 'error');

        $select = $attributes->select()
            ->from($attributes)
            ->where('attrs_attributes.position > ?', $attribute->position)
            ->order('attrs_attributes.position ASC')
            ->limit(1);
        if ($attribute->parent_id)
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        else
            $select->where('attrs_attributes.parent_id IS NULL');
        $next = $attributes->fetchRow($select);

        if ($next) {
            $nextPos = $next->position;

            $next->position = 10000;
            $next->save();

            $pagePos = $attribute->position;
            $attribute->position = $nextPos;
            $attribute->save();

            $next->position = $pagePos;
            $next->save();
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index'
        )));
    }

    public function moveUpAttributeAction()
    {
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->getParam('attribute_id'))->current();
        if (!$attribute)
            return $this->_forward('notfound', 'error');

        $zones = new Attrs_Zones();

        $zone = $zones->find($this->getParam('zone_id'))->current();
        if (!$zone)
            return $this->_forward('notfound', 'error');

        $zoneAttributes = new Attrs_Zone_Attributes();
        $zoneAttribute = $zoneAttributes->fetchRow(
            $zoneAttributes->select()
                ->where('zone_id = ?', $zone->id)
                ->where('attribute_id = ?', $attribute->id)
        );

        if (!$zoneAttribute)
            return $this->_forward('notfound', 'error');

        $select = $zoneAttributes->select()
            ->from($zoneAttributes)
            ->join('attrs_attributes', 'attrs_zone_attributes.attribute_id=attrs_attributes.id', null)
            ->where('attrs_zone_attributes.zone_id = ?', $zone->id)
            ->where('attrs_zone_attributes.position < ?', $zoneAttribute->position)
            ->order('attrs_zone_attributes.position DESC')
            ->limit(1);
        if ($attribute->parent_id) {
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        } else {
            $select->where('attrs_attributes.parent_id IS NULL');
        }
        $prev = $zoneAttributes->fetchRow($select);

        if ($prev) {
            $prevPos = $prev->position;

            $prev->position = 10000;
            $prev->save();

            $pagePos = $zoneAttribute->position;
            $zoneAttribute->position = $prevPos;
            $zoneAttribute->save();

            $prev->position = $pagePos;
            $prev->save();
        }

        return $this->_redirect($this->zoneUrl($zone));
    }

    public function moveDownAttributeAction()
    {
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->getParam('attribute_id'))->current();
        if (!$attribute)
            return $this->_forward('notfound', 'error');

        $zones = new Attrs_Zones();

        $zone = $zones->find($this->getParam('zone_id'))->current();
        if (!$zone)
            return $this->_forward('notfound', 'error');

        $zoneAttributes = new Attrs_Zone_Attributes();
        $zoneAttribute = $zoneAttributes->fetchRow(
            $zoneAttributes->select()
                ->where('zone_id = ?', $zone->id)
                ->where('attribute_id = ?', $attribute->id)
        );

        if (!$zoneAttribute)
            return $this->_forward('notfound', 'error');

        $select = $zoneAttributes->select()
            ->from($zoneAttributes)
            ->join('attrs_attributes', 'attrs_zone_attributes.attribute_id=attrs_attributes.id', null)
            ->where('attrs_zone_attributes.zone_id = ?', $zone->id)
            ->where('attrs_zone_attributes.position > ?', $zoneAttribute->position)
            ->order('attrs_zone_attributes.position ASC')
            ->limit(1);
        if ($attribute->parent_id)
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        else
            $select->where('attrs_attributes.parent_id IS NULL');
        $next = $zoneAttributes->fetchRow($select);

        if ($next) {
            $nextPos = $next->position;

            $next->position = 10000;
            $next->save();

            $pagePos = $zoneAttribute->position;
            $zoneAttribute->position = $nextPos;
            $zoneAttribute->save();

            $next->position = $pagePos;
            $next->save();
        }

        return $this->_redirect($this->zoneUrl($zone));
    }


}