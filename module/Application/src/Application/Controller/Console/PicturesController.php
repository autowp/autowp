<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Picture;

use Zend_ProgressBar;
use Zend_ProgressBar_Adapter_Console;

class PicturesController extends AbstractActionController
{
    public function clearQueueAction()
    {
        $console = Console::getInstance();
        $imageStorage = $this->imageStorage();

        $table = new Picture();
        $pictures = $table->fetchAll(
            $table->select(true)
                ->where('status = ?', Picture::STATUS_REMOVING)
                ->where('removing_date is null OR (removing_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) )')
                ->limit(1000)
        );

        $count = count($pictures);

        if ($count) {
            $console->writeLine(sprintf("Removing %d pictures", $count));

            $adapter = new Zend_ProgressBar_Adapter_Console(array(
                'textWidth' => 80,
                'elements'  => array(
                    Zend_ProgressBar_Adapter_Console::ELEMENT_PERCENT,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_BAR,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_ETA,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_TEXT
                )
            ));
            $progressBar = new Zend_ProgressBar($adapter, 0, count($pictures));

            foreach ($pictures as $idx => $picture) {
                $imageId = $picture->image_id;
                if ($imageId) {
                    $picture->delete();
                    $imageStorage->removeImage($imageId);
                } else {
                    $console->writeLine("Brokern image `{$picture->id}`. Skip");
                }

                $progressBar->update($idx + 1, $picture->id);
            }

            $progressBar->finish();
        } else {
            $console->writeLine("Nothing to clear");
        }
    }
}