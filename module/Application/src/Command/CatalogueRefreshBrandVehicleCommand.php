<?php

namespace Application\Command;

use Application\Model\ItemParent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogueRefreshBrandVehicleCommand extends Command
{
    private ItemParent $itemParent;

    /** @var string|null The default command name */
    protected static $defaultName = 'catalogue-refresh-brand-vehicle';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, ItemParent $itemParent)
    {
        parent::__construct($name);

        $this->itemParent = $itemParent;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->itemParent->refreshAllAuto();

        return 0;
    }
}
