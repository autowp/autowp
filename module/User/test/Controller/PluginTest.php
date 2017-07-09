<?php

namespace AutowpTest\User\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Autowp\User\Controller\Plugin\User;
use Zend\Authentication\AuthenticationService;
use Autowp\User\Auth\Adapter\Id;

class PluginTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    /**
     * @return User
     */
    private function getPlugin()
    {
        return $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get(User::class);
    }

    public function testLogedIn()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getPlugin()->__invoke()->logedIn());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getPlugin()->__invoke()->logedIn());
    }

    public function testGet()
    {
        $this->getApplication(); // to initialize

        $this->assertNull($this->getPlugin()->get());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertNotNull($this->getPlugin()->__invoke()->get());
    }

    public function testIsAllowed()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getPlugin()->__invoke()->isAllowed('car', 'edit_meta'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getPlugin()->__invoke()->isAllowed('car', 'edit_meta'));
    }

    public function testInheritsRole()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getPlugin()->__invoke()->inheritsRole('moder'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getPlugin()->__invoke()->inheritsRole('moder'));
    }

    public function testTimezone()
    {
        $this->getApplication(); // to initialize

        $this->assertEquals('UTC', $this->getPlugin()->__invoke()->timezone('moder'));

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('Europe/Moscow', $this->getPlugin()->__invoke()->timezone('moder'));
    }
}
