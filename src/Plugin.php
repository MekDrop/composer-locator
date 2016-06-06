<?php

namespace mindplay\composer_locator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $dump_package_map = function () use ($composer, $io) {
            $io->write("<info>Dumping package paths</info>");

            $manager = $composer->getInstallationManager();

            $root_package = $composer->getPackage();

            $root_path = getcwd();

            $paths = [];

            if ($root_package->getName() !== '__root__') {
                $paths[$root_package->getName()] = $root_path;
            }
            
            $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();

            foreach ($packages as $package) {
                $name = $package->getName();

                $paths[$name] = $manager->getInstallPath($package);

                if ($name === "mindplay/composer-locator") {
                    $output_path = $manager->getInstallPath($package) . "/src/ComposerLocator.php";
                }
            }

            if (isset($output_path)) {
                $content = strtr(
                    file_get_contents(__DIR__ . '/ComposerLocator.tpl.php'),
                    [
                        '$DATE'  => date('Y-m-d H:i:s'),
                        '$PATHS' => preg_replace('/\s+/', " ", var_export($paths, true)),
                        '$ROOT_PATH' => var_export($root_path, true),
                    ]
                );

                $fs = new Filesystem();

                $fs->dumpFile($output_path, $content);

                $io->write("<info>" . count($packages) . " package paths dumped</info>");
            } else {
                throw new RuntimeException("internal error - failed to enumerate packages");
            }
        };

        $composer->getEventDispatcher()->addListener("post-install-cmd", $dump_package_map);
        $composer->getEventDispatcher()->addListener("post-update-cmd", $dump_package_map);
    }
}
