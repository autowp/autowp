<?php

namespace Application\Hydrator\Api;

use Autowp\User\Model\User;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Traversable;

class VotingVariantVoteHydrator extends RestHydrator
{
    private User $userModel;

    public function __construct(ServiceLocatorInterface $serviceManager) {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'user_id' => (int) $object['user_id'],
        ];

        if ($this->filterComposite->filter('user')) {
            $user = $this->userModel->getRow((int) $object['user_id']);

            $result['user'] = $user ? $this->extractValue('user', $user) : null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
