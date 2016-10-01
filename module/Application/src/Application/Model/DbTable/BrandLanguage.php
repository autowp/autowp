<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class BrandLanguage extends Zend_Db_Table
{
    protected $_name = 'brand_language';
    protected $_primary = ['brand_id', 'language'];
}