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
        $totalBrands = $this->table->getAdapter()->fetchOne($sql);
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
    
    private function _utfCharToNumber($char) {
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
        $db = $this->table->getAdapter();
        
        $select = $db->select()
            ->from($this->table->info('name'), [
                'id', 'folder', 'img',
                'name' => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)',
                'cars_count' => $this->countExpr(),
                'new_cars_count' => new Zend_Db_Expr('COUNT(IF(cars.add_datetime > DATE_SUB(NOW(), INTERVAL :new_days DAY), 1, NULL))'),
                'carpictures_count', 'enginepictures_count', 
                'logopictures_count', 'mixedpictures_count',
                'unsortedpictures_count'
            ])
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
            ->join('cars', 'car_parent_cache.car_id = cars.id', null)
            ->group('brands.id')
            ->order(['brands.position', 'name'])
            ->bind([
                'language' => $language,
                'new_days' => self::NEW_DAYS
            ]);
        
        $result = [];
        
        foreach ($db->fetchAll($select) as $row) {
       
            $name = $row['name'];
        
            $char = str_replace('Š', 'S', mb_strtoupper(mb_substr($name, 0, 1)));
            $char = str_replace('Ö', 'O', $char);
            $char = str_replace('Ẽ', 'E', $char);
        
            if (!isset($result[$char])) {
                $result[$char] = [
                    'id'     => $this->_utfCharToNumber($char),
                    'brands' => []
                ];
            }
            
            $picturesCount = $row['carpictures_count'] + $row['enginepictures_count'] + 
                $row['logopictures_count'] + $row['mixedpictures_count'] + 
                $row['unsortedpictures_count'];
        
            $result[$char]['brands'][] = [
                'id'             => $row['id'],
                'name'           => $name,
                'catname'        => $row['folder'],
                'img'            => $row['img'],
                'totalPictures'  => $picturesCount,
                'newCars'        => $row['new_cars_count'],
                'totalCars'      => $row['cars_count']
            ];
        }
        
        return $result;
    }
}