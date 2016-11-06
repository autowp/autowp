<?php

namespace Application\Model\DbTable;

use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Db\Table;
use Application\Model\DbTable\BrandRow;
use Application\Model\Brand as BrandModel;

class Brand extends Table
{
    protected $_name = 'brands';
    protected $_primary = 'id';
    protected $_rowClass = BrandRow::class;

    /**
     * @param string $catname
     * @return BrandRow
     */
    public function findRowByCatname($catname)
    {
        return $this->fetchRow([
            'folder = ?' => (string)$catname
        ]);
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
        $filenameFilter = new FilenameSafe();
        $data['folder'] = $filenameFilter->filter($data['caption']);
        $data['position'] = 0;

        if (mb_strlen($data['caption']) > BrandModel::MAX_NAME) {
            throw new Exception('Name is too long');
        }

        if ($this->fetchRow(['folder = ?' => $data['folder']])) {
            throw new Exception('Folder ' . $data['folder'] . ' already exists');
        }

        return parent::insert($data);
    }

}