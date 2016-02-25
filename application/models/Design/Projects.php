<?php

class Design_Projects extends Zend_Db_Table
{
    protected $_name = 'design_projects';
    protected $_rowClass = 'Design_Projects_Row';
    protected $_referenceMap    = array(
        'Brand' => array(
            'columns'           => array('brand_id'),
            'refTableClass'     => 'Brands',
            'refColumns'        => array('id')
        )
    );
}