<?php

namespace Nobox;

use GuzzleHttp\ClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends Command
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    public function configure()
    {
        $this->setName('new')
             ->setDescription('Create and Setup new Laravel app using Nobox fork.')
             ->addArgument('name', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $projectName = $input->getArgument('name');

        $helper = $this->getHelper('question');
        // assert that the forlder doesn't already exist
        $directory = getcwd() . '/' . $projectName;

        $output->writeln('<comment>Crafting application...</comment>');

        $this->assertApplicationDoesNotExist($directory, $output);

        $this->download($zipFile = $this->makeFileName())
             ->extract($zipFile, $directory)
             ->rename($directory)
             ->cleanUp($zipFile)
             ->install($directory, $output);

        // make github repository config optional
        $question = new ConfirmationQuestion(
            'Do you want to link an empty github repository to this project? (y/n): ',
            false,
            '/^(y|j)/i'
        );

        $response = $helper->ask($input, $output, $question);

        if ($response) {

            // Ask the user for the repository url
            $question = new Question('<question>What is the repository url (git@github.com:repo.git) ?</question> ', $projectName);
            $repositoryUrl = $helper->ask($input, $output, $question);

            $gitConfig = [
                'url' => $repositoryUrl
            ];

            // Proceed to github setup
            $this->setupGitProject($directory, $output, $gitConfig);

            $output->writeln('<info>Github setup completed.</info>');
        }

        $output->writeln('<info>Application ready!!</info>');
    }

    /**
     * Generate temporary zip file
     * @return [file path] the path of the temporary zip file
     */
    private function makeFileName()
    {
        return getcwd() . '/nobox-laravel_' . md5(time().uniqid()) . '.zip';
    }

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

    /**
     * Download repository zip file
     * @param  [path] $zipFile Destination path
     */
    private function download($zipFile)
    {
        $response = $this->client->get('https://github.com/Nobox/laravel/archive/master.zip')->getBody();

        file_put_contents($zipFile, $response);

        return $this;
    }

    private function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $currentDir = getcwd();

        $archive->open($zipFile);
        $archive->extractTo($currentDir);
        $archive->close();

        return $this;
    }

    private function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }

    private function rename($directory)
    {
        $currentExtracedDir = getcwd() . '/laravel-master';
        rename($currentExtracedDir, $directory);

        return $this;
    }


    private function install($directory, OutputInterface $output)
    {
        $output->writeln('<info>Application generated...lets install composer, npm and bower.</info>');

        $composer = $this->findComposer();

        $commands = [
            $composer.' install',
            'npm install',
            'bower install',
            'cp .env.example .env',
            'php artisan key:generate'
            ];

        $this->runProcess($commands, $directory, $output);

        $output->writeln('<info>Building complete...moving forward</info>');

        return $this;
    }


    private function setupGitProject($directory, $output, $config)
    {
        $commands = [
            'cd ' . $directory,
            'git init',
            'git add .',
            'git commit -m "Project Setup"',
            'git remote add origin ' . $config['url'],
            'git push -u origin master'
        ];

        $this->runProcess($commands, $directory, $output);
    }


    /**
     * Run a list of terminal commands
     * @param  [array]         $commands  [description]
     * @param  [path]          $directory [description]
     * @param  OutputInterface $output    [description]
     * @return [void]                     [description]
     */
    private function runProcess($commands, $directory, OutputInterface $output)
    {
        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }
        return 'composer';
    }

}
