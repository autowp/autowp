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

use function strpos;

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
    public function fillPointAction(): void
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

    public function dfIndexAction(): void
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

    public function fixFilenamesAction(): void
    {
        $table   = $this->picture->getTable();
        $perPage = 100;

        for ($i = 0;; $i++) {
            print "Page $i\n";

            $select = $table->getSql()->select()
                ->columns(['id', 'image_id'])
                ->join('image', 'pictures.image_id = image.id', ['filepath'])
                ->order(['id'])
                ->offset($i * $perPage)
                ->limit($perPage);

            $rows = $table->selectWith($select);

            if ($rows->count() <= 0) {
                break;
            }

            foreach ($rows as $row) {
                $pattern = $this->picture->getFileNamePattern($row['id']);

                $match = strpos($row['filepath'], $pattern) !== false;
                if (! $match) {
                    print "{$row['id']}# {$row['filepath']} not match pattern $pattern\n";
                } else {
                    print "{$row['id']}# {$row['filepath']} is ok\n";
                }

                if (! $match) {
                    $this->imageStorage->changeImageName($row['image_id'], [
                        'pattern' => $pattern,
                    ]);
                }
            }
        }
    }
}
