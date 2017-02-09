<?php

namespace Application\Model\DbTable;

use Autowp\Image;

use Autowp\Commons\Db\Table;

use Zend_Db_Expr;

class Picture extends Table
{
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

        $perspectives = [];
        if (count($perspectiveIds)) {
            $perspectiveTable = new Perspective();
            $pRows = $perspectiveTable->find(array_keys($perspectiveIds));

            foreach ($pRows as $row) {
                $perspectives[$row->id] = $row->name;
            }
        }

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
            'status' => Picture::STATUS_ACCEPTED,
            'change_status_user_id' => $userId
        ]);
        if (! $picture->accept_datetime) {
            $picture->accept_datetime = new Zend_Db_Expr('NOW()');

            $isFirstTimeAccepted = true;
        }
        $picture->save();

        return true;
    }
}
