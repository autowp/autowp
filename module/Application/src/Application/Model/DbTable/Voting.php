<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class Voting extends Table
{
    protected $_name = 'voting';
    protected $_primary = ['id'];
}
