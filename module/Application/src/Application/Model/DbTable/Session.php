<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class Session extends Zend_Db_Table
{
    protected $_primary = 'id';
    protected $_name = 'session';
}