<?php

namespace Application\Model\DbTable\Article;

use Zend_Db_Table;

class BrandCache extends Zend_Db_Table
{
    protected $_primary = ['article_id', 'brand_id'];
    protected $_name = 'articles_brands_cache';
    protected $_referenceMap = [
        'Article' => [
            'columns'       => ['article_id'],
            'refTableClass' => \Application\Model\DbTable\Article::class,
            'refColumns'    => ['id']
        ],
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ]
    ];
}