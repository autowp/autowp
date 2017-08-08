<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

class Picture
{
    const
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    const MAX_NAME = 255;

    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
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
            'perspective_id'      => null,
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

        if ($options['perspective_id'] !== null) {
            $select->where(['picture_item.perspective_id' => $options['perspective_id']]);
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
}
