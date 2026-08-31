<?php

declare(strict_types=1);

namespace foun10\EasyCache\Command;

use foun10\EasyCache\Core\EasyCache;
use OxidEsales\Eshop\Core\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CacheClearCommand
 * @package foun10\EasyCache\Command
 */
class CacheClearCommand extends Command
{
    /**
     * @var EasyCache
     */
    protected $easyCache;

    /**
     * CacheClearCommand constructor.
     * @param EasyCache|null $easyCache
     * @param null $name
     */
    public function __construct(?EasyCache $easyCache = null, $name = null)
    {
        $this->easyCache = $easyCache ?: Registry::get(EasyCache::class);

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('foun10:easycache:clear')
            ->setDescription('Clear the entire EasyCache full-page file cache')
            ->setHelp(<<<'EOF'
                Command <info>%command.name%</info> removes all cached files from
                <comment>source/foun10cache</comment> and truncates the cache stats table.
                EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Clearing EasyCache...');

        $removed = $this->easyCache->clearAll();

        $output->writeln(sprintf('Removed %d cached file(s).', $removed));

        // Symfony's Command::run() enforces an int return: anything else is a TypeError from
        // 5.0 on, which would kill the process *after* the cache was already cleared - a
        // nightly cron would clear the cache and still mail a fatal every single night.
        // Literal 0 rather than Command::SUCCESS, because that constant only exists from
        // Symfony 5.1 and the OXID 6 line ships older console components.
        return 0;
    }
}
