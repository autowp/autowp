<?php

use Application\Db\Table;

class Modification_Picture extends Table
{
    protected $_name = 'modification_picture';
    protected $_primary = ['modification_id', 'picture_id'];
}