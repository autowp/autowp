<?php 

namespace Application\Model;

use Project_Db_Table;
use Picture;

use Zend_Db_Expr;

class CarOfDay
{
    /**
     * @var Project_Db_Table
     */
    private $_table;
    
    public function __construct()
    {
        $this->_table = new Project_Db_Table([
            'name'    => 'of_day',
            'primary' => 'day_date'
        ]);
    }
    
    public function pick()
    {
        $dayRow = $this->_table->fetchRow([
            'day_date = CURDATE()'
        ]);
        
        if (!$dayRow) {
            $dayRow = $this->_table->createRow([
                'day_date' => new Zend_Db_Expr('CURDATE()')
            ]);
        }
        
        if (!$dayRow['car_id']) {
            $db = $this->_table->getAdapter();
            $sql =  '
                SELECT c.id, count(p.id) AS p_count
                FROM cars AS c
                    INNER JOIN car_parent_cache AS cpc ON c.id=cpc.parent_id
                    INNER JOIN pictures AS p ON cpc.car_id=p.car_id
                WHERE p.type=? AND p.status=?
                    AND (c.begin_year AND c.end_year OR c.begin_model_year AND c.end_model_year)
                    AND c.id NOT IN (SELECT car_id FROM of_day WHERE car_id)
                GROUP BY c.id
                HAVING p_count >= 5
                ORDER BY RAND()
                LIMIT 1
            ';
            $row = $db->fetchRow($sql, [Picture::CAR_TYPE_ID, Picture::STATUS_ACCEPTED]);
            if ($row) {
                print $row['id']  ."\n";
                
                $dayRow->car_id = $row['id'];
                $dayRow->save();
            }
        }
    }
    
    public function getCurrent()
    {
        $row = $this->_table->fetchRow(array(
            'day_date <= CURDATE()'
        ), 'day_date DESC');
        
        return $row ? $row->car_id : null;
    }
}