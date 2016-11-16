<?php

namespace Application\Model\DbTable\Picture;

use Application\Db\Table;
use Application\Model\DbTable\Picture\Row as PictureRow;

class View extends Table
{
    protected $_name = 'picture_view';
    protected $_primary = 'picture_id';
    protected $_referenceMap = [
        'Picture' => [
            'columns'       => ['picture_id'],
            'refTableClass' => \Application\Model\DbTable\Picture::class,
            'refColumns'    => ['id']
        ]
    ];

    public function inc(PictureRow $picture)
    {
        $sql = '
            INSERT INTO picture_view (picture_id, views)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views=views+1
        ';
        $this->getAdapter()->query($sql, [
            $picture->id
        ]);

        /*$view = $this->fetchRow([
            'picture_id = ?'    =>    $picture->id
        ]);
        if ($view) {
            $view->views = new Zend_Db_Expr('views + 1');
            $view->save();
        } else {
            $view = $this->fetchNew();
            $view->setFromArray([
                'picture_id'    =>    $picture->id,
                'views'            =>    1
            ]);
            $view->save();
        }*/
    }

    /**
     * @param PictureRow $picture
     * @return int
     */
    public function get(PictureRow $picture)
    {
        $view = $this->fetchRow([
            'picture_id = ?' => $picture->id
        ]);

        return $view ? (int)$view->views : 0;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getValues(array $ids)
    {
        if (count($ids) <= 0) {
            return [];
        }

        $db = $this->getAdapter();

        return $db->fetchPairs(
            $db->select()
                ->from($this->info('name'), ['picture_id', 'views'])
                ->where('picture_id in (?)', $ids)
        );
    }
}
