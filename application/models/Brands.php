<?php

use Autowp\Filter\Filename\Safe;

class Brands extends Project_Db_Table
{
    protected $_name = 'brands';
    protected $_primary = 'id';
    protected $_rowClass = 'Brands_Row';
    protected $_referenceMap = array(
        'Type' => array(
            'columns'       => array('type_id'),
            'refTableClass' => 'Brand_Types',
            'refColumns'    => array('id')
        )
    );

    /**
     * @param string $catname
     * @return Brands_Row
     */
    public function findRowByCatname($catname)
    {
        return $this->fetchRow(array(
            'folder = ?' => (string)$catname
        ));
    }

    /**
     * @param string $caption
     * @return Brands_Row
     */
    public function fetchRowByCaption($caption)
    {
        return $this->fetchRow(array(
            'caption = ?' => (string)$caption
        ));
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function insert(array $data)
    {
        $data['caption'] = trim($data['caption']);
        $data['group_id'] = null;
        $data['type_id'] = $data['type_id'];

        // generate folder name
        $filenameFilter = new Safe();
        $data['folder'] = $filenameFilter->filter($data['caption']);
        $data['position'] = 0;

        if (mb_strlen($data['caption']) > 50) {
            throw new Exception('Name is too long');
        }

        if ($this->fetchRow(array('folder = ?' => $data['folder']))) {
            throw new Exception('Folder ' . $data['folder'] . ' already exists');
        }

        return parent::insert($data);
    }

}