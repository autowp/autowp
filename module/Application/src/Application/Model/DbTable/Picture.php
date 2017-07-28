<?php

namespace Application\Model\DbTable;

use Autowp\Commons\Db\Table;
use Autowp\Image;
use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Model\DbTable;
use Application\Model\Item as ItemModel;
use Application\Model\Perspective;
use Application\Model\Picture as PictureModel;
use Application\Model\PictureModerVote;

use Zend_Db_Expr;

class Picture extends Table
{
    protected $_name = 'pictures';

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
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Perspective
     */
    private $perspective;

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

        if (isset($options['pictureModerVote'])) {
            $this->pictureModerVote = $options['pictureModerVote'];
            unset($options['pictureModerVote']);
        }

        if (isset($options['perspective'])) {
            $this->perspective = $options['perspective'];
            unset($options['perspective']);
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

    public function getNameData($rows, array $options = [])
    {
        $result = [];

        $language = isset($options['language']) ? $options['language'] : 'en';
        $large = isset($options['large']) && $options['large'];

        // prefetch
        $itemIds = [];
        $perspectiveIds = [];
        foreach ($rows as $index => $row) {
            $db = $this->getAdapter();
            $pictureItemRows = $db->fetchAll(
                $db->select(true)
                    ->from('picture_item', ['item_id', 'perspective_id'])
                    ->where('picture_id = ?', $row['id'])
            );
            foreach ($pictureItemRows as $pictureItemRow) {
                $itemIds[$pictureItemRow['item_id']] = true;
                if (in_array($pictureItemRow['perspective_id'], $this->prefixedPerspectives)) {
                    $perspectiveIds[$pictureItemRow['perspective_id']] = true;
                }
            }
        }

        $items = [];
        if (count($itemIds)) {
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
                ->where('item.id in (?)', array_keys($itemIds))
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
                $items[$row['id']] = $data;
            }
        }

        $perspectives = $this->perspective->getOnlyPairs($perspectiveIds);

        foreach ($rows as $index => $row) {
            if ($row['name']) {
                $result[$row['id']] = [
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

            $resultItems = [];
            foreach ($pictureItemRows as $pictureItemRow) {
                $itemId = $pictureItemRow['item_id'];
                $perspectiveId = $pictureItemRow['perspective_id'];

                $item = isset($items[$itemId]) ? $items[$itemId] : [];

                $resultItems[] = array_replace($item, [
                    'perspective' => isset($perspectives[$perspectiveId])
                        ? $perspectives[$perspectiveId]
                        : null
                ]);
            }

            $result[$row['id']] = [
                'items' => $resultItems
            ];
        }

        return $result;
    }

    public function accept($pictureId, $userId, &$isFirstTimeAccepted)
    {
        $isFirstTimeAccepted = false;

        $picture = $this->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $picture->setFromArray([
            'status' => PictureModel::STATUS_ACCEPTED,
            'change_status_user_id' => $userId
        ]);
        if (! $picture->accept_datetime) {
            $picture->accept_datetime = new Zend_Db_Expr('NOW()');

            $isFirstTimeAccepted = true;
        }
        $picture->save();

        return true;
    }

    public function canAccept(\Autowp\Commons\Db\Table\Row $row): bool
    {
        if (! in_array($row['status'], [PictureModel::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getNegativeVotesCount($row['id']);

        return $votes <= 0;
    }

    public function canDelete(\Autowp\Commons\Db\Table\Row $row): bool
    {
        if (! in_array($row['status'], [PictureModel::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getPositiveVotesCount($row['id']);

        return $votes <= 0;
    }

    /**
     * @param array $options
     * @return Image\Storage\Request
     */
    public static function buildFormatRequest(array $options)
    {
        $defaults = [
            'image_id'    => null,
            'crop_left'   => null,
            'crop_top'    => null,
            'crop_width'  => null,
            'crop_height' => null
        ];
        $options = array_replace($defaults, $options);

        $request = [
            'imageId' => $options['image_id']
        ];
        if (self::checkCropParameters($options)) {
            $request['crop'] = [
                'left'   => $options['crop_left'],
                'top'    => $options['crop_top'],
                'width'  => $options['crop_width'],
                'height' => $options['crop_height']
            ];
        }

        return new Image\Storage\Request($request);
    }

    /**
     * @return Request
     */
    public function getFormatRequest(\Autowp\Commons\Db\Table\Row $row)
    {
        return self::buildFormatRequest($row->toArray());
    }

    private static function between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public static function checkCropParameters($options)
    {
        // Check existance and correct of crop parameters
        return  ! is_null($options['crop_left']) && ! is_null($options['crop_top']) &&
            ! is_null($options['crop_width']) && ! is_null($options['crop_height']) &&
            self::between($options['crop_left'], 0, $options['width']) &&
            self::between($options['crop_width'], 1, $options['width']) &&
            self::between($options['crop_top'], 0, $options['height']) &&
            self::between($options['crop_height'], 1, $options['height']);
    }

    public function cropParametersExists(\Autowp\Commons\Db\Table\Row $row)
    {
        return self::checkCropParameters($row->toArray());
    }

    public function getFileNamePattern(\Autowp\Commons\Db\Table\Row $row): string
    {
        $result = rand(1, 9999);

        $filenameFilter = new FilenameSafe();

        $itemTable = new Item();
        $cars = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('picture_item', 'item.id = picture_item.item_id', [])
                ->where('picture_item.picture_id = ?', $row['id'])
        );

        if (count($cars) > 1) {
            $brands = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item.item_type_id = ?', ItemModel::BRAND)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                    ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                    ->where('picture_item.picture_id = ?', $row['id'])
            );

            $f = [];
            foreach ($brands as $brand) {
                $f[] = $filenameFilter->filter($brand->catname);
            }
            $f = array_unique($f);
            sort($f, SORT_STRING);

            $brandsFolder = implode('/', $f);
            $firstChar = mb_substr($brandsFolder, 0, 1);

            $result = $firstChar . '/' . $brandsFolder .'/mixed';
        } elseif (count($cars) == 1) {
            $car = $cars[0];

            $carCatname = $filenameFilter->filter($car->name);

            $brands = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item.item_type_id = ?', ItemModel::BRAND)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                    ->where('item_parent_cache.item_id = ?', $car->id)
            );

            $sBrands = [];
            foreach ($brands as $brand) {
                $sBrands[$brand->id] = $brand;
            }

            if (count($sBrands) > 1) {
                $f = [];
                foreach ($sBrands as $brand) {
                    $f[] = $filenameFilter->filter($brand->catname);
                }
                $f = array_unique($f);
                sort($f, SORT_STRING);

                $carFolder = $carCatname;
                foreach ($f as $i) {
                    $carFolder = str_replace($i, '', $carFolder);
                }

                $carFolder = str_replace('__', '_', $carFolder);
                $carFolder = trim($carFolder, '_-');

                $brandsFolder = implode('/', $f);
                $firstChar = mb_substr($brandsFolder, 0, 1);

                $result = $firstChar . '/' . $brandsFolder . '/' . $carFolder . '/' . $carCatname;
            } else {
                if (count($sBrands) == 1) {
                    $sBrandsA = array_values($sBrands);
                    $brand = $sBrandsA[0];

                    $brandFolder = $filenameFilter->filter($brand->catname);
                    $firstChar = mb_substr($brandFolder, 0, 1);

                    $carFolder = $carCatname;
                    $carFolder = trim(str_replace($brandFolder, '', $carFolder), '_-');

                    $result = implode('/', [
                        $firstChar,
                        $brandFolder,
                        $carFolder,
                        $carCatname
                    ]);
                } else {
                    $carFolder = $filenameFilter->filter($car->name);
                    $firstChar = mb_substr($carFolder, 0, 1);
                    $result = $firstChar . '/' . $carFolder.'/'.$carCatname;
                }
            }
        }

        $result = str_replace('//', '/', $result);

        return $result;
    }
}
