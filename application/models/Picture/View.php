<?php

class Picture_View extends Project_Db_Table
{
    protected $_name = 'picture_view';
    protected $_primary = 'picture_id';
    protected $_referenceMap    = array(
        'Picture' => array(
            'columns'       => array('picture_id'),
            'refTableClass' => 'Picture',
            'refColumns'    => array('id')
        )
    );

    public function inc(Picture_Row $picture)
    {
        $sql = '
            INSERT INTO picture_view (picture_id, views)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views=views+1
        ';
        $this->getAdapter()->query($sql, array(
            $picture->id
        ));

        /*$view = $this->fetchRow(array(
            'picture_id = ?'    =>    $picture->id
        ));
        if ($view) {
            $view->views = new Zend_Db_Expr('views + 1');
            $view->save();
        } else {
            $view = $this->fetchNew();
            $view->setFromArray(array(
                'picture_id'    =>    $picture->id,
                'views'            =>    1
            ));
            $view->save();
        }*/
    }

    /**
     * @param Picture_Row $picture
     * @return int
     */
    public function get(Picture_Row $picture)
    {
        $view = $this->fetchRow(array(
            'picture_id = ?' => $picture->id
        ));

        return $view ? (int)$view->views : 0;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getValues(array $ids)
    {
        if (count($ids) <= 0) {
            return array();
        }

        $db = $this->getAdapter();

        return $db->fetchPairs(
            $db->select()
                ->from($this->info('name'), array('picture_id', 'views'))
                ->where('picture_id in (?)', $ids)
        );
    }
}