<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class PreviewPictureHydrator extends AbstractRestHydrator
{
    private int $userId;

    private ?string $userRole;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();
        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('picture', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('thumb', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
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

    public function setUserId(int $userId): self
    {
        if ($this->userId !== $userId) {
            $this->userId   = $userId;
            $this->userRole = null;
        }

        $this->getStrategy('picture')->setUserId($this->userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @param mixed|null        $context
     */
    public function extract($object, $context = null): array
    {
        $result = [];

        if (isset($object['url'])) {
            $result['url'] = $object['url'];
        }

        $result['picture'] = $this->extractValue('picture', $object['row']);

        $largeFormat = is_array($context) && isset($context['large_format']) && $context['large_format'];

        if (isset($object['row']['image_id'])) {
            $result['thumb'] = $this->extractValue('thumb', [
                'image'  => $object['row']['image_id'],
                'format' => $largeFormat ? 'picture-thumb-large' : 'picture-thumb-medium',
            ]);
        } else {
            $result['thumb'] = null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
