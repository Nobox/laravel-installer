<?php
namespace Nobox\Traits;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;


trait ProcessHelper
{
    private function processIterator($commands, $directory, $output)
    {

        foreach ($commands as $alias => $command) {
            $this->runProcess($command, $directory, $output, $alias);
        }
    }

    /**
     * Run a terminal command
     * @param  [array]         $command  [description]
     * @param  [path]          $directory [description]
     * @param  OutputInterface $output    [description]
     * @return [void]                     [description]
     */
    private function runProcess($command, $directory, $output, $alias)
    {
        $output->writeln('');

        if(is_array($command['line'])) {
            $commandLine = implode(' && ', $command['line']);
        } else {
            $commandLine = $command['line'];
        }


        $process = new Process($commandLine, $directory);
        $process->setTimeout(7600);
        $process->start();

        if ($output->isVerbose()) {
            $process->wait(function ($type, $buffer) {
                echo $buffer;
            });
        } else {

            $progress = new ProgressBar($output);
            $progress->setFormat("<comment>%message%</comment> [%bar%]");
            $progress->setMessage($command['title']);
            $progress->start();
            $progress->setRedrawFrequency(10000);

            while ($process->isRunning()) {
                 $progress->advance();
            }

            $progress->finish();
            $progress->clear();
        }

        $output->writeln('');
        $output->write('<comment>' . $command['title'] . ' </comment><info>√ done</info>');
    }
}
