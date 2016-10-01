<?php

class Articles extends Zend_Db_Table
{
    const   IMAGES_CAT_PATH = 'img/articles/',
            PREVIEW_CAT_PATH = 'img/articles/preview/',
            PREVIEW_WIDTH = 80,
            PREVIEW_HEIGHT = 80;

    protected $_name = 'articles';
    protected $_rowClass = 'Articles_Row';
    protected $_referenceMap = [
        'Author' => [
            'columns'       => ['author_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Html' => [
            'columns'       => ['html_id'],
            'refTableClass' => \Application\Model\DbTable\Html::class,
            'refColumns'    => ['id']
        ]
    ];

    public function findRowByCatname($catname)
    {
        return $this->fetchRow([
            'catname = ?' => $catname
        ]);
    }
}