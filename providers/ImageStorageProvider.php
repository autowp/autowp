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

        $select = $pictureTable->select(true)
            ->join('formated_image', 'pictures.image_id = formated_image.image_id', null)
            ->where('formated_image.format = ?', 'picture-thumb')
            ->join('image', 'formated_image.formated_image_id = image.id', null)
            ->where('image.width > 155')
            ->where('image.date_add < ?', "2014-09-24 00:00:00")
            ->where('pictures.id')
            ->order(array('id asc'));

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(150);

        $pages = $paginator->count();

        for ($i=1; $i<=$pages; $i++) {

            print 'PAGE ' . $i . ' of ' . $pages . PHP_EOL;

            $paginator->setCurrentPageNumber($i);

            foreach ($paginator->getCurrentItems() as $row) {

                print $row->id . PHP_EOL;

                $imageStorage->flush(array(
                    'format' => 'picture-thumb',
                    'image'  => $row->image_id,
                ));

                $imageStorage->flush(array(
                    'format' => 'picture-medium',
                    'image'  => $row->image_id,
                ));

                $imageStorage->getFormatedImage($row->getFormatRequest(), 'picture-thumb');
                $imageStorage->getFormatedImage($row->getFormatRequest(), 'picture-medium');

                usleep(200000);

            }
        }
    }
}