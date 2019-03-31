<?php

namespace Application\Hydrator\Api\Strategy;

use Interop\Container\ContainerInterface;
use Zend\Hydrator\Strategy\StrategyInterface;

use Autowp\Image\Storage;

class Image implements StrategyInterface
{
    /**
     * @var ContainerInterface
     */
    private $serviceManager;

    /**
     * @var Storage
     */
    private $imageStorage;

    public function __construct(ContainerInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * @return Storage
     */
    private function getImageStorage()
    {
        if (! $this->imageStorage) {
            $this->imageStorage = $this->serviceManager->get(Storage::class);
        }

        return $this->imageStorage;
    }

    public function extract($value)
    {
        $photo = null;

        if (isset($value['image'], $value['format'])) {
            $imageInfo = null;
            try {
                $imageInfo = $this->getImageStorage()->getFormatedImage($value['image'], $value['format']);
            } catch (Storage\Exception $e) {
            }
            if ($imageInfo) {
                $photo = $imageInfo->toArray();
            }
        } elseif (isset($value['image'])) {
            $imageInfo = null;
            try {
                $imageInfo = $this->getImageStorage()->getImage($value['image']);
            } catch (Storage\Exception $e) {
            }
            if ($imageInfo) {
                $photo = $imageInfo->toArray();
            }
        }

        return $photo;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $value
     * @return null
     */
    public function hydrate($value)
    {
        return null;
    }
}
