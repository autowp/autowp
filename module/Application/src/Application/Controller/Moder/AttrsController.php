<?php

namespace Application\Controller\Moder;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\Attribute as AttributeForm;
use Application\Form\Moder\AttributeListOption as AttributeListOptionForm;
use Application\Service\SpecificationsService;

class AttrsController extends AbstractActionController
{
    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var TableGateway
     */
    private $listOptionTable;

    /**
     * @var TableGateway
     */
    private $zoneAttributeTable;

    /**
     * @var TableGateway
     */
    private $attributeTable;

    /**
     * @var TableGateway
     */
    private $zoneTable;

    /**
     * @var TableGateway
     */
    private $typeTable;

    public function __construct(
        SpecificationsService $specsService,
        TableGateway $listOptionTable,
        TableGateway $zoneTable,
        TableGateway $typeTable,
        TableGateway $zoneAttributeTable,
        TableGateway $attributeTable
    ) {
        $this->specsService = $specsService;

        $this->listOptionTable = $listOptionTable;
        $this->attributeTable = $attributeTable;
        $this->zoneAttributeTable = $zoneAttributeTable;
        $this->zoneTable = $zoneTable;
        $this->typeTable = $typeTable;
    }

    public function indexAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $zones = [];
        foreach ($this->zoneTable->select([]) as $row) {
            $zones[] = $row;
        }

        return [
            'attributes' => $this->getAttributesIndexRecursive(0),
            'zones'      => $zones
        ];
    }

    private function getAttributesIndexRecursive(int $parentId): array
    {
        $select = new Sql\Select($this->attributeTable->getTable());
        $select->order('position');

        if ($parentId) {
            $select->where(['parent_id' => $parentId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        $result = [];
        foreach ($this->attributeTable->selectWith($select) as $row) {
            $result[] = [
                'id'              => (int)$row['id'],
                'name'            => $row['name'],
                'childAttributes' => $this->getAttributesIndexRecursive($row['id']),
                'moveDownUrl'     => $this->url()->fromRoute('moder/attrs/params', [
                    'action'       => 'attribute-down',
                    'attribute_id' => $row['id']
                ]),
                'moveUpUrl'     => $this->url()->fromRoute('moder/attrs/params', [
                    'action'       => 'attribute-up',
                    'attribute_id' => $row['id']
                ])
            ];
        }

        return $result;
    }

    private function attributeUrl($attribute)
    {
        return $this->url()->fromRoute('moder/attrs/params', [
            'action'       => 'attribute',
            'attribute_id' => $attribute['id']
        ]);
    }

    public function attributeAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attribute = $this->attributeTable->select(['id' => (int)$this->params('attribute_id')])->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $unitOptions = ['' => '--'];
        foreach ($this->specsService->getUnits() as $unit) {
            $unitOptions[$unit['id']] = $unit['name'];
        }

        $typeOptions = ['' => '--'];
        foreach ($this->typeTable->select([]) as $type) {
            $typeOptions[$type['id']] = $type['name'];
        }

        $formAttributeEdit = new AttributeForm();
        $formAttributeEdit->get('unit_id')->setValueOptions($unitOptions);
        $formAttributeEdit->get('type_id')->setValueOptions($typeOptions);
        $formAttributeEdit->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'edit'
        ], [], true));
        $formAttributeEdit->populateValues((array)$attribute);

        $formAttributeNew = new AttributeForm();
        $formAttributeNew->get('unit_id')->setValueOptions($unitOptions);
        $formAttributeEdit->get('type_id')->setValueOptions($typeOptions);
        $formAttributeNew->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'new'
        ], [], true));

        $formListOption = new AttributeListOptionForm();
        $formListOption->get('parent_id')->setValueOptions(
            array_replace(
                ['' => '--'],
                $this->getListOptionsParents($attribute['id'], null)
            )
        );
        $formListOption->setAttribute('action', $this->url()->fromRoute(null, [
            'form' => 'option'
        ], [], true));

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch ($this->params('form')) {
                case 'new':
                    $formAttributeNew->setData($this->params()->fromPost());
                    if ($formAttributeNew->isValid()) {
                        $values = $formAttributeNew->getData();

                        $select = new Sql\Select($this->attributeTable->getTable());
                        $select->columns(['max' => new Sql\Expression('max(position)')])
                            ->where(['parent_id' => $attribute['id']]);

                        $row = $this->attributeTable->selectWith($select)->current();

                        $max = $row ? (int)$row['max'] : 0;

                        $this->attributeTable->insert([
                            'name'        => $values['name'],
                            'description' => $values['description'],
                            'type_id'     => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'     => $values['unit_id'] ? $values['unit_id'] : null,
                            'parent_id'   => $attribute['id'],
                            'precision'   => $values['precision'] ? $values['precision'] : null,
                            'position'    => $max + 1
                        ]);

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;

                case 'edit':
                    $formAttributeEdit->setData($this->params()->fromPost());
                    if ($formAttributeEdit->isValid()) {
                        $values = $formAttributeEdit->getData();

                        $this->attributeTable->update([
                            'name'          => $values['name'],
                            'description'   => $values['description'],
                            'type_id'       => $values['type_id'] ? $values['type_id'] : null,
                            'unit_id'       => $values['unit_id'] ? $values['unit_id'] : null,
                            'precision'     => $values['precision'] ? $values['precision'] : null
                        ], [
                            'id' => $attribute['id']
                        ]);

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;

                case 'option':
                    $formListOption->setData($this->params()->fromPost());
                    if ($formListOption->isValid()) {
                        $values = $formListOption->getData();

                        $select = new Sql\Select($this->listOptionTable->getTable());
                        $select->columns(['max' => new Sql\Expression('MAX(position)')])
                            ->where(['attribute_id' => $attribute['id']]);

                        $row = $this->listOptionTable->selectWith($select)->current();
                        $max = $row ? (int)$row['max'] : 0;

                        $this->listOptionTable->insert([
                            'name'          => $values['name'],
                            'attribute_id'  => $attribute['id'],
                            'parent_id'     => $values['parent_id'] ? $values['parent_id'] : null,
                            'position'      => 1 + $max
                        ]);

                        return $this->redirect()->toUrl($this->attributeUrl($attribute));
                    }
                    break;
            }
        }

        return [
            'attribute'         => $attribute,
            'formAttributeEdit' => $formAttributeEdit,
            'formAttributeNew'  => $formAttributeNew,
            'attributes'        => $this->getAttributes($attribute['id']),
            'options'           => $this->getListOptions($attribute['id'], null),
            'formListOption'    => $formListOption
        ];
    }

    private function getListOptionsParents($attributeId, $parentId)
    {
        $select = new Sql\Select($this->listOptionTable->getTable());
        $select
            ->where(['attribute_id = ?' => $attributeId])
            ->order('position');

        if ($parentId) {
            $select->where(['parent_id = ?' => $parentId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        $rows = $this->listOptionTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            $result[$id] = $row['name'];
            $result = array_replace($result, $this->getListOptionsParents($attributeId, $id));
        }

        return $result;
    }

    private function getListOptions($attributeId, $parentId)
    {
        $select = new Sql\Select($this->listOptionTable->getTable());
        $select
            ->where(['attribute_id = ?' => $attributeId])
            ->order('position');

        if ($parentId) {
            $select->where(['parent_id = ?' => $parentId]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        $rows = $this->listOptionTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'     => $row['id'],
                'name'   => $row['name'],
                'childs' => $this->getListOptions($attributeId, $row['id'])
            ];
        }

        return $result;
    }

    private function getAttributes($parentId)
    {
        $select = new Sql\Select($this->attributeTable->getTable());
        $select->where(['parent_id' => $parentId])
            ->order('position');

        $result = [];
        foreach ($this->attributeTable->selectWith($select) as $row) {
            $type = $this->typeTable->select(['id' => $row['type_id']])->current();
            $result[] = [
                'id'     => $row['id'],
                'name'   => $row['name'],
                'type'   => $type,
                'unit'   => $this->specsService->getUnit($row['unit_id']),
                'childs' => $this->getAttributes($row['id'])
            ];
        }

        return $result;
    }

    private function getZoneUrl(int $zoneId)
    {
        return $this->url()->fromRoute('moder/attrs/params', [
            'action'  => 'zone',
            'zone_id' => $zoneId
        ]);
    }

    public function zoneAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $zone = $this->zoneTable->select(['id' => (int)$this->params('zone_id')])->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            switch ($this->params('form')) {
                case 'attributes':
                    $ids = (array)$request->getPost('attribute_id');
                    if (count($ids)) {
                        $attributes = $this->attributeTable->select([
                            new Sql\Predicate\In('id', $ids)
                        ]);
                        foreach ($attributes as $attribute) {
                            $exists = (bool)$this->zoneAttributeTable->select([
                                'zone_id'      => $zone['id'],
                                'attribute_id' => $attribute['id']
                            ])->current();
                            if (! $exists) {
                                $select = new Sql\Select($this->zoneAttributeTable->getTable());
                                $select->columns(['max' => new Sql\Expression('MAX(position)')])
                                    ->where(['zone_id' => $zone['id']]);

                                $row = $this->zoneAttributeTable->selectWith($select)->current();
                                $maxPosition = $row ? $row['max'] : 0;

                                $this->zoneAttributeTable->insert([
                                    'zone_id'       => $zone['id'],
                                    'attribute_id'  => $attribute['id'],
                                    'position'      => $maxPosition + 1
                                ]);
                            }
                        }
                        $this->zoneAttributeTable->delete([
                            'zone_id = ?' => $zone['id'],
                            new Sql\Predicate\NotIn('attribute_id', $ids)
                        ]);
                    } else {
                        $this->zoneAttributeTable->delete([
                            'zone_id = ?' => $zone['id']
                        ]);
                    }
                    break;
            }
            return $this->redirect()->toUrl($this->getZoneUrl($zone['id']));
        }

        return [
            'zone'          => $zone,
            'allAttributes' => $this->getAllZoneAttributesRecursive($zone['id'], 0),
            'attributes'    => $this->getZoneAttributesRecursive($zone['id'], 0),
            /*'formAttribute' => $formAttribute,
            'attributes' => $group->findAttrs_Attributes()*/
        ];
    }

    private function isZoneHasAttribute(int $zoneId, int $attributeId): bool
    {
        return (bool)$this->zoneAttributeTable->select([
            'zone_id'      => $zoneId,
            'attribute_id' => $attributeId
        ])->current();
    }

    private function getZoneAttributesRecursive(int $zoneId, int $parentId): array
    {
        $select = new Sql\Select($this->attributeTable->getTable());

        $select->join('attrs_zone_attributes', 'attrs_zone_attributes.attribute_id = attrs_attributes.id', [])
            ->where(['attrs_zone_attributes.zone_id' => $zoneId])
            ->order('attrs_zone_attributes.position');

        if ($parentId) {
            $select->where(['attrs_attributes.parent_id' => $parentId]);
        } else {
            $select->where(['attrs_attributes.parent_id IS NULL']);
        }

        $result = [];
        foreach ($this->attributeTable->selectWith($select) as $row) {
            $result[] = [
                'id'              => (int)$row['id'],
                'name'            => $row['name'],
                'childAttributes' => $this->getZoneAttributesRecursive($zoneId, $row['id']),
                'moveUpUrl'       => $this->url()->fromRoute('moder/attrs/params', [
                    'action'       => 'move-up-attribute',
                    'attribute_id' => $row['id'],
                    'zone_id'      => $zoneId
                ]),
                'moveDownUrl'       => $this->url()->fromRoute('moder/attrs/params', [
                    'action'       => 'move-down-attribute',
                    'attribute_id' => $row['id'],
                    'zone_id'      => $zoneId
                ])
            ];
        }

        return $result;
    }

    private function getAllZoneAttributesRecursive(int $zoneId, int $parentId): array
    {
        if ($parentId) {
            $filter = ['parent_id' => $parentId];
        } else {
            $filter = ['parent_id IS NULL'];
        }

        $result = [];
        foreach ($this->attributeTable->select($filter) as $row) {
            $result[] = [
                'id'              => (int)$row['id'],
                'name'            => $row['name'],
                'checked'         => $this->isZoneHasAttribute($zoneId, $row['id']),
                'childAttributes' => $this->getAllZoneAttributesRecursive($zoneId, $row['id'])
            ];
        }

        return $result;
    }

    public function attributeUpAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attribute = $this->attributeTable->select(['id' => (int)$this->params('attribute_id')])->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->attributeTable->getTable());
        $select->where(['attrs_attributes.position < ?' => $attribute['position']])
            ->order('attrs_attributes.position DESC')
            ->limit(1);
        if ($attribute['parent_id']) {
            $select->where(['attrs_attributes.parent_id' => $attribute['parent_id']]);
        } else {
            $select->where(['attrs_attributes.parent_id IS NULL']);
        }
        $prev = $this->attributeTable->selectWith($select)->current();

        if ($prev) {
            $prevPos = $prev['position'];
            $pagePos = $attribute['position'];

            $this->setAttributePosition($prev['id'], 10000);
            $this->setAttributePosition($attribute['id'], $prevPos);
            $this->setAttributePosition($prev['id'], $pagePos);
        }

        return $this->redirect()->toRoute(null, [
            'action' => 'index'
        ], [], true);
    }

    private function setAttributePosition(int $attributeId, int $position)
    {
        $this->attributeTable->update([
            'position' => $position
        ], [
            'id' => $attributeId
        ]);
    }

    public function attributeDownAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attribute = $this->attributeTable->select(['id' => (int)$this->params('attribute_id')])->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->attributeTable->getTable());
        $select->where(['attrs_attributes.position > ?' => $attribute['position']])
            ->order('attrs_attributes.position ASC')
            ->limit(1);
        if ($attribute['parent_id']) {
            $select->where(['attrs_attributes.parent_id' => $attribute['parent_id']]);
        } else {
            $select->where(['attrs_attributes.parent_id IS NULL']);
        }
        $next = $this->attributeTable->selectWith($select)->current();

        if ($next) {
            $nextPos = $next['position'];
            $pagePos = $attribute['position'];

            $this->setAttributePosition($next['id'], 10000);
            $this->setAttributePosition($attribute['id'], $nextPos);
            $this->setAttributePosition($next['id'], $pagePos);
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

        $attribute = $this->attributeTable->select(['id' => (int)$this->params('attribute_id')])->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $zone = $this->zoneTable->select(['id' => (int)$this->params('zone_id')])->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $zoneAttribute = $this->zoneAttributeTable->select([
            'zone_id'      => $zone['id'],
            'attribute_id' => $attribute['id']
        ])->current();

        if (! $zoneAttribute) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->zoneAttributeTable->getTable());

        $select
            ->join('attrs_attributes', 'attrs_zone_attributes.attribute_id = attrs_attributes.id', [])
            ->where([
                'attrs_zone_attributes.zone_id'      => $zone['id'],
                'attrs_zone_attributes.position < ?' => $zoneAttribute['position']
            ])
            ->order('attrs_zone_attributes.position DESC')
            ->limit(1);
        if ($attribute['parent_id']) {
            $select->where(['attrs_attributes.parent_id' => $attribute['parent_id']]);
        } else {
            $select->where(['attrs_attributes.parent_id IS NULL']);
        }
        $prev = $this->zoneAttributeTable->selectWith($select)->current();

        if ($prev) {
            $prevPos = $prev['position'];
            $pagePos = $zoneAttribute['position'];

            $this->setZoneAttributePosition($prev['zone_id'], $prev['attribute_id'], 10000);
            $this->setZoneAttributePosition($zoneAttribute['zone_id'], $zoneAttribute['attribute_id'], $prevPos);
            $this->setZoneAttributePosition($prev['zone_id'], $prev['attribute_id'], $pagePos);
        }

        return $this->redirect()->toUrl($this->getZoneUrl($zone['id']));
    }

    private function setZoneAttributePosition(int $zoneId, int $attriuteId, int $position)
    {
        $this->zoneAttributeTable->update([
            'position' => $position
        ], [
            'zone_id'      => $zoneId,
            'attribute_id' => $attriuteId
        ]);
    }

    public function moveDownAttributeAction()
    {
        if (! $this->user()->isAllowed('attrs', 'edit')) {
            return $this->forbiddenAction();
        }

        $attribute = $this->attributeTable->select(['id' => (int)$this->params('attribute_id')])->current();
        if (! $attribute) {
            return $this->notFoundAction();
        }

        $zone = $this->zoneTable->select(['id' => (int)$this->params('zone_id')])->current();
        if (! $zone) {
            return $this->notFoundAction();
        }

        $zoneAttribute = $this->zoneAttributeTable->select([
            'zone_id'      => $zone['id'],
            'attribute_id' => $attribute['id']
        ])->current();

        if (! $zoneAttribute) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->zoneAttributeTable->getTable());

        $select
            ->join('attrs_attributes', 'attrs_zone_attributes.attribute_id = attrs_attributes.id', [])
            ->where([
                'attrs_zone_attributes.zone_id' => $zone['id'],
                'attrs_zone_attributes.position > ?' => $zoneAttribute['position']
            ])
            ->order('attrs_zone_attributes.position ASC')
            ->limit(1);
        if ($attribute['parent_id']) {
            $select->where(['attrs_attributes.parent_id' => $attribute['parent_id']]);
        } else {
            $select->where(['attrs_attributes.parent_id IS NULL']);
        }
        $next = $this->zoneAttributeTable->selectWith($select)->current();

        if ($next) {
            $nextPos = $next->position;
            $pagePos = $zoneAttribute->position;

            $this->setZoneAttributePosition($next['zone_id'], $next['attribute_id'], 10000);
            $this->setZoneAttributePosition($zoneAttribute['zone_id'], $zoneAttribute['attribute_id'], $nextPos);
            $this->setZoneAttributePosition($next['zone_id'], $next['attribute_id'], $pagePos);
        }

        return $this->redirect()->toUrl($this->getZoneUrl($zone['id']));
    }
}
