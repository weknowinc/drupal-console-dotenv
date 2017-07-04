<?php

namespace Drupal\Console\Dotenv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DebugCommand
 *
 * @package Drupal\Console\Dotenv\Command
 */
class DebugCommand extends Command
{
    use CommandTrait;

    /**
     * @var string
     */
    protected $appRoot;

    /**
     * @var string
     */
    protected $consoleRoot;

    /**
     * InitCommand constructor.
     *
     * @param string $appRoot
     * @param string $consoleRoot
     */
    public function __construct(
        $appRoot,
        $consoleRoot = null
    ) {
        $this->appRoot = $appRoot;
        $this->consoleRoot = $consoleRoot?$consoleRoot:$appRoot;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('dotenv:debug')
            ->setDescription('Debug Dotenv debug values.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        $this->debugFile($io);
    }

    private function debugFile(DrupalStyle $io)
    {
        $fs = new Filesystem();
        $envFile = $this->consoleRoot . '/.env';
        if (!$fs->exists($envFile)) {
            $io->warning('File '. $envFile . ' not found.');

            return 1;
        }

        $fileContent = file_get_contents($envFile);
        $io->writeln($fileContent);
    }
}
