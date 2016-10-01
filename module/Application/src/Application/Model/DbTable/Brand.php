<?php

namespace Application\Model\DbTable;

use Autowp\Filter\Filename\Safe;
use Application\Db\Table;

class Brand extends Table
{
    protected $_name = 'brands';
    protected $_primary = 'id';
    protected $_rowClass = 'Brand_Row';
    protected $_referenceMap = [
        'Type' => [
            'columns'       => ['type_id'],
            'refTableClass' => 'Brand_Types',
            'refColumns'    => ['id']
        ]
    ];

    /**
     * @param string $catname
     * @return Brand_Row
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
        $filenameFilter = new Safe();
        $data['folder'] = $filenameFilter->filter($data['caption']);
        $data['position'] = 0;

        if (mb_strlen($data['caption']) > 50) {
            throw new Exception('Name is too long');
        }

        if ($this->fetchRow(['folder = ?' => $data['folder']])) {
            throw new Exception('Folder ' . $data['folder'] . ' already exists');
        }

        return parent::insert($data);
    }

}