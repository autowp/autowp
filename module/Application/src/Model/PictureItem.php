<?php

namespace Application\Model;

use ArrayObject;
use Exception;
use InvalidArgumentException;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_key_exists;
use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function in_array;

class PictureItem
{
    public const
        PICTURE_CONTENT    = 1,
        PICTURE_AUTHOR     = 2,
        PICTURE_COPYRIGHTS = 3;

    private TableGateway $table;

    private TableGateway $itemTable;

    private TableGateway $pictureTable;

    public function __construct(TableGateway $table, TableGateway $itemTable, TableGateway $pictureTable)
    {
        $this->table        = $table;
        $this->itemTable    = $itemTable;
        $this->pictureTable = $pictureTable;
    }

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    private function getRow(int $pictureId, int $itemId, int $type)
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        return currentFromResultSetInterface($this->table->select([
            'picture_id' => $pictureId,
            'item_id'    => $itemId,
            'type'       => $type,
        ]));
    }

    public function add(int $pictureId, int $itemId, int $type): void
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        if (! $this->isAllowedTypeByItemId($itemId, $type)) {
            throw new InvalidArgumentException("Combination not allowed");
        }

        $params = [
            'picture_id' => $pictureId,
            'item_id'    => $itemId,
            'type'       => $type,
        ];
        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();
        $stmt    = $adapter->createStatement('
            INSERT IGNORE INTO picture_item (picture_id, item_id, type)
            VALUES (:picture_id, :item_id, :type)
        ');
        $result  = $stmt->execute($params);

        if ($result->getAffectedRows() > 0) {
            $this->updateContentCount($pictureId);
        }
    }

    public function remove(int $pictureId, int $itemId, int $type): void
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        if (! $itemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $this->table->delete([
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $itemId,
            'type'           => $type,
        ]);

        $this->updateContentCount($pictureId);
    }

    public function isExists(int $pictureId, int $itemId, int $type): bool
    {
        return (bool) $this->getRow($pictureId, $itemId, $type);
    }

    public function changePictureItem(int $pictureId, int $type, int $oldItemId, int $newItemId): void
    {
        if (! $newItemId) {
            throw new InvalidArgumentException("Item id is invalid");
        }

        $row = $this->getRow($pictureId, $oldItemId, $type);

        if (! $row) {
            throw new Exception("Item not found");
        }

        if (! $this->isAllowedTypeByItemId($newItemId, $type)) {
            throw new InvalidArgumentException("Combination not allowed");
        }

        $this->table->update([
            'item_id' => $newItemId,
        ], [
            'type'           => $type,
            'picture_id = ?' => $pictureId,
            'item_id = ?'    => $oldItemId,
        ]);
    }

    public function setPictureItems(int $pictureId, int $type, array $itemIds): void
    {
        if (! $pictureId) {
            throw new InvalidArgumentException("Picture id is invalid");
        }

        foreach ($itemIds as &$itemId) {
            $itemId = (int) $itemId;
            if (! $itemId) {
                throw new InvalidArgumentException("Item id is invalid");
            }
        }
        unset($itemId);

        foreach ($itemIds as $itemId) {
            $row = $this->getRow($pictureId, $itemId, $type);

            if (! $row) {
                if (! $this->isAllowedTypeByItemId($itemId, $type)) {
                    throw new InvalidArgumentException("Combination not allowed");
                }

                $this->table->insert([
                    'picture_id' => $pictureId,
                    'item_id'    => $itemId,
                    'type'       => $type,
                ]);
            }
        }

        $filter = [
            'picture_id = ?' => $pictureId,
            'type'           => $type,
        ];
        if ($itemIds) {
            $filter[] = new Sql\Predicate\NotIn('item_id', $itemIds);
        }

        $this->table->delete($filter);

        $this->updateContentCount($pictureId);
    }

    /**
     * @return int[]
     */
    public function getPictureItems(int $pictureId, int $type): array
    {
        $rows = $this->table->select([
            'picture_id' => $pictureId,
            'type'       => $type,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = (int) $row['item_id'];
        }

        return $result;
    }

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getPictureItemData(int $pictureId, int $itemId, int $type)
    {
        return currentFromResultSetInterface($this->table->select([
            'picture_id' => $pictureId,
            'item_id'    => $itemId,
            'type'       => $type,
        ]));
    }

    public function getPictureItemsData(int $pictureId, int $type = 0): array
    {
        $filter = [
            'picture_id' => $pictureId,
        ];

        if ($type) {
            $filter['type'] = $type;
        }

        $rows = $this->table->select($filter);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function getPictureItemsByItemType(int $pictureId, array $itemType): array
    {
        $select = $this->table->getSql()->select();
        $select->columns(['item_id', 'type'])
            ->join('item', 'picture_item.item_id = item.id', [])
            ->where([
                'picture_item.picture_id' => $pictureId,
                'picture_item.type'       => self::PICTURE_CONTENT,
                new Sql\Predicate\In('item.item_type_id', $itemType),
            ]);

        $rows = $this->table->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'item_id' => (int) $row['item_id'],
                'type'    => (int) $row['type'],
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function getData(array $options): array
    {
        $defaults = [
            'picture'      => null,
            'item'         => null,
            'onlyWithArea' => false,
        ];
        $options  = array_replace($defaults, $options);

        $select = $this->table->getSql()->select();

        $select->columns([
            'picture_id',
            'item_id',
            'type',
            'crop_left',
            'crop_top',
            'crop_width',
            'crop_height',
        ]);

        if ($options['onlyWithArea']) {
            $select->where([
                'type' => self::PICTURE_CONTENT,
                'crop_width and crop_height',
            ]);
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
            if ($row['crop_width'] && $row['crop_height']) {
                $area = [
                    (int) $row['crop_left'],
                    (int) $row['crop_top'],
                    (int) $row['crop_width'],
                    (int) $row['crop_height'],
                ];
            }

            $result[] = [
                'picture_id' => (int) $row['picture_id'],
                'item_id'    => (int) $row['item_id'],
                'type'       => (int) $row['type'],
                'area'       => $area,
            ];
        }

        return $result;
    }

    public function setProperties(int $pictureId, int $itemId, int $type, array $properties): void
    {
        $row = $this->getRow($pictureId, $itemId, $type);
        if (! $row) {
            return;
        }

        $set = [];

        if ($type === self::PICTURE_CONTENT) {
            if (array_key_exists('perspective', $properties)) {
                $perspective           = $properties['perspective'];
                $set['perspective_id'] = $perspective ? (int) $perspective : null;
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
        }

        if ($set) {
            $this->table->update($set, [
                'picture_id = ?' => $pictureId,
                'item_id = ?'    => $itemId,
                'type'           => $type,
            ]);
        }
    }

    public function getPerspective(int $pictureId, int $itemId): ?int
    {
        $row = $this->getRow($pictureId, $itemId, self::PICTURE_CONTENT);
        if (! $row) {
            return null;
        }

        return (int) $row['perspective_id'];
    }

    public function getArea(int $pictureId, int $itemId): ?array
    {
        $row = $this->getRow($pictureId, $itemId, self::PICTURE_CONTENT);
        if (! $row) {
            return null;
        }

        if (! $row['crop_left'] || ! $row['crop_top'] || ! $row['crop_width'] || ! $row['crop_height']) {
            return null;
        }

        return [
            (int) $row['crop_left'],
            (int) $row['crop_top'],
            (int) $row['crop_width'],
            (int) $row['crop_height'],
        ];
    }

    public function isAllowedType(int $itemTypeId, int $type): bool
    {
        $allowed = [
            Item::BRAND     => [self::PICTURE_CONTENT, self::PICTURE_COPYRIGHTS],
            Item::CATEGORY  => [self::PICTURE_CONTENT],
            Item::ENGINE    => [self::PICTURE_CONTENT],
            Item::FACTORY   => [self::PICTURE_CONTENT],
            Item::VEHICLE   => [self::PICTURE_CONTENT],
            Item::TWINS     => [self::PICTURE_CONTENT],
            Item::MUSEUM    => [self::PICTURE_CONTENT],
            Item::PERSON    => [self::PICTURE_CONTENT, self::PICTURE_AUTHOR, self::PICTURE_COPYRIGHTS],
            Item::COPYRIGHT => [self::PICTURE_COPYRIGHTS],
        ];

        if (! isset($allowed[$itemTypeId])) {
            return false;
        }

        return in_array($type, $allowed[$itemTypeId]);
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    public function isAllowedTypeByItemId(int $itemId, int $type): bool
    {
        $select = $this->itemTable->getSql()->select()
            ->columns(['item_type_id'])
            ->where(['id' => $itemId]);

        $row = currentFromResultSetInterface($this->itemTable->selectWith($select));
        if (! $row) {
            return false;
        }

        return $this->isAllowedType($row['item_type_id'], $type);
    }

    public function getTable(): TableGateway
    {
        return $this->table;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     * @throws Exception
     */
    public function updateContentCount(int $pictureId): void
    {
        $select = $this->table->getSql()->select()
            ->columns(['count' => new Sql\Expression('COUNT(1)')])
            ->where([
                'picture_id' => $pictureId,
                'type'       => self::PICTURE_CONTENT,
            ]);

        $row   = currentFromResultSetInterface($this->table->selectWith($select));
        $count = $row ? $row['count'] : 0;

        $this->pictureTable->update([
            'content_count' => $count,
        ], [
            'id' => $pictureId,
        ]);
    }
}
