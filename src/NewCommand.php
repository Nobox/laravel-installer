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
use Symfony\Component\Console\Helper\ProgressBar;

class NewCommand extends Command
{
    private $client;
    protected $gitHubStatus;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        $this->gitHubStatus = false;

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

        $directory = getcwd() . '/' . $projectName;

        // assert that the forlder doesn't already exist
        $this->assertApplicationDoesNotExist($directory, $output);

        // Ask the user for github repo (optional)
        $question = new ConfirmationQuestion(
            'Do you want to link an empty github repository to this project? (y/n): ',
            false,
            '/^(y|j)/i'
        );

        $hasGithub = $helper->ask($input, $output, $question);


        if ($hasGithub) {


            $this->checkGithubAccount('git config --global user.email', $directory, $output);


            if (!$this->gitHubStatus) {
                $emailQuestion = new Question('<question>What is your github email?</question> ', $projectName);
                $githubEmail = $helper->ask($input, $output, $emailQuestion);
                $gitConfig['email'] = $githubEmail;
            }


            // Ask the user for the repository url
            $question = new Question('<question>What is the repository url (git@github.com:repo.git) ?</question> ', $projectName);
            $repositoryUrl = $helper->ask($input, $output, $question);

            $gitConfig['url'] = $repositoryUrl;
        }


        $output->writeln('<comment>Crafting application...</comment>');



        $this->download($zipFile = $this->makeFileName(), $output)
             ->extract($zipFile, $directory, $output)
             ->rename($directory)
             ->cleanUp($zipFile)
             ->install($directory, $output);



        if ($hasGithub) {
            // Proceed to github setup
            $this->setupGitProject($directory, $output, $gitConfig);

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
    private function download($zipFile, $output)
    {
        $output->writeln('<comment>Downloading repository...</comment>');

        $response = $this->client->get('https://github.com/Nobox/laravel/archive/master.zip')->getBody();

        file_put_contents($zipFile, $response);

        return $this;
    }

    private function extract($zipFile, $directory, $output)
    {
        $output->writeln('<comment>Extracting repository...</comment>');

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
        $output->writeln('<info>Installing dependencies from composer, npm and bower.</info>');

        $composer = $this->findComposer();

        $commands = [
            $composer.' install',
            'npm install',
            'bower install',
            'cp .env.example .env',
            'php artisan key:generate',
            'gulp'
        ];

        $progress = new ProgressBar($output, 800);
        $progress->setFormat('[%bar%] %percent%%');
        $progress->start();

        $this->runProcess($commands, $directory, $output, $progress);

        $progress->finish();
        $progress->clear();

        $output->writeln('');
        $output->writeln('<info>Dependencies installation completed.</info>');

        return $this;
    }


    private function setupGitProject($directory, $output, $config)
    {
        $output->writeln('<info>Linking github repository to project.</info>');

        if (!$this->gitHubStatus) {
            $commands = [
                'cd ' . $directory,
                'git config --global user.email "' . $config['email'] . '"',
                'git init',
                'git add .',
                'git commit -m "Project Setup"',
                'git remote add origin ' . $config['url'],
                'git push -u origin master'
            ];
        } else {
            $commands = [
                'cd ' . $directory,
                'git init',
                'git add .',
                'git commit -m "Project Setup"',
                'git remote add origin ' . $config['url'],
                'git push -u origin master'
            ];
        }

        $progress = new ProgressBar($output, 4);
        $progress->setFormat('[%bar%] %percent%%');
        $progress->start();

        $this->runProcess($commands, $directory, $output, $progress);

        $progress->finish();
        $progress->clear();
        $output->writeln('');

        $output->writeln('<info>Git setup completed.</info>');
    }


    private function checkGithubAccount($command, $directory, OutputInterface $output)
    {
        $process = new Process($command, $directory);

        $process->run(function ($type, $line) use ($output) {
            if ($line !== '') {
                $this->gitHubStatus = true;
            }
        });
    }


    /**
     * Run a list of terminal commands
     * @param  [array]         $commands  [description]
     * @param  [path]          $directory [description]
     * @param  OutputInterface $output    [description]
     * @return [void]                     [description]
     */
    private function runProcess($commands, $directory, OutputInterface $output, $progress)
    {
        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        $process->run(function ($type, $line) use ($output, $progress) {
            if ($output->isVerbose()) {
                $progress->setFormat('%message% [%bar%] %percent%%');
                $progress->setMessage($line);
            }

            $progress->advance();
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
