<?php

namespace Application\Model\DbTable\Modification;

use Application\Db\Table;

class Picture extends Table
{
    protected $_name = 'modification_picture';
    protected $_primary = ['modification_id', 'picture_id'];
}