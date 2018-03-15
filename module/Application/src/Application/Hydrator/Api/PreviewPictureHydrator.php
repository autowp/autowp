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
        $this->addStrategy('thumb', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('medium', $strategy);
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
        $request = $object['row'] ? Picture::buildFormatRequest($object['row']) : null;

        return [
            'picture'   => $this->extractValue('picture', $object['row']),
            'url'       => $object['url'],
            'thumb'     => $request ? $this->extractValue('thumb', [
                'image'  => $request,
                'format' => 'picture-thumb'
            ]) : null,
            'medium'     => $request ? $this->extractValue('medium', [
                'image'  => $request,
                'format' => 'picture-thumb-medium'
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
