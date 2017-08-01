<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\ExifGPSExtractor;
use Application\Model\DbTable;

use Zend_Db_Expr;

use geoPHP;
use Point;

class PicturesController extends AbstractActionController
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(DbTable\Picture $pictureTable)
    {
        $this->pictureTable = $pictureTable;
    }

    public function fillPointAction()
    {
        $console = Console::getInstance();
        $imageStorage = $this->imageStorage();

        $rows = $this->pictureTable->fetchAll([
            'point is null'
        ], 'id');

        $extractor = new ExifGPSExtractor();

        geoPHP::version();

        foreach ($rows as $row) {
            $console->writeLine($row['id']);
            $exif = $imageStorage->getImageEXIF($row['image_id']);
            $gps = $extractor->extract($exif);
            if ($gps !== false) {
                $console->writeLine("Picture " . $row['id']);

                $point = new Point($gps['lng'], $gps['lat']);
                $pointExpr = new Zend_Db_Expr($$this->pictureTable->getAdapter()->quoteInto('GeomFromWKB(?)', $point->out('wkb')));

                $row['point'] = $pointExpr;
                $row->save();
            }
        }

        $console->writeLine("Done");
    }
}
