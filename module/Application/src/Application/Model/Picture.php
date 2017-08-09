<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Math\Rand;
use Zend\Paginator;

use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Model\Item as ItemModel;

class Picture
{
    const
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    const MAX_NAME = 255;

    const IDENTITY_LENGTH = 6;

    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var TableGateway
     */
    private $itemTable;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    public function __construct(
        TableGateway $table,
        TableGateway $itemTable,
        PictureModerVote $pictureModerVote
    ) {
        $this->table = $table;
        $this->itemTable = $itemTable;
        $this->pictureModerVote = $pictureModerVote;
    }

    private function applyIdFilter(Sql\Select $select, $value, string $id)
    {
        if (is_array($value)) {
            $value = array_values($value);

            if (count($value) == 1) {
                $this->applyIdFilter($select, $value[0], $id);
                return;
            }

            if (count($value) < 1) {
                $this->applyIdFilter($select, 0, $id);
                return;
            }

            $select->where([new Sql\Predicate\In($id, $value)]);
            return;
        }

        if (! is_scalar($value)) {
            throw new \Exception('`id` must be scalar or array of scalar');
        }

        $select->where([$id => $value]);
    }

    private function applyPerspectiveFilter(Sql\Select $select, $options)
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options
            ];
        }

        $defaults = [
            'id'    => null,
            'group' => null
        ];
        $options = array_replace($defaults, $options);

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'picture_item.perspective_id');
        }

        if ($options['group'] !== null) {
            $select
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    []
                )
                ->where(['mp.group_id' => $options['group']]);
        }
    }

    private function applyItemFilters(Sql\Select $select, $options)
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options
            ];
        }

        $defaults = [
            'id'                  => null,
            'ancestor_or_self'    => null,
            'perspective'         => null,
            'perspective_is_null' => null,
            'vehicle_type'        => null,
        ];
        $options = array_replace($defaults, $options);

        $select->join('picture_item', 'pictures.id = picture_item.picture_id', []);

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'picture_item.item_id');
        }

        if ($options['ancestor_or_self'] !== null) {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->where(['item_parent_cache.parent_id' => $options['ancestor_or_self']]);
        }

        if ($options['perspective'] !== null) {
            $this->applyPerspectiveFilter($select, $options['perspective']);
        }

        if ($options['perspective_is_null'] !== null) {
            if ($options['perspective_is_null']) {
                $select->where([new Sql\Predicate\IsNull('picture_item.perspective_id')]);
            } else {
                $select->where([new Sql\Predicate\IsNotNull('picture_item.perspective_id')]);
            }
        }

        if ($options['vehicle_type'] !== null) {
            $select
                ->join('vehicle_vehicle_type', 'picture_item.item_id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where(['car_types_parents.parent_id' => $options['vehicle_type']]);
        }

        return ['pictures.id'];
    }

    public function getSelect(array $options)
    {
        $defaults = [
            'id'               => null,
            'columns'          => null,
            'status'           => null,
            'item'             => null,
            'has_comments'     => null,
            'user'             => null,
            'has_special_name' => null,
            'similar'          => null,
            'has_similar'      => null,
            'has_moder_votes'  => null,
            'has_accept_votes' => null,
            'has_delete_votes' => null,
            'has_moder_votes'  => null,
            'is_replace'       => null,
            'is_lost'          => null,
            'has_point'        => null,
            'order'            => null,
        ];
        $options = array_replace($defaults, $options);

        $select = $this->table->getSql()->select();
        $group = [];

        $joinPdr = false;
        $joinLeftComments = false;
        $joinComments = false;

        if ($options['identity'] !== null) {
            $select->where(['pictures.identity' => (string)$options['identity']]);
        }

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'pictures.id');
        }

        if ($options['status'] !== null) {
            $value = $options['status'];
            if (is_array($value)) {
                $select->where([new Sql\Predicate\In('pictures.status', $value)]);
            } else {
                $select->where(['pictures.status' => $value]);
            }
        }

        if ($options['item']) {
            $subGroup = $this->applyItemFilters($select, $options['item']);
            $group = array_merge($group, $subGroup);
        }

        if ($options['has_comments'] !== null) {
            if ($options['has_comments']) {
                $joinComments = true;
                $select->where(['comment_topic.messages > 0']);
            } else {
                $joinLeftComments = true;
                $select->where(['(comment_topic.messages = 0 or comment_topic.messages is null)']);
            }
        }

        if ($options['user'] !== null) {
            $select->where(['pictures.owner_id' => $options['user']]);
        }

        if ($options['has_special_name']) {
            $select->where(['pictures.name <> "" and pictures.name is not null']);
        }

        if ($options['has_similar']) {
            $options['order'] = 'similarity';
            $select->join('df_distance', 'pictures.id = df_distance.src_picture_id', [])
                ->where(['not df_distance.hide']);

            if (strlen($options['status'])) {
                $select->join(['similar' => 'pictures'], 'df_distance.dst_picture_id = similar.id', []);

                if ($options['status'] !== null) {
                    $value = $options['status'];
                    if (is_array($value)) {
                        $select->where([new Sql\Predicate\In('similar.status', $value)]);
                    } else {
                        $select->where(['similar.status' => $value]);
                    }
                }
            }
        }

        if ($options['has_moder_votes'] !== null) {
            if ($options['has_moder_votes']) {
                $joinPdr = true;
            } else {
                $select
                    ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id = pdr.picture_id', [], $select::JOIN_LEFT)
                    ->where('pdr.picture_id IS NULL');
            }
        }

        if ($options['has_accept_votes']) {
            $select->join(['pdr2' => 'pictures_moder_votes'], 'pictures.id = pdr2.picture_id', [])
                ->where('pdr2.vote > 0');
        }

        if ($options['has_delete_votes']) {
            $select->join(['pdr3' => 'pictures_moder_votes'], 'pictures.id = pdr3.picture_id', [])
                ->where('pdr3.vote <= 0');
        }

        if ($options['is_replace'] !== null) {
            if ($options['is_replace']) {
                $select->where(['pictures.replace_picture_id']);
            } else {
                $select->where(['pictures.replace_picture_id is null']);
            }
        }

        if ($options['is_lost']) {
            $select
                ->join(['pi_left' => 'picture_item'], 'pictures.id = pi_left.picture_id', [], $select::JOIN_LEFT)
                ->where(['pi_left.item_id IS NULL']);
        }

        if ($options['has_point']) {
            $select->where(['pictures.point IS NOT NULL']);
        }

        switch ($options['order']) {
            case 'accept_datetime_desc':
                $select->order('accept_datetime desc');
                break;
            case 'add_date_desc':
                $select->order('pictures.add_date DESC');
                break;
            case 'add_date_asc':
                $select->order('pictures.add_date ASC');
                break;
            case 'resolution_desc':
                $select->order(['pictures.width DESC', 'pictures.height DESC']);
                break;
            case 'resolution_asc':
                $select->order(['pictures.width', 'pictures.height']);
                break;
            case 'filesize_desc':
                $select->order('pictures.filesize DESC');
                break;
            case 'filesize_asc':
                $select->order('pictures.filesize ASC');
                break;
            case 'comments':
                $joinLeftComments = true;
                $select->order('comment_topic.messages DESC');
                break;
            case 'views':
                $select->join('picture_view', 'pictures.id = picture_view.picture_id', [], $select::JOIN_LEFT)
                    ->order('picture_view.views DESC');
                break;
            case 'moder_votes':
                $joinPdr = true;
                $select->order('pdr.day_date DESC');
                break;
            case 'similarity':
                $select->order('df_distance.distance ASC');
                break;
            case 'removing_date':
                $select->order(['pictures.removing_date DESC', 'pictures.id']);
                break;
            case 'likes':
                $select->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', [])
                    ->where('picture_vote_summary.positive > 0')
                    ->order('picture_vote_summary.positive DESC');
                break;
            case 'dislikes':
                $select->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', [])
                    ->where('picture_vote_summary.negative > 0')
                    ->order('picture_vote_summary.negative DESC');
                break;
            case 'status':
                $select->order('pictures.status');
                break;
            case 'random':
                $select->order(new Sql\Expression('rand() desc'));
                break;
            case 'perspective_group':
                $select->order(['mp.position', 'pictures.width DESC', 'pictures.height DESC']);
                $group[] = 'mp.position';
                break;
        }

        if ($joinLeftComments) {
            $select->join(
                'comment_topic',
                new Sql\Expression(
                    'pictures.id = comment_topic.item_id and comment_topic.type_id = ?',
                    [\Application\Comments::PICTURES_TYPE_ID]
                ),
                [],
                $select::JOIN_LEFT
            );
        } elseif ($joinComments) {
            $select
                ->join('comment_topic', 'pictures.id = comment_topic.item_id', [])
                ->where(['comment_topic.type_id' => \Application\Comments::PICTURES_TYPE_ID]);
        }

        if ($joinPdr) {
            $select->join(['pdr' => 'pictures_moder_votes'], 'pictures.id = pdr.picture_id', []);
        }

        $group = array_unique($group, SORT_STRING);

        if ($group) {
            $select->group($group);
        }

        return $select;
    }

    public function getPaginator(array $options): Paginator\Paginator
    {
        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $this->getSelect($options),
                $this->table->getAdapter()
            )
        );
    }

    public function getCount(array $options): int
    {
        return $this->getPaginator($options)->getTotalItemCount();
    }

    public function getRow(array $options)
    {
        $select = $this->getSelect($options);
        $select->limit(1);

        return $this->table->selectWith($select)->current();
    }

    public function isExists(array $options): bool
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id']);
        $select->limit(1);

        return (bool)$this->table->selectWith($select)->current();
    }

    public function getRows(array $options): array
    {
        $select = $this->getSelect($options);
        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    public function getIds(array $options): array
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->columns(['id']);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = (int)$row['id'];
        }

        return $result;
    }

    public function getTable(): TableGateway
    {
        return $this->table;
    }

    public function getFileNamePattern($row): string
    {
        $result = rand(1, 9999);

        $filenameFilter = new FilenameSafe();

        $select = new Sql\Select($this->itemTable->getTable());
        $select
            ->join('picture_item', 'item.id = picture_item.item_id', [])
            ->where(['picture_item.picture_id' => $row['id']])
            ->limit(1);

        $cars = [];
        foreach ($this->itemTable->selectWith($select) as $itemRow) {
            $cars[] = $itemRow;
        }

        if (count($cars) > 1) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->where([
                    'item.item_type_id'       => ItemModel::BRAND,
                    'picture_item.picture_id' => $row['id']
                ]);

            $brands = $this->itemTable->selectWith($select);

            $f = [];
            foreach ($brands as $brand) {
                $f[] = $filenameFilter->filter($brand['catname']);
            }
            $f = array_unique($f);
            sort($f, SORT_STRING);

            $brandsFolder = implode('/', $f);
            $firstChar = mb_substr($brandsFolder, 0, 1);

            $result = $firstChar . '/' . $brandsFolder .'/mixed';
        } elseif (count($cars) == 1) {
            $car = $cars[0];

            $carCatname = $filenameFilter->filter($car['name']);

            $select = new Sql\Select($this->itemTable->getTable());
            $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->where([
                    'item.item_type_id'         => ItemModel::BRAND,
                    'item_parent_cache.item_id' => $car['id']
                ]);

            $brands = $this->itemTable->selectWith($select);

            $sBrands = [];
            foreach ($brands as $brand) {
                $sBrands[$brand['id']] = $brand;
            }

            if (count($sBrands) > 1) {
                $f = [];
                foreach ($sBrands as $brand) {
                    $f[] = $filenameFilter->filter($brand['catname']);
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

                    $brandFolder = $filenameFilter->filter($brand['catname']);
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
                    $carFolder = $filenameFilter->filter($car['name']);
                    $firstChar = mb_substr($carFolder, 0, 1);
                    $result = $firstChar . '/' . $carFolder.'/'.$carCatname;
                }
            }
        }

        $result = str_replace('//', '/', $result);

        return $result;
    }

    public function canAccept($row): bool
    {
        if (! in_array($row['status'], [self::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getNegativeVotesCount($row['id']);

        return $votes <= 0;
    }

    public function canDelete($row): bool
    {
        if (! in_array($row['status'], [self::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getPositiveVotesCount($row['id']);

        return $votes <= 0;
    }

    public function accept(int $pictureId, int $userId, &$isFirstTimeAccepted): bool
    {
        $primaryKey = ['id' => $pictureId];

        $isFirstTimeAccepted = false;

        $picture = $this->getRow($primaryKey);
        if (! $picture) {
            return false;
        }

        $set = [
            'status'                => self::STATUS_ACCEPTED,
            'change_status_user_id' => $userId
        ];

        if (! $picture['accept_datetime']) {
            $set['accept_datetime'] = new Sql\Expression('NOW()');

            $isFirstTimeAccepted = true;
        }
        $this->table->update($set, $primaryKey);

        return true;
    }

    public function generateIdentity()
    {
        do {
            $identity = $this->randomIdentity();

            $exists = $this->isExists([
                'identity' => $identity
            ]);
        } while ($exists);

        return $identity;
    }

    private function randomIdentity()
    {
        $alpha = "abcdefghijklmnopqrstuvwxyz";
        $number = "0123456789";

        return Rand::getString(1, $alpha) . Rand::getString(self::IDENTITY_LENGTH - 1, $alpha . $number);
    }
}
