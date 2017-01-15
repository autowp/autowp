<?php

namespace Application\Model\DbTable;

use Autowp\Image;

use Application\Db\Table;
use Application\Model\Brand as BrandModel;
use Application\Model\PictureItem;

use Zend_Db_Expr;

class Picture extends Table
{
    const
        VEHICLE_TYPE_ID  = 1,
        FACTORY_TYPE_ID  = 7;

    const
        STATUS_NEW      = 'new',
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    protected $_name = 'pictures';

    protected $_rowClass = Picture\Row::class;

    protected $_referenceMap = [
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
            }
        }

        $cars = [];
        if (count($carIds)) {
            $table = new Item();

            $db = $table->getAdapter();

            $columns = [
                'id',
                'begin_model_year', 'end_model_year',
                'spec' => 'spec.short_name',
                'spec_full' => 'spec.name',
                'body',
                'name' => 'if(length(item_language.name) > 0, item_language.name, item.name)',
                'begin_year', 'end_year', 'today',
            ];
            if ($large) {
                $columns[] = 'begin_month';
                $columns[] = 'end_month';
            }

            $select = $db->select()
                ->from('item', $columns)
                ->where('item.id in (?)', array_keys($carIds))
                ->joinLeft('spec', 'item.spec_id = spec.id', null)
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :language', null);

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

        $factories = [];
        if (count($factoryIds)) {
            $table = new Factory();
            foreach ($table->find(array_keys($factoryIds)) as $row) {
                $factories[$row->id] = $row->name;
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

            $result[$row['id']] = [
                'type'  => $row['type'],
                'items' => $items
            ];
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
                foreach ($params['item_ids'] as $carId) {
                    $itemTable = new Item();
                    $car = $itemTable->find($carId)->current();
                    if ($car) {
                        $car->refreshPicturesCount();
                        $brandModel->refreshPicturesCountByVehicle($car->id);
                    }
                }
                break;
            case Picture::FACTORY_TYPE_ID:
                break;
        }
    }

    public function refreshPictureCounts(PictureItem $pictureItem, $picture)
    {
        $data = $picture->toArray();
        $data['item_ids'] = $pictureItem->getPictureItems($picture->id);

        $this->refreshCounts($data);
    }

    public function addToCar(PictureItem $pictureItem, $pictureId, $id, $userId, array $options)
    {
        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $itemTable = new Item();
        $car = $itemTable->find($id)->current();

        if (! $car) {
            return false;
        }

        $oldParams = [
            'type'     => $picture->type,
            'item_ids' => $pictureItem->getPictureItems($picture->id),
        ];

        $picture->setFromArray([
            'type'     => Picture::VEHICLE_TYPE_ID,
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

        $log = new Log\Event();
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

        $itemTable = new Item();
        $car = $itemTable->find($id)->current();

        if (! $car) {
            return false;
        }

        $oldParams = [
            'type'       => $picture->type,
            'item_ids'   => $pictureItem->getPictureItems($picture->id),
        ];

        $picture->setFromArray([
            'type' => Picture::VEHICLE_TYPE_ID,
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

        $log = new Log\Event();
        $log($userId, sprintf(
            'Картинка %s связана с автомобилем %s',
            htmlspecialchars($picture->id),
            htmlspecialchars('#' . $car->id)
        ), [$car, $picture]);

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
            'item_ids'   => $pictureItem->getPictureItems($picture->id),
        ];

        $picture->setFromArray([
            'type'       => Picture::FACTORY_TYPE_ID,
        ]);
        $picture->save();

        $pictureItem->setPictureItems($picture->id, []);

        $this->imageStorage->changeImageName($picture->image_id, [
            'pattern' => $picture->getFileNamePattern(),
        ]);

        $this->refreshCounts($oldParams);
        $this->refreshPictureCounts($pictureItem, $picture);

        $log = new Log\Event();
        $log($userId, sprintf(
            'Назначение завода %s картинке %s',
            htmlspecialchars($factory->name),
            htmlspecialchars($options['pictureNameFormatter']->format($picture, $options['language']))
        ), [$factory, $picture]);

        return true;
    }
}
