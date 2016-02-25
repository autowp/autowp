<?php

class Articles extends Zend_Db_Table
{
    const   IMAGES_CAT_PATH = 'img/articles/',
            PREVIEW_CAT_PATH = 'img/articles/preview/',
            PREVIEW_WIDTH = 80,
            PREVIEW_HEIGHT = 80;

    protected $_name = 'articles';
    protected $_rowClass = 'Articles_Row';
    protected $_referenceMap    = array(
        'Author' => array(
            'columns'           => array('author_id'),
            'refTableClass'     => 'Users',
            'refColumns'        => array('id')
        ),
        'Html' => array(
            'columns'           => array('html_id'),
            'refTableClass'     => 'Htmls',
            'refColumns'        => array('id')
        )
    );

    public function findRowByCatname($catname)
    {
        return $this->fetchRow(array(
            'catname = ?' => $catname
        ));
    }
}