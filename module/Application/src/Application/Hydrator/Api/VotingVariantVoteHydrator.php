<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;
use Zend\Hydrator\Exception\InvalidArgumentException;
use Autowp\User\Model\User;

class VotingVariantVoteHydrator extends RestHydrator
{
    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'user_id' => (int)$object['user_id'],
        ];

        if ($this->filterComposite->filter('user')) {
            $user = $this->userModel->getRow((int)$object['user_id']);

            $result['user'] = $user ? $this->extractValue('user', $user) : null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array $data
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
