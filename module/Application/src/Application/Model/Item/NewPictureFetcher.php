<?php

namespace Application\Model\Item;

use Application\Model\DbTable;

use Zend_Db_Expr;

class NewPictureFetcher extends PictureFetcher
{
    const COUNT = 6;

    private $pictureIds = [];

    public function setPictureIds(array $pictureIds)
    {
        $this->pictureIds = $pictureIds;
    }

    public function fetch(array $item, array $options = [])
    {
        $pictureTable = $this->getPictureTable();

        $select = $pictureTable->select();

        $select = $this->getPictureSelect($item['id'], [
            'ids'   => $this->pictureIds,
            'limit' => 6,
            'acceptedSort' => true
        ]);

        $db = $pictureTable->getAdapter();
        $db->fetchRow($select);

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $result[] = [
                'format' => 'picture-thumb',
                'row'    => $row,
            ];
        }

        return $result;
    }

    public function getTotalPictures(array $itemIds, $onlyExactly)
    {
        $result = [];
        foreach ($itemIds as $itemId) {
            $result[$itemId] = null;
        }
        if (count($itemIds)) {
            $pictureTable = $this->getPictureTable();
            $pictureTableAdapter = $pictureTable->getAdapter();

            $select = $pictureTableAdapter->select()
                ->from($pictureTable->info('name'), ['picture_item.item_id', new Zend_Db_Expr('COUNT(1)')])
                ->where('pictures.id IN (?)', $this->pictureIds)
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('picture_item.item_id IN (?)', $itemIds)
                ->group('picture_item.item_id');

            $result = array_replace($result, $pictureTableAdapter->fetchPairs($select));
        }
        return $result;
    }
}
