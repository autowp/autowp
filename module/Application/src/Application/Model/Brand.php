<?php

namespace Application\Model;

use Application\Model\DbTable\Brand as Table;
use Zend_Db_Expr;

use Collator;
use Transliterator;

class Brand
{
    const TOP_COUNT = 150;

    const NEW_DAYS = 7;

    const MAX_NAME = 80;

    const MAX_FULLNAME = 255;

    const ICON_FORMAT = 'brandicon';

    /**
     * @var Table
     */
    private $table;

    private $collators = [];

    public function __construct()
    {
        $this->table = new Table();
    }

    public function getTotalCount()
    {
        $sql = 'SELECT COUNT(1) FROM brands';
        return $this->table->getAdapter()->fetchOne($sql);
    }

    private function countExpr()
    {
        $db = $this->table->getAdapter();

        return new Zend_Db_Expr('(' .
            '(' .
                $db->select()
                    ->from('cars', 'count(distinct cars.id)')
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = brands.id')
                    ->assemble() .
            ') + (' .
                $db->select()
                    ->from('brand_engine', 'count(engine_parent_cache.engine_id)')
                    ->where('brand_engine.brand_id = brands.id')
                    ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                    ->assemble() .
            ')' .
        ')');
    }

    private function getCollator($language)
    {
        if (!isset($this->collators[$language])) {
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

                if ($aIsHan && !$bIsHan) {
                    return -1;
                }

                if ($bIsHan && !$aIsHan) {
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
            ->from('brands', [
                'id', 'folder',
                'name' => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)',
                'cars_count' => $this->countExpr()
            ])
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->where('brands.id <> 58') // exclude "other"
            ->group('brands.id')
            ->order('cars_count DESC')
            ->limit(self::TOP_COUNT)
            ->bind([
                'language' => $language
            ]);

        foreach ($db->fetchAll($select) as $brandRow) {
            $newCarsCount = $db->fetchOne(
                $db->select()
                    ->from('cars', 'count(distinct cars.id)')
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brandRow['id'])
                    ->where('cars.add_datetime > DATE_SUB(NOW(), INTERVAL ? DAY)', self::NEW_DAYS)
            );

            $items[] = [
                'id'             => $brandRow['id'],
                'catname'        => $brandRow['folder'],
                'name'           => $brandRow['name'],
                'cars_count'     => $brandRow['cars_count'],
                'new_cars_count' => $newCarsCount
            ];
        }

        usort($items, function($a, $b) use($language) {
            return $this->compareName($a['name'], $b['name'], $language);
        });

        return $items;
    }

    private function utfCharToNumber($char)
    {
        $i = 0;
        $number = '';
        while (isset($char{$i})) {
            $number.= ord($char{$i});
            ++$i;
        }
        return $number;
    }

    public function getFullBrandsList($language)
    {
        $rows = $this->getList([
            'language' => $language,
            'columns'  => [
                'img',
                'cars_count' => $this->countExpr(),
                'new_cars_count' => new Zend_Db_Expr('COUNT(IF(cars.add_datetime > DATE_SUB(NOW(), INTERVAL :new_days DAY), 1, NULL))'),
                'carpictures_count', 'enginepictures_count',
                'logopictures_count', 'mixedpictures_count',
                'unsortedpictures_count'
            ]
        ], function($select) use ($language) {
            $select
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                ->group('brands.id')
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

            if (!$isNumber) {
                $isHan = preg_match("/^\p{Han}$/u", $char);
                if ($isHan) {
                    $char = mb_substr($tr->transliterate($char), 0, 1);
                    $isLatin = true;
                }

                if (!$isHan) {
                    $isCyrillic = preg_match("/^\p{Cyrillic}$/u", $char);

                    if (!$isCyrillic) {
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

            if (!isset($result[$line][$char])) {
                $result[$line][$char] = [
                    'id'     => $this->utfCharToNumber($char),
                    'char'   => $char,
                    'brands' => []
                ];
            }

            $picturesCount = $row['carpictures_count'] + $row['enginepictures_count'] +
                $row['logopictures_count'] + $row['mixedpictures_count'] +
                $row['unsortedpictures_count'];

            $result[$line][$char]['brands'][] = [
                'id'             => $row['id'],
                'name'           => $name,
                'catname'        => $row['catname'],
                'img'            => $row['img'],
                'totalPictures'  => $picturesCount,
                'newCars'        => $row['new_cars_count'],
                'totalCars'      => $row['cars_count']
            ];
        }

        foreach ($result as &$line) {
            uksort($line, function($a, $b) use($language) {
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
                ->where('folder = ?', (string)$catname)
        );
    }

    private function fetchBrand($language, $callback)
    {
        $db = $this->table->getAdapter();

        $select = $db->select()
            ->from('brands', [
                'id', 'folder', 'type_id',
                'name' => 'IF(LENGTH(brand_language.name)>0, brand_language.name, brands.caption)',
                'full_caption', 'img', 'text_id'
            ])
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->bind([
                'language' => (string)$language
            ]);

        $callback($select);

        $brand = $db->fetchRow($select);

        if (!$brand) {
            return null;
        }

        return [
            'id'        => $brand['id'],
            'name'      => $brand['name'],
            'catname'   => $brand['folder'],
            'full_name' => $brand['full_caption'],
            'img'       => $brand['img'],
            'text_id'   => $brand['text_id'],
            'type_id'   => $brand['type_id']
        ];
    }

    public function getBrandById($id, $language)
    {
        return $this->fetchBrand($language, function($select) use ($id) {
            $select->where('brands.id = ?', (int)$id);
        });
    }

    public function getBrandByCatname($catname, $language)
    {
        return $this->fetchBrand($language, function($select) use ($catname) {
            $select->where('brands.folder = ?', (string)$catname);
        });
    }

    public function getFactoryBrandId($factoryId)
    {
        $brand = $this->table->fetchRow(
            $this->table->select(true)
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('factory_car', 'car_parent_cache.car_id = factory_car.car_id', null)
                ->where('factory_car.factory_id = ?', (int)$factoryId)
                ->group('brands.id')
                ->order(new Zend_Db_Expr('count(1) desc'))
        );
        if (!$brand) {
            return null;
        }

        return $brand->id;
    }

    public function getBrand($options, callable $callback)
    {
        $result = $this->getList($options, $callback);
        if (count($result) > 0) {
            return $result;
        }

        return null;
    }

    public function getList($options, callable $callback)
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
            'type_id',
            'catname' => 'folder',
            'name'    => 'IF(brand_language.name IS NOT NULL and LENGTH(brand_language.name)>0, brand_language.name, brands.caption)'
        ];
        foreach ($options['columns'] as $column => $expr) {
            switch ($expr) {
                case 'id':
                case 'type_id':
                case 'name':
                    break;
                case 'img':
                    $columns[] = 'img';
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
            ->from('brands', $columns)
            ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
            ->order(['brands.position'])
            ->bind([
                'language' => (string)$options['language']
            ]);

        $callback($select);

        $items = $db->fetchAll($select);

        usort($items, function($a, $b) use($options) {
            return $this->compareName($a['name'], $b['name'], $options['language']);
        });

        return $items;
    }

    public function createIconsSprite($imageStorage, $destImg, $destCss)
    {
        $list = $this->getList([
            'language' => 'en',
            'columns'  => [
                'img'
            ]
        ], function($select) {
            $select->where('img');
        });

        $images = [];

        $format = $imageStorage->getFormat(self::ICON_FORMAT);

        $background = $format->getBackground();

        foreach ($list as $brand) {
            $img = false;
            if ($brand['img']) {
                $imageInfo = $imageStorage->getFormatedImage($brand['img'], self::ICON_FORMAT);
                if ($imageInfo) {
                    $img = $imageInfo->getSrc();
                }
            }

            if ($img) {
                $img = str_replace('http://i.wheelsage.org/', PUBLIC_DIR . '/', $img);
                $images[$brand['catname']] = escapeshellarg($img);
            }
        }

        $count = count($images);
        $width = (int)ceil(sqrt($count));
        $height = ceil($count / $width);

        $cmd = sprintf(
            'montage ' . implode(' ' , $images) . ' -background %s -geometry +0+0 -tile %dx %s',
            escapeshellarg($background ? $background : 'none'),
            $width,
            escapeshellarg($destImg)
        );

        //print $cmd . PHP_EOL;
        exec($cmd);

        $css = [];
        $index = 0;
        foreach ($images as $catname => $img) {
            $top = floor($index / $width);
            $left = $index - $top * $width;
            $css[] = sprintf(
                '.brandicon-%s {background-position: -%dpx -%dpx}',
                $catname,
                $left * $format->getWidth(),
                $top * $format->getHeight()
            );
            $index++;
        }

        file_put_contents($destCss, implode(' ', $css));
    }
}