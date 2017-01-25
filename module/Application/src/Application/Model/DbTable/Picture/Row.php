<?php

namespace Application\Model\DbTable\Picture;

use Autowp\Image\Storage\Request;
use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Model\DbTable;

use Exception;

class Row extends \Autowp\Commons\Db\Table\Row
{
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

    public function cropParametersExists()
    {
        return self::checkCropParameters($this->toArray());
    }

    /**
     * @throws Exception
     * @return string
     */
    public function getFileNamePattern()
    {
        $result = rand(1, 9999);

        $filenameFilter = new FilenameSafe();

        $itemTable = new DbTable\Item();
        $cars = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('picture_item', 'item.id = picture_item.item_id', null)
                ->where('picture_item.picture_id = ?', $this->id)
        );

        if (count($cars) > 1) {
            $brands = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                    ->where('picture_item.picture_id = ?', $this->id)
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
                    ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
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

                $result = $firstChar . '/' . $brandsFolder .'/'.$carFolder.'/'.$carCatname;
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

    /**
     * @deprecated
     * @param string $ext
     * @return string
     */
    public function getFileNameTemplate($ext)
    {
        return $this->getFileNamePattern() . '_%d.' . $ext;
    }

    public function getImageOptions($col)
    {
        $options = [];

        if ($this->cropParametersExists()) {
            $options['crop'] = [
                'left'   => $this->crop_left,
                'top'    => $this->crop_top,
                'width'  => $this->crop_width,
                'height' => $this->crop_height
            ];
        }

        return $options;
    }

    /**
     * @return Request
     */
    public function getFormatRequest()
    {
        return self::buildFormatRequest($this->toArray());
    }

    /**
     * @param array $options
     * @return Request
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

        return new Request($request);
    }

    public function canAccept()
    {
        if (! in_array($this->status, [DbTable\Picture::STATUS_NEW, DbTable\Picture::STATUS_INBOX])) {
            return false;
        }

        $moderVoteTable = new DbTable\Picture\ModerVote();
        $deleteVote = $moderVoteTable->fetchRow([
            'picture_id = ?' => $this->id,
            'vote = 0'
        ]);
        if ($deleteVote) {
            return false;
        }

        return true;
    }

    public function canDelete()
    {
        if (! in_array($this->status, [DbTable\Picture::STATUS_NEW, DbTable\Picture::STATUS_INBOX])) {
            return false;
        }

        $moderVoteTable = new DbTable\Picture\ModerVote();
        $acceptVote = $moderVoteTable->fetchRow([
            'picture_id = ?' => $this->id,
            'vote > 0'
        ]);
        if ($acceptVote) {
            return false;
        }

        return true;
    }
}
