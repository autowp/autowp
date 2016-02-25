<?php

class Articles_Criterias_Votes extends Zend_Db_Table
{
    protected $_name = 'articles_criterias_votes';
    protected $_primary = array('article_id', 'criteria_id');
    protected $_referenceMap    = array(
        'Article' => array(
            'columns'           => array('article_id'),
            'refTableClass'     => 'Articles',
            'refColumns'        => array('id')
        ),
        'Criteria' => array(
            'columns'           => array('criteria_id'),
            'refTableClass'     => 'Articles_Votings_Criterias',
            'refColumns'        => array('id')
        )
    );
}