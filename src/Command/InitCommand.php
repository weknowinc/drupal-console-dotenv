<?php

namespace Drupal\Console\Dotenv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\Component\Utility\Crypt;
use Drupal\Console\Dotenv\Generator\InitGenerator;
use Webmozart\PathUtil\Path;

/**
 * Class InitCommand
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
     * @var InitGenerator
     */
    protected $generator;

    private $envParameters = [
        'environment' => 'local',
        'database_name' => 'drupal',
        'database_user' => 'drupal',
        'database_password' => 'drupal',
        'database_host' => '127.0.0.1',
        'database_port' => '3306',
    ];

    /**
     * InitCommand constructor.
     *
     * @param string        $appRoot
     * @param string        $consoleRoot
     * @param InitGenerator $generator
     */
    public function __construct(
        $appRoot,
        $consoleRoot = null,
        InitGenerator $generator
    ) {
        $this->appRoot = $appRoot;
        $this->consoleRoot = $consoleRoot?$consoleRoot:$appRoot;
        $this->generator = $generator;
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
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        foreach ($this->envParameters as $key => $value) {
            $this->envParameters[$key] = $io->ask(
                'Enter value for ' . strtoupper($key),
                $value
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        $this->copyFiles($io);

        $this->generator->addSkeletonDir(
            __DIR__ . '/../../templates'
        );
        $this->generator->generate(
            $io,
            $this->envParameters,
            $this->consoleRoot
        );
    }

    private function copyFiles(DrupalStyle $io)
    {
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

        include_once $this->appRoot . '/core/includes/bootstrap.inc';
        include_once $this->appRoot . '/core/includes/install.inc';

        $settings['config_directories'] = [
            CONFIG_SYNC_DIRECTORY => (object) [
                'value' => Path::makeRelative(
                    $this->consoleRoot . '/config/sync',
                    $this->appRoot
                ),
                'required' => true,
            ],
        ];

        $settings['settings']['hash_salt'] = (object) [
            'value'    => Crypt::randomBytesBase64(55),
            'required' => true,
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
