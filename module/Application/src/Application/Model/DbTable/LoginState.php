<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class LoginState extends Zend_Db_Table
{
    protected $_name = 'login_state';
    protected $_primary = 'state';
}