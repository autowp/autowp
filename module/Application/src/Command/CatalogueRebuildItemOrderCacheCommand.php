<?php

namespace Application\Command;

use Application\Model\Item;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogueRebuildItemOrderCacheCommand extends Command
{
    private Item $itemModel;

    /** @var string|null The default command name */
    protected static $defaultName = 'catalogue-rebuild-item-order-cache';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, Item $itemModel)
    {
        parent::__construct($name);

        $this->itemModel = $itemModel;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paginator = $this->itemModel->getPaginator([
            'columns' => ['id'],
        ]);
        $paginator->setItemCountPerPage(100);

        $pagesCount = $paginator->count();
        for ($i = 1; $i <= $pagesCount; $i++) {
            $paginator->setCurrentPageNumber($i);
            foreach ($paginator->getCurrentItems() as $item) {
                print $item['id'] . "\n";
                $this->itemModel->updateOrderCache($item['id']);
            }
        }

        return 0;
    }
}
