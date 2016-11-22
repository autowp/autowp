<?php

namespace Application\Db;

use Zend_Db_Table;

class Table extends Zend_Db_Table
{
    protected $_rowClass = Table\Row::class;
}
