<?php

use Autowp\Image;

use Application\Db\Table;

class Picture extends Table
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

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ],
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => 'Brands',
            'refColumns'    => ['id']
        ],
        'Engine' => [
            'columns'       => ['engine_id'],
            'refTableClass' => 'Engines',
            'refColumns'    => ['id']
        ],
        'Factory' => [
            'columns'       => ['factory_id'],
            'refTableClass' => 'Factory',
            'refColumns'    => ['id']
        ],
        'Owner' => [
            'columns'       => ['owner_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Change_Perspective_User' => [
            'columns'       => ['change_perspective_user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Change_Status_User' => [
            'columns'       => ['change_status_user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Source' => [
            'columns'       => ['source_id'],
            'refTableClass' => 'Sources',
            'refColumns'    => ['id']
        ],
    ];

    private $prefixedPerspectives = [5, 6, 17, 20, 21, 22, 23];

    /**
     * @var Image\Storage
     */
    private $imageStorage;

    /**
     * setOptions()
     *
     * @param array $options
     * @return Zend_Db_Table_Abstract
     */
    public function setOptions(Array $options)
    {
        if (isset($options['imageStorage'])) {
            $this->imageStorage = $options['imageStorage'];
            unset($options['imageStorage']);
        }
    }

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

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function getNames($rows, array $options = [])
    {
        $result = [];

        $language = isset($options['language']) ? $options['language'] : 'en';

        // prefetch
        $carIds = [];
        $perspectiveIds = [];
        $engineIds = [];
        $brandIds = [];
        $factoryIds = [];
        foreach ($rows as $index => $row) {
            switch ($row['type']) {
                case Picture::CAR_TYPE_ID:
                    $carIds[$row['car_id']] = true;
                    if (in_array($row['perspective_id'], $this->prefixedPerspectives)) {
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

        $cars = [];
        if (count($carIds)) {
            $table = new Cars();

            $db = $table->getAdapter();

            $select = $db->select()
                ->from('cars', [
                    'id',
                    'begin_model_year', 'end_model_year',
                    'spec' => 'spec.short_name',
                    'spec_full' => 'spec.name',
                    'body',
                    'name' => 'if(length(car_language.name) > 0, car_language.name, cars.caption)',
                    'begin_year', 'end_year', 'today',
                ])
                ->where('cars.id in (?)', array_keys($carIds))
                ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :language', null);

            foreach ($db->fetchAll($select, ['language' => $language]) as $row) {
                $cars[$row['id']] = [
                    'begin_model_year' => $row['begin_model_year'],
                    'end_model_year'   => $row['end_model_year'],
                    'spec'             => $row['spec'],
                    'spec_full'        => $row['spec_full'],
                    'body'             => $row['body'],
                    'name'             => $row['name'],
                    'begin_year'       => $row['begin_year'],
                    'end_year'         => $row['end_year'],
                    'today'            => $row['today']
                ];
            }
        }

        $translate = $options['translator'];

        $perspectives = [];
        if (count($perspectiveIds)) {
            $perspectiveTable = new Perspectives();
            $pRows = $perspectiveTable->find(array_keys($perspectiveIds));

            foreach ($pRows as $row) {
                $name = $translate->translate($row->name, $language);
                //$name = $row->name;
                $perspectives[$row->id] = self::mbUcfirst($name) . ' ';
            }
        }

        $engines = [];
        if (count($engineIds)) {
            $table = new Engines();
            foreach ($table->find(array_keys($engineIds)) as $row) {
                $engines[$row->id] = $row->caption;
            }
        }

        $factories = [];
        if (count($factoryIds)) {
            $table = new Factory();
            foreach ($table->find(array_keys($factoryIds)) as $row) {
                $factories[$row->id] = $row->name;
            }
        }

        $brands = [];
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
                        //$perspective = isset($perspectives[$row['perspective_id']]) ? $perspectives[$row['perspective_id']] : '';
                        //$caption = $perspective . $car;
                        $caption = array_replace($car, [
                            'perspective' => isset($perspectives[$row['perspective_id']]) ? $perspectives[$row['perspective_id']] : null
                        ]);
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

    public function getNameData($rows, array $options = [])
    {
        $result = [];

        $language = isset($options['language']) ? $options['language'] : 'en';
        $large = isset($options['large']) && $options['large'];

        // prefetch
        $carIds = [];
        $perspectiveIds = [];
        $engineIds = [];
        $brandIds = [];
        $factoryIds = [];
        foreach ($rows as $index => $row) {
            switch ($row['type']) {
                case Picture::CAR_TYPE_ID:
                    $carIds[$row['car_id']] = true;
                    if (in_array($row['perspective_id'], $this->prefixedPerspectives)) {
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

        $cars = [];
        if (count($carIds)) {
            $table = new Cars();

            $db = $table->getAdapter();

            $columns = [
                'id',
                'begin_model_year', 'end_model_year',
                'spec' => 'spec.short_name',
                'spec_full' => 'spec.name',
                'body',
                'name' => 'if(length(car_language.name) > 0, car_language.name, cars.caption)',
                'begin_year', 'end_year', 'today',
            ];
            if ($large) {
                $columns[] = 'begin_month';
                $columns[] = 'end_month';
            }

            $select = $db->select()
                ->from('cars', $columns)
                ->where('cars.id in (?)', array_keys($carIds))
                ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :language', null);

            foreach ($db->fetchAll($select, ['language' => $language]) as $row) {
                $data = [
                    'begin_model_year' => $row['begin_model_year'],
                    'end_model_year'   => $row['end_model_year'],
                    'spec'             => $row['spec'],
                    'spec_full'        => $row['spec_full'],
                    'body'             => $row['body'],
                    'name'             => $row['name'],
                    'begin_year'       => $row['begin_year'],
                    'end_year'         => $row['end_year'],
                    'today'            => $row['today']
                ];
                if ($large) {
                    $data['begin_month'] = $row['begin_month'];
                    $data['end_month'] = $row['end_month'];
                }
                $cars[$row['id']] = $data;
            }
        }

        $perspectives = [];
        if (count($perspectiveIds)) {
            $perspectiveTable = new Perspectives();
            $pRows = $perspectiveTable->find(array_keys($perspectiveIds));

            foreach ($pRows as $row) {
                $perspectives[$row->id] = $row->name;
            }
        }

        $engines = [];
        if (count($engineIds)) {
            $table = new Engines();
            foreach ($table->find(array_keys($engineIds)) as $row) {
                $engines[$row->id] = $row->caption;
            }
        }

        $factories = [];
        if (count($factoryIds)) {
            $table = new Factory();
            foreach ($table->find(array_keys($factoryIds)) as $row) {
                $factories[$row->id] = $row->name;
            }
        }

        $brands = [];
        if (count($brandIds)) {
            $table = new Brands();
            foreach ($table->find(array_keys($brandIds)) as $row) {
                $brands[$row->id] = $row->getLanguageName($language);
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['name']) {
                $result[$row['id']] = [
                    'type' => $row['type'],
                    'name' => $row['name']
                ];
                continue;
            }

            $caption = [
                'type' => $row['type'],
            ];

            switch ($row['type']) {
                case Picture::CAR_TYPE_ID:
                    $car = isset($cars[$row['car_id']]) ? $cars[$row['car_id']] : null;
                    if ($car) {
                        $caption = [
                            'type' => $row['type'],
                            'car' => $car,
                            'perspective' => isset($perspectives[$row['perspective_id']]) ? $perspectives[$row['perspective_id']] : null
                        ];
                    } else {
                        $caption = [
                            'type' => $row['type'],
                            'car' => null,
                            'perspective' => isset($perspectives[$row['perspective_id']]) ? $perspectives[$row['perspective_id']] : null
                        ];
                    }
                    break;

                case Picture::ENGINE_TYPE_ID:
                    $engine = isset($engines[$row['engine_id']]) ? $engines[$row['engine_id']] : null;
                    $caption = [
                        'type' => $row['type'],
                        'engine' => $engine
                    ];
                    break;

                case Picture::LOGO_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::UNSORTED_TYPE_ID:
                    $brand = isset($brands[$row['brand_id']]) ? $brands[$row['brand_id']] : null;
                    $caption = [
                        'type' => $row['type'],
                        'brand' => $brand
                    ];
                    break;

                case Picture::FACTORY_TYPE_ID:
                    $caption = [
                        'type' => $row['type'],
                        'factory' => isset($factories[$row['factory_id']]) ? $factories[$row['factory_id']] : null
                    ];
                    break;

                default:
                    $caption = [
                        'type' => $row['type']
                    ];
                    break;
            }

            $result[$row['id']] = $caption;
        }

        return $result;
    }

    public function accept($pictureId, $userId, &$isFirstTimeAccepted)
    {
        $isFirstTimeAccepted = false;

        $picture = $this->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        $picture->setFromArray([
            'status' => Picture::STATUS_ACCEPTED,
            'change_status_user_id' => $userId
        ]);
        if (!$picture->accept_datetime) {
            $picture->accept_datetime = new Zend_Db_Expr('NOW()');

            $isFirstTimeAccepted = true;
        }
        $picture->save();

        $this->refreshCounts($picture->toArray());

        return true;
    }

    private function refreshCounts($params)
    {
        switch ($params['type']) {
            case Picture::CAR_TYPE_ID:
                if ($params['car_id']) {
                    $carTable = new Cars();
                    $car = $carTable->find($params['car_id'])->current();
                    if ($car) {
                        $car->refreshPicturesCount();
                        //TODO: brands_cars_cache
                        foreach ($car->findBrandsViaBrand_Car() as $brand) {
                            $brand->refreshPicturesCount();
                        }
                    }
                }
                break;
            case Picture::ENGINE_TYPE_ID:
                if ($params['engine_id']) {
                    $brandTable = new Brands();
                    $brands = $brandTable->fetchAll(
                        $brandTable->select(true)
                            ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                            ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                            ->where('engine_parent_cache.engine_id = ?', $params['engine_id'])
                            ->group('brands.id')
                    );

                    foreach ($brands as $brand) {
                        $brand->refreshEnginePicturesCount();
                    }
                }
                break;
            case Picture::MIXED_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
                break;
            case Picture::FACTORY_TYPE_ID:
                break;
        }
    }

    public function moveToEngine($pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        $engineTable = new Engines();
        $engine = $engineTable->find($id)->current();

        if (!$engine) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_id'     => $picture->car_id,
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'car_id'     => null,
            'factory_id' => null,
            'brand_id'   => null,
            'engine_id'  => $engine->id,
            'type'       => Picture::ENGINE_TYPE_ID,
        ]);
        $picture->save();

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshCounts($picture->toArray());

        $log = new Log_Events();
        $log($userId, sprintf(
            'Назначение двигателя %s картинке %s',
            $engine->caption,
            $picture->getCaption($options)
        ), [$engine, $picture]);

        return true;
    }

    public function moveToCar($pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        $carTable = new Cars();
        $car = $carTable->find($id)->current();

        if (!$car) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_id'     => $picture->car_id,
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'car_id'     => $car->id,
            'factory_id' => null,
            'brand_id'   => null,
            'engine_id'  => null,
            'type'       => Picture::CAR_TYPE_ID,
        ]);
        $picture->save();

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshCounts($picture->toArray());

        $log = new Log_Events();
        $log($userId, sprintf(
            'Картинка %s связана с автомобилем %s',
            htmlspecialchars($picture->id),
            htmlspecialchars($car->getFullName('en'))
        ), [$car, $picture]);

        return true;
    }

    public function moveToBrand($pictureId, $id, $type, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        $brandTable = new Brands();
        $brand = $brandTable->find($id)->current();
        if (!$brand) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_id'     => $picture->car_id,
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'car_id'     => null,
            'factory_id' => null,
            'brand_id'   => $brand->id,
            'engine_id'  => null,
            'type'       => $type,
        ]);
        $picture->save();

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshCounts($picture->toArray());

        $log = new Log_Events();
        $log($userId, sprintf(
            'Назначение бренда %s картинке %s',
            htmlspecialchars($brand->caption),
            htmlspecialchars($picture->getCaption($options))
        ), [$picture, $brand]);

        return true;
    }

    public function moveToFactory($pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        $factoryTable = new Factory();
        $factory = $factoryTable->find($id)->current();

        if (!$factory) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_id'     => $picture->car_id,
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'car_id'     => null,
            'factory_id' => $factory->id,
            'brand_id'   => null,
            'engine_id'  => null,
            'type'       => Picture::FACTORY_TYPE_ID,
        ]);
        $picture->save();

        $this->imageStorage->changeImageName($picture->image_id, [
            'pattern' => $picture->getFileNamePattern(),
        ]);

        $this->refreshCounts($oldParams);
        $this->refreshCounts($picture->toArray());

        $log = new Log_Events();
        $log($userId, sprintf(
            'Назначение завода %s картинке %s',
            htmlspecialchars($factory->name),
            htmlspecialchars($picture->getCaption($options))
        ), [$factory, $picture]);

        return true;
    }
}