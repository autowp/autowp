<?php

namespace Application\Model;

use Collator;
use Transliterator;

use Autowp\Image;

use Application\Model\DbTable;

use Zend_Db_Expr;

class Brand
{
    const TOP_COUNT = 150;

    const NEW_DAYS = 7;

    const MAX_NAME = 80;

    const MAX_FULLNAME = 255;

    const ICON_FORMAT = 'brandicon';

    /**
     * @var DbTable\Item
     */
    private $table;

    private $collators = [];

    public function __construct()
    {
        $this->table = new DbTable\Item();
    }

    public function getTotalCount()
    {
        $sql = 'SELECT COUNT(1) FROM item WHERE item_type_id = ?';
        return $this->table->getAdapter()->fetchOne($sql, [Item::BRAND]);
    }

    private function countExpr()
    {
        $db = $this->table->getAdapter();

        return new Zend_Db_Expr('(' .
            '(' .
                $db->select()
                    ->from(['product' => 'item'], 'count(distinct product.id)')
                    ->join('item_parent_cache', 'product.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = item.id')
                    ->assemble() .
            ')' .
        ')');
    }

    private function getCollator($language)
    {
        if (! isset($this->collators[$language])) {
            $this->collators[$language] = new Collator($language);
        }

        return $this->collators[$language];
    }

    private function compareName($a, $b, $language)
    {
        $coll = $this->getCollator($language);
        switch ($language) {
            case 'zh':
                $aIsHan = (bool)preg_match("/^\p{Han}/u", $a);
                $bIsHan = (bool)preg_match("/^\p{Han}/u", $b);

                if ($aIsHan && ! $bIsHan) {
                    return -1;
                }

                if ($bIsHan && ! $aIsHan) {
                    return 1;
                }

                return $coll->compare($a, $b);
                break;

            default:
                return $coll->compare($a, $b);
                break;
        }
    }

    public function getTopBrandsList($language)
    {
        $db = $this->table->getAdapter();

        $items = [];

        $select = $db->select(true)
            ->from('item', [
                'id', 'catname',
                'name' => 'IF(LENGTH(item_language.name)>0, item_language.name, item.name)',
                'cars_count' => $this->countExpr()
            ])
            ->joinLeft(
                'item_language',
                'item.id = item_language.item_id and item_language.language = :language',
                null
            )
            ->where('item.item_type_id = ?', Item::BRAND)
            ->where('item.position = 0') // exclude "other"
            ->group('item.id')
            ->order('cars_count DESC')
            ->limit(self::TOP_COUNT)
            ->bind([
                'language' => $language
            ]);

        foreach ($db->fetchAll($select) as $brandRow) {
            $newCarsCount = $db->fetchOne(
                $db->select()
                    ->from('item', 'count(distinct item.id)')
                    ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $brandRow['id'])
                    ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                    ->where('item.add_datetime > DATE_SUB(NOW(), INTERVAL ? DAY)', self::NEW_DAYS)
            );

            $items[] = [
                'id'             => $brandRow['id'],
                'catname'        => $brandRow['catname'],
                'name'           => $brandRow['name'],
                'cars_count'     => $brandRow['cars_count'],
                'new_cars_count' => $newCarsCount
            ];
        }

        usort($items, function ($a, $b) use ($language) {
            return $this->compareName($a['name'], $b['name'], $language);
        });

        return $items;
    }

    private function utfCharToNumber($char)
    {
        $i = 0;
        $number = '';
        while (isset($char{$i})) {
            $number .= ord($char{$i});
            ++$i;
        }
        return $number;
    }

    public function getFullBrandsList($language)
    {
        $rows = $this->getList([
            'language' => $language,
            'columns'  => [
                'logo_id',
                'cars_count' => new Zend_Db_Expr(
                    'COUNT(subitem.id)'
                ),
                'new_cars_count' => new Zend_Db_Expr(
                    'COUNT(IF(subitem.add_datetime > DATE_SUB(NOW(), INTERVAL :new_days DAY), 1, NULL))'
                ),
                'pictures_count' => new Zend_Db_Expr(
                    '(' .
                        $this->table->getAdapter()->select()
                            ->from(['ipc_all' => 'item_parent_cache'], 'COUNT(DISTINCT pictures.id)')
                            ->join('picture_item', 'ipc_all.item_id = picture_item.item_id', null)
                            ->join('pictures', 'picture_item.picture_id = pictures.id', null)
                            ->where('item.id = ipc_all.parent_id')
                            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                            ->assemble() .
                    ')'
                ),
            ]
        ], function ($select) use ($language) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                ->join(['subitem' => 'item'], 'item_parent_cache.item_id = subitem.id', null)
                ->group('item.id')
                ->bind([
                    'language' => $language,
                    'new_days' => self::NEW_DAYS
                ]);
        });

        $result = [
            'numbers'  => [],
            'cyrillic' => [],
            'latin'    => [],
            'other'    => []
        ];

        $tr = Transliterator::create('Any-Latin;Latin-ASCII;');

        /*foreach ($rows as $row) {
            print $row['name'] . PHP_EOL;
        }*/

        foreach ($rows as $row) {
            $name = $row['name'];

            $char = mb_substr($name, 0, 1);

            $isNumber = preg_match("/^[0-9]$/u", $char);
            $isCyrillic = false;
            $isLatin = false;

            if (! $isNumber) {
                $isHan = preg_match("/^\p{Han}$/u", $char);
                if ($isHan) {
                    $char = mb_substr($tr->transliterate($char), 0, 1);
                    $isLatin = true;
                }

                if (! $isHan) {
                    $isCyrillic = preg_match("/^\p{Cyrillic}$/u", $char);

                    if (! $isCyrillic) {
                        $char = $tr->transliterate($char);

                        $isLatin = preg_match("/^[A-Za-z]$/u", $char);
                    }
                }
                $char = mb_strtoupper($char);
            }

            if ($isNumber) {
                $line = 'numbers';
            } elseif ($isCyrillic) {
                $line = 'cyrillic';
            } elseif ($isLatin) {
                $line = 'latin';
            } else {
                $line = 'other';
            }

            //print $this->utfCharToNumber($char) . PHP_EOL;

            if (! isset($result[$line][$char])) {
                $result[$line][$char] = [
                    'id'     => $this->utfCharToNumber($char),
                    'char'   => $char,
                    'brands' => []
                ];
            }

            /*$picturesCount = $row['carpictures_count'] + $row['enginepictures_count'] +
                $row['logopictures_count'] + $row['mixedpictures_count'] +
                $row['unsortedpictures_count'];*/

            $result[$line][$char]['brands'][] = [
                'id'             => $row['id'],
                'name'           => $name,
                'catname'        => $row['catname'],
                'logo_id'        => $row['logo_id'],
                'totalPictures'  => $row['pictures_count'],
                'newCars'        => $row['new_cars_count'],
                'totalCars'      => $row['cars_count']
            ];
        }

        foreach ($result as &$line) {
            uksort($line, function ($a, $b) use ($language) {
                return $this->compareName($a, $b, $language);
            });
        }
        unset($line);

        return $result;
    }

    public function getBrandIdByCatname($catname)
    {
        $db = $this->table->getAdapter();

        return $db->fetchOne(
            $db->select()
                ->from('brands', 'id')
                ->where('catname = ?', (string)$catname)
        );
    }

    private function fetchBrand($language, $callback)
    {
        $db = $this->table->getAdapter();

        $select = $db->select()
            ->from('item', [
                'id', 'catname',
                'name' => 'IF(LENGTH(item_language.name)>0, item_language.name, item.name)',
                'full_name', 'logo_id'
            ])
            ->joinLeft(
                'item_language',
                'item.id = item_language.item_id and item_language.language = :language',
                null
            )
            ->where('item.item_type_id = ?', Item::BRAND)
            ->bind([
                'language' => (string)$language
            ]);

        $callback($select);

        $brand = $db->fetchRow($select);

        if (! $brand) {
            return null;
        }

        return [
            'id'        => $brand['id'],
            'name'      => $brand['name'],
            'catname'   => $brand['catname'],
            'full_name' => $brand['full_name'],
            'logo_id'   => $brand['logo_id'],
        ];
    }

    public function getBrandById($id, $language)
    {
        return $this->fetchBrand($language, function ($select) use ($id) {
            $select->where('item.id = ?', (int)$id);
        });
    }

    public function getBrandByCatname($catname, $language)
    {
        return $this->fetchBrand($language, function ($select) use ($catname) {
            $select->where('item.catname = ?', (string)$catname);
        });
    }

    public function getBrand($options, callable $callback)
    {
        $result = $this->getList($options, $callback);
        if (count($result) > 0) {
            return $result;
        }

        return null;
    }

    public function getList($options, callable $callback = null)
    {
        if (is_string($options)) {
            $options = [
                'language' => $options
            ];
        }

        $defaults = [
            'language' => 'en',
            'columns'  => []
        ];
        $options = array_replace($defaults, $options);

        $db = $this->table->getAdapter();

        $columns = [
            'id',
            'catname',
            'position',
            'name'    => 'IF(' .
                'item_language.name IS NOT NULL and LENGTH(item_language.name)>0,' .
                'item_language.name,' .
                'item.name' .
            ')'
        ];
        foreach ($options['columns'] as $column => $expr) {
            switch ($expr) {
                case 'id':
                case 'name':
                    break;
                case 'logo_id':
                    $columns[] = 'logo_id';
                    break;
                default:
                    if (is_numeric($column)) {
                        $columns[] = $expr;
                    } else {
                        $columns[$column] = $expr;
                    }
            }
        }

        $select = $db->select()
            ->from('item', $columns)
            ->where('item.item_type_id = ?', Item::BRAND)
            ->joinLeft(
                'item_language',
                'item.id = item_language.item_id and item_language.language = :language',
                null
            )
            ->order(['item.position'])
            ->bind([
                'language' => (string)$options['language']
            ]);

        if ($callback) {
            $callback($select);
        }

        $items = $db->fetchAll($select);

        usort($items, function ($a, $b) use ($options) {

            if ($a['position'] != $b['position']) {
                return ($a['position'] < $b['position']) ? -1 : 1;
            }

            return $this->compareName($a['name'], $b['name'], $options['language']);
        });

        return $items;
    }

    public function createIconsSprite(Image\Storage $imageStorage, $destImg, $destCss)
    {
        $list = $this->getList([
            'language' => 'en',
            'columns'  => [
                'logo_id'
            ]
        ], function ($select) {
            $select->where('logo_id');
        });

        $images = [];

        $format = $imageStorage->getFormat(self::ICON_FORMAT);

        $background = $format->getBackground();

        foreach ($list as $brand) {
            $img = false;
            if ($brand['logo_id']) {
                $img = $imageStorage->getFormatedImagePath($brand['logo_id'], self::ICON_FORMAT);
            }

            if ($img) {
                $img = str_replace('http://i.wheelsage.org/', PUBLIC_DIR . '/', $img);
                $catname = str_replace('.', '_', $brand['catname']);
                $images[$catname] = escapeshellarg($img);
            }
        }

        $count = count($images);
        $width = (int)ceil(sqrt($count));
        if ($width <= 0) {
            $width = 1;
        }

        $cmd = sprintf(
            'montage ' . implode(' ', $images) . ' -background %s -geometry +1+1 -tile %dx %s',
            escapeshellarg($background ? $background : 'none'),
            $width,
            escapeshellarg($destImg)
        );

        $cmdFilename = tempnam(sys_get_temp_dir(), 'brandicons');
        file_put_contents($cmdFilename, $cmd);
        chmod($cmdFilename, 0700);
        exec($cmdFilename);

        $css = [];
        $index = 0;
        foreach ($images as $catname => $img) {
            $top = floor($index / $width);
            $left = $index - $top * $width;
            $css[] = sprintf(
                '.brandicon.brandicon-%s {background-position: -%dpx -%dpx}',
                $catname,
                1 + ($format->getWidth() + 1 + 1) * $left,
                1 + ($format->getHeight() + 1 + 1) * $top
            );
            $index++;
        }

        file_put_contents($destCss, implode(' ', $css));
    }
}
