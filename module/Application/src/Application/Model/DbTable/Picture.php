<?php

namespace Application\Model\DbTable;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\Image;

use Application\Model\Perspective;

use Zend_Db_Table;

class Picture extends Zend_Db_Table
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
     * @var Perspective
     */
    private $perspective;

    /**
     * @var TableGateway
     */
    private $itemTable;

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

        if (isset($options['perspective'])) {
            $this->perspective = $options['perspective'];
            unset($options['perspective']);
        }

        if (isset($options['itemTable'])) {
            $this->itemTable = $options['itemTable'];
            unset($options['itemTable']);
        }
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
            $columns = [
                'id',
                'begin_model_year', 'end_model_year',
                'body',
                'name' => new Sql\Expression('if(length(item_language.name) > 0, item_language.name, item.name)'),
                'begin_year', 'end_year', 'today',
            ];
            if ($large) {
                $columns[] = 'begin_month';
                $columns[] = 'end_month';
            }

            $select = new Sql\Select($this->itemTable->getTable());
            $select->columns($columns)
                ->where([new Sql\Predicate\In('item.id', array_keys($itemIds))])
                ->join('spec', 'item.spec_id = spec.id', [
                    'spec'      => 'short_name',
                    'spec_full' => 'name',
                ], $select::JOIN_LEFT)
                ->join(
                    'item_language',
                    new Sql\Expression(
                        'item.id = item_language.item_id and item_language.language = ?',
                        [$language]
                    ),
                    [],
                    $select::JOIN_LEFT
                );

            foreach ($this->itemTable->selectWith($select) as $row) {
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
    public function getFormatRequest($row)
    {
        if ($row instanceof \Zend_Db_Table_Row_Abstract) {
            $row = $row->toArray();
        } elseif ($row instanceof \ArrayObject) {
            $row = (array)$row;
        }

        return self::buildFormatRequest($row);
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

    public function cropParametersExists(\Zend_Db_Table_Row_Abstract $row)
    {
        return self::checkCropParameters($row->toArray());
    }
}
