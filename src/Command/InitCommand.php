<?php

namespace Drupal\Console\Dotenv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\Component\Utility\Crypt;
use Webmozart\PathUtil\Path;

/**
 * Class ExampleOneCommand
 *
 * @package Drupal\Console\Dotenv\Command
 */
class InitCommand extends Command
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
     * @param string               $appRoot
     * @param string               $consoleRoot
     */
    public function __construct(
        $appRoot,
        $consoleRoot = null
    )
    {
        $this->appRoot = $appRoot;
        $this->consoleRoot = $consoleRoot?$consoleRoot:$appRoot;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('dotenv:init')
            ->setDescription('Dotenv initializer.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        $this->copyFiles($io);
    }

    private function copyFiles($io) {
        $fs = new Filesystem();
        $defaultSettingsFile = $this->appRoot . '/sites/default/default.settings.php';
        $settingsFile = $this->appRoot . '/sites/default/settings.php';

        if (!$fs->exists($defaultSettingsFile)) {
            $defaultSettingsFile = Path::makeRelative(
                $defaultSettingsFile,
                $this->consoleRoot
            );
            $io->error('File: ' . $defaultSettingsFile . 'not found.');

            return 1;
        }

        if ($fs->exists($settingsFile)) {
            $fs->rename(
                $settingsFile,
                $settingsFile.'.original',
                true
            );

            $settingsOriginalFile = Path::makeRelative(
                $settingsFile,
                $this->consoleRoot
            );

            $io->success('File '.$settingsOriginalFile.'.original created.');
        }

        $fs->copy(
            $defaultSettingsFile,
            $settingsFile
        );

        require_once $this->appRoot . '/core/includes/bootstrap.inc';
        require_once $this->appRoot . '/core/includes/install.inc';

        $settings['config_directories'] = [
            CONFIG_SYNC_DIRECTORY => (object) [
                'value' => Path::makeRelative(
                    $this->consoleRoot . '/config/sync',
                    $this->appRoot
                ),
                'required' => TRUE,
            ],
        ];

        $settings['settings']['hash_salt'] = (object) [
            'value'    => Crypt::randomBytesBase64(55),
            'required' => TRUE,
        ];

        drupal_rewrite_settings($settings, $settingsFile);

        $settingsFileContent = file_get_contents($settingsFile);
        file_put_contents(
            $settingsFile,
            $settingsFileContent .
            file_get_contents(
                __DIR__ . '/../../files/settings.dist'
            )
        );

        $fs->chmod($settingsFile, 0666);

        $settingsFile = Path::makeRelative(
            $settingsFile,
            $this->consoleRoot
        );

        $io->success('File '.$settingsFile.' created.');

        $envFile = $this->consoleRoot . '/.env';
        if (!$fs->exists($envFile)) {
            $fs->copy(
                __DIR__ . '/../../files/.env.dist',
                $this->consoleRoot . '/.env',
                true
            );
            $io->success("File .env created.");
        }

        $gitIgnoreFile = $this->consoleRoot . '/.gitignore';
        $gitIgnoreExampleFile = $this->consoleRoot . '/example.gitignore';
        if (!$fs->exists($gitIgnoreFile)) {
            if (!$fs->exists($gitIgnoreExampleFile)) {
                $fs->copy(
                    $gitIgnoreExampleFile,
                    $gitIgnoreFile
                );
            }
        }

        if ($fs->exists($gitIgnoreFile)) {
            $gitIgnoreContent = file_get_contents($gitIgnoreFile);
            if (strpos($gitIgnoreContent, '.env') === false) {
                file_put_contents(
                    $gitIgnoreFile,
                    $gitIgnoreContent .
                    file_get_contents(
                        __DIR__ . '/../../files/.gitignore.dist'
                    )
                );

                $io->success("File .gitignore updated.");
            }
        }
    }
}
