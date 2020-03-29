<?php

namespace AutowpTest\User\View;

use Application\Test\AbstractHttpControllerTestCase;
use Autowp\User\Auth\Adapter\Id;
use Autowp\User\Model\User as UserModel;
use Autowp\User\View\Helper\User;
use DateTime;
use Laminas\Authentication\AuthenticationService;

use function gmmktime;

class HelperTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    private function getHelper(): User
    {
        return $this->getApplicationServiceLocator()->get('ViewHelperManager')->get(User::class);
    }

    public function testLogedIn()
    {
        $this->assertFalse($this->getHelper()->__invoke()->logedIn());

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->logedIn());
    }

    public function testGet()
    {
        $this->assertNull($this->getHelper()->get());

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertNotNull($this->getHelper()->__invoke()->get());
    }

    public function testIsAllowed()
    {
        $this->assertFalse($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));
    }

    public function testInheritsRole()
    {
        $this->assertFalse($this->getHelper()->__invoke()->inheritsRole('moder'));

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->inheritsRole('moder'));
    }

    public function testTimezone()
    {
        $this->assertEquals('UTC', $this->getHelper()->__invoke()->timezone());

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('Europe/Moscow', $this->getHelper()->__invoke()->timezone());
    }

    public function testHumanTime()
    {
        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt   = new DateTime();
        $dt->setTimestamp($time);

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('1 января 2000 г.', $this->getHelper()->__invoke()->humanTime($dt));
    }

    public function testHumanDate()
    {
        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt   = new DateTime();
        $dt->setTimestamp($time);

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('1 января 2000 г.', $this->getHelper()->__invoke()->humanDate($dt));
    }

    public function testAvatar()
    {
        $this->assertEquals('', $this->getHelper()->__invoke()->avatar());

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertStringContainsString('55502f40dc8b7c769880b10874abc9d0', $this->getHelper()->__invoke()->avatar());
    }

    public function testToString()
    {
        $this->assertEquals('', $this->getHelper()->__invoke()->__toString());

        $serviceManager = $this->getApplicationServiceLocator();
        $userModel      = $serviceManager->get(UserModel::class);

        $adapter = new Id($userModel);
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertStringContainsString('tester', $this->getHelper()->__invoke()->__toString());
    }
}
