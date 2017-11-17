<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

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
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

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
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
