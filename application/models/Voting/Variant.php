<?php

class Voting_Variant extends Project_Db_Table
{
    protected $_name = 'voting_variant';
    protected $_primary = 'id';
    protected $_referenceMap    = array(
        'Voting' => array(
            'columns'           => array('voting_id'),
            'refTableClass'     => 'Voting',
            'refColumns'        => array('id')
        )
    );
}