<?php

namespace Application\Model\DbTable;

use Project_Db_Table;

class BrandLink extends Project_Db_Table
{
    protected $_name = 'links';
    protected $_primary = 'id';
    
    protected $_referenceMap = array(
        'Brand' => array(
            'columns'       => array('brandId'),
            'refTableClass' => 'Brands',
            'refColumns'    => array('id')
        )
    );
}