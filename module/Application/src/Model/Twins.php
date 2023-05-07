<?php

namespace Application\Model;

use Exception;
use Laminas\Db\Sql;

use function array_replace;

class Twins
{
    private Brand $brand;

    public function __construct(Brand $brand) {
        $this->brand = $brand;
    }

    /**
     * @throws Exception
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
}
