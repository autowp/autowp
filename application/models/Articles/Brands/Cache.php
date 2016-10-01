<?php

class Articles_Brands_Cache extends Zend_Db_Table
{
    protected $_primary = ['article_id', 'brand_id'];
    protected $_name = 'articles_brands_cache';
    protected $_referenceMap = [
        'Article' => [
            'columns'       => ['article_id'],
            'refTableClass' => 'Articles',
            'refColumns'    => ['id']
        ],
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ]
    ];
}