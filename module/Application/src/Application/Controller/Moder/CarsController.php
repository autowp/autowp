<?php

namespace Application\Controller\Moder;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\DbTable;
use Application\Model\Modification;

class CarsController extends AbstractActionController
{
    /**
     * @var TableGateway
     */
    private $modificationTable;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var Modification
     */
    private $modification;

    /**
     * @var TableGateway
     */
    private $modificationPicture;

    /**
     * @var TableGateway
     */
    private $modificationGroupTable;

    public function __construct(
        TableGateway $modificationTable,
        DbTable\Picture $pictureTable,
        Modification $modification,
        TableGateway $modificationPicture,
        TableGateway $modificationGroupTable
    ) {
        $this->modificationTable = $modificationTable;
        $this->pictureTable = $pictureTable;
        $this->modification = $modification;
        $this->modificationPicture = $modificationPicture;
        $this->modificationGroupTable = $modificationGroupTable;
    }

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
        $db = $this->pictureTable->getAdapter();
        $itemTable = $this->catalogue()->getItemTable();

        $language = $this->language();

        $select = new Sql\Select($this->modificationTable->getTable());
        $select->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where(['item_parent_cache.item_id' => $car['id']])
            ->order('modification.name');

        if ($groupId) {
            $select->where(['modification.group_id' => $groupId]);
        } else {
            $select->where(['modification.group_id IS NULL']);
        }

        $modifications = [];
        foreach ($this->modificationTable->selectWith($select) as $mRow) {
            $picturesCount = $db->fetchOne(
                $db->select()
                    ->from('modification_picture', 'count(1)')
                    ->where('modification_picture.modification_id = ?', $mRow['id'])
                    ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $car['id'])
            );

            $isInherited = $mRow['item_id'] != $car['id'];
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
                'name'      => $mRow['name'],
                'url'       => $this->url()->fromRoute('moder/modification/params', [
                    'action'          => 'edit',
                    'item_id'         => $car['id'],
                    'modification_id' => $mRow['id']
                ], [], true),
                'count'     => $picturesCount,
                'canDelete' => ! $isInherited && $this->modification->canDelete($mRow['id']),
                'deleteUrl' => $this->url()->fromRoute('moder/modification/params', [
                    'action'     => 'delete',
                    'id'         => $mRow['id']
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

        $mgRows = $this->modificationGroupTable->select([]);

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $groups[] = [
                'name'          => $mgRow['name'],
                'modifications' => $this->carMofificationsGroupModifications($car, $mgRow['id'])
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

        $pictureTable = new DbTable\Picture();
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
                        ->where('item_parent_cache.parent_id = ?', $car['id'])
                );

                if ($pictureRow) {
                    foreach ($modificationIds as &$modificationId) {
                        $modificationId = (int)$modificationId;

                        $mpRow = $this->modificationPicture->select([
                            'picture_id'      => $pictureRow['id'],
                            'modification_id' => $modificationId
                        ])->current();
                        if (! $mpRow) {
                            $this->modificationPicture->insert([
                                'picture_id'      => $pictureRow['id'],
                                'modification_id' => $modificationId
                            ]);
                        }
                    }
                    unset($modificationId); // prevent bugs

                    $select = new Sql\Select($this->modificationPicture->getTable());
                    $select
                        ->join('modification', 'modification_picture.modification_id = modification.id', [])
                        ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
                        ->where([
                            'modification_picture.picture_id' => $pictureRow['id'],
                            'item_parent_cache.item_id'       => $car['id']
                        ]);

                    if ($modificationIds) {
                        $select->where([
                            new Sql\Predicate\NotIn('modification.id', $modificationIds)
                        ]);
                    }

                    $mpRows = $this->modificationPicture->selectWith($select);
                    foreach ($mpRows as $mpRow) {
                        $mpRow->delete();
                        $this->modificationPicture->delete([
                            'picture_id'      => $mpRow['picture_id'],
                            'modification_id' => $mpRow['modification_id']
                        ]);
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
                ->where('item_parent_cache.parent_id = ?', $car['id'])
                ->order('pictures.id')
        );

        foreach ($pictureRows as $pictureRow) {
            $request = DbTable\Picture::buildFormatRequest($pictureRow->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

            $select = new Sql\Select($this->modificationPicture->getTable());
            $select->columns(['modification_id'])
                ->where(['picture_id' => $pictureRow['id']]);

            $modificationIds = [];
            foreach ($this->modificationPicture->selectWith($select) as $row) {
                $modificationIds[] = $row['modification_id'];
            }

            $pictures[] = [
                'id'              => $pictureRow['id'],
                'name'            => $this->pic()->name($pictureRow, $language),
                'url'             => $this->pic()->href($pictureRow),
                'src'             => $imageInfo ? $imageInfo->getSrc() : null,
                'modificationIds' => $modificationIds
            ];
        }


        $mgRows = $this->modificationGroupTable->select([]);

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $select = new Sql\Select($this->modificationTable->getTable());

            $select->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
                ->where([
                    'modification.group_id'    => $mgRow['id'],
                    'item_parent_cache.item_id' => $car['id']
                ])
                ->order('modification.name');
            $mRows = $this->modificationTable->selectWith($select);

            $modifications = [];
            foreach ($mRows as $mRow) {
                $modifications[] = [
                    'id'     => $mRow['id'],
                    'name'   => $mRow['name'],
                ];
            }

            $groups[] = [
                'name'          => $mgRow['name'],
                'modifications' => $modifications
            ];
        }

        $select = new Sql\Select($this->modificationTable->getTable());

        $select->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where([
                'modification.group_id IS NULL',
                'item_parent_cache.item_id' => $car['id']
            ])
            ->order('modification.name');
        $mRows = $this->modificationTable->selectWith($select);

        $modifications = [];
        foreach ($mRows as $mRow) {
            $modifications[] = [
                'id'   => $mRow['id'],
                'name' => $mRow['name'],
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
