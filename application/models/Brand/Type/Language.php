<?php

class Brand_Type_Language extends Zend_Db_Table
{
    protected $_name = 'brand_type_language';
    protected $_primary = array('brand_type_id', 'language');
}