<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\HostManager;
use Application\Model\DbTable;
use Application\Model\BrandVehicle;

class ItemParentController extends AbstractActionController
{
    private $languages = ['en'];

    /**
     * @var BrandVehicle
     */
    private $model;

    public function __construct(
        BrandVehicle $model,
        array $languages
    ) {
        $this->model = $model;
        $this->languages = $languages;
    }

    public function itemAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $itemParentTable = new DbTable\Item\ParentTable();
        $itemParentLanguageTable = new DbTable\Item\ParentLanguage();
        $itemTable = $this->catalogue()->getItemTable();

        $itemParentRow = $itemParentTable->fetchRow([
            'parent_id = ?' => $this->params('parent_id'),
            'item_id = ?'   => $this->params('item_id')
        ]);

        if (! $itemParentRow) {
            return $this->notFoundAction();
        }

        $parentRow = $itemTable->find($itemParentRow->parent_id)->current();
        $itemRow = $itemTable->find($itemParentRow->item_id)->current();
        if (! $parentRow || ! $itemRow) {
            return $this->notFoundAction();
        }

        $form = new \Application\Form\Moder\ItemParent(null, [
            'languages' => $this->languages,
            'parentId'  => $parentRow->id,
            'itemId'    => $itemRow->id
        ]);

        $values = [
            'catname' => $itemParentRow->catname,
            'type'    => $itemParentRow->type
        ];

        $bvlRows = $itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $itemRow->id,
            'parent_id = ?' => $parentRow->id
        ]);
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow->language] = [
                'name' => $bvlRow->name
            ];
        }

        $form->populateValues($values);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $this->model->setItemParent($parentRow->id, $itemRow->id, $values, false);

                return $this->redirect()->toRoute(null, [], [], true);
            }
        }

        return [
            'parent' => $parentRow,
            'item'   => $itemRow,
            'form'   => $form
        ];
    }
}
