<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class Article extends Zend_Db_Table
{
    const IMAGES_CAT_PATH = 'img/articles/',
          PREVIEW_CAT_PATH = 'img/articles/preview/',
          PREVIEW_WIDTH = 80,
          PREVIEW_HEIGHT = 80;

    protected $_name = 'articles';
    protected $_rowClass = \Application\Model\DbTable\Article\Row::class;

    protected $_referenceMap = [
        'Author' => [
            'columns'       => ['author_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
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
