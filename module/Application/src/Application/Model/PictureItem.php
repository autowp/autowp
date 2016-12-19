<?php

namespace Application\Model;

use Zend_Db_Table;

use InvalidArgumentException;

class PictureItem
{
    private $table;

    public function __construct()
    {
        $this->table = new Zend_Db_Table([
            'name'    => 'picture_item',
            'primary' => ['picture_id', 'item_id']
        ]);
    }

    /**
     * @param int $pictureId
     * @param int $itemId
     * @throws InvalidArgumentException
     * @return \Zend_Db_Table_Row_Abstract|NULL
     */
    private function getRow($pictureId, $itemId)
    {
        $pictureId = (int)$pictureId;
        $itemId = (int)$itemId;

        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->table->fetchRow([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $itemId
        ]);

        return $row;
    }

    public function add($pictureId, $itemId)
    {
        $pictureId = (int)$pictureId;
        $itemId = (int)$itemId;

        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $itemId);

        if (! $row) {
            $row = $this->table->createRow([
                'picture_id' => $pictureId,
                'item_id'    => $itemId
            ]);
            $row->save();
        }
    }

    public function remove($pictureId, $itemId)
    {
        $pictureId = (int)$pictureId;
        $itemId = (int)$itemId;

        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $itemId);
        if ($row) {
            $row->delete();
        }
    }

    public function isExists($pictureId, $itemId)
    {
        return (bool)$this->getRow($pictureId, $itemId);
    }

    public function changePictureItem($pictureId, $oldItemId, $newItemId)
    {
        $newItemId = (int)$newItemId;

        if (! $newItemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $oldItemId);

        if (! $row) {
            throw new \Exception("Item not found");
        }

        $row->item_id = $newItemId;
        $row->save();
    }

    public function setPictureItems($pictureId, array $itemIds)
    {
        $pictureId = (int)$pictureId;

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
                $row = $this->table->createRow([
                    'picture_id' => $pictureId,
                    'item_id'    => $itemId
                ]);
                $row->save();
            }
        }

        $filter = [
            'picture_id = ?' => $pictureId
        ];
        if ($itemIds) {
            $filter['item_id not in (?)'] = $itemIds;
        }

        $this->table->delete($filter);
    }

    public function getPictureItems($pictureId)
    {
        $db = $this->table->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from($this->table->info('name'), 'item_id')
                ->where('picture_id = ?', $pictureId)
        );
    }
    
    public function getPictureItemsByType($pictureId, $type)
    {
        $db = $this->table->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from($this->table->info('name'), 'item_id')
                ->where('picture_id = ?', $pictureId)
                ->join('cars', 'picture_item.item_id = cars.id', null)
                ->where('cars.item_type_id = ?', $type)
        );
    }

    public function getData(array $options)
    {
        $defaults = [
            'picture'      => null,
            'item'         => null,
            'onlyWithArea' => false
        ];
        $options = array_replace($defaults, $options);

        $db = $this->table->getAdapter();

        $select = $db->select()
            ->from($this->table->info('name'), [
                'picture_id', 'item_id',
                'crop_left', 'crop_top', 'crop_width', 'crop_height'
            ]);

        if ($options['onlyWithArea']) {
            $select->where('crop_left and crop_top and crop_width and crop_height');
        }

        if ($options['picture']) {
            $select->where('picture_id = ?', $options['picture']);
        }

        if ($options['item']) {
            $select->where('item_id = ?', $options['item']);
        }

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $area = null;
            if ($row['crop_left'] && $row['crop_top'] && $row['crop_width'] && $row['crop_height']) {
                $area = [
                    (int)$row['crop_left'],  (int)$row['crop_top'],
                    (int)$row['crop_width'], (int)$row['crop_height'],
                ];
            }

            $result[] = [
                'picture_id' => $row['picture_id'],
                'item_id'    => $row['item_id'],
                'area'       => $area
            ];
        }

        return $result;
    }

    public function setProperties($pictureId, $itemId, array $properties)
    {
        $row = $this->getRow($pictureId, $itemId);
        if ($row) {
            if (array_key_exists('perspective', $properties)) {
                $perspective = $properties['perspective'];
                $row->perspective_id = $perspective ? (int)$perspective : null;
            }

            if (array_key_exists('area', $properties)) {
                $area = $properties['area'];
                if ($area) {
                    $row->setFromArray([
                        'crop_left'   => $area['left'],
                        'crop_top'    => $area['top'],
                        'crop_width'  => $area['width'],
                        'crop_height' => $area['height'],
                    ]);
                } else {
                    $row->setFromArray([
                        'crop_left'   => null,
                        'crop_top'    => null,
                        'crop_width'  => null,
                        'crop_height' => null,
                    ]);
                }
            }

            $row->save();
        }
    }

    public function getPerspective($pictureId, $itemId)
    {
        $row = $this->getRow($pictureId, $itemId);
        if (! $row) {
            return null;
        }

        return $row->perspective_id;
    }

    public function getArea($pictureId, $itemId)
    {
        $row = $this->getRow($pictureId, $itemId);
        if (! $row) {
            return null;
        }

        if (! $row->crop_left || ! $row->crop_top || ! $row->crop_width || ! $row->crop_height) {
            return null;
        }

        return [
            (int)$row->crop_left,  (int)$row->crop_top,
            (int)$row->crop_width, (int)$row->crop_height,
        ];
    }
}
