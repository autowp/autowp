<?php

namespace Application\Model;

use Application\Comments;
use Application\Model\Item as ItemModel;
use Application\Module;
use ArrayAccess;
use ArrayObject;
use Autowp\ZFComponents\Filter\FilenameSafe;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Math\Rand;
use Laminas\Paginator;
use Traversable;

use function array_keys;
use function array_merge;
use function array_replace;
use function array_unique;
use function array_values;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function mb_substr;
use function rand;
use function sort;
use function str_replace;
use function strlen;
use function trim;
use function usort;

use const SORT_STRING;

class Picture
{
    public const
        STATUS_ACCEPTED = 'accepted',
        STATUS_REMOVING = 'removing',
        STATUS_REMOVED  = 'removed',
        STATUS_INBOX    = 'inbox';

    public const MAX_NAME = 255;

    private const IDENTITY_LENGTH = 6;

    private TableGateway $table;

    private TableGateway $itemTable;

    private PictureModerVote $pictureModerVote;

    private TableGateway $pictureItemTable;

    private array $prefixedPerspectives = [5, 6, 17, 20, 21, 22, 23, 24, 28];

    private Perspective $perspective;

    private LanguagePriority $languagePriority;

    public function __construct(
        TableGateway $table,
        TableGateway $itemTable,
        PictureModerVote $pictureModerVote,
        TableGateway $pictureItemTable,
        Perspective $perspective
    ) {
        $this->table            = $table;
        $this->itemTable        = $itemTable;
        $this->pictureModerVote = $pictureModerVote;
        $this->pictureItemTable = $pictureItemTable;
        $this->perspective      = $perspective;

        $this->languagePriority = new LanguagePriority();
    }

    /**
     * @param array|int $value
     * @throws Exception
     */
    private function applyIdFilter(Sql\Select $select, $value, string $id): void
    {
        if (is_array($value)) {
            $value = array_values($value);

            if (count($value) === 1) {
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
            throw new Exception('`id` must be scalar or array of scalar');
        }

        $select->where([$id => $value]);
    }

    /**
     * @param array|int $options
     * @throws Exception
     */
    private function applyPerspectiveFilter(Sql\Select $select, $options): void
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options,
            ];
        }

        $defaults = [
            'id'    => null,
            'group' => null,
        ];
        $options  = array_replace($defaults, $options);

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

    /**
     * @param array|int $options
     */
    private function applyAncestorFilter(Sql\Select $select, $options, string $idColumn): void
    {
        if (! is_array($options)) {
            $options = ['id' => $options];
        }

        $defaults = [
            'id'        => null,
            'link_type' => null,
        ];
        $options  = array_replace($defaults, $options);

        $select->join(['ipc_ancestor' => 'item_parent_cache'], $idColumn . ' = ipc_ancestor.item_id', []);

        if ($options['id']) {
            $select->where(['ipc_ancestor.parent_id' => $options['id']]);
        }

        if ($options['link_type']) {
            if (! in_array(ItemParent::TYPE_SPORT, $options['link_type'])) {
                $select->where(['not ipc_ancestor.sport']);
            }

            if (! in_array(ItemParent::TYPE_TUNING, $options['link_type'])) {
                $select->where(['not ipc_ancestor.tuning']);
            }
        }
    }

    /**
     * @param array|int $options
     */
    private function applyExcludeAncestorFilter(Sql\Select $select, $options, string $idColumn): void
    {
        if (! is_array($options)) {
            $options = ['id' => $options];
        }

        $defaults = [
            'id' => null,
        ];
        $options  = array_replace($defaults, $options);

        if ($options['id']) {
            $subSelect = new Sql\Select();
            $subSelect
                ->from('picture_item')
                ->columns(['picture_id'])
                ->join(
                    ['ipc_exclude_ancestor' => 'item_parent_cache'],
                    'picture_item.item_id = ipc_exclude_ancestor.item_id',
                    []
                )
                ->where(['ipc_exclude_ancestor.parent_id' => (int) $options['id']]);

            $select->where([new Sql\Predicate\NotIn($idColumn, $subSelect)]);
        }
    }

    private function applyEngineFilter(Sql\Select $select, array $options): void
    {
        $defaults = [
            'ancestor_or_self' => null,
        ];
        $options  = array_replace($defaults, $options);

        if ($options['ancestor_or_self'] !== null) {
            $this->applyAncestorFilter($select, $options['ancestor_or_self'], 'item.engine_item_id');
        }
    }

    /**
     * @param array|int $options
     * @throws Exception
     */
    private function applyItemFilters(Sql\Select $select, $options, bool $forceJoinItem): array
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options,
            ];
        }

        $defaults = [
            'id'                       => null,
            'item_type_id'             => null,
            'ancestor_or_self'         => null,
            'exclude_ancestor_or_self' => null,
            'perspective'              => null,
            'perspective_is_null'      => null,
            'perspective_exclude'      => null,
            'vehicle_type'             => null,
            'parent'                   => null,
            'contains_picture'         => null,
            'engine'                   => null,
            'link_type'                => null,
        ];
        $options  = array_replace($defaults, $options);

        $joinItem = $forceJoinItem;

        if ($options['item_type_id'] || $options['engine'] || isset($options['perspective_is_null'])) {
            $joinItem = true;
        }

        $select->join('picture_item', 'pictures.id = picture_item.picture_id', []);

        if ($joinItem) {
            $select->join('item', 'picture_item.item_id = item.id', []);
        }

        if ($options['engine']) {
            $this->applyEngineFilter($select, $options['engine']);
        }

        if ($options['link_type']) {
            $select->where(['picture_item.type' => $options['link_type']]);
        }

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'picture_item.item_id');
        }

        if ($options['item_type_id']) {
            $this->applyIdFilter($select, $options['item_type_id'], 'item.item_type_id');
        }

        if ($options['ancestor_or_self'] !== null) {
            $this->applyAncestorFilter($select, $options['ancestor_or_self'], 'picture_item.item_id');
        }

        if ($options['exclude_ancestor_or_self'] !== null) {
            $this->applyExcludeAncestorFilter($select, $options['exclude_ancestor_or_self'], 'pictures.id');
        }

        if ($options['perspective'] !== null) {
            $this->applyPerspectiveFilter($select, $options['perspective']);
        }

        if ($options['perspective_is_null'] !== null) {
            $this->applyIdFilter($select, ItemModel::VEHICLE, 'item.item_type_id');
            $select->where(['picture_item.type' => PictureItem::PICTURE_CONTENT]);
            if ($options['perspective_is_null']) {
                $select->where([new Sql\Predicate\IsNull('picture_item.perspective_id')]);
            } else {
                $select->where([new Sql\Predicate\IsNotNull('picture_item.perspective_id')]);
            }
        }

        if ($options['perspective_exclude']) {
            $predicate = new Sql\Predicate\PredicateSet([
                new Sql\Predicate\NotIn('picture_item.perspective_id', $options['perspective_exclude']),
                new Sql\Predicate\IsNull('picture_item.perspective_id'),
            ], Sql\Predicate\PredicateSet::COMBINED_BY_OR);

            $select->where($predicate);
        }

        if ($options['vehicle_type'] !== null) {
            $select
                ->join('vehicle_vehicle_type', 'picture_item.item_id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where(['car_types_parents.parent_id' => $options['vehicle_type']]);
        }

        if ($options['parent']) {
            $select->join('item_parent', 'picture_item.item_id = item_parent.item_id', [])
                ->where(['item_parent.parent_id' => $options['parent']]);
        }

        if ($options['contains_picture']) {
            $select->join(['pi2' => 'picture_item'], 'picture_item.item_id = pi2.item_id', []);

            $this->applyIdFilter($select, $options['contains_picture'], 'pi2.picture_id');
        }

        return ['pictures.id'];
    }

    private function applyColumns(Sql\Select $select, array $columns): void
    {
        $result = [];

        foreach ($columns as $key => $column) {
            switch ($column) {
                case 'id':
                case 'identity':
                case 'name':
                case 'width':
                case 'height':
                case 'image_id':
                case 'filesize':
                case 'status':
                    if (is_numeric($key)) {
                        $result[] = $column;
                    } else {
                        $result[$key] = $column;
                    }
                    break;
                case 'messages':
                    $predicate = new Sql\Predicate\PredicateSet([
                        new Sql\Predicate\Operator(
                            'ct.type_id',
                            Sql\Predicate\Operator::OPERATOR_EQUAL_TO,
                            Comments::PICTURES_TYPE_ID
                        ),
                        new Sql\Predicate\Operator(
                            'ct.item_id',
                            Sql\Predicate\Operator::OPERATOR_EQUAL_TO,
                            'pictures.id',
                            Sql\Predicate\Operator::TYPE_IDENTIFIER,
                            Sql\Predicate\Operator::TYPE_IDENTIFIER
                        ),
                    ]);
                    /**
                     * @psalm-suppress InvalidArgument
                     */
                    $select->join(
                        ['ct' => 'comment_topic'],
                        $predicate,
                        ['messages'],
                        $select::JOIN_LEFT
                    );
                    break;
            }
        }

        $select->columns($result);
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @throws Exception
     */
    public function getSelect(array $options): Sql\Select
    {
        $defaults = [
            'id'               => null,
            'id_exclude'       => null,
            'id_lt'            => null,
            'id_gt'            => null,
            'identity'         => null,
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
            'has_likes'        => null,
            'has_dislikes'     => null,
            'is_replace'       => null,
            'is_lost'          => null,
            'has_point'        => null,
            'order'            => null,
            'has_copyrights'   => null,
            'limit'            => null,
            'accepted_in_days' => null,
            'log'              => null,
            'group'            => [],
            'add_date'         => null,
            'accept_date'      => null,
            'timezone'         => null,
            'added_from'       => null,
        ];
        $options  = array_replace($defaults, $options);

        $forceJoinItem = false;
        if ($options['order'] === 'ancestor_stock_front_first') {
            $forceJoinItem = true;
        }

        $select = $this->table->getSql()->select();
        $group  = $options['group'];

        $pictureItemJoined    = false;
        $joinPdr              = false;
        $joinLeftComments     = false;
        $joinComments         = false;
        $joinVotesSummary     = false;
        $joinLeftVotesSummary = false;

        if (isset($options['columns']) && $options['columns']) {
            $this->applyColumns($select, $options['columns']);
        }

        if ($options['identity'] !== null) {
            $select->where(['pictures.identity' => (string) $options['identity']]);
        }

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'pictures.id');
        }

        if ($options['id_lt']) {
            $select->where(['pictures.id < ?' => $options['id_lt']]);
        }

        if ($options['id_gt']) {
            $select->where(['pictures.id > ?' => $options['id_gt']]);
        }

        if ($options['id_exclude']) {
            $value = (array) $options['id_exclude'];
            if (count($value) > 0) {
                $select->where([new Sql\Predicate\NotIn('pictures.id', $value)]);
            }
        }

        if ($options['add_date']) {
            if (! isset($options['timezone'])) {
                throw new Exception("Timezone not provided");
            }

            $this->setDateFilter($select, 'pictures.add_date', $options['add_date'], $options['timezone']);
        }

        if ($options['accept_date']) {
            if (! isset($options['timezone'])) {
                throw new Exception("Timezone not provided");
            }

            $this->setDateFilter($select, 'pictures.accept_datetime', $options['accept_date'], $options['timezone']);
        }

        if ($options['added_from']) {
            if (! isset($options['timezone'])) {
                throw new Exception("Timezone not provided");
            }

            $timezone   = new DateTimeZone($options['timezone']);
            $dbTimezine = new DateTimeZone(Module::MYSQL_TIMEZONE);

            $date = DateTime::createFromFormat('Y-m-d', $options['added_from'], $timezone);

            $start = clone $date;
            $start->setTime(0, 0, 0);
            $start->setTimezone($dbTimezine);

            $select->where([
                'pictures.add_date > ?' => $start->format(Module::MYSQL_DATETIME_FORMAT),
            ]);
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
            $subGroup          = $this->applyItemFilters($select, $options['item'], $forceJoinItem);
            $group             = array_merge($group, $subGroup);
            $pictureItemJoined = true;
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

        if ($options['has_likes']) {
            $joinVotesSummary = true;
            $select->where('picture_vote_summary.positive > 0');
        }

        if ($options['has_dislikes']) {
            $joinVotesSummary = true;
            $select->where('picture_vote_summary.negative > 0');
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

        if (isset($options['has_point'])) {
            if ($options['has_point']) {
                $select->where(['pictures.point IS NOT NULL']);
            } else {
                $select->where(['pictures.point IS NULL']);
            }
        }

        if ($options['has_copyrights']) {
            $select->where(['pictures.copyrights_text_id IS NOT NULL']);
        }

        if ($options['accepted_in_days']) {
            $select->where([
                new Sql\Predicate\Expression(
                    'pictures.accept_datetime > DATE_SUB(CURDATE(), INTERVAL ? DAY)',
                    [(int) $options['accepted_in_days']]
                ),
            ]);
        }

        if ($options['log']) {
            $select->join('log_events_pictures', 'pictures.id = log_events_pictures.picture_id', []);
            $this->applyIdFilter($select, $options['log'], 'log_events_pictures.log_event_id');
        }

        if (is_array($options['order'])) {
            $select->order($options['order']);
        } else {
            switch ($options['order']) {
                case 'accept_datetime_desc':
                    $select->order(['accept_datetime desc', 'pictures.add_date DESC', 'pictures.id DESC']);
                    break;
                case 'add_date_desc':
                    $select->order(['pictures.add_date DESC', 'pictures.id DESC']);
                    break;
                case 'add_date_asc':
                    $select->order(['pictures.add_date ASC', 'pictures.id ASC']);
                    break;
                case 'resolution_desc':
                    $select->order([
                        'pictures.width DESC',
                        'pictures.height DESC',
                        'pictures.add_date DESC',
                        'pictures.id DESC',
                    ]);
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
                    $select->order(new Sql\Expression('MAX(pdr.day_date) DESC'));
                    break;
                case 'similarity':
                    $select->order('df_distance.distance ASC');
                    $group[] = 'df_distance.distance';
                    break;
                case 'removing_date':
                    $select->order(['pictures.removing_date DESC', 'pictures.id']);
                    break;
                case 'likes':
                    $joinLeftVotesSummary = true;
                    $select->order([
                        'picture_vote_summary.positive DESC',
                        'pictures.add_date DESC',
                        'pictures.id DESC',
                    ]);
                    break;
                case 'dislikes':
                    $joinLeftVotesSummary = true;
                    $select->order([
                        'picture_vote_summary.negative DESC',
                        'pictures.add_date DESC',
                        'pictures.id DESC',
                    ]);
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
                case 'ancestor_stock_front_first':
                    $group[] = 'ipc_ancestor.tuning';
                    $group[] = 'ipc_ancestor.sport';
                    $group[] = 'item.is_concept';
                    $group[] = 'picture_item.perspective_id';
                    $select->order([
                        new Sql\Expression('ipc_ancestor.tuning asc'),
                        new Sql\Expression('ipc_ancestor.sport asc'),
                        new Sql\Expression('item.is_concept asc'),
                        new Sql\Expression('picture_item.perspective_id = 10 desc'),
                        new Sql\Expression('picture_item.perspective_id = 1 desc'),
                        new Sql\Expression('picture_item.perspective_id = 7 desc'),
                        new Sql\Expression('picture_item.perspective_id = 8 desc'),
                    ]);
                    break;

                case 'front_angle':
                    $group[] = 'picture_item.perspective_id';
                    $select->order([
                        new Sql\Expression('picture_item.perspective_id=7 DESC'),
                        new Sql\Expression('picture_item.perspective_id=8 DESC'),
                        new Sql\Expression('picture_item.perspective_id=1 DESC'),
                    ]);
                    break;

                case 'perspectives':
                    if (! $pictureItemJoined) {
                        throw new Exception("Can't sort by perspective. Specify item");
                    }
                    $group[] = 'perspectives.position';
                    $select
                        ->join('perspectives', 'picture_item.perspective_id = perspectives.id', [], $select::JOIN_LEFT)
                        ->order([
                            'perspectives.position',
                            'pictures.width DESC',
                            'pictures.height DESC',
                            'pictures.add_date DESC',
                            'pictures.id DESC',
                        ]);
                    break;

                case 'id_desc':
                    $select->order(['pictures.id DESC']);
                    break;

                case 'id':
                case 'id_asc':
                    $select->order(['pictures.id']);
                    break;
            }
        }

        if ($joinLeftComments) {
            $select->join(
                'comment_topic',
                new Sql\Expression(
                    'pictures.id = comment_topic.item_id and comment_topic.type_id = ?',
                    [Comments::PICTURES_TYPE_ID]
                ),
                [],
                $select::JOIN_LEFT
            );
        } elseif ($joinComments) {
            $select
                ->join('comment_topic', 'pictures.id = comment_topic.item_id', [])
                ->where(['comment_topic.type_id' => Comments::PICTURES_TYPE_ID]);
        }

        if ($joinPdr) {
            $group[] = 'pictures.id';
            $select->join(['pdr' => 'pictures_moder_votes'], 'pictures.id = pdr.picture_id', []);
        }

        if ($joinVotesSummary) {
            $select->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', []);
        } elseif ($joinLeftVotesSummary) {
            $select->join(
                'picture_vote_summary',
                'pictures.id = picture_vote_summary.picture_id',
                [],
                $select::JOIN_LEFT
            );
        }

        $group = array_unique($group, SORT_STRING);

        if ($group) {
            $select->group($group);
        }

        if ($options['limit']) {
            $select->limit($options['limit']);
        }

        //print_r($select->getSqlString($this->table->getAdapter()->getPlatform())); exit;

        return $select;
    }

    /**
     * @throws Exception
     */
    private function setDateFilter(Sql\Select $select, string $column, string $date, string $timezone): void
    {
        $timezone   = new DateTimeZone($timezone);
        $dbTimezine = new DateTimeZone(Module::MYSQL_TIMEZONE);

        $dateObj = DateTime::createFromFormat('Y-m-d', $date, $timezone);

        if ($dateObj === false) {
            throw new Exception("Failed to parse date `$date`");
        }

        $start = clone $dateObj;
        $start->setTime(0, 0, 0);
        $start->setTimezone($dbTimezine);

        $end = clone $dateObj;
        $end->setTime(23, 59, 59);
        $end->setTimezone($dbTimezine);

        $select->where([
            new Sql\Predicate\Between(
                $column,
                $start->format(Module::MYSQL_DATETIME_FORMAT),
                $end->format(Module::MYSQL_DATETIME_FORMAT)
            ),
        ]);
    }

    /**
     * @throws Exception
     */
    public function getPaginator(array $options): Paginator\Paginator
    {
        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();
        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($this->getSelect($options), $adapter)
        );
    }

    /**
     * @throws Exception
     */
    public function getCount(array $options): int
    {
        return $this->getPaginator($options)->getTotalItemCount();
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @throws Exception
     */
    public function getCountDistinct(array $options): int
    {
        $select = $this->getSelect($options);

        $select->reset(Sql\Select::LIMIT);
        $select->reset(Sql\Select::OFFSET);
        $select->reset(Sql\Select::ORDER);
        $select->reset(Sql\Select::COLUMNS);
        $select->reset(Sql\Select::GROUP);
        $select->columns(['id']);
        $select->quantifier(Sql\Select::QUANTIFIER_DISTINCT);

        $countSelect = new Sql\Select();

        $countSelect->columns(['count' => new Sql\Expression('COUNT(1)')]);
        $countSelect->from(['original_select' => $select]);

        $statement = $this->itemTable->getSql()->prepareStatementForSqlObject($countSelect);
        $row       = $statement->execute()->current();

        return (int) $row['count'];
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getRow(array $options)
    {
        $select = $this->getSelect($options);
        $select->limit(1);

        return currentFromResultSetInterface($this->table->selectWith($select));
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    public function isExists(array $options): bool
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id']);
        $select->limit(1);

        return (bool) currentFromResultSetInterface($this->table->selectWith($select));
    }

    /**
     * @throws Exception
     */
    public function getRows(array $options): array
    {
        $select = $this->getSelect($options);
        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getIds(array $options): array
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->columns(['id']);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = (int) $row['id'];
        }

        return $result;
    }

    public function getTable(): TableGateway
    {
        return $this->table;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function getFileNamePattern(int $pictureId): string
    {
        $result = rand(1, 9999);

        $filenameFilter = new FilenameSafe();

        $select = new Sql\Select($this->itemTable->getTable());
        $select
            ->columns(['id', 'name', 'item_type_id'])
            ->join('picture_item', 'item.id = picture_item.item_id', ['type'])
            ->where(['picture_item.picture_id' => $pictureId])
            ->order([new Sql\Expression('picture_item.type = ? DESC', [PictureItem::PICTURE_CONTENT])])
            ->limit(3);

        $items = [];
        foreach ($this->itemTable->selectWith($select) as $itemRow) {
            $items[] = $itemRow;
        }

        $primaryItems = [];
        foreach ($items as $item) {
            if ((int) $item['type'] === PictureItem::PICTURE_CONTENT) {
                $primaryItems[] = $item;
            }
        }

        if (count($primaryItems) > 1) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->where([
                    'item.item_type_id'       => ItemModel::BRAND,
                    'picture_item.picture_id' => $pictureId,
                ]);

            $brands = $this->itemTable->selectWith($select);

            $f = [];
            foreach ($brands as $brand) {
                $f[] = $filenameFilter->filter($brand['catname']);
            }
            $f = array_unique($f);
            sort($f, SORT_STRING);

            $brandsFolder = implode('/', $f);

            $parts = [];
            foreach ($primaryItems as $item) {
                $parts[] = $filenameFilter->filter($item['name']);
            }
            $itemCatname  = implode('/', $parts);
            $itemFilename = implode('_', $parts);

            $result    = ($brandsFolder ? $brandsFolder . '/' : '') . $itemCatname . '/' . $itemFilename;
            $firstChar = mb_substr($result, 0, 1);
            $result    = $firstChar . '/' . $result;
        } elseif (count($primaryItems) === 1) {
            $car = $primaryItems[0];

            $carCatname = $filenameFilter->filter($car['name']);

            $select = new Sql\Select($this->itemTable->getTable());
            $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->where([
                    'item.item_type_id'         => ItemModel::BRAND,
                    'item_parent_cache.item_id' => $car['id'],
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
                $firstChar    = mb_substr($brandsFolder, 0, 1);

                $result = $firstChar . '/' . $brandsFolder . '/' . $carFolder . '/' . $carCatname;
            } else {
                if (count($sBrands) === 1) {
                    $sBrandsA = array_values($sBrands);
                    $brand    = $sBrandsA[0];

                    $brandFolder = $filenameFilter->filter($brand['catname']);
                    $firstChar   = mb_substr($brandFolder, 0, 1);

                    $carFolder = $carCatname;
                    $carFolder = trim(str_replace($brandFolder, '', $carFolder), '_-');

                    $result = implode('/', [
                        $firstChar,
                        $brandFolder,
                        $carFolder,
                        $carCatname,
                    ]);
                } else {
                    $carFolder = $filenameFilter->filter($car['name']);
                    $firstChar = mb_substr($carFolder, 0, 1);
                    $result    = $firstChar . '/' . $carFolder . '/' . $carCatname;
                }
            }
        } elseif (count($items) > 0) {
            $parts = [];
            foreach ($items as $item) {
                $parts[] = $filenameFilter->filter($item['name']);
            }
            $folder    = implode('/', $parts);
            $firstChar = mb_substr($folder, 0, 1);
            $result    = $firstChar . '/' . $folder;
        }

        $result = str_replace('//', '/', $result);

        return $result;
    }

    /**
     * @param array|ArrayAccess $row
     */
    public function canAccept($row): bool
    {
        if (! in_array($row['status'], [self::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getNegativeVotesCount($row['id']);

        return $votes <= 0;
    }

    /**
     * @param array|ArrayAccess $row
     */
    public function canDelete($row): bool
    {
        if (! in_array($row['status'], [self::STATUS_INBOX])) {
            return false;
        }

        $votes = $this->pictureModerVote->getPositiveVotesCount($row['id']);

        return $votes <= 0;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @throws Exception
     */
    public function accept(int $pictureId, int $userId, bool &$isFirstTimeAccepted): bool
    {
        $primaryKey = ['id' => $pictureId];

        $isFirstTimeAccepted = false;

        $picture = $this->getRow($primaryKey);
        if (! $picture) {
            return false;
        }

        $set = [
            'status'                => self::STATUS_ACCEPTED,
            'change_status_user_id' => $userId,
        ];

        if (! $picture['accept_datetime']) {
            $set['accept_datetime'] = new Sql\Expression('NOW()');

            $isFirstTimeAccepted = true;
        }
        $this->table->update($set, $primaryKey);

        return true;
    }

    /**
     * @throws Exception
     */
    public function generateIdentity(): string
    {
        do {
            $identity = $this->randomIdentity();

            $exists = $this->isExists([
                'identity' => $identity,
            ]);
        } while ($exists);

        return $identity;
    }

    private function randomIdentity(): string
    {
        $alpha  = "abcdefghijklmnopqrstuvwxyz";
        $number = "0123456789";

        return Rand::getString(1, $alpha) . Rand::getString(self::IDENTITY_LENGTH - 1, $alpha . $number);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     * @throws Exception
     */
    public function getTotalPicturesSize(): int
    {
        $select = $this->table->getSql()->select();
        $select->columns(['sum' => new Sql\Expression('sum(filesize)')]);
        $row = currentFromResultSetInterface($this->table->selectWith($select));
        return $row ? (int) $row['sum'] : 0;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param array|ArrayAccess|Traversable $rows
     */
    public function getNameData($rows, array $options = []): array
    {
        $result = [];

        $language = $options['language'] ?? 'en';
        $large    = isset($options['large']) && $options['large'];

        // prefetch
        $itemIds        = [];
        $perspectiveIds = [];
        foreach ($rows as $index => $row) {
            $pictureItemRows = $this->pictureItemTable->select([
                'picture_id' => $row['id'],
                'type'       => PictureItem::PICTURE_CONTENT,
            ]);

            foreach ($pictureItemRows as $pictureItemRow) {
                $itemIds[$pictureItemRow['item_id']] = $pictureItemRow['crop_left'];
                if (in_array($pictureItemRow['perspective_id'], $this->prefixedPerspectives)) {
                    $perspectiveIds[$pictureItemRow['perspective_id']] = true;
                }
            }
        }

        $items = [];
        if (count($itemIds)) {
            /** @var Adapter $adapter */
            $adapter   = $this->table->getAdapter();
            $subSelect = $this->languagePriority->getSelectItemName($language, $adapter);

            $columns = [
                'id',
                'begin_model_year',
                'end_model_year',
                'begin_model_year_fraction',
                'end_model_year_fraction',
                'body',
                'name' => new Sql\Expression('(' . $subSelect . ')'),
                'begin_year',
                'end_year',
                'today',
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
                ], $select::JOIN_LEFT);

            foreach ($this->itemTable->selectWith($select) as $row) {
                $data = [
                    'begin_model_year'          => $row['begin_model_year'],
                    'end_model_year'            => $row['end_model_year'],
                    'begin_model_year_fraction' => $row['begin_model_year_fraction'],
                    'end_model_year_fraction'   => $row['end_model_year_fraction'],
                    'spec'                      => $row['spec'],
                    'spec_full'                 => $row['spec_full'],
                    'body'                      => $row['body'],
                    'name'                      => $row['name'],
                    'begin_year'                => $row['begin_year'],
                    'end_year'                  => $row['end_year'],
                    'today'                     => $row['today'],
                ];
                if ($large) {
                    $data['begin_month'] = $row['begin_month'];
                    $data['end_month']   = $row['end_month'];
                }
                $items[$row['id']] = $data;
            }
        }

        $perspectives = $this->perspective->getOnlyPairs(array_keys($perspectiveIds));

        foreach ($rows as $index => $row) {
            if ($row['name']) {
                $result[$row['id']] = [
                    'name' => $row['name'],
                ];
                continue;
            }

            $subRows = $this->pictureItemTable->select([
                'picture_id' => $row['id'],
                'type'       => PictureItem::PICTURE_CONTENT,
            ]);

            $pictureItemRows = [];
            foreach ($subRows as $subRow) {
                $pictureItemRows[] = $subRow;
            }

            usort($pictureItemRows, function ($rowA, $rowB) use ($itemIds) {
                $a = $itemIds[$rowA['item_id']] ?? 0;
                $b = $itemIds[$rowB['item_id']] ?? 0;

                if ($a === $b) {
                    return 0;
                }
                return $a < $b ? -1 : 1;
            });

            $resultItems = [];
            foreach ($pictureItemRows as $pictureItemRow) {
                $itemId        = $pictureItemRow['item_id'];
                $perspectiveId = $pictureItemRow['perspective_id'];

                $item = $items[$itemId] ?? [];

                $resultItems[] = array_replace($item, [
                    'perspective' => $perspectives[$perspectiveId] ?? null,
                ]);
            }

            $result[$row['id']] = [
                'items' => $resultItems,
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getTopLikes(int $limit): array
    {
        $select = $this->table->getSql()->select()
            ->columns(['owner_id', 'volume' => new Sql\Expression('sum(picture_vote.value)')])
            ->join('picture_vote', 'pictures.id = picture_vote.picture_id', [])
            ->where(['pictures.owner_id != picture_vote.user_id'])
            ->group('pictures.owner_id')
            ->order('volume DESC')
            ->limit($limit);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[(int) $row['owner_id']] = (int) $row['volume'];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getTopOwnerFans(int $userId, int $limit): array
    {
        $select = $this->table->getSql()->select()
            ->columns([])
            ->join('picture_vote', 'pictures.id = picture_vote.picture_id', [
                'user_id',
                'volume' => new Sql\Expression('count(1)'),
            ])
            ->where(['pictures.owner_id' => $userId])
            ->group('picture_vote.user_id')
            ->order('volume desc')
            ->limit($limit);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[(int) $row['user_id']] = (int) $row['volume'];
        }

        return $result;
    }
}
