<?php

class Museum extends Project_Db_Table
{
    protected $_name = 'museum';
    protected $_primary = 'id';

    protected $_images = array(
        'image' => array('dir' => 'img/museums')
    );
}