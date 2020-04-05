<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Controller\Api\VotingController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\Request;

class VotingControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testVoting()
    {
        $this->dispatch('https://www.autowp.ru/api/voting/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('api/voting/item/get');
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     */
    public function testVoteAndGetVotes()
    {
        $tables = $this->getApplication()->getServiceManager()->get('TableManager');

        /** @var TableGateway $table */
        $table = $tables->get('voting');
        $table->insert([
            'name'         => 'Test vote',
            'multivariant' => 0,
            'begin_date'   => new Sql\Expression('CURDATE()'),
            'end_date'     => "2050-01-01",
            'votes'        => 0,
            'text'         => "Test vote text",
        ]);
        $id = $table->getLastInsertValue();

        $table = $tables->get('voting_variant');
        $table->insert([
            'voting_id' => $id,
            'name'      => 'First variant',
            'votes'     => 0,
            'position'  => 1,
            'text'      => "First variant text",
        ]);
        $table->insert([
            'voting_id' => $id,
            'name'      => 'Second variant',
            'votes'     => 0,
            'position'  => 2,
            'text'      => "Second variant text",
        ]);
        $variantId = $table->getLastInsertValue();

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/voting/' . $id, Request::METHOD_PATCH, [
            'vote' => $variantId,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('api/voting/item/patch');

        // get vote page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/voting/' . $id, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('api/voting/item/get');

        // get votes
        $this->reset();
        $url = 'https://www.autowp.ru/api/voting/' . $id . '/variant/' . $variantId . '/vote';
        $this->dispatch($url, Request::METHOD_GET, [
            'fields' => 'user',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('api/voting/item/variant/item/vote/get');
    }
}
