<?php

class Image extends Project_Db_Table
{
    protected $_name = 'image';
    protected $_primary = 'id';
    protected $_rowClass = 'Image_Row';

    public function createRowFromFile($file)
    {
        list($width, $height, $type, $attr) = getimagesize($file);
        $width = (int)$width;
        $height = (int)$height;

        if ($width && $height) {
            $row = $this->fetchNew();

            //$row->set
        }
    }
}