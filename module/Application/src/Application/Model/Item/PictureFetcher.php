<?php

namespace Application\Model\Item;

use Application\Model\DbTable;

abstract class PictureFetcher
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;
    
    /**
     * @var boolean
     */
    protected $dateSort;
    
    abstract public function fetch(array $item, array $options = []);
    
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
    
    /**
     * @return DbTable\Picture
     */
    protected function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new DbTable\Picture();
    }
    
    protected function getPictureSelect($carId, array $options)
    {
        $defaults = [
            'perspectiveGroup'    => false,
            'type'                => null,
            'exclude'             => [],
            'excludeItems'        => null,
            'dateSort'            => false,
            'onlyChilds'          => null,
            'onlyExactlyPictures' => false
        ];
        $options = array_merge($defaults, $options);
    
        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();
        $select = $db->select()
            ->from(
                $pictureTable->info('name'),
                [
                    'id', 'name', 'type', 'brand_id', 'factory_id',
                    'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity'
                ]
            )
            ->join(
                'picture_item',
                'pictures.id = picture_item.picture_id',
                ['perspective_id', 'item_id']
            )
            ->where('pictures.status IN (?)', [
                DbTable\Picture::STATUS_ACCEPTED,
                DbTable\Picture::STATUS_NEW
            ])
            ->limit(1);
    
        $order = [];

        if ($options['onlyExactlyPictures']) {
            $select->where('picture_item.item_id = ?', $carId);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->join('cars', 'picture_item.item_id = cars.id', null)
                ->where('item_parent_cache.parent_id = ?', $carId);

            $order[] = 'cars.is_concept asc';
            $order[] = 'item_parent_cache.sport asc';
            $order[] = 'item_parent_cache.tuning asc';

            if (isset($options['type'])) {
                switch ($options['type']) {
                    case DbTable\Vehicle\ParentTable::TYPE_DEFAULT:
                        break;
                    case DbTable\Vehicle\ParentTable::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case DbTable\Vehicle\ParentTable::TYPE_SPORT:
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
                    null
                )
                ->where('mp.group_id = ?', $options['perspectiveGroup']);

                $order[] = 'mp.position';
        }

        if ($options['exclude']) {
            $select->where('pictures.id not in (?)', $options['exclude']);
        }

        if ($options['excludeItems']) {
            $select->where('picture_item.item_id not in (?)', $options['excludeItems']);
        }

        if ($options['dateSort']) {
            $select->join(['picture_car' => 'cars'], 'cars.id = picture_car.id', null);
            $order = array_merge($order, ['picture_car.begin_order_cache', 'picture_car.end_order_cache']);
        }
        $order = array_merge($order, ['pictures.width DESC', 'pictures.height DESC']);

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
                    null
                )
                ->where('cpc_oc.parent_id IN (?)', $options['onlyChilds']);
        }

        return $select;
    }
}
