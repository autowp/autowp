<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\DbTable;
use Application\Model\Modification;

class CarsController extends AbstractActionController
{
    /**
     * @param \Autowp\Commons\Db\Table\Row $car
     * @return string
     */
    private function carModerUrl(\Autowp\Commons\Db\Table\Row $item, $full = false, $tab = null, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        if ($tab) {
            $url .= '?' . http_build_query([
                'tab' => $tab
            ]);
        }

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . $url;
    }

    /**
     * @param \Autowp\Commons\Db\Table\Row $car
     * @return void
     */
    private function redirectToCar(\Autowp\Commons\Db\Table\Row $car, $tab = null)
    {
        return $this->redirect()->toUrl($this->carModerUrl($car, true, $tab));
    }

    private function carMofificationsGroupModifications(\Autowp\Commons\Db\Table\Row $car, $groupId)
    {
        $modModel = new Modification();
        $mTable = new DbTable\Modification();
        $db = $mTable->getAdapter();
        $itemTable = $this->catalogue()->getItemTable();

        $language = $this->language();

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
            ->where('item_parent_cache.item_id = ?', $car->id)
            ->order('modification.name');

        if ($groupId) {
            $select->where('modification.group_id = ?', $groupId);
        } else {
            $select->where('modification.group_id IS NULL');
        }

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $mRow) {
            $picturesCount = $db->fetchOne(
                $db->select()
                    ->from('modification_picture', 'count(1)')
                    ->where('modification_picture.modification_id = ?', $mRow->id)
                    ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $car->id)
            );

            $isInherited = $mRow->item_id != $car->id;
            $inheritedFrom = null;

            if ($isInherited) {
                $carRow = $itemTable->fetchRow(
                    $itemTable->select(true)
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                        ->join('modification', 'item.id = modification.item_id', null)
                        ->where('modification.id = ?', $mRow['id'])
                );

                if ($carRow) {
                    $inheritedFrom = [
                        'name' => $this->car()->formatName($carRow, $language),
                        'url'  => $this->carModerUrl($carRow)
                    ];
                }
            }

            $modifications[] = [
                'inherited'     => $isInherited,
                'inheritedFrom' => $inheritedFrom,
                'name'      => $mRow->name,
                'url'       => $this->url()->fromRoute('moder/modification/params', [
                    'action'          => 'edit',
                    'item_id'         => $car['id'],
                    'modification_id' => $mRow->id
                ], [], true),
                'count'     => $picturesCount,
                'canDelete' => ! $isInherited && $modModel->canDelete($mRow->id),
                'deleteUrl' => $this->url()->fromRoute('moder/modification/params', [
                    'action'     => 'delete',
                    'id'         => $mRow->id
                ], [], true)
            ];
        }

        return $modifications;
    }

    public function carModificationsAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $mgTable = new DbTable\Modification\Group();

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $groups[] = [
                'name'          => $mgRow->name,
                'modifications' => $this->carMofificationsGroupModifications($car, $mgRow->id)
            ];
        }

        $groups[] = [
            'name'          => null,
            'modifications' => $this->carMofificationsGroupModifications($car, null),
        ];

        $model = new ViewModel([
            'car'    => $car,
            'groups' => $groups
        ]);
        return $model->setTerminal(true);
    }

    public function carModificationPicturesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $mTable = new DbTable\Modification();
        $mpTable = new DbTable\Modification\Picture();
        $mgTable = new DbTable\Modification\Group();
        $pictureTable = new DbTable\Picture();
        $db = $mpTable->getAdapter();
        $imageStorage = $this->imageStorage();
        $language = $this->language();


        $request = $this->getRequest();
        if ($request->isPost()) {
            $picture = (array)$this->params('picture', []);

            foreach ($picture as $pictureId => $modificationIds) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->where('pictures.id = ?', (int)$pictureId)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $car->id)
                );

                if ($pictureRow) {
                    foreach ($modificationIds as &$modificationId) {
                        $modificationId = (int)$modificationId;

                        $mpRow = $mpTable->fetchRow([
                            'picture_id = ?'      => $pictureRow->id,
                            'modification_id = ?' => $modificationId
                        ]);
                        if (! $mpRow) {
                            $mpRow = $mpTable->createRow([
                                'picture_id'      => $pictureRow->id,
                                'modification_id' => $modificationId
                            ]);
                            $mpRow->save();
                        }
                    }
                    unset($modificationId); // prevent bugs

                    $select = $mpTable->select(true)
                        ->where('modification_picture.picture_id = ?', $pictureRow->id)
                        ->join('modification', 'modification_picture.modification_id = modification.id', null)
                        ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
                        ->where('item_parent_cache.item_id = ?', $car->id);

                    if ($modificationIds) {
                        $select->where('modification.id not in (?)', $modificationIds);
                    }

                    $mpRows = $mpTable->fetchAll($select);
                    foreach ($mpRows as $mpRow) {
                        $mpRow->delete();
                    }
                }
            }

            return $this->redirectToCar($car, 'modifications');
        }



        $pictures = [];

        $pictureRows = $pictureTable->fetchAll(
            $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->order('pictures.id')
        );

        foreach ($pictureRows as $pictureRow) {
            $request = DbTable\Picture\Row::buildFormatRequest($pictureRow->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

            $modificationIds = $db->fetchCol(
                $db->select()
                    ->from('modification_picture', 'modification_id')
                    ->where('picture_id = ?', $pictureRow->id)
            );

            $pictures[] = [
                'id'              => $pictureRow->id,
                'name'            => $this->pic()->name($pictureRow, $language),
                'url'             => $this->pic()->href($pictureRow),
                'src'             => $imageInfo ? $imageInfo->getSrc() : null,
                'modificationIds' => $modificationIds
            ];
        }


        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $mRows = $mTable->fetchAll(
                $mTable->select(true)
                ->where('modification.group_id = ?', $mgRow->id)
                ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $car->id)
                ->order('modification.name')
            );

            $modifications = [];
            foreach ($mRows as $mRow) {
                $modifications[] = [
                    'id'     => $mRow->id,
                    'name'   => $mRow->name,
                ];
            }

            $groups[] = [
                'name'          => $mgRow->name,
                'modifications' => $modifications
            ];
        }

        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->where('modification.group_id IS NULL')
                ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $car->id)
                ->order('modification.name')
        );

        $modifications = [];
        foreach ($mRows as $mRow) {
            $modifications[] = [
                'id'   => $mRow->id,
                'name' => $mRow->name,
            ];
        }

        $groups[] = [
            'name'          => null,
            'modifications' => $modifications
        ];


        return [
            'pictures' => $pictures,
            'groups'   => $groups
        ];
    }
}
