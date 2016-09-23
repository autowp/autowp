<?php

use Application\Db\Table;

class Factory extends Table
{
    protected $_name = 'factory';
    protected $_primary = 'id';
    protected $_rowClass = 'Factory_Row';
}