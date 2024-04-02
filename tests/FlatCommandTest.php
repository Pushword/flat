<?php

namespace Pushword\Flat\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FlatCommandTest extends KernelTestCase
{
    public function testImport(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:flat:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertTrue(str_contains($output, 'ended'));

        $exportDir = $kernel->getCacheDir().'/test-exporter';

        $command = $application->find('pushword:flat:export');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            'exportDir' => $exportDir,
        ]);

        self::assertFileExists($exportDir.'/homepage.md');
        self::assertFileExists($exportDir.'/installation.md');
    }
}
