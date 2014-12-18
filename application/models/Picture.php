<?php

class Picture extends Project_Db_Table
{
    const
        UNSORTED_TYPE_ID = 0,
        CAR_TYPE_ID      = 1,
        LOGO_TYPE_ID     = 2,
        MIXED_TYPE_ID    = 3,
        ENGINE_TYPE_ID   = 4,
        INTERIOR_TYPE_ID = 6;

    const
        STATUS_NEW      = 'new',
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox',
        DEFAULT_STATUS  = self::STATUS_INBOX;

    protected $_name = 'pictures';

    protected $_rowClass = 'Picture_Row';

    protected $_referenceMap = array(
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        ),
        'Brand' => array(
            'columns'       => array('brand_id'),
            'refTableClass' => 'Brands',
            'refColumns'    => array('id')
        ),
        'Engine' => array(
            'columns'       => array('engine_id'),
            'refTableClass' => 'Engines',
            'refColumns'    => array('id')
        ),
        'Owner' => array(
            'columns'       => array('owner_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'Change_Perspective_User' => array(
            'columns'       => array('change_perspective_user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'Change_Status_User' => array(
            'columns'       => array('change_status_user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'Source' => array(
            'columns'       => array('source_id'),
            'refTableClass' => 'Sources',
            'refColumns'    => array('id')
        ),
    );

    protected static $_resolutions = array (
        array('width' => 640,   'height' => 480),
        array('width' => 800,   'height' => 600),
        array('width' => 1024,  'height' => 768),
        array('width' => 1280,  'height' => 960),
        array('width' => 1600,  'height' => 1200),
        array('width' => 1920,  'height' => 1440),
        array('width' => 2048,  'height' => 1536),
        array('width' => 2560,  'height' => 1920),
        array('width' => 2800,  'height' => 2100),
        array('width' => 3200,  'height' => 2400),
    );

    public static function getResolutions()
    {
        return self::$_resolutions;
    }

    public function generateIdentity()
    {
        do {
            $identity = $this->randomIdentity();

            $exists = $this->getAdapter()->fetchOne(
                $this->getAdapter()->select()
                    ->from($this->info('name'), 'id')
                    ->where('identity = ?', $identity)
            );
        } while ($exists);

        return $identity;
    }

    public function randomIdentity()
    {
        $alpha = "abcdefghijklmnopqrstuvwxyz";
        $number = "0123456789";
        $length = 6;

        $dict = $alpha;

        $result = '';

        for ($i=0; $i<$length; $i++) {
            $index = rand(0, strlen($dict) - 1);
            $result .= $dict{$index};

            $dict = $alpha . $number;
        }

        return $result;
    }
}