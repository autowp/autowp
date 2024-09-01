<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\PictureController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Json\Json;

class PictureControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testGetList(): void
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader(
            $this->getApplicationServiceLocator()->get('Config')['keycloak']
        ));
        $this->dispatch('http://www.autowp.ru/api/picture', Request::METHOD_GET, [
            'fields' => 'owner,thumb_medium,add_date,exif,image,items.item.name_html,'
                        . 'items.item.brands.name_html,special_name,copyrights,'
                        . 'change_status_user,rights,moder_votes,moder_voted,'
                        . 'is_last,views,accepted_count,similar.picture.thumb_medium,'
                        . 'replaceable,siblings.name_text,ip.rights,ip.blacklist',
            'limit'  => 100,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/index');

        $this->assertResponseHeaderContains('Content-Type', 'application/json; charset=utf-8');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertNotEmpty($json);
    }
}
