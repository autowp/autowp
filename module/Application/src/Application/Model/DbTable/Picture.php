<?php

namespace Application\Model\DbTable;

use Autowp\Image;

use Application\Db\Table;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Engine;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Log\Event as LogEvent;
use Application\Model\DbTable\Perspective;
use Application\Model\DbTable\Vehicle;

use Zend_Db_Expr;
use Application\Model\PictureItem;

class Picture extends Table
{
    const
        UNSORTED_TYPE_ID = 0,
        VEHICLE_TYPE_ID  = 1,
        LOGO_TYPE_ID     = 2,
        MIXED_TYPE_ID    = 3,
        ENGINE_TYPE_ID   = 4,
        FACTORY_TYPE_ID  = 7;

    const
        STATUS_NEW      = 'new',
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    protected $_name = 'pictures';

    protected $_rowClass = \Application\Model\DbTable\Picture\Row::class;

    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => BrandTable::class,
            'refColumns'    => ['id']
        ],
        'Engine' => [
            'columns'       => ['engine_id'],
            'refTableClass' => Engine::class,
            'refColumns'    => ['id']
        ],
        'Factory' => [
            'columns'       => ['factory_id'],
            'refTableClass' => Factory::class,
            'refColumns'    => ['id']
        ],
        'Owner' => [
            'columns'       => ['owner_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'Change_Status_User' => [
            'columns'       => ['change_status_user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'Source' => [
            'columns'       => ['source_id'],
            'refTableClass' => \Application\Model\DbTable\Sources::class,
            'refColumns'    => ['id']
        ],
    ];

    private $prefixedPerspectives = [5, 6, 17, 20, 21, 22, 23, 24];

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
    public function setOptions(array $options)
    {
        if (isset($options['imageStorage'])) {
            $this->imageStorage = $options['imageStorage'];
            unset($options['imageStorage']);
        }
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

        for ($i = 0; $i < $length; $i++) {
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
                case Picture::VEHICLE_TYPE_ID:
                    $db = $this->getAdapter();
                    $pictureItemRows = $db->fetchAll(
                        $db->select(true)
                            ->from('picture_item', ['item_id', 'perspective_id'])
                            ->where('picture_id = ?', $row['id'])
                    );
                    foreach ($pictureItemRows as $pictureItemRow) {
                        $carIds[$pictureItemRow['item_id']] = true;
                        if (in_array($pictureItemRow['perspective_id'], $this->prefixedPerspectives)) {
                            $perspectiveIds[$pictureItemRow['perspective_id']] = true;
                        }
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
            $table = new Vehicle();

            $db = $table->getAdapter();

            $columns = [
                'id',
                'begin_model_year', 'end_model_year',
                'spec' => 'spec.short_name',
                'spec_full' => 'spec.name',
                'body',
                'name' => 'if(length(car_language.name) > 0, car_language.name, cars.name)',
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
            $perspectiveTable = new Perspective();
            $pRows = $perspectiveTable->find(array_keys($perspectiveIds));

            foreach ($pRows as $row) {
                $perspectives[$row->id] = $row->name;
            }
        }

        $engines = [];
        if (count($engineIds)) {
            $table = new Engine();
            foreach ($table->find(array_keys($engineIds)) as $row) {
                $engines[$row->id] = $row->name;
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
            $table = new BrandTable();
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

            $name = [
                'type' => $row['type'],
            ];

            switch ($row['type']) {
                case Picture::VEHICLE_TYPE_ID:
                    $db = $this->getAdapter();
                    $pictureItemRows = $db->fetchAll(
                        $db->select()
                            ->from('picture_item', ['item_id', 'perspective_id'])
                            ->where('picture_id = ?', $row['id'])
                    );

                    $items = [];
                    foreach ($pictureItemRows as $pictureItemRow) {
                        $carId = $pictureItemRow['item_id'];
                        $perspectiveId = $pictureItemRow['perspective_id'];

                        $car = isset($cars[$carId]) ? $cars[$carId] : [];

                        $items[] = array_replace($car, [
                            'perspective' => isset($perspectives[$perspectiveId])
                                ? $perspectives[$perspectiveId]
                                : null
                        ]);
                    }


                    $name = [
                        'type'  => $row['type'],
                        'items' => $items
                    ];
                    break;

                case Picture::ENGINE_TYPE_ID:
                    $engine = isset($engines[$row['engine_id']]) ? $engines[$row['engine_id']] : null;
                    $name = [
                        'type' => $row['type'],
                        'engine' => $engine
                    ];
                    break;

                case Picture::LOGO_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::UNSORTED_TYPE_ID:
                    $brand = isset($brands[$row['brand_id']]) ? $brands[$row['brand_id']] : null;
                    $name = [
                        'type' => $row['type'],
                        'brand' => $brand
                    ];
                    break;

                case Picture::FACTORY_TYPE_ID:
                    $name = [
                        'type' => $row['type'],
                        'factory' => isset($factories[$row['factory_id']]) ? $factories[$row['factory_id']] : null
                    ];
                    break;

                default:
                    $name = [
                        'type' => $row['type']
                    ];
                    break;
            }

            $result[$row['id']] = $name;
        }

        return $result;
    }

    public function accept(PictureItem $pictureItem, $pictureId, $userId, &$isFirstTimeAccepted)
    {
        $isFirstTimeAccepted = false;

        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $picture->setFromArray([
            'status' => Picture::STATUS_ACCEPTED,
            'change_status_user_id' => $userId
        ]);
        if (! $picture->accept_datetime) {
            $picture->accept_datetime = new Zend_Db_Expr('NOW()');

            $isFirstTimeAccepted = true;
        }
        $picture->save();

        $this->refreshPictureCounts($pictureItem, $picture);

        return true;
    }

    private function refreshCounts($params)
    {
        switch ($params['type']) {
            case Picture::VEHICLE_TYPE_ID:
                $brandModel = new BrandModel();
                foreach ($params['car_ids'] as $carId) {
                    $carTable = new Vehicle();
                    $car = $carTable->find($carId)->current();
                    if ($car) {
                        $car->refreshPicturesCount();
                        $brandModel->refreshPicturesCountByVehicle($car->id);
                    }
                }
                break;
            case Picture::ENGINE_TYPE_ID:
                if ($params['engine_id']) {
                    $brandTable = new BrandTable();
                    $brands = $brandTable->fetchAll(
                        $brandTable->select(true)
                            ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                            ->join(
                                'engine_parent_cache',
                                'brand_engine.engine_id = engine_parent_cache.parent_id',
                                null
                            )
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

    public function refreshPictureCounts(PictureItem $pictureItem, $picture)
    {
        $data = $picture->toArray();
        $data['car_ids'] = $pictureItem->getPictureItems($picture->id);

        $this->refreshCounts($data);
    }

    public function moveToEngine(PictureItem $pictureItem, $pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $engineTable = new Engine();
        $engine = $engineTable->find($id)->current();

        if (! $engine) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_ids'    => $pictureItem->getPictureItems($picture->id),
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'factory_id' => null,
            'brand_id'   => null,
            'engine_id'  => $engine->id,
            'type'       => Picture::ENGINE_TYPE_ID,
        ]);
        $picture->save();

        $pictureItem->setPictureItems($picture->id, []);

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new LogEvent();
        $log($userId, sprintf(
            'Назначение двигателя %s картинке %s',
            htmlspecialchars($engine->name),
            htmlspecialchars($options['pictureNameFormatter']->format($picture, $options['language']))
        ), [$engine, $picture]);

        return true;
    }

    public function addToCar(PictureItem $pictureItem, $pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $carTable = new Vehicle();
        $car = $carTable->find($id)->current();

        if (! $car) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_ids'    => $pictureItem->getPictureItems($picture->id),
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'factory_id' => null,
            'brand_id'   => null,
            'engine_id'  => null,
            'type'       => Picture::VEHICLE_TYPE_ID,
        ]);
        $picture->save();

        $pictureItem->add($picture->id, $car->id);

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new LogEvent();
        $log($userId, sprintf(
            'Картинка %s связана с автомобилем %s',
            htmlspecialchars($picture->id),
            htmlspecialchars('#' . $car->id)
        ), [$car, $picture]);

        return true;
    }

    public function moveToCar(PictureItem $pictureItem, $pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $carTable = new Vehicle();
        $car = $carTable->find($id)->current();

        if (! $car) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_ids'    => $pictureItem->getPictureItems($picture->id),
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'factory_id' => null,
            'brand_id'   => null,
            'engine_id'  => null,
            'type'       => Picture::VEHICLE_TYPE_ID,
        ]);
        $picture->save();

        $pictureItem->setPictureItems($picture->id, [$car->id]);

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new LogEvent();
        $log($userId, sprintf(
            'Картинка %s связана с автомобилем %s',
            htmlspecialchars($picture->id),
            htmlspecialchars('#' . $car->id)
        ), [$car, $picture]);

        return true;
    }

    public function moveToBrand(PictureItem $pictureItem, $pictureId, $id, $type, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $brandTable = new BrandTable();
        $brand = $brandTable->find($id)->current();
        if (! $brand) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_ids'    => $pictureItem->getPictureItems($picture->id),
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'factory_id' => null,
            'brand_id'   => $brand->id,
            'engine_id'  => null,
            'type'       => $type,
        ]);
        $picture->save();

        $pictureItem->setPictureItems($picture->id, []);

        if ($picture->image_id) {
            $this->imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new LogEvent();
        $log($userId, sprintf(
            'Назначение бренда %s картинке %s',
            htmlspecialchars($brand->name),
            htmlspecialchars($options['pictureNameFormatter']->format($picture, $options['language']))
        ), [$picture, $brand]);

        return true;
    }

    public function moveToFactory(PictureItem $pictureItem, $pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $factoryTable = new Factory();
        $factory = $factoryTable->find($id)->current();

        if (! $factory) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'engine_id'  => $picture->engine_id,
            'brand_id'   => $picture->brand_id,
            'car_ids'    => $pictureItem->getPictureItems($picture->id),
            'factory_id' => $picture->factory_id
        ];

        $picture->setFromArray([
            'factory_id' => $factory->id,
            'brand_id'   => null,
            'engine_id'  => null,
            'type'       => Picture::FACTORY_TYPE_ID,
        ]);
        $picture->save();

        $pictureItem->setPictureItems($picture->id, []);

        $this->imageStorage->changeImageName($picture->image_id, [
            'pattern' => $picture->getFileNamePattern(),
        ]);

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new LogEvent();
        $log($userId, sprintf(
            'Назначение завода %s картинке %s',
            htmlspecialchars($factory->name),
            htmlspecialchars($options['pictureNameFormatter']->format($picture, $options['language']))
        ), [$factory, $picture]);

        return true;
    }
}
