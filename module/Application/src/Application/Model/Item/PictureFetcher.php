<?php

namespace Application\Model\Item;

use Zend\Db\Sql;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\PictureItem;

abstract class PictureFetcher
{
    /**
     * @var boolean
     */
    protected $dateSort;

    /**
     * @var Picture
     */
    protected $pictureModel;

    /**
     * @var Item
     */
    protected $itemModel;

    /**
     * @var int|null
     */
    private $pictureItemTypeId;

    abstract public function fetch($item, array $options = []);

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            $this->$method($value);
        }
    }

    /**
     * @param boolean $value
     * @return PictureFetcher
     */
    public function setDateSort($value)
    {
        $this->dateSort = (bool)$value;

        return $this;
    }

    public function setPictureModel(Picture $model)
    {
        $this->pictureModel = $model;

        return $this;
    }

    public function setItemModel(Item $item)
    {
        $this->itemModel = $item;

        return $this;
    }

    public function setPictureItemTypeId($value)
    {
        $this->pictureItemTypeId = $value;

        return $this;
    }

    protected function getPictureSelect($itemId, array $options)
    {
        $defaults = [
            'perspectiveGroup'    => false,
            'type'                => null,
            'exclude'             => [],
            'ids'                 => [],
            'excludeItems'        => null,
            'dateSort'            => false,
            'acceptedSort'        => false,
            'onlyChilds'          => null,
            'onlyExactlyPictures' => false,
            'limit'               => 1
        ];
        $options = array_merge($defaults, $options);

        $select = $this->pictureModel->getTable()->getSql()->select();
        $select
            ->columns([
                'id', 'name',
                'image_id', 'width', 'height', 'identity',
                'status', 'owner_id', 'filesize'
            ])
            ->join(
                'picture_item',
                'pictures.id = picture_item.picture_id',
                ['perspective_id', 'item_id']
            )
            ->where([
                'pictures.status'   => Picture::STATUS_ACCEPTED,
                'picture_item.type' => $this->pictureItemTypeId
                    ? $this->pictureItemTypeId
                    : PictureItem::PICTURE_CONTENT,
            ])
            ->limit($options['limit']);

        $order = [];

        if ($options['onlyExactlyPictures']) {
            $select->where(['picture_item.item_id' => $itemId]);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->join('item', 'picture_item.item_id = item.id', [])
                ->where(['item_parent_cache.parent_id' => $itemId]);

            $order[] = 'item.is_concept asc';
            $order[] = 'item_parent_cache.sport asc';
            $order[] = 'item_parent_cache.tuning asc';

            if (isset($options['type'])) {
                switch ($options['type']) {
                    case ItemParent::TYPE_DEFAULT:
                        break;
                    case ItemParent::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case ItemParent::TYPE_SPORT:
                        $select->where('item_parent_cache.sport');
                        break;
                }
            }
        }

        if ($options['perspectiveGroup']) {
            $select
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    []
                )
                ->where(['mp.group_id' => $options['perspectiveGroup']]);

                $order[] = 'mp.position';
        }

        if ($options['ids']) {
            $select->where([new Sql\Predicate\In('pictures.id', $options['ids'])]);
        }

        if ($options['exclude']) {
            $select->where([new Sql\Predicate\NotIn('pictures.id', $options['exclude'])]);
        }

        if ($options['excludeItems']) {
            $select->where([new Sql\Predicate\NotIn('picture_item.item_id', $options['excludeItems'])]);
        }

        if ($options['dateSort']) {
            $select->join(['picture_car' => 'item'], 'item.id = picture_car.id', []);
            $order = array_merge($order, ['picture_car.begin_order_cache', 'picture_car.end_order_cache']);
        }

        if ($options['acceptedSort']) {
            $order[] = 'pictures.accept_datetime DESC';
        }

        $order = array_merge(['pictures.content_count ASC'], $order, ['pictures.width DESC', 'pictures.height DESC']);

        $select->order($order);

        if ($options['onlyChilds']) {
            $select
                ->join(
                    ['pi_oc' => 'picture_item'],
                    'pi_oc.picture_id = pictures.id'
                )
                ->join(
                    ['cpc_oc' => 'item_parent_cache'],
                    'cpc_oc.item_id = pi_oc.item_id',
                    []
                )
                ->where([new Sql\Predicate\In('cpc_oc.parent_id', $options['onlyChilds'])]);
        }

        return $select;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param array $itemIds
     * @param bool $onlyExactly
     * @return array
     */
    public function getTotalPictures(array $itemIds, bool $onlyExactly)
    {
        $result = [];
        foreach ($itemIds as $itemId) {
            $result[$itemId] = null;
        }
        if (count($itemIds)) {
            $select = $this->pictureModel->getTable()->getSql()->select();

            $select->where(['pictures.status' => Picture::STATUS_ACCEPTED]);

            if ($onlyExactly) {
                $select
                    ->columns(['count' => new Sql\Expression('COUNT(1)')])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', ['id' => 'item_id'])
                    ->where([new Sql\Predicate\In('picture_item.item_id', $itemIds)])
                    ->group('picture_item.item_id');
            } else {
                $select
                    ->columns(['count' => new Sql\Expression('COUNT(1)')])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                    ->join(
                        'item_parent_cache',
                        'picture_item.item_id = item_parent_cache.item_id',
                        ['id' => 'parent_id']
                    )
                    ->where([new Sql\Predicate\In('item_parent_cache.parent_id', $itemIds)])
                    ->group('item_parent_cache.parent_id');
            }

            foreach ($this->pictureModel->getTable()->selectWith($select) as $row) {
                $result[(int)$row['id']] = (int)$row['count'];
            }
        }
        return $result;
    }
}
