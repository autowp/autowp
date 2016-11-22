<?php

namespace AutowpTest\User\Auth;

use Zend\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase;

use Autowp\User\Auth\Adapter\Id;
use Autowp\User\Auth\Adapter\Login;
use Autowp\User\Auth\Adapter\Remember;

class AdapterTest extends AbstractConsoleControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testIdAdapter()
    {
        $this->getApplication(); // to initialize

        $adapter = new Id();
        $adapter->setIdentity(1);
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }

    public function testLoginAdapter()
    {
        $this->getApplication(); // to initialize

        $config = $this->getApplicationServiceLocator()->get('Config');
        $db = $this->getApplicationServiceLocator()->get(\Zend_Db_Adapter_Abstract::class);

        $expr = 'MD5(CONCAT(' . $db->quote($config['users']['salt']) . ', "123456"))';
        $adapter = new Login('test@example.com', $expr);
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }

    public function testRememberAdapter()
    {
        $this->getApplication(); // to initialize

        $adapter = new Remember();
        $adapter->setCredential('admin-token');
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }
}
