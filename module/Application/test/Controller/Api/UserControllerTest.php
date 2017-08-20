<?php

namespace ApplicationTest\Controller\Api;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Autowp\User\Model\User;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\RegistrationController;
use Application\Controller\Api\UserController;

class UserControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testDelete()
    {
        $email = 'test'.microtime(true).'@example.com';
        $password = 'password';

        $this->dispatch('https://www.autowp.ru/registration', Request::METHOD_POST, [
            'email'            => $email,
            'name'             => 'Test user',
            'password'         => $password,
            'password_confirm' => $password
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RegistrationController::class);
        $this->assertMatchedRouteName('registration');
        $this->assertActionName('index');

        // get id
        $userModel = $this->getApplicationServiceLocator()->get(User::class);
        $table = $userModel->getTable();
        $userRow = $table->selectWith(
            $table->getSql()->select()
                ->order('id desc')
                ->limit(1)
        )->current();

        // delete user
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/user/' . $userRow['id'],
            Request::METHOD_PUT,
            [
                'deleted' => 1
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/put');
        $this->assertActionName('put');
    }
}
