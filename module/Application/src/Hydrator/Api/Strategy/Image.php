<?php

namespace Application\Hydrator\Api\Strategy;

use ArrayAccess;
use Autowp\Image\Storage;
use ImagickException;
use interop\container\containerinterface;
use Laminas\Hydrator\Strategy\StrategyInterface;

class Image implements StrategyInterface
{
    private containerinterface $serviceManager;

    private Storage $imageStorage;

    public function __construct(containerinterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    private function getImageStorage(): Storage
    {
        if (! isset($this->imageStorage)) {
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
     * @param object $value
     * @return mixed|null
     */
    public function hydrate($value)
    {
        return null;
    }
}
