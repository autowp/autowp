<?php

class Brand_Types extends Zend_Db_Table
{
    protected $_name = 'brand_types';
    protected $_primary = 'id';

    public function findByCatname($folder)
    {
        return $this->fetchAll(array(
            'catname = ?' => (string)$folder
        ));
    }

}