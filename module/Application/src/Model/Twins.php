<?php

namespace Application\Model;

use Exception;
use Laminas\Db\Sql;
use Laminas\Paginator\Paginator;

use function array_replace;

class Twins
{
    private Picture $picture;

    private Item $item;

    private Brand $brand;

    public function __construct(
        Picture $picture,
        Item $item,
        Brand $brand
    ) {
        $this->picture = $picture;
        $this->item    = $item;
        $this->brand   = $brand;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function getBrands(array $options): array
    {
        $defaults = [
            'language' => 'en',
            'limit'    => null,
        ];
        $options  = array_replace($defaults, $options);

        $limit = $options['limit'];

        return $this->brand->getList([
            'language' => $options['language'],
            'columns'  => [
                'count'     => new Sql\Expression('count(distinct twins.id)'),
                'new_count' => new Sql\Expression(
                    'count(distinct if(twins.add_datetime > date_sub(NOW(), INTERVAL 7 DAY), twins.id, null))'
                ),
            ],
        ], function (Sql\Select $select) use ($limit): void {
            $select
                ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', [])
                ->join('item_parent', 'ipc1.item_id = item_parent.item_id', [])
                ->join(['twins' => 'item'], 'item_parent.parent_id = twins.id', [])
                ->where(['twins.item_type_id' => Item::TWINS])
                ->group('item.id');

            if ($limit > 0) {
                $select
                    ->order('count desc')
                    ->limit($limit);
            }
        });
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getGroupsPicturesCount(array $groupIds): array
    {
        if (! $groupIds) {
            return [];
        }

        $select = $this->picture->getTable()->getSql()->select();

        $select->columns(['count' => new Sql\Expression('COUNT(DISTINCT pictures.id)')])
            ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', ['parent_id'])
            ->where([
                'pictures.status' => Picture::STATUS_ACCEPTED,
                new Sql\Predicate\In('item_parent_cache.parent_id', $groupIds),
            ])
            ->group('item_parent_cache.parent_id');

        $result = [];
        foreach ($this->picture->getTable()->selectWith($select) as $row) {
            $result[(int) $row['parent_id']] = (int) $row['count'];
        }

        return $result;
    }

    public function getGroupBrandIds(int $groupId): array
    {
        return $this->item->getIds([
            'item_type_id'       => Item::BRAND,
            'descendant_or_self' => [
                'parent' => [
                    'id'           => $groupId,
                    'item_type_id' => Item::TWINS,
                ],
            ],
        ]);
    }

    public function getTotalBrandsCount(): int
    {
        return $this->item->getCountDistinct([
            'item_type_id'       => Item::BRAND,
            'descendant_or_self' => [
                'parent' => [
                    'item_type_id' => Item::TWINS,
                ],
            ],
        ]);
    }

    public function getGroupsPaginator(int $brandId = 0): Paginator
    {
        $filter = [
            'item_type_id' => Item::TWINS,
            'order'        => 'item.add_datetime desc',
        ];

        if ($brandId) {
            $filter['child'] = [
                'ancestor_or_self' => [
                    'item_type_id' => Item::BRAND,
                    'id'           => $brandId,
                ],
            ];
        }

        return $this->item->getPaginator($filter);
    }

    public function getGroupCars(int $groupId): array
    {
        return $this->item->getRows([
            'parent' => $groupId,
            'order'  => 'name',
        ]);
    }

    /**
     * @throws Exception
     */
    public function getGroup(int $groupId): ?array
    {
        $row = $this->item->getRow([
            'id'           => $groupId,
            'item_type_id' => Item::TWINS,
        ]);
        if (! $row) {
            return null;
        }

        return [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'begin_year' => $row['begin_year'],
            'end_year'   => $row['end_year'],
            'today'      => $row['today'],
        ];
    }

    public function getCarGroups(int $itemId): array
    {
        $rows = $this->item->getRows([
            'item_type_id'       => Item::TWINS,
            'descendant_or_self' => $itemId,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
            ];
        }

        return $result;
    }

    public function getCarsGroups(array $itemIds, string $language): array
    {
        if (! $itemIds) {
            return [];
        }

        $rows = $this->item->getRows([
            'language'           => $language,
            'columns'            => ['id', 'name'],
            'item_type_id'       => Item::TWINS,
            'descendant_or_self' => [
                'id'      => $itemIds,
                'columns' => ['item_id' => 'id'],
            ],
        ]);

        $result = [];
        foreach ($itemIds as $itemId) {
            $result[(int) $itemId] = [];
        }
        foreach ($rows as $row) {
            $itemId            = (int) $row['item_id'];
            $result[$itemId][] = [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
            ];
        }

        return $result;
    }
}
