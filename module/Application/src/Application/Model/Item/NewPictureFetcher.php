<?php

namespace Application\Model\Item;

use Zend\Db\Sql;

use Application\Model\Picture;

class NewPictureFetcher extends PictureFetcher
{
    const COUNT = 6;

    private $pictureIds = [];

    public function setPictureIds(array $pictureIds)
    {
        $this->pictureIds = $pictureIds;
    }

    public function fetch($item, array $options = [])
    {
        $select = $this->getPictureSelect($item['id'], [
            'ids'   => $this->pictureIds,
            'limit' => 6,
            'acceptedSort' => true
        ]);

        $rows = $this->pictureModel->getTable()->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'format' => 'picture-thumb',
                'row'    => $row,
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function getTotalPictures(array $itemIds, $onlyExactly)
    {
        $result = [];
        foreach ($itemIds as $itemId) {
            $result[$itemId] = null;
        }

        if (count($itemIds) <= 0) {
            return $result;
        }

        $select = $this->pictureModel->getTable()->getSql()->select();

        $select->columns(['count' => new Sql\Expression('COUNT(1)')])
            ->join('picture_item', 'pictures.id = picture_item.picture_id', ['item_id'])
            ->where([
                new Sql\Predicate\In('pictures.id', $this->pictureIds),
                'pictures.status' => Picture::STATUS_ACCEPTED,
                new Sql\Predicate\In('picture_item.item_id', $itemIds)
            ])
            ->group('picture_item.item_id');

        foreach ($this->pictureModel->getTable()->selectWith($select) as $row) {
            $result[(int)$row['item_id']] = (int)$row['count'];
        }

        return $result;
    }
}
