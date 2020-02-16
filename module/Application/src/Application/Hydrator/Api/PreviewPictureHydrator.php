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

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('thumb', $strategy);
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

    public function extract($object, $context = null)
    {
        $result = [];

        if (isset($object['url'])) {
            $result['url'] = $object['url'];
        }

        $result['picture'] = $this->extractValue('picture', $object['row']);

        $largeFormat = is_array($context) && isset($context['large_format']) && $context['large_format'];

        if (isset($object['row']['image_id'])) {
            $result['thumb'] = $this->extractValue('thumb', [
                'image' => $object['row']['image_id'],
                'format' => $largeFormat ? 'picture-thumb-large' : 'picture-thumb-medium'
            ]);
        } else {
            $result['thumb'] = null;
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
