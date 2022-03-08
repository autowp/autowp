<?php

namespace Application\Command;

use Application\ExifGPSExtractor;
use Application\Model\Picture;
use Autowp\Image\StorageInterface;
use Exception;
use Laminas\Console\Console;
use Laminas\Db\Sql;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PicturesFillPointCommand extends Command
{
    private Picture $picture;

    private StorageInterface $imageStorage;

    /** @var string|null The default command name */
    protected static $defaultName = 'pictures-fill-point';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, Picture $picture, StorageInterface $storage)
    {
        parent::__construct($name);

        $this->picture      = $picture;
        $this->imageStorage = $storage;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console      = Console::getInstance();
        $imageStorage = $this->imageStorage;

        $rows = $this->picture->getRows([
            'has_point' => false,
            'order'     => 'id',
        ]);

        $extractor = new ExifGPSExtractor();

        foreach ($rows as $row) {
            $console->writeLine($row['id']);
            $exif = $imageStorage->getImageEXIF($row['image_id']);
            $gps  = $extractor->extract($exif);
            if ($gps !== false) {
                $console->writeLine("Picture " . $row['id']);

                $this->picture->getTable()->update([
                    'point' => new Sql\Expression('Point(?, ?)', [$gps['lng'], $gps['lat']]),
                ], [
                    'id' => $row['id'],
                ]);
            }
        }

        $console->writeLine("Done");

        return 0;
    }
}
