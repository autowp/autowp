<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;
use Zend\Hydrator\Exception\InvalidArgumentException;
use Zend\Stdlib\ArrayUtils;

class PreviewPictureHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('picture', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    public function setUserId($userId)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
            $this->userRole = null;
        }

        $this->getStrategy('picture')->setUserId($this->userId);

        return $this;
    }

    public function extract($object)
    {
        $result = [];

        if (isset($object['url'])) {
            $result['url'] = $object['url'];
        }

        if ($this->filterComposite->filter('picture')) {
            $result['picture'] = $this->extractValue('picture', $object['row']);
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
