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

    public function updateCounters()
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


        // ВЫЧИЩАЕМ УМЕРШИЕ КАРТИНКИ ДНЯ ИЗ КАРТИНОК ДНЯ
        $sql =  '
            UPDATE of_day
                LEFT JOIN pictures ON of_day.picture_id=pictures.id
            SET of_day.picture_id=NULL WHERE pictures.id IS NULL
        ';
        $db->query($sql);


        /*$brands = new Brands();
        foreach ($brands->fetchAll() as $brand) {
            $brand->refreshActivePicturesCount();
        }

        echo "\r\nUpdated brands active pictures count\r\n";*/
    }

    public function expiredPictureVotes()
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

        $pictures = new Picture();
        $users = new Users();

        $sql = '
            SELECT user_id, COUNT(1) FROM picture_delete_requests
            WHERE day_date<DATE_SUB(NOW(), INTERVAL 180 DAY)
            GROUP BY user_id
        ';
        foreach ($db->fetchAll($sql) as $row) {
            $user = $users->find($row['user_id'])->current();
            $sql = '
                SELECT picture_id, reason FROM picture_delete_requests
                WHERE user_id=? AND day_date<DATE_SUB(NOW(), INTERVAL 180 DAY)
                ORDER BY day_date LIMIT 5
            ';
            foreach ($db->fetchAll($sql, array($user->id)) as $subRow) {
                $picture = $pictures->find($subRow['picture_id'])->current();

                $message = "Уважаемый модератор, срок жизни (180 дней) заявки на удаление картинки истек\n".
                           "По-видимому с вами не согласны остальные модераторы\n".
                           "Заявка будет удалена. Если вы все ещё считаете, что картинку следует удалить - снова подайте заявку\n".
                           $picture->getUrl(true)."\nПричина: ".$subRow['reason'];

                $user->sendPersonalMessage(null, $message);

                $sql = 'DELETE FROM picture_delete_requests WHERE picture_id=? AND user_id=?';
                $db->query($sql, array($picture['id'], $user['id']));
            }

        }
    }

    public function restoreUserVotes()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');


        $userTable = new Users();
        $userTable->restoreVotes();

        echo "User votes restored\n";
    }

    public function refreshUserVoteLimits()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');


        $userTable = new Users();

        $ids = $userTable->getAdapter()->fetchCol(
            $userTable->getAdapter()->select()
                ->from($userTable->info('name'), 'id')
                ->where('not deleted')
                ->where('last_online > DATE_SUB(NOW(), INTERVAL 3 MONTH)')
        );

        foreach ($ids as $id) {
            $user = $userTable->find($id)->current();
            if ($user) {
                $user->updateVotesLimit();
                print $user->votes_per_day . ' ' . $user->getCompoundName() . PHP_EOL;
            }
        }
    }
}

