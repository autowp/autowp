<?php

class Voting_Variant_Vote extends Project_Db_Table
{
	protected $_name = 'voting_variant_vote';
	protected $_primary = array('voting_variant_id', 'user_id');
	protected $_referenceMap	= array(
		'Voting_Variant' => array(
			'columns'		=>	array('voting_variant_id'),
			'refTableClass'	=>	'Voting_Variant',
			'refColumns'	=>	array('id')
		),
		'User' => array(
			'columns'		=>	array('user_id'),
			'refTableClass'	=>	'Users',
			'refColumns'	=>	array('id')
		)
	);
}