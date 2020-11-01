<?php

namespace Application\Controller\Console;

use Application\Model\Brand;
use Autowp\Image\Storage;
use Aws\S3\S3Client;
use Laminas\Mvc\Controller\AbstractActionController;

use function array_rand;
use function is_array;

class BuildController extends AbstractActionController
{
    private Brand $brand;

    private array $fileStorageConfig;

    private Storage $imageStorage;

    public function __construct(Brand $brand, array $fileStorageConfig, Storage $imageStorage)
    {
        $this->brand             = $brand;
        $this->fileStorageConfig = $fileStorageConfig;
        $this->imageStorage      = $imageStorage;
    }

    public function brandsSpriteAction(): string
    {
        // pick random endpoint
        $s3Config = $this->fileStorageConfig['s3'];
        if (isset($s3Config['endpoint']) && is_array($s3Config['endpoint'])) {
            $s3endpoints          = $s3Config['endpoint'];
            $s3Config['endpoint'] = $s3endpoints[array_rand($s3endpoints)];
        }

        $this->brand->createIconsSprite(
            $this->imageStorage,
            new S3Client($s3Config),
            $this->fileStorageConfig['bucket']
        );

        return "done\n";
    }
}
