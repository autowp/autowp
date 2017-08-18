<?php

namespace Application\Controller\Console;

use geoPHP;
use Point;

use Zend\Db\Sql;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\ExifGPSExtractor;
use Application\Model\Picture;

class PicturesController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
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
}
