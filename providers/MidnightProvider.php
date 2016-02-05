<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class MidnightProvider extends Zend_Tool_Project_Provider_Abstract
{

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

        $db = $zendApp->getBootstrap()->getResource('db');


        // ПРОВЕРЯЕМ НАЛИЧИЕ АВТОМОБИЛЯ ДНЯ
        $sql = 'SELECT car_id FROM of_day WHERE day_date=CURDATE()';
        $row = $db->fetchRow($sql);

        if (!$row['car_id']) {
            // ВЫБИРАЕМ АВТОМОБИЛЬ ДНЯ
            // , AVG(p.ratio) AS avg_ratio
            $sql =  '
                SELECT c.id, count(p.id) AS p_count
                FROM cars AS c
                    INNER JOIN car_parent_cache AS cpc ON c.id=cpc.parent_id
                    INNER JOIN pictures AS p ON cpc.car_id=p.car_id
                WHERE p.type=? AND p.status=?
                    AND (c.begin_year AND c.end_year OR c.begin_model_year AND c.end_model_year)
                    AND c.id NOT IN (SELECT car_id FROM of_day WHERE car_id)
                GROUP BY c.id
                HAVING p_count >= 5
                ORDER BY RAND()
                LIMIT 1
            ';
            $row = $db->fetchRow($sql, array(Picture::CAR_TYPE_ID, Picture::STATUS_ACCEPTED));
            if ($row) {
                $cars = new Cars();
                $car = $cars->find($row['id'])->current();

                if ($car) {
                    print $car->id."\n";

                    $sql =  '
                        INSERT INTO of_day (day_date, car_id) VALUES (CURDATE(), ?)
                        ON DUPLICATE KEY UPDATE car_id=VALUES(car_id)
                    ';
                    $db->query($sql, $car->id);
                }
            }
        }
    }
}