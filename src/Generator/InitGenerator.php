<?php

/**
 * @file
 * Contains Drupal\Console\Dotenv\Generator\InitGenerator.
 */
namespace Drupal\Console\Dotenv\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Drupal\Console\Core\Generator\Generator;
use Drupal\Console\Core\Style\DrupalStyle;

/**
 * Class InitGenerator
 *
 * @package Drupal\Console\Dotenv\Generator
 */
class InitGenerator extends Generator
{
    /**
     * @param DrupalStyle $io
     * @param array       $envParameters
     * @param string      $consoleRoot
     */
    public function generate(
        DrupalStyle $io,
        $envParameters,
        $consoleRoot
    ) {
        $fs = new Filesystem();
        $envFile = $consoleRoot . '/.env';

        if ($fs->exists($envFile)) {
            $fs->rename(
                $envFile,
                $envFile.'.original',
                true
            );

            $io->success('File .env.original created.');
        }

        $this->renderFile(
            '.env.dist.twig',
            $consoleRoot . '/.env',
            $envParameters
        );

        $io->success("File .env created.");
    }
}
