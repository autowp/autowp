<?php

namespace Application\Hydrator\Api;

use DateTime;
use DateInterval;

use Zend\Permissions\Acl\Acl;

use Autowp\User\Model\DbTable\User;

class UserHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    protected $userId = null;
    
    private $acl;
    
    private $router;
    
    public function __construct($serviceManager)
    {
        parent::__construct();
        
        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
    }
    
    public function extract($object)
    {
        $deleted = (bool)$object['deleted'];

        if ($deleted) {
            $user = [
                'id'       => null,
                'name'     => null,
                'deleted'  => $deleted,
                'url'      => null,
                'longAway' => false,
                'green'    => false
            ];
        } else {

            $longAway = false;
            if ($lastOnline = $object->getDateTime('last_online')) {
                $date = new DateTime();
                $date->sub(new DateInterval('P6M'));
                if ($date > $lastOnline) {
                    $longAway = true;
                }
            } else {
                $longAway = true;
            }
            
            $isGreen = $object->role && $this->acl->isAllowed($object->role, 'status', 'be-green');

            $user = [
                'id'       => (int)$object['id'],
                'name'     => $object['name'],
                'deleted'  => $deleted,
                'url'      => $this->router->assemble([
                    'user_id' => $object->identity ? $object->identity : 'user' . $object->id
                ], [
                    'name' => 'users/user'
                ]),
                'longAway' => $longAway,
                'green'    => $isGreen
            ];
        }
        
        return $user;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
    
    public function setUserId($userId)
    {
        $this->userId = $userId;
    
        return $this;
    }
}