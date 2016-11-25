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

    public function add($pictureId, $itemId)
    {
        $pictureId = (int)$pictureId;
        $itemId = (int)$itemId;

        if (!$pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (!$itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->table->fetchRow([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $itemId
        ]);

        if (!$row) {
            $row = $this->table->createRow([
                'picture_id' => $pictureId,
                'item_id'    => $itemId
            ]);
            $row->save();
        }
    }

    public function changePictureItem($pictureId, $oldItemId, $newItemId)
    {
        $pictureId = (int)$pictureId;
        $oldItemId = (int)$oldItemId;
        $newItemId = (int)$newItemId;

        if (!$pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (!$oldItemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        if (!$newItemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->table->fetchRow([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $oldItemId
        ]);

        if (!$row) {
            throw new \Exception("Item not found");
        }

        $row->item_id = $newItemId;
        $row->save();
    }

    public function setPictureItems($pictureId, array $itemIds)
    {
        $pictureId = (int)$pictureId;

        if (!$pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        foreach ($itemIds as &$itemId) {
            $itemId = (int)$itemId;
            if (!$itemId) {
                throw new InvalidArgumentException("Item id is invalid");
            }
        }
        unset($itemId);

        foreach ($itemIds as $itemId) {
            $row = $this->table->fetchRow([
                'picture_id = ?' => $pictureId,
                'item_id = ?'    => $itemId
            ]);

            if (!$row) {
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

    public function setProperties($pictureId, $itemId, array $properties)
    {
        $pictureId = (int)$pictureId;
        $itemId = (int)$itemId;

        if (!$pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (!$itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->table->fetchRow([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $itemId
        ]);
        if ($row) {
            if (array_key_exists('perspective', $properties)) {
                $perspective = $properties['perspective'];
                $row->perspective_id = $perspective ? (int)$perspective : null;
            }

            if (array_key_exists('crop', $properties)) {
                $crop = $properties['crop'];
                if ($crop) {
                    $row->setFromArray([
                        'crop_left'   => $crop['left'],
                        'crop_top'    => $crop['top'],
                        'crop_width'  => $crop['width'],
                        'crop_height' => $crop['height'],
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
}
