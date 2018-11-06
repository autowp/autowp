<?php

namespace Application\Controller\Console;

use geoPHP;
use Point;

use Zend\Db\Sql;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\DuplicateFinder;
use Application\ExifGPSExtractor;
use Application\Model\Picture;

class PicturesController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var DuplicateFinder
     */
    private $df;

    public function __construct(Picture $picture, DuplicateFinder $df)
    {
        $this->picture = $picture;
        $this->df = $df;
    }

    public function fillPointAction()
    {
        $console = Console::getInstance();
        $imageStorage = $this->imageStorage();

        $rows = $this->picture->getRows([
            'has_point' => false,
            'order'     => 'id'
        ]);

        $extractor = new ExifGPSExtractor();

        geoPHP::version();

        foreach ($rows as $row) {
            $console->writeLine($row['id']);
            $exif = $imageStorage->getImageEXIF($row['image_id']);
            $gps = $extractor->extract($exif);
            if ($gps !== false) {
                $console->writeLine("Picture " . $row['id']);

                $point = new Point($gps['lng'], $gps['lat']);

                $this->picture->getTable()->update([
                    'point' => new Sql\Expression('GeomFromWKB(?)', [$point->out('wkb')])
                ], [
                    'id' => $row['id']
                ]);
            }
        }

        $console->writeLine("Done");
    }

    public function dfIndexAction()
    {
        $table = $this->picture->getTable();
        $select = $table->getSql()->select()
            ->columns(['id'])
            ->join('df_hash', 'pictures.id = df_hash.picture_id', [], $select::JOIN_LEFT)
            ->where(['df_hash.picture_id IS NULL']);

        foreach ($table->selectWith($select) as $row) {
            print $row['id'] . PHP_EOL;
            $this->df->indexImage($row['id']);
        }
    }
}
