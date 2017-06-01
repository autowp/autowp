<?php

namespace Application\Hydrator\Api;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;

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
            'thumbnail' => $object['row'] ? $this->extractValue('thumbnail', [
                'image'  => DbTable\Picture\Row::buildFormatRequest($object['row']),
                'format' => $object['format']
            ]) : null
        ];
    }

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $table = new User();
            $db = $table->getAdapter();
            $this->userRole = $db->fetchOne(
                $db->select()
                    ->from($table->info('name'), ['role'])
                    ->where('id = ?', $this->userId)
            );
        }

        return $this->userRole;
    }
}
