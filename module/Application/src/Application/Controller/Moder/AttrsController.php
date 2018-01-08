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
    private $attributeTable;

    /**
     * @var TableGateway
     */
    private $typeTable;

    public function __construct(
        SpecificationsService $specsService,
        TableGateway $listOptionTable,
        TableGateway $typeTable,
        TableGateway $attributeTable
    ) {
        $this->specsService = $specsService;

        $this->listOptionTable = $listOptionTable;
        $this->attributeTable = $attributeTable;
        $this->typeTable = $typeTable;
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
        $formAttributeNew->get('type_id')->setValueOptions($typeOptions);
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
}
