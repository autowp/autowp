<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Stdlib\ArrayUtils;

use Application\Model\Picture;

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
        $this->addStrategy('thumbnail', $strategy);
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
        return [
            'picture'   => $this->extractValue('picture', $object['row']),
            'url'       => $object['url'],
            'large'     => $object['format'] == 'picture-thumb-medium',
            'thumbnail' => $object['row'] ? $this->extractValue('thumbnail', [
                'image'  => Picture::buildFormatRequest($object['row']),
                'format' => $object['format']
            ]) : null
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
