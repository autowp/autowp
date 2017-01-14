<?php

namespace Application\Controller\Console;

use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\SessionManager;

use Application\Model\Comments;
use Application\Model\DbTable\Category\ParentTable as CategoryParent;
use Application\Model\DbTable\Vehicle;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Exception;

use Zend_Db_Adapter_Abstract;

class MaintenanceController extends AbstractActionController
{
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    private $db;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    public function __construct(Zend_Db_Adapter_Abstract $db, SessionManager $sessionManager)
    {
        $this->db = $db;
        $this->sessionManager = $sessionManager;
    }

    public function dumpAction()
    {
        $console = Console::getInstance();

        $config = $this->db->getConfig();

        $destFile = __DIR__ . '/../../../../../../data/dump/' . date('Y-m-d_H.i.s') . '.dump.sql';

        $console->write('Dumping ... ');

        $cmd = sprintf(
            'mysqldump -u%s -p%s -h%s --set-gtid-purged=OFF --hex-blob %s -r %s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['dbname']),
            escapeshellarg($destFile)
        );

        exec($cmd);

        if (! file_exists($destFile)) {
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
        $gcMaxLifetime = $this->sessionManager->getConfig()->getOptions('options')['gc_maxlifetime'];
        if (! $gcMaxLifetime) {
            throw new Exception('Option session.gc_maxlifetime not found');
        }

        $this->sessionManager->getSaveHandler()->gc($gcMaxLifetime);

        Console::getInstance()->writeLine("Garabage collected", ColorInterface::GREEN);
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

        $itemTable = new Vehicle();

        $select = $itemTable->select(true)
            ->order('id');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
        $paginator->setItemCountPerPage(100);

        $pagesCount = $paginator->count();
        for ($i = 1; $i <= $pagesCount; $i++) {
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
