<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\ContactsController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Request;
use Laminas\Json\Json;

class ContactsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testCreateDeleteContact(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/contacts/1', Request::METHOD_PUT);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ContactsController::class);
        $this->assertMatchedRouteName('api/contacts/item/put');
        $this->assertActionName('put');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['status']);

        $this->reset();

        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/contacts/1', Request::METHOD_DELETE);

        $this->assertResponseStatusCode(204);
        $this->assertModuleName('application');
        $this->assertControllerName(ContactsController::class);
        $this->assertMatchedRouteName('api/contacts/item/delete');
        $this->assertActionName('delete');
    }
}
