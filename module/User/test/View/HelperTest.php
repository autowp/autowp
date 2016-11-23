<?php

namespace AutowpTest\User\View;

use Zend\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase;

use Autowp\User\View\Helper\User;
use Zend\Authentication\AuthenticationService;
use Autowp\User\Auth\Adapter\Id;

class HelperTest extends AbstractConsoleControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    /**
     * @return User
     */
    private function getHelper()
    {
        return $this->getApplicationServiceLocator()->get('ViewHelperManager')->get(User::class);
    }

    public function testLogedIn()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getHelper()->__invoke()->logedIn());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->logedIn());
    }

    public function testGet()
    {
        $this->getApplication(); // to initialize

        $this->assertNull($this->getHelper()->get());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertNotNull($this->getHelper()->__invoke()->get());
    }

    public function testIsAllowed()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));
    }

    public function testInheritsRole()
    {
        $this->getApplication(); // to initialize

        $this->assertFalse($this->getHelper()->__invoke()->inheritsRole('moder'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->inheritsRole('moder'));
    }

    public function testTimezone()
    {
        $this->getApplication(); // to initialize

        $this->assertEquals('UTC', $this->getHelper()->__invoke()->timezone('moder'));

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('Europe/Moscow', $this->getHelper()->__invoke()->timezone('moder'));
    }

    public function testHumanTime()
    {
        $this->getApplication(); // to initialize

        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt = new \DateTime();
        $dt->setTimestamp($time);

        $this->assertEquals('January 1, 2000', $this->getHelper()->__invoke()->humanTime($dt));

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('January 1, 2000', $this->getHelper()->__invoke()->humanTime($dt));
    }

    public function testHumanDate()
    {
        $this->getApplication(); // to initialize

        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt = new \DateTime();
        $dt->setTimestamp($time);

        $this->assertEquals('January 1, 2000', $this->getHelper()->__invoke()->humanDate($dt));

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('January 1, 2000', $this->getHelper()->__invoke()->humanDate($dt));
    }

    public function testAvatar()
    {
        $this->getApplication(); // to initialize

        $this->assertEquals('', $this->getHelper()->__invoke()->avatar());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertContains('55502f40dc8b7c769880b10874abc9d0', $this->getHelper()->__invoke()->avatar());
    }

    public function testToString()
    {
        $this->getApplication(); // to initialize

        $this->assertEquals('', $this->getHelper()->__invoke()->__toString());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertContains('tester', $this->getHelper()->__invoke()->__toString());
    }
}
