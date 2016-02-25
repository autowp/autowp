<?php

class Articles_Design_Projects extends Zend_Db_Table
{
    protected $_primary = array('article_id', 'design_project_id');
    protected $_name = 'articles_design_projects';
    protected $_referenceMap    = array(
        'Article' => array(
            'columns'           => array('article_id'),
            'refTableClass'     => 'Articles',
            'refColumns'        => array('id')
        ),
        'Design_Project' => array(
            'columns'           => array('design_project_id'),
            'refTableClass'     => 'Design_Projects',
            'refColumns'        => array('id')
        )
    );
}