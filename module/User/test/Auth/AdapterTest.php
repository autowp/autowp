<?php

namespace AutowpTest\User\Auth;

use Autowp\User\Auth\Adapter\Id;
use Autowp\User\Auth\Adapter\Login;
use Autowp\User\Auth\Adapter\Remember;
use Autowp\User\Model\User;
use Application\Service\UsersService;
use Application\Test\AbstractHttpControllerTestCase;

class AdapterTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testIdAdapter()
    {
        $this->getApplication(); // to initialize

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel = $serviceManager->get(User::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }

    public function testLoginAdapter()
    {
        $this->getApplication(); // to initialize

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel = $serviceManager->get(User::class);
        $userService = $serviceManager->get(UsersService::class);

        $expr = $userService->getPasswordHashExpr('123456');
        $adapter = new Login($userModel, 'test@example.com', $expr);
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }

    public function testRememberAdapter()
    {
        $this->getApplication(); // to initialize

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel = $serviceManager->get(User::class);

        $adapter = new Remember($userModel);
        $adapter->setCredential('admin-token');
        $result = $adapter->authenticate();

        $this->assertTrue($result->isValid());
    }
}
