<?php

namespace Application\Command;

use Application\DuplicateFinder;
use Application\Model\Picture;
use Autowp\Image\Storage\Exception;
use Autowp\Image\StorageInterface;
use Laminas\Db\Sql;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use const PHP_EOL;

class PicturesDfIndexCommand extends Command
{
    private Picture $picture;

    private DuplicateFinder $df;

    private StorageInterface $imageStorage;

    /** @var string|null The default command name */
    protected static $defaultName = 'pictures-df-index';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, Picture $picture, DuplicateFinder $df, StorageInterface $storage)
    {
        parent::__construct($name);

        $this->picture      = $picture;
        $this->df           = $df;
        $this->imageStorage = $storage;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table  = $this->picture->getTable();
        $select = $table->getSql()->select()
            ->columns(['id', 'image_id'])
            ->join('df_hash', 'pictures.id = df_hash.picture_id', [], Sql\Select::JOIN_LEFT)
            ->where(['df_hash.picture_id IS NULL']);

        foreach ($table->selectWith($select) as $row) {
            print $row['id'] . ' / ' . $row['image_id'] . PHP_EOL;
            $image = $this->imageStorage->getImage($row['image_id']);
            if ($image) {
                $this->df->indexImage($row['id'], $image->getSrc());
            }
        }

        return 0;
    }
}
