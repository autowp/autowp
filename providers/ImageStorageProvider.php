<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class ImageStorageProvider extends Zend_Tool_Project_Provider_Abstract
{
    public function migrate()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml
        /* @var $zendApp Zend_Application */
        $zendApp = $this->_loadedProfile->search('BootstrapFile')->getApplicationInstance();

        $zendApp
            ->bootstrap('backCompatibility')
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db')
            ->bootstrap('imageStorage');

        $imageStorage = $zendApp->getBootstrap()->getResource('imageStorage');


        $pictureTable = new Picture();

        do {

            $select = $pictureTable->select(true)
                ->order('rand()');

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage(100);

            $pages = $paginator->count();

            for ($i=1; $i<=$pages; $i++) {

                print 'PAGE ' . $i . ' of ' . $pages . PHP_EOL;

                $paginator->setCurrentPageNumber($i);

                foreach ($paginator->getCurrentItems() as $row) {

                    print $row->id . PHP_EOL;

                    $imageStorage->getFormatedImage($row->getFormatRequest(), 'picture-gallery-full');
                    if ($row->cropParametersExists()) {
                        $imageStorage->getFormatedImage($row->getFormatRequest(), 'picture-gallery');
                    }

                    usleep(250000);

                }
            }

        } while (count($rows) > 0);
    }
}