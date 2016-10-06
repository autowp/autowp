<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Comments;
use Application\Model\DbTable\Category\ParentTable as CategoryParent;
use Application\Model\DbTable\Vehicle;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Exception;

use Zend_Db_Adapter_Abstract;
use Zend_Session;

class MaintenanceController extends AbstractActionController
{
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    private $db;

    private $sessionConfig;

    public function __construct(Zend_Db_Adapter_Abstract $db, array $sessionConfig)
    {
        $this->db = $db;
        $this->sessionConfig = $sessionConfig;
    }

    public function dumpAction()
    {
        $console = Console::getInstance();

        $config = $this->db->getConfig();

        $destFile = __DIR__ . '/../../../application/data/dump/' . date('Y-m-d_H.i.s') . '.dump.sql';

        $console->write('Dumping ... ');

        $cmd = sprintf(
            'mysqldump -u%s -p%s -h%s --set-gtid-purged=OFF %s > %s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['dbname']),
            escapeshellarg($destFile)
        );

        exec($cmd);

        if (!file_exists($destFile)) {
            throw new Exception('Error creating dump');
        }

        $console->writeLine('ok');

        $console->write('Gzipping ... ');

        $cmd = sprintf(
            'gzip %s',
            escapeshellarg($destFile)
        );

        exec($cmd);

        $console->writeLine('ok');
    }

    public function clearSessionsAction()
    {
        $console = Console::getInstance();

        $maxlifetime = $this->sessionConfig['gc_maxlifetime'];
        if (!$maxlifetime) {
            throw new Exception('Option session.gc_maxlifetime not found');
        }

        Zend_Session::getSaveHandler()->gc($maxlifetime);

        $console->writeLine("Sessions garabage collected");
    }

    public function rebuildCategoryParentAction()
    {
        $cpTable = new CategoryParent();

        $cpTable->rebuild();

        Console::getInstance()->writeLine("Ok");
    }

    public function rebuildCarOrderCacheAction()
    {
        $console = Console::getInstance();

        $carTable = new Vehicle();

        $select = $carTable->select(true)
            ->order('id');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
        $paginator->setItemCountPerPage(100);

        $pagesCount = $paginator->count();
        for ($i=1; $i<=$pagesCount; $i++) {
            $paginator->setCurrentPageNumber($i);
            foreach ($paginator->getCurrentItems() as $carRow) {
                $console->writeLine($carRow->id);
                $carRow->updateOrderCache();
            }
        }

        $console->writeLine("ok");
    }

    public function commentsRepliesCountAction()
    {
        $comments = new Comments();

        $affected = $comments->updateRepliesCount();

        Console::getInstance()->writeLine("ok $affected");
    }

}