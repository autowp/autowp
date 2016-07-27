<?php

class Users extends Project_Db_Table
{
    protected $_name = 'users';
    protected $_rowClass = 'Users_Row';

    const MAX_NAME = 50;

    public function updateSpecsVolumes()
    {
        $db = $this->getAdapter();
        $pairs = $db->fetchPairs(
            $db->select()
                ->from('users', array('id', 'count(attrs_user_values.user_id)'))
                ->joinLeft('attrs_user_values', 'attrs_user_values.user_id = users.id', null)
                ->where('not users.specs_volume_valid')
                ->group('users.id')
        );

        foreach ($pairs as $userId => $volume) {
            $this->update(array(
                'specs_volume'       => $volume,
                'specs_volume_valid' => 1
            ), array(
                'id = ?' => $userId
            ));
        }
    }
}