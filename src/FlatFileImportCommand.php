<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlatFileImportCommand extends Command
{
    protected static $defaultName = 'pushword:flat:import';

    protected \Pushword\Flat\FlatFileImporter $importer;

    public function __construct(
        FlatFileImporter $importer
    ) {
        $this->importer = $importer;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Syncing flat file inside database.')
            ->addArgument('host', InputArgument::OPTIONAL, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Import will start in few seconds...');

        $this->importer->run($input->getArgument('host'));

        $output->writeln('Import ended.');

        return 0;
    }
}
