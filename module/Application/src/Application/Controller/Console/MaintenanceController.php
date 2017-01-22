<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\SessionManager;

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
        $config = $this->db->getConfig();

        $dir = __DIR__ . '/../../../../../../data/dump';

        if (! is_dir($dir)) {
            if (! mkdir($dir, null, true)) {
                throw new Exception("Error creating dir `$dir`");
            }
        }

        $destFile = realpath($dir) . '/' . date('Y-m-d_H.i.s') . '.dump.sql';

        print 'Dumping ... ';

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

        print "ok\n";

        print 'Gzipping ... ';

        $cmd = sprintf(
            'gzip %s',
            escapeshellarg($destFile)
        );

        exec($cmd);

        return "ok\n";
    }

    public function clearSessionsAction()
    {
        $gcMaxLifetime = $this->sessionManager->getConfig()->getOptions('options')['gc_maxlifetime'];
        if (! $gcMaxLifetime) {
            throw new Exception('Option session.gc_maxlifetime not found');
        }

        $this->sessionManager->getSaveHandler()->gc($gcMaxLifetime);

        return "Garabage collected\n";
    }
}
