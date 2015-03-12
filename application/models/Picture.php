<?php

class Picture extends Project_Db_Table
{
    const
        UNSORTED_TYPE_ID = 0,
        CAR_TYPE_ID      = 1,
        LOGO_TYPE_ID     = 2,
        MIXED_TYPE_ID    = 3,
        ENGINE_TYPE_ID   = 4,
        FACTORY_TYPE_ID  = 7;

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
        'Factory' => array(
            'columns'       => array('factory_id'),
            'refTableClass' => 'Factory',
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

    protected $_prefixedPerspectives = array(5, 6, 17, 20, 21);

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

    protected static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function getNames($rows, array $options = array())
    {
        $result = array();

        $language = isset($options['language']) ? $options['language'] : 'en';

        // prefetch
        $carIds = array();
        $perspectiveIds = array();
        $engineIds = array();
        $brandIds = array();
        $factoryIds = array();
        foreach ($rows as $index => $row) {
            switch ($row['type']) {
                case Picture::CAR_TYPE_ID:
                    $carIds[$row['car_id']] = true;
                    if (in_array($row['perspective_id'], $this->_prefixedPerspectives)) {
                        $perspectiveIds[$row['perspective_id']] = true;
                    }
                    break;

                case Picture::ENGINE_TYPE_ID:
                    $engineIds[$row['engine_id']] = true;
                    break;

                case Picture::LOGO_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::UNSORTED_TYPE_ID:
                    $brandIds[$row['brand_id']] = true;
                    break;

                case Picture::FACTORY_TYPE_ID:
                    $factoryIds[$row['factory_id']] = true;
                    break;
            }
        }

        $cars = array();
        if (count($carIds)) {
            $table = new Cars();

            $db = $table->getAdapter();

            $select = $db->select()
                ->from('cars', array(
                    'id',
                    'begin_model_year', 'end_model_year',
                    'spec' => 'spec.short_name',
                    'body',
                    'name' => 'if(length(car_language.name) > 0, car_language.name, cars.caption)',
                    'begin_year', 'end_year', 'today',
                ))
                ->where('cars.id in (?)', array_keys($carIds))
                ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :language', null);

            foreach ($db->fetchAll($select, array('language' => $language)) as $row) {
                $cars[$row['id']] = Cars_Row::buildFullName(array(
                    'begin_model_year' => $row['begin_model_year'],
                    'end_model_year'   => $row['end_model_year'],
                    'spec'             => $row['spec'],
                    'body'             => $row['body'],
                    'name'             => $row['name'],
                    'begin_year'       => $row['begin_year'],
                    'end_year'         => $row['end_year'],
                    'today'            => $row['today']
                ));
            }
        }

        $perspectives = array();
        if (count($perspectiveIds)) {
            $perspectiveLangTable = new Perspective_Language();
            $pRows = $perspectiveLangTable->fetchAll(array(
                'perspective_id in (?)' => array_keys($perspectiveIds),
                'language = ?'          => $language
            ));

            foreach ($pRows as $row) {
                $perspectives[$row->perspective_id] = self::mbUcfirst($row->name) . ' ';
            }
        }

        $engines = array();
        if (count($engineIds)) {
            $table = new Engines();
            foreach ($table->find(array_keys($engineIds)) as $row) {
                $engines[$row->id] = $row->caption;
            }
        }

        $factories = array();
        if (count($factoryIds)) {
            $table = new Factory();
            foreach ($table->find(array_keys($factoryIds)) as $row) {
                $factories[$row->id] = $row->name;
            }
        }

        $brands = array();
        if (count($brandIds)) {
            $table = new Brands();
            foreach ($table->find(array_keys($brandIds)) as $row) {
                $brands[$row->id] = $row->getLanguageName($language);
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['name']) {
                $result[$row['id']] = $row['name'];
                continue;
            }

            $caption = null;

            switch ($row['type']) {
                case Picture::CAR_TYPE_ID:
                    $car = isset($cars[$row['car_id']]) ? $cars[$row['car_id']] : null;
                    if ($car) {
                        $perspective = isset($perspectives[$row['perspective_id']]) ? $perspectives[$row['perspective_id']] : '';
                        $caption = $perspective . $car;
                    }
                    break;

                case Picture::ENGINE_TYPE_ID:
                    $engine = isset($engines[$row['engine_id']]) ? $engines[$row['engine_id']] : null;
                    $caption = 'Двигатель' . ($engine ? ' ' . $engine : '');
                    break;

                case Picture::LOGO_TYPE_ID:
                    $brand = isset($brands[$row['brand_id']]) ? $brands[$row['brand_id']] : null;
                    $caption = 'Логотип' . ($brand ? ' ' . $brand : '');
                    break;

                case Picture::MIXED_TYPE_ID:
                    $brand = isset($brands[$row['brand_id']]) ? $brands[$row['brand_id']] : null;
                    $caption = ($brand ? $brand . ' ' : '') . 'Разное';
                    break;

                case Picture::UNSORTED_TYPE_ID:
                    $brand = isset($brands[$row['brand_id']]) ? $brands[$row['brand_id']] : null;
                    $caption = $brand ? $brand : 'Несортировано';
                    break;

                case Picture::FACTORY_TYPE_ID:
                    $caption = isset($factories[$row['factory_id']]) ? $factories[$row['factory_id']] : null;
                    break;
            }

            if (!$caption) {
                $caption = 'Изображение №' . $row['id'];
            }

            $result[$row['id']] = $caption;
        }

        return $result;
    }
}