<?php

namespace Application\Model;

use Application\Model\DbTable\Brand as Table;
use Zend_Db_Expr;

class Brand
{
    const TOP_COUNT = 150;

    const NEW_DAYS = 7;

    /**
     * @var Table
     */
    private $table;

    public function __construct()
    {
        $this->table = new Table();
    }

    public function getTotalCount()
    {
        $sql = 'SELECT COUNT(1) FROM brands';
        return $this->table->getAdapter()->fetchOne($sql);
    }

    private function countExpr()
    {
        $db = $this->table->getAdapter();

        return new Zend_Db_Expr('(' .
            '(' .
                $db->select()
                    ->from('cars', 'count(distinct cars.id)')
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = brands.id')
                    ->assemble() .
            ') + (' .
                $db->select()
                    ->from('brand_engine', 'count(engine_parent_cache.engine_id)')
                    ->where('brand_engine.brand_id = brands.id')
                    ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                    ->assemble() .
            ')' .
        ')');
    }

    public function getTopBrandsList($language)
    {
        $db = $this->table->getAdapter();

        $items = array();

        $select = $db->select(true)
            ->from('brands', [
                'id', 'folder',
                'name' => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)',
                'cars_count' => $this->countExpr()
            ])
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->where('brands.id <> 58') // exclude "other"
            ->group('brands.id')
            ->order('cars_count DESC')
            ->limit(self::TOP_COUNT)
            ->bind([
                'language' => $language
            ]);

        foreach ($db->fetchAll($select) as $brandRow) {
            $newCarsCount = $db->fetchOne(
                $db->select()
                    ->from('cars', 'count(distinct cars.id)')
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brandRow['id'])
                    ->where('cars.add_datetime > DATE_SUB(NOW(), INTERVAL ? DAY)', self::NEW_DAYS)
            );

            $items[] = array(
                'id'             => $brandRow['id'],
                'catname'        => $brandRow['folder'],
                'name'           => $brandRow['name'],
                'cars_count'     => $brandRow['cars_count'],
                'new_cars_count' => $newCarsCount
            );
        }

        usort($items, function($a, $b) {
            return strcoll($a['name'], $b['name']);
        });

        return $items;
    }

    private function utfCharToNumber($char)
    {
        $i = 0;
        $number = '';
        while (isset($char{$i})) {
            $number.= ord($char{$i});
            ++$i;
        }
        return $number;
    }

    public function getFullBrandsList($language)
    {
        $rows = $this->getList([
            'language' => $language,
            'columns'  => [
                'img',
                'cars_count' => $this->countExpr(),
                'new_cars_count' => new Zend_Db_Expr('COUNT(IF(cars.add_datetime > DATE_SUB(NOW(), INTERVAL :new_days DAY), 1, NULL))'),
                'carpictures_count', 'enginepictures_count',
                'logopictures_count', 'mixedpictures_count',
                'unsortedpictures_count'
            ]
        ], function($select) use ($language) {
            $select
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                ->group('brands.id')
                ->bind([
                    'language' => $language,
                    'new_days' => self::NEW_DAYS
                ]);
        });

        $result = [];

        foreach ($rows as $row) {

            $name = $row['name'];

            $char = str_replace('Š', 'S', mb_strtoupper(mb_substr($name, 0, 1)));
            $char = str_replace('Ö', 'O', $char);
            $char = str_replace('Ẽ', 'E', $char);

            if (!isset($result[$char])) {
                $result[$char] = [
                    'id'     => $this->utfCharToNumber($char),
                    'brands' => []
                ];
            }

            $picturesCount = $row['carpictures_count'] + $row['enginepictures_count'] +
                $row['logopictures_count'] + $row['mixedpictures_count'] +
                $row['unsortedpictures_count'];

            $result[$char]['brands'][] = [
                'id'             => $row['id'],
                'name'           => $name,
                'catname'        => $row['catname'],
                'img'            => $row['img'],
                'totalPictures'  => $picturesCount,
                'newCars'        => $row['new_cars_count'],
                'totalCars'      => $row['cars_count']
            ];
        }

        return $result;
    }

    public function getBrandIdByCatname($catname)
    {
        $db = $this->table->getAdapter();

        return $db->fetchOne(
            $db->select()
                ->from('brands', 'id')
                ->where('folder = ?', (string)$catname)
        );
    }

    private function fetchBrand($language, $callback)
    {
        $db = $this->table->getAdapter();

        $select = $db->select()
            ->from('brands', [
                'id', 'folder', 'type_id',
                'name' => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)',
                'full_caption', 'img', 'text_id'
            ])
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->bind([
                'language' => (string)$language
            ]);

        $callback($select);

        $brand = $db->fetchRow($select);

        if (!$brand) {
            return null;
        }

        return [
            'id'        => $brand['id'],
            'name'      => $brand['name'],
            'catname'   => $brand['folder'],
            'full_name' => $brand['full_caption'],
            'img'       => $brand['img'],
            'text_id'   => $brand['text_id'],
            'type_id'   => $brand['type_id']
        ];
    }

    public function getBrandById($id, $language)
    {
        return $this->fetchBrand($language, function($select) use ($id) {
            $select->where('brands.id = ?', (int)$id);
        });
    }

    public function getBrandByCatname($catname, $language)
    {
        return $this->fetchBrand($language, function($select) use ($catname) {
            $select->where('brands.folder = ?', (string)$catname);
        });
    }

    public function getFactoryBrandId($factoryId)
    {
        $brand = $this->table->fetchRow(
            $this->table->select(true)
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('factory_car', 'car_parent_cache.car_id = factory_car.car_id', null)
                ->where('factory_car.factory_id = ?', (int)$factoryId)
                ->group('brands.id')
                ->order(new Zend_Db_Expr('count(1) desc'))
        );
        if (!$brand) {
            return null;
        }

        return $brand->id;
    }

    public function getList($options, callable $callback)
    {
        if (is_string($options)) {
            $options = [
                'language' => $options
            ];
        }

        $defaults = [
            'language' => 'en',
            'columns'  => []
        ];
        $options = array_replace($defaults, $options);

        $db = $this->table->getAdapter();

        $columns = [
            'id',
            'type_id',
            'catname' => 'folder',
            'name'    => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)'
        ];
        foreach ($options['columns'] as $column => $expr) {
            switch ($expr) {
                case 'id':
                case 'type_id':
                case 'name':
                    break;
                case 'img':
                    $columns[] = 'img';
                    break;
                default:
                    if (is_numeric($column)) {
                        $columns[] = $expr;
                    } else {
                        $columns[$column] = $expr;
                    }
            }
        }
        //var_dump($columns);

        $select = $db->select()
            ->from('brands', $columns)
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->order(['brands.position', 'name'])
            ->bind([
                'language' => (string)$options['language']
            ]);

        $callback($select);

        $items = $db->fetchAll($select);

        return $items;
    }
}