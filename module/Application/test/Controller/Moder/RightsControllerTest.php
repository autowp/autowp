<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\Moder\RightsController;

class RightsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/rights', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights');
        $this->assertActionName('index');
    }

    public function testIndexIsForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/rights', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights');
        $this->assertActionName('forbidden');
    }

    public function testCreateRoleAndPrivileges()
    {
        $role = 'test-role-' . date('Y-m-d-H-i-s');

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/rights/index/form/add-role', Request::METHOD_POST, [
            'role'           => $role,
            'parent_role_id' => 1
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights/params');
        $this->assertActionName('index');

        $adapter = $this->getApplication()->getServiceManager()->get(\Zend\Db\Adapter\AdapterInterface::class);
        $roleTable = new TableGateway('acl_roles', $adapter);
        $select = new Sql\Select($roleTable->getTable());
        $select
            ->order('id desc')
            ->limit(1);
        $roleRow = $roleTable->selectWith($select)->current();

        $this->assertEquals($role, $roleRow['name']);

        // add parent
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/rights/index/form/add-role-parent', Request::METHOD_POST, [
            'role_id'        => $roleRow['id'],
            'parent_role_id' => 49
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights/params');
        $this->assertActionName('index');

        // add-rule allow
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/rights/index/form/add-rule', Request::METHOD_POST, [
            'role_id'      => $roleRow['id'],
            'privilege_id' => 11,
            'what'         => 1
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights/params');
        $this->assertActionName('index');

        // add-rule deny
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/rights/index/form/add-rule', Request::METHOD_POST, [
            'role_id'      => $roleRow['id'],
            'privilege_id' => 11,
            'what'         => 0
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights/params');
        $this->assertActionName('index');
    }
}
