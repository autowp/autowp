<?php

namespace AutowpTest\User\View;

use Application\Test\AbstractHttpControllerTestCase;

use Autowp\User\View\Helper\User;
use Zend\Authentication\AuthenticationService;
use Autowp\User\Auth\Adapter\Id;

class HelperTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    /**
     * @return User
     */
    private function getHelper()
    {
        return $this->getApplicationServiceLocator()->get('ViewHelperManager')->get(User::class);
    }

    public function testLogedIn()
    {
        $this->assertFalse($this->getHelper()->__invoke()->logedIn());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->logedIn());
    }

    public function testGet()
    {
        $this->assertNull($this->getHelper()->get());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertNotNull($this->getHelper()->__invoke()->get());
    }

    public function testIsAllowed()
    {
        $this->assertFalse($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->isAllowed('car', 'edit_meta'));
    }

    public function testInheritsRole()
    {
        $this->assertFalse($this->getHelper()->__invoke()->inheritsRole('moder'));

        $adapter = new Id();
        $adapter->setIdentity(3);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertTrue($this->getHelper()->__invoke()->inheritsRole('moder'));
    }

    public function testTimezone()
    {
        $this->assertEquals('UTC', $this->getHelper()->__invoke()->timezone('moder'));

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('Europe/Moscow', $this->getHelper()->__invoke()->timezone('moder'));
    }

    public function testHumanTime()
    {
        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt = new \DateTime();
        $dt->setTimestamp($time);

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('1 января 2000 г.', $this->getHelper()->__invoke()->humanTime($dt));
    }

    public function testHumanDate()
    {
        $time = gmmktime(0, 0, 0, 1, 1, 2000);
        $dt = new \DateTime();
        $dt->setTimestamp($time);

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertEquals('1 января 2000 г.', $this->getHelper()->__invoke()->humanDate($dt));
    }

    public function testAvatar()
    {
        $this->assertEquals('', $this->getHelper()->__invoke()->avatar());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertContains('55502f40dc8b7c769880b10874abc9d0', $this->getHelper()->__invoke()->avatar());
    }

    public function testToString()
    {
        $this->assertEquals('', $this->getHelper()->__invoke()->__toString());

        $adapter = new Id();
        $adapter->setIdentity(1);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        $this->assertContains('tester', $this->getHelper()->__invoke()->__toString());
    }
}
