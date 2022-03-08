<?php

namespace Application\Command;

use Application\Model\Picture;
use Autowp\Image\StorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function strpos;

class PicturesFixFilenamesCommand extends Command
{
    private Picture $picture;

    private StorageInterface $imageStorage;

    /** @var string|null The default command name */
    protected static $defaultName = 'pictures-fix-filenames';

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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table   = $this->picture->getTable();
        $perPage = 100;

        for ($i = 0;; $i++) {
            print "Page $i\n";

            $select = $table->getSql()->select()
                ->columns(['id', 'image_id'])
                ->join('image', 'pictures.image_id = image.id', ['filepath'])
                ->order(['id'])
                ->offset($i * $perPage)
                ->limit($perPage);

            $rows = $table->selectWith($select);

            if ($rows->count() <= 0) {
                break;
            }

            foreach ($rows as $row) {
                $pattern = $this->picture->getFileNamePattern($row['id']);

                $match = strpos($row['filepath'], $pattern) !== false;
                if (! $match) {
                    print "{$row['id']}# {$row['filepath']} not match pattern $pattern\n";
                } else {
                    print "{$row['id']}# {$row['filepath']} is ok\n";
                }

                if (! $match) {
                    $this->imageStorage->changeImageName($row['image_id'], [
                        'pattern' => $pattern,
                    ]);
                }
            }
        }

        return 0;
    }
}
