<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\Attribute as AttributeForm;
use Application\Form\Moder\AttributeListOption as AttributeListOptionForm;
use Application\Model\DbTable\Attr;

class AttrsController extends AbstractActionController
{
    public function indexAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $itemTypes = new Attr\ItemType();
        $attributes = new Attr\Attribute();

        return [
            'itemTypes'  => $itemTypes->fetchAll(),
            'attributes' => $attributes->fetchAll('parent_id IS NULL', 'position')
        ];
    }

    private function attributeUrl($attribute)
    {
        return $this->url()->fromRoute('moder/attrs/params', [
            'action'       => 'attribute',
            'attribute_id' => $attribute->id
        ]);
    }

    public function attributeAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->params('attribute_id'))->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $formAttributeEdit = new AttributeForm();
        $formAttributeEdit->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'edit'
        ], [], true));
        $formAttributeEdit->populateValues($attribute->toArray());

        $formAttributeNew = new AttributeForm();
        $formAttributeNew->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'new'
        ], [], true));

        $formListOption = new AttributeListOptionForm(null, [
            'attribute' => $attribute
        ]);
        $formListOption->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'option'
        ], [], true));

        $options = new Attr\ListOption();

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch ($this->params('form')) {
                case 'new':
                    $formAttributeNew->setData($this->params()->fromPost());
                    if ($formAttributeNew->isValid()) {
                        $values = $formAttributeNew->getData();

                        $position = $attributes->getAdapter()->fetchOne(
                            $attributes->getAdapter()->select()
                                ->from($attributes->info('name'), 'max(position)')
                                ->where('parent_id = ?', $attribute->id)
                        ) + 1;

                        $new = $attributes->createRow([
                            'name'        => $values['name'],
                            'description' => $values['description'],
                            'type_id'     => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'     => $values['unit_id'] ? $values['unit_id'] : null,
                            'parent_id'   => $attribute->id,
                            'precision'   => $values['precision'] ? $values['precision'] : null,
                            'position'    => $position
                        ]);
                        $new->save();

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;

                case 'edit':
                    $formAttributeEdit->setData($this->params()->fromPost());
                    if ($formAttributeEdit->isValid()) {
                        $values = $formAttributeEdit->getData();

                        $attribute->setFromArray([
                            'name'          => $values['name'],
                            'description'   => $values['description'],
                            'type_id'       => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'       => $values['unit_id'] ? $values['unit_id'] : null,
                            'precision'     => $values['precision'] ? $values['precision'] : null
                        ]);
                        $attribute->save();

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;

                case 'option':
                    $formListOption->setData($this->params()->fromPost());
                    if ($formListOption->isValid()) {
                        $values = $formListOption->getData();

                        $new = $options->fetchNew();
                        $new->setFromArray([
                            'name'          => $values['name'],
                            'attribute_id'  => $attribute->id,
                            'parent_id'     => $values['parent_id'] ? $values['parent_id'] : null,
                            'position'      => 1 + (int)$options->getAdapter()->fetchOne(
                                $options->select()
                                    ->from($options, ['MAX(position)'])
                                    ->where('attribute_id = ?', $attribute->id)
                            )
                        ]);
                        $new->save();

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;
            }
        }

        return [
            //'itemType'      => $itemType,
            'attribute'         => $attribute,
            'formAttributeEdit' => $formAttributeEdit,
            'formAttributeNew'  => $formAttributeNew,
            'attributes'        => $attributes->fetchAll([
                'parent_id = ?' => $attribute->id
            ], 'position'),
            'options'           => $options->fetchAll(
                $options->select()
                    ->where('attribute_id = ?', $attribute->id)
                    ->where('parent_id IS NULL')
                    ->order('position')
            ),
            'formListOption'    => $formListOption
        ];
    }

    private function zoneUrl($zone)
    {
        return $this->url()->fromRoute('moder/attrs/params', [
            'action'  => 'zone',
            'zone_id' => $zone->id
        ]);
    }

    public function zoneAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $zones = new Attr\Zone();

        $zone = $zones->find($this->params('zone_id'))->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $itemType = $zone->findParentRow(Attr\ItemType::class);

        $attributes = new Attr\Attribute();

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch ($this->params('form')) {
                case 'attributes':
                    $zoneAttributes = new Attr\ZoneAttribute();
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
                            if (! $exists) {
                                $zoneAttributes->insert([
                                    'zone_id'       => $zone->id,
                                    'attribute_id'  => $attribute->id,
                                    'position'      => 1 + $zoneAttributes->getAdapter()->fetchOne(
                                        $zoneAttributes->select()
                                            ->from($zoneAttributes, ['MAX(position)'])
                                            ->where('zone_id = ?', $zone->id)
                                    )
                                ]);
                            }
                        }
                        $zoneAttributes->delete([
                            'zone_id = ?'             => $zone->id,
                            'attribute_id NOT IN (?)' => $ids
                        ]);
                    } else {
                        $zoneAttributes->delete([
                            'zone_id = ?' => $zone->id
                        ]);
                    }
                    break;
            }
            return $this->redirect()->toUrl($this->zoneUrl($zone));
        }

        return [
            'itemType'   => $itemType,
            'zone'       => $zone,
            'attributes' => $attributes->fetchAll(
                $attributes->select()->where('parent_id IS NULL')
            )
            /*'formAttribute' => $formAttribute,
            'attributes' => $group->findAttrs_Attributes()*/
        ];
    }

    public function attributeUpAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->params('attribute_id'))->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $select = $attributes->select()
            ->from($attributes)
            ->where('attrs_attributes.position < ?', $attribute->position)
            ->order('attrs_attributes.position DESC')
            ->limit(1);
        if ($attribute->parent_id) {
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        } else {
            $select->where('attrs_attributes.parent_id IS NULL');
        }
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

        return $this->redirect()->toRoute(null, [
            'action' => 'index'
        ], [], true);
    }

    public function attributeDownAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->params('attribute_id'))->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $select = $attributes->select()
            ->from($attributes)
            ->where('attrs_attributes.position > ?', $attribute->position)
            ->order('attrs_attributes.position ASC')
            ->limit(1);
        if ($attribute->parent_id) {
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        } else {
            $select->where('attrs_attributes.parent_id IS NULL');
        }
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

        return $this->redirect()->toRoute(null, [
            'action' => 'index'
        ], [], true);
    }

    public function moveUpAttributeAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->params('attribute_id'))->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $zones = new Attr\Zone();

        $zone = $zones->find($this->params('zone_id'))->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $zoneAttributes = new Attr\ZoneAttribute();
        $zoneAttribute = $zoneAttributes->fetchRow(
            $zoneAttributes->select()
                ->where('zone_id = ?', $zone->id)
                ->where('attribute_id = ?', $attribute->id)
        );

        if (! $zoneAttribute) {
            return $this->notFoundAction();
        }

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

        return $this->redirect()->toUrl($this->zoneUrl($zone));
    }

    public function moveDownAttributeAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->params('attribute_id'))->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $zones = new Attr\Zone();

        $zone = $zones->find($this->params('zone_id'))->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $zoneAttributes = new Attr\ZoneAttribute();
        $zoneAttribute = $zoneAttributes->fetchRow(
            $zoneAttributes->select()
                ->where('zone_id = ?', $zone->id)
                ->where('attribute_id = ?', $attribute->id)
        );

        if (! $zoneAttribute) {
            return $this->notFoundAction();
        }

        $select = $zoneAttributes->select()
            ->from($zoneAttributes)
            ->join('attrs_attributes', 'attrs_zone_attributes.attribute_id=attrs_attributes.id', null)
            ->where('attrs_zone_attributes.zone_id = ?', $zone->id)
            ->where('attrs_zone_attributes.position > ?', $zoneAttribute->position)
            ->order('attrs_zone_attributes.position ASC')
            ->limit(1);
        if ($attribute->parent_id) {
            $select->where('attrs_attributes.parent_id = ?', $attribute->parent_id);
        } else {
            $select->where('attrs_attributes.parent_id IS NULL');
        }
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

        return $this->redirect()->toUrl($this->zoneUrl($zone));
    }
}
