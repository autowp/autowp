<?php

namespace Application\Hydrator\Api\Strategy;

use ArrayAccess;
use Autowp\Image\Storage;
use ImagickException;
use Interop\Container\ContainerInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;

class Image implements StrategyInterface
{
    private ContainerInterface $serviceManager;

    private Storage $imageStorage;

    public function __construct(ContainerInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    private function getImageStorage(): Storage
    {
        if (! $this->imageStorage) {
            $this->imageStorage = $this->serviceManager->get(Storage::class);
        }

        return $this->imageStorage;
    }

    /**
     * @param array|ArrayAccess $value
     * @throws ImagickException
     */
    public function extract($value): ?array
    {
        $photo = null;

        if (isset($value['image'], $value['format'])) {
            try {
                $imageInfo = $this->getImageStorage()->getFormatedImage($value['image'], $value['format']);
            } catch (Storage\Exception $e) {
                $imageInfo = null;
            }
            if ($imageInfo) {
                $photo = $imageInfo->toArray();
            }
        } elseif (isset($value['image'])) {
            try {
                $imageInfo = $this->getImageStorage()->getImage($value['image']);
            } catch (Storage\Exception $e) {
                $imageInfo = null;
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
