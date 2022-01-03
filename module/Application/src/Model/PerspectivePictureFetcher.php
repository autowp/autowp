<?php

namespace Application\Model;

use ArrayAccess;
use Exception;
use Laminas\Db\Sql;

use function array_merge;
use function array_shift;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function ucfirst;

class PerspectivePictureFetcher
{
    protected Picture $pictureModel;

    private ?int $pictureItemTypeId;

    private int $perspectivePageId = 0;

    private array $perspectiveCache = [];

    private bool $onlyExactlyPictures = false;

    private Perspective $perspective;

    private int $perspectiveId = 0;

    private int $containsPerspectiveId = 0;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            $this->$method($value);
        }
    }

    public function setPictureModel(Picture $model): void
    {
        $this->pictureModel = $model;
    }

    public function setPictureItemTypeId(?int $value): void
    {
        $this->pictureItemTypeId = $value;
    }

    protected function getPictureSelect(int $itemId, array $options): Sql\Select
    {
        $defaults = [
            'perspectiveGroup'    => false,
            'exclude'             => [],
            'ids'                 => [],
            'acceptedSort'        => false,
            'onlyExactlyPictures' => false,
            'limit'               => 1,
        ];
        $options  = array_merge($defaults, $options);

        $select = $this->pictureModel->getTable()->getSql()->select();
        $select
            ->columns([
                'id',
                'name',
                'image_id',
                'width',
                'height',
                'identity',
                'status',
                'owner_id',
                'filesize',
                'add_date',
                'dpi_x',
                'dpi_y',
                'point',
            ])
            ->join(
                'picture_item',
                'pictures.id = picture_item.picture_id',
                ['perspective_id', 'item_id']
            )
            ->where([
                'pictures.status'   => Picture::STATUS_ACCEPTED,
                'picture_item.type' => $this->pictureItemTypeId ?: PictureItem::PICTURE_CONTENT,
            ])
            ->limit($options['limit']);

        $order = [];

        if ($options['onlyExactlyPictures']) {
            $select->where(['picture_item.item_id' => $itemId]);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->join('item', 'picture_item.item_id = item.id', [])
                ->where(['item_parent_cache.parent_id' => $itemId]);

            $order[] = 'item.is_concept asc';
            $order[] = 'item_parent_cache.sport asc';
            $order[] = 'item_parent_cache.tuning asc';
        }

        if ($this->perspectiveId) {
            $select->where(['picture_item.perspective_id' => $this->perspectiveId]);
        } elseif ($options['perspectiveGroup']) {
            $select
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    []
                )
                ->where(['mp.group_id' => $options['perspectiveGroup']]);

            $order[] = 'mp.position';
        }

        if ($this->containsPerspectiveId) {
            $select
                ->join(['pi2' => 'picture_item'], 'pictures.id = pi2.picture_id', [])
                ->where(['pi2.perspective_id' => $this->containsPerspectiveId]);
        }

        if ($options['ids']) {
            $select->where([new Sql\Predicate\In('pictures.id', $options['ids'])]);
        }

        if ($options['exclude']) {
            $select->where([new Sql\Predicate\NotIn('pictures.id', $options['exclude'])]);
        }

        if ($options['acceptedSort']) {
            $order[] = 'pictures.accept_datetime DESC';
        }

        $order = array_merge(['pictures.content_count ASC'], $order, ['pictures.width DESC', 'pictures.height DESC']);

        $select->order($order);

        return $select;
    }

    /**
     * @throws Exception
     */
    public function getTotalPictures(int $itemId, bool $onlyExactly): int
    {
        if (! $itemId) {
            return 0;
        }

        $select = $this->pictureModel->getTable()->getSql()->select();

        $select->where(['pictures.status' => Picture::STATUS_ACCEPTED]);

        if ($onlyExactly) {
            $select
                ->columns(['count' => new Sql\Expression('COUNT(1)')])
                ->join('picture_item', 'pictures.id = picture_item.picture_id', ['id' => 'item_id'])
                ->where(['picture_item.item_id' => $itemId]);
        } else {
            $select
                ->columns(['count' => new Sql\Expression('COUNT(1)')])
                ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                ->join(
                    'item_parent_cache',
                    'picture_item.item_id = item_parent_cache.item_id',
                    ['id' => 'parent_id']
                )
                ->where(['item_parent_cache.parent_id' => $itemId]);
        }

        if ($this->perspectiveId) {
            $select->where(['picture_item.perspective_id' => $this->perspectiveId]);
        }

        $row = currentFromResultSetInterface($this->pictureModel->getTable()->selectWith($select));
        if (! $row) {
            return 0;
        }

        return $row['count'];
    }

    private function getPerspectiveGroupIds(int $pageId): array
    {
        if (isset($this->perspectiveCache[$pageId])) {
            return $this->perspectiveCache[$pageId];
        }

        $ids = $this->perspective->getPageGroupIds($pageId);

        $this->perspectiveCache[$pageId] = $ids;

        return $ids;
    }

    public function setPerspective(Perspective $model): void
    {
        $this->perspective = $model;
    }

    public function setPerspectivePageId(int $id): void
    {
        $this->perspectivePageId = $id;
    }

    public function setPerspectiveId(int $id): void
    {
        $this->perspectiveId = $id;
    }

    public function setContainsPerspectiveId(int $id): void
    {
        $this->containsPerspectiveId = $id;
    }

    public function setOnlyExactlyPictures(bool $value): void
    {
        $this->onlyExactlyPictures = $value;
    }

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    public function fetch($item, array $options = []): array
    {
        $pictures = [];
        $usedIds  = [];

        $totalPictures = isset($options['totalPictures']) ? (int) $options['totalPictures'] : null;

        $useLargeFormat = false;
        if ($this->perspectivePageId) {
            $pPageId = $this->perspectivePageId;
        } else {
            $useLargeFormat = $totalPictures > 30;
            $pPageId        = $useLargeFormat ? 5 : 4;
        }

        $perspectiveGroupIds = $this->getPerspectiveGroupIds($pPageId);

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getPictureSelect($item['id'], [
                'onlyExactlyPictures' => $this->onlyExactlyPictures,
                'perspectiveGroup'    => $groupId,
                'exclude'             => $usedIds,
            ]);

            $select->limit(1);

            $picture = currentFromResultSetInterface($this->pictureModel->getTable()->selectWith($select));

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[]  = (int) $picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        $needMore = count($perspectiveGroupIds) - count($usedIds);

        if ($needMore > 0) {
            $select = $this->getPictureSelect($item['id'], [
                'onlyExactlyPictures' => $this->onlyExactlyPictures,
                'exclude'             => $usedIds,
            ]);

            $select->limit($needMore);

            $rows = $this->pictureModel->getTable()->selectWith($select);

            $morePictures = [];
            foreach ($rows as $row) {
                $morePictures[] = $row;
            }

            foreach ($pictures as $key => $picture) {
                if (count($morePictures) <= 0) {
                    break;
                }
                if (! $picture) {
                    $pictures[$key] = array_shift($morePictures);
                }
            }
        }

        $result        = [];
        $emptyPictures = 0;
        foreach ($pictures as $picture) {
            if ($picture) {
                $result[] = [
                    'row' => $picture,
                ];
            } else {
                $result[] = null;
                $emptyPictures++;
            }
        }

        if ($emptyPictures > 0 && ((int) $item['item_type_id'] === Item::ENGINE)) {
            $pictureRows = $this->pictureModel->getRows([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'perspective' => 17,
                    'engine'      => [
                        'ancestor_or_self' => $item['id'],
                    ],
                ],
                'limit'  => $emptyPictures,
            ]);

            $extraPicIdx = 0;

            foreach ($result as $idx => $picture) {
                if ($picture) {
                    continue;
                }
                if (count($pictureRows) <= $extraPicIdx) {
                    break;
                }
                $pictureRow   = $pictureRows[$extraPicIdx++];
                $result[$idx] = [
                    'row'           => $pictureRow,
                    'isVehicleHood' => true,
                ];
            }
        }

        return [
            'large_format' => $useLargeFormat,
            'pictures'     => $result,
        ];
    }
}
