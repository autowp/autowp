<?php

namespace Application\Controller\Console;

use Application\DuplicateFinder;
use Application\ExifGPSExtractor;
use Application\Model\Picture;
use Autowp\Image\StorageInterface;
use geoPHP;
use Laminas\Console\Console;
use Laminas\Db\Sql;
use Laminas\Mvc\Controller\AbstractActionController;
use Point;

use const PHP_EOL;

class PicturesController extends AbstractActionController
{
    private Picture $picture;

    private DuplicateFinder $df;

    private StorageInterface $imageStorage;

    public function __construct(Picture $picture, DuplicateFinder $df, StorageInterface $storage)
    {
        $this->picture      = $picture;
        $this->df           = $df;
        $this->imageStorage = $storage;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function fillPointAction()
    {
        $console      = Console::getInstance();
        $imageStorage = $this->imageStorage();

        $rows = $this->picture->getRows([
            'has_point' => false,
            'order'     => 'id',
        ]);

        $extractor = new ExifGPSExtractor();

        geoPHP::version();

        foreach ($rows as $row) {
            $console->writeLine($row['id']);
            $exif = $imageStorage->getImageEXIF($row['image_id']);
            $gps  = $extractor->extract($exif);
            if ($gps !== false) {
                $console->writeLine("Picture " . $row['id']);

                $point = new Point($gps['lng'], $gps['lat']);

                $this->picture->getTable()->update([
                    'point' => new Sql\Expression('ST_GeomFromWKB(?)', [$point->out('wkb')]),
                ], [
                    'id' => $row['id'],
                ]);
            }
        }

        $console->writeLine("Done");
    }

    public function dfIndexAction()
    {
        $table  = $this->picture->getTable();
        $select = $table->getSql()->select()
            ->columns(['id', 'image_id'])
            ->join('df_hash', 'pictures.id = df_hash.picture_id', [], Sql\Select::JOIN_LEFT)
            ->where(['df_hash.picture_id IS NULL']);

        foreach ($table->selectWith($select) as $row) {
            print $row['id'] . ' / ' . $row['image_id'] . PHP_EOL;
            $image = $this->imageStorage->getImage($row['image_id']);
            if ($image) {
                $this->df->indexImage($row['id'], $image->getSrc());
            }
        }
    }
}
