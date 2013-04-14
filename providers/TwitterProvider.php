<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class TwitterProvider extends Zend_Tool_Project_Provider_Abstract
{
    protected function _pictureByPerspective($pictureTable, $car, $perspective)
    {
        $select = $pictureTable->select(true)
            ->where('pictures.type = ?', Pictures::CAR_TYPE_ID)
            ->where('pictures.car_id = ?', $car->id)
            ->where('pictures.status IN (?)', array(Pictures::STATUS_ACCEPTED, Pictures::STATUS_NEW))
            ->order(array(
                'pictures.ratio DESC', 'pictures.votes DESC',
                'pictures.width DESC', 'pictures.height DESC',
                'pictures.comments DESC', 'pictures.views DESC'
            ))
            ->limit(1);
        if ($perspective) {
            $select->where('pictures.perspective_id = ?', $perspective);
        }
        return $pictureTable->fetchRow($select);
    }

    public function carOfDay()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $dayTable = new Of_Day();

        $dayRow = $dayTable->fetchRow(array(
            'day_date = CURDATE()',
            'not twitter_sent'
        ));

        if (!$dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $car = $dayRow->findParentCars();

        if (!$car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        $pictureTable = new Pictures();

        /* Hardcoded perspective priority list */
        $perspectives = array(10, 1, 7, 8, 11, 3, 7, 12, 4, 8);

        foreach ($perspectives as $perspective) {
            $picture = $this->_pictureByPerspective($pictureTable, $car, $perspective);
            if ($picture) {
                break;
            }
        }

        if (!$picture) {
            $picture = $this->_pictureByPerspective($pictureTable, $car, false);
        }

        if (!$picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'http://www.autowp.ru/picture/' . ($picture->identity ? $picture->identity : $picture->id);

        $text = 'Автомобиль дня: ' . $car->getFullName() . ' ' . $url;

        $options = $zendApp->getOptions();
        $twOptions = $options['twitter'];

        $token = new Zend_Oauth_Token_Access();
        $token->setParams($twOptions['token']);

        $twitter = new Zend_Service_Twitter(array(
            'username'     => $twOptions['username'],
            'accessToken'  => $token,
            'oauthOptions' => $twOptions['oauthOptions']
        ));

        $response = $twitter->statusesUpdate($text);

        if ($response->isSuccess()) {
            $dayRow->twitter_sent = true;
            $dayRow->save();

            print 'ok' . PHP_EOL;
        } else {
            print_r($response->getErrors());
        }
    }
}