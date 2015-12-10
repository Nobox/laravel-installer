<?php
namespace Nobox\Traits;

use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;


trait Files
{
    /**
     * Check if the application folder exists
     * @param  [path]          $directory [description]
     * @param  OutputInterface $output    [description]
     * @return [void]
     */
    private function assertApplicationDoesNotExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            $output->writeln('<error>Application already exists!</error>');

            exit(1);
        }
    }

    private function rename($directory)
    {
        $currentExtracedDir = getcwd() . '/laravel-master';
        rename($currentExtracedDir, $directory);

        return $this;
    }


    private function extract($zipFile, $directory, $output)
    {
        $output->write('<comment>Extracting repository... </comment> ');

        $archive = new ZipArchive;

        $currentDir = getcwd();

        $archive->open($zipFile);
        $archive->extractTo($currentDir);
        $archive->close();
        $output->write('<info>√ done</info>', 1);
        return $this;
    }

    private function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }
}
