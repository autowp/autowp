<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\RestHydrator;

class TrafficController extends AbstractRestfulController
{
    /**
     * @var TrafficControl
     */
    private $service;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;
    
    public function __construct(TrafficControl $service, RestHydrator $hydrator)
    {
        $this->service = $service;
        $this->hydrator = $hydrator;
    }
    
    public function listAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getTopData();
        
        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
            //'user_id'  => $user ? $user['id'] : null
        ]);
        
        $result = [];
        foreach ($data as $row) {
            $result[] = $this->hydrator->extract($row);
        }
        
        return new JsonModel([
            'items' => $result
        ]);
    }
    
    public function whitelistListAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $data = $this->service->getWhitelistData();
        
        $users = new User();
        
        foreach ($data as &$row) {
            //$row['users'] = []; 
            /*$users->fetchAll([
            'last_ip = INET_ATON(?)' => $row['ip']
            ]);*/
        }
        unset($row);
        
        return new JsonModel([
            'items' => $data
        ]);
    }
    
    public function whitelistCreateAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $data = $this->processBodyContent($this->getRequest());
        
        $ip = trim($data['ip']);
        
        if (! $ip) {
            return $this->getResponse()->setStatusCode(400);
        }
        
        $this->service->addToWhitelist($ip, 'manual click');
        
        /*$this->getResponse()->getHeaders()->addHeaderLine(
            'Location',
            $this->url()->fromRoute('api/traffic/whitelist/item/get', [
                'id' => $ip
            ])
        );*/
        return $this->getResponse()->setStatusCode(201);
    }
    
    public function whitelistItemDeleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $this->service->deleteFromWhitelist($this->params('ip'));
        
        return $this->getResponse()->setStatusCode(204);
    }
    
    public function blacklistCreateAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }
        
        $data = $this->processBodyContent($this->getRequest());
        
        $ip = $data['ip'];
        
        if ($ip === null) {
            return $this->notFoundAction();
        }
        
        $this->service->ban(
            $ip,
            $data['period'] * 3600,
            $this->user()->get()->id,
            $data['reason']
        );
        
        return $this->getResponse()->setStatusCode(201);
    }
    
    public function blacklistItemDeleteAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }
        
        $ip = $this->params('ip');
        
        if ($ip === null) {
            return $this->notFoundAction();
        }
        
        $this->service->unban($ip);
        
        return $this->getResponse()->setStatusCode(204);
    }
}
