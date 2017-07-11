<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\VotingController;
use Application\Test\AbstractHttpControllerTestCase;

class VotingControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testVoting()
    {
        $this->dispatch('https://www.autowp.ru/voting/voting/id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('votings/voting');
    }

    public function testVoteAndGetVotes()
    {
        $adapter = $this->getApplication()->getServiceManager()->get(\Zend\Db\Adapter\AdapterInterface::class);

        $table = new TableGateway('voting', $adapter);
        $table->insert([
            'name'         => 'Test vote',
            'multivariant' => 0,
            'begin_date'   => new Sql\Expression('CURDATE()'),
            'end_date'     => "2020-01-01",
            'votes'        => 0,
            'text'         => "Test vote text"
        ]);
        $id = $table->getLastInsertValue();

        $table = new TableGateway('voting_variant', $adapter);
        $table->insert([
            'voting_id'    => $id,
            'name'         => 'First variant',
            'votes'        => 0,
            'position'     => 1,
            'text'         => "First variant text"
        ]);
        $table->insert([
            'voting_id'    => $id,
            'name'         => 'Second variant',
            'votes'        => 0,
            'position'     => 2,
            'text'         => "Second variant text"
        ]);
        $variantId = $table->getLastInsertValue();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/voting/vote/id/' . $id, Request::METHOD_POST, [
            'variant' => $variantId
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('votings/vote');

        // get vote page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/voting/voting/id/' . $id, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('votings/voting');

        // get votes
        $this->reset();
        $this->dispatch('https://www.autowp.ru/voting/voting-variant-votes/id/' . $variantId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(VotingController::class);
        $this->assertMatchedRouteName('votings/voting-variant-votes');
    }
}
