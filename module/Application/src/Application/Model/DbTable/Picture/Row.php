<?php

namespace Application\Model\DbTable\Picture;

use Autowp\Image\Storage\Request;
use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\ModerVote as PictureModerVote;
use Application\Model\DbTable\Vehicle;

use Exception;

class Row extends \Application\Db\Table\Row
{
    private static function between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public static function checkCropParameters($options)
    {
        // Check existance and correct of crop parameters
        return  !is_null($options['crop_left']) && !is_null($options['crop_top']) &&
                !is_null($options['crop_width']) && !is_null($options['crop_height']) &&
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

        switch ($this->type) {
            case Picture::LOGO_TYPE_ID:
                $brand = $this->findParentRow(BrandTable::class);
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname.'/logotypes/'.$catname.'_logo';
                }
                break;

            case Picture::MIXED_TYPE_ID:
                $brand = $this->findParentRow(BrandTable::class);
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname.'/mixed/'.$catname.'_mixed';
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brand = $this->findParentRow(BrandTable::class);
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname . '/unsorted/' . $catname.'_unsorted';
                }
                break;

            case Picture::VEHICLE_TYPE_ID:
                $car = $this->findParentRow(Vehicle::class);
                if ($car) {
                    $carCatname = $filenameFilter->filter($car->caption);

                    $brandTable = new BrandTable();

                    $brands = $brandTable->fetchAll(
                        $brandTable->select(true)
                            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    $sBrands = [];
                    foreach ($brands as $brand) {
                        $sBrands[$brand->id] = $brand;
                    }

                    if (count($sBrands) > 1) {
                        $f = [];
                        foreach ($sBrands as $brand) {
                            $f[] = $filenameFilter->filter($brand->folder);
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

                            $brandFolder = $filenameFilter->filter($brand->folder);
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
                            $carFolder = $filenameFilter->filter($car->caption);
                            $firstChar = mb_substr($carFolder, 0, 1);
                            $result = $firstChar . '/' . $carFolder.'/'.$carCatname;
                        }
                    }
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engine = $this->findParentRow(\Application\Model\DbTable\Engine::class);
                if ($engine) {
                    $result = implode('/', [
                        'engines',
                        $filenameFilter->filter($engine->caption)
                    ]);
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                $factory = $this->findParentRow(\Application\Model\DbTable\Factory::class);
                if ($factory) {
                    $result = implode('/', [
                        'factories',
                        $filenameFilter->filter($factory->name)
                    ]);
                }
                break;

            default:
                throw new Exception("Unknown picture type [{$this->type}]");
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

    protected function _delete()
    {
        $comments = new CommentMessage();
        $comments->delete([
            'type_id = ?' => CommentMessage::PICTURES_TYPE_ID,
            'item_id = ?' => $this->id,
        ]);

        //$this->flushFormatImages();

        //$this->removeSigned();
        //$this->removePicture280();
        //$this->removeThumb();
        //$this->removePod();
        //$this->removeSource();


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
        if (!in_array($this->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX])) {
            return false;
        }

        $moderVoteTable = new PictureModerVote();
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
        if (!in_array($this->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX])) {
            return false;
        }

        $moderVoteTable = new PictureModerVote();
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