<?php

namespace AutowpTest\User\Auth;

use Application\Test\AbstractHttpControllerTestCase;

use Autowp\User\Auth\Adapter\Id;
use Autowp\User\Auth\Adapter\Login;
use Autowp\User\Auth\Adapter\Remember;

class AdapterTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

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
