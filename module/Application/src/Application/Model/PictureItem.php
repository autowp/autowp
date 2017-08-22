<?php

namespace Application\Model;

use InvalidArgumentException;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class PictureItem
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getRow(int $pictureId, int $itemId)
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        return $this->table->select([
            'picture_id' => $pictureId,
            'item_id'    => $itemId
        ])->current();
    }

    public function add(int $pictureId, int $itemId)
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $itemId);

        if (! $row) {
            $this->table->insert([
                'picture_id' => $pictureId,
                'item_id'    => $itemId
            ]);
        }
    }

    public function remove(int $pictureId, int $itemId)
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $this->table->delete([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $itemId
        ]);
    }

    public function isExists(int $pictureId, int $itemId)
    {
        return (bool)$this->getRow($pictureId, $itemId);
    }

    public function changePictureItem(int $pictureId, int $oldItemId, int $newItemId)
    {
        if (! $newItemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $oldItemId);

        if (! $row) {
            throw new \Exception("Item not found");
        }

        $this->table->update([
            'item_id' => $newItemId
        ], [
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $oldItemId
        ]);
    }

    public function setPictureItems(int $pictureId, array $itemIds)
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        foreach ($itemIds as &$itemId) {
            $itemId = (int)$itemId;
            if (! $itemId) {
                throw new InvalidArgumentException("Item id is invalid");
            }
        }
        unset($itemId);

        foreach ($itemIds as $itemId) {
            $row = $this->getRow($pictureId, $itemId);

            if (! $row) {
                $this->table->insert([
                    'picture_id' => $pictureId,
                    'item_id'    => $itemId
                ]);
            }
        }

        $filter = [
            'picture_id = ?' => $pictureId
        ];
        if ($itemIds) {
            $filter[] = new Sql\Predicate\NotIn('item_id', $itemIds);
        }

        $this->table->delete($filter);
    }

    public function getPictureItems(int $pictureId): array
    {
        $rows = $this->table->select([
            'picture_id' => $pictureId
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = (int)$row['item_id'];
        }

        return $result;
    }

    public function getPictureItemData(int $pictureId, int $itemId)
    {
        return $this->table->select([
            'picture_id' => $pictureId,
            'item_id'    => $itemId
        ])->current();
    }

    public function getPictureItemsData(int $pictureId)
    {
        $rows = $this->table->select([
            'picture_id' => $pictureId
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }

        return $result;
    }

    public function getPictureItemsByType(int $pictureId, $type): array
    {
        $select = $this->table->getSql()->select();
        $select->columns(['item_id'])
            ->join('item', 'picture_item.item_id = item.id', [])
            ->where([
                'picture_id' => $pictureId,
                new Sql\Predicate\In('item.item_type_id', $type)
            ]);

        $rows = $this->table->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = (int)$row['item_id'];
        }

        return $result;
    }

    public function getData(array $options): array
    {
        $defaults = [
            'picture'      => null,
            'item'         => null,
            'onlyWithArea' => false
        ];
        $options = array_replace($defaults, $options);

        $select = $this->table->getSql()->select();

        $select->columns([
            'picture_id', 'item_id',
            'crop_left', 'crop_top', 'crop_width', 'crop_height'
        ]);

        if ($options['onlyWithArea']) {
            $select->where(['crop_left and crop_top and crop_width and crop_height']);
        }

        if ($options['picture']) {
            $select->where(['picture_id' => $options['picture']]);
        }

        if ($options['item']) {
            $select->where(['item_id' => $options['item']]);
        }

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $area = null;
            if ($row['crop_left'] && $row['crop_top'] && $row['crop_width'] && $row['crop_height']) {
                $area = [
                    (int)$row['crop_left'],  (int)$row['crop_top'],
                    (int)$row['crop_width'], (int)$row['crop_height'],
                ];
            }

            $result[] = [
                'picture_id' => (int)$row['picture_id'],
                'item_id'    => (int)$row['item_id'],
                'area'       => $area
            ];
        }

        return $result;
    }

    public function setProperties(int $pictureId, int $itemId, array $properties)
    {
        $row = $this->getRow($pictureId, $itemId);
        if (! $row) {
            return;
        }

        $set = [];

        if (array_key_exists('perspective', $properties)) {
            $perspective = $properties['perspective'];
            $set['perspective_id'] = $perspective ? (int)$perspective : null;
        }

        if (array_key_exists('area', $properties)) {
            $area = $properties['area'];
            if ($area) {
                $set = array_replace($set, [
                    'crop_left'   => $area['left'],
                    'crop_top'    => $area['top'],
                    'crop_width'  => $area['width'],
                    'crop_height' => $area['height'],
                ]);
            } else {
                $set = array_replace($set, [
                    'crop_left'   => null,
                    'crop_top'    => null,
                    'crop_width'  => null,
                    'crop_height' => null,
                ]);
            }
        }

        if ($set) {
            $this->table->update($set, [
                'picture_id = ?' => $pictureId,
                'item_id = ?'    => $itemId
            ]);
        }
    }

    public function getPerspective(int $pictureId, int $itemId)
    {
        $row = $this->getRow($pictureId, $itemId);
        if (! $row) {
            return null;
        }

        return $row['perspective_id'];
    }

    public function getArea(int $pictureId, int $itemId)
    {
        $row = $this->getRow($pictureId, $itemId);
        if (! $row) {
            return null;
        }

        if (! $row['crop_left'] || ! $row['crop_top'] || ! $row['crop_width'] || ! $row['crop_height']) {
            return null;
        }

        return [
            (int)$row['crop_left'],  (int)$row['crop_top'],
            (int)$row['crop_width'], (int)$row['crop_height'],
        ];
    }
}
