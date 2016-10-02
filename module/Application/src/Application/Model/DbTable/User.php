<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class User extends Table
{
    protected $_name = 'users';
    protected $_rowClass = \Application\Model\DbTable\User\Row::class;

    const MIN_NAME = 2;
    const MAX_NAME = 50;
    const MIN_PASSWORD = 6;
    const MAX_PASSWORD = 50;

    public function updateSpecsVolumes()
    {
        $db = $this->getAdapter();
        $pairs = $db->fetchPairs(
            $db->select()
                ->from('users', ['id', 'count(attrs_user_values.user_id)'])
                ->joinLeft('attrs_user_values', 'attrs_user_values.user_id = users.id', null)
                ->where('not users.specs_volume_valid')
                ->group('users.id')
        );

        foreach ($pairs as $userId => $volume) {
            $this->update([
                'specs_volume'       => $volume,
                'specs_volume_valid' => 1
            ], [
                'id = ?' => $userId
            ]);
        }
    }
}