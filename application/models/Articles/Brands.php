<?php

class Articles_Brands extends Zend_Db_Table
{
    protected $_primary = ['article_id', 'brand_id'];
    protected $_name = 'articles_brands';
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