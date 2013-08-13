<?php

class Perspective_Language extends Zend_Db_Table
{
    protected $_name = 'perspective_language';
    protected $_primary = array('perspective_id', 'language');
}