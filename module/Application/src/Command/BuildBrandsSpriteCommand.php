<?php

namespace Application\Command;

use Application\Model\Brand;
use Autowp\Image\Storage;
use Aws\S3\S3Client;
use ImagickException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_rand;
use function is_array;

class BuildBrandsSpriteCommand extends Command
{
    private Brand $brand;

    private array $fileStorageConfig;

    private Storage $imageStorage;

    /** @var string|null The default command name */
    protected static $defaultName = 'build-brands-sprite';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, Brand $brand, array $fileStorageConfig, Storage $imageStorage)
    {
        parent::__construct($name);

        $this->brand             = $brand;
        $this->fileStorageConfig = $fileStorageConfig;
        $this->imageStorage      = $imageStorage;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws ImagickException
     * @throws Storage\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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

        return 0;
    }
}
