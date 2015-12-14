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
use Symfony\Component\Console\Helper\ProgressBar;
use Nobox\Traits\Files;
use Nobox\Traits\Environment;
use Nobox\Traits\Github;
use Nobox\Traits\ProcessHelper;

class CloneCommand extends Command
{
    private $client;
    protected $projectName;
    protected $linkedGithubAccount;
    protected $githubConfig;
    protected $githubRepository;

    use Files, Environment, Github, ProcessHelper;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        $this->gitHubStatus = false;
        parent::__construct();

    }

    public function configure()
    {
        $this->setName('clone')
             ->setDescription('Clone and Setup Existing Laravel Project that used the Nobox fork into a directory')
             ->addArgument('repository', InputArgument::REQUIRED)
             ->addArgument('name', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $this->projectName = $input->getArgument('name');
        $this->githubRepository = $input->getArgument('repository');
        $directory = getcwd() . '/' . $this->projectName;

        // check if the user has github account linked to file system
        $this->checkGithubAccount('git config --global user.email', $directory, $output);

        if (!$this->gitHubStatus) {
            $emailQuestion = new Question('<question>What is your github email?</question> ');
            $githubEmail = $helper->ask($input, $output, $emailQuestion);

            $this->githubConfig['email'] = $githubEmail;

            $command['github-set-email'] = [
                'title' => 'Setting github email',
                'line' => [
                    'git config --global user.email "' . $this->githubConfig['email'] . '"'
                ]
            ];
        }


        // clone repository

        $command['github-clone'] = [
            'title' => 'Clonning Github Repository into assigned directory',
            'line' => [
                'git clone ' . $this->githubRepository . ' ' . $this->projectName
            ]
        ];


        $this->processIterator($command, $directory, $output);

        // setup project

        $this->install($directory, $output);

        $output->writeln('<info>Application ready!!</info>');
    }


    private function install($directory, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<comment>Beginning dependencies installation...</comment>');

        $composer = $this->findComposer();

        $batch1 = [
            'composer' => [
                'title' => 'Running composer dependencies installation',
                'line' => $composer.' install',
                'progress' => true
            ],
            'npm' => [
                'title' => 'Running npm dependencies installation (this will take some time...)',
                'line' => 'npm install',
                'progress' => false,
            ],
            'bower' => [
                'title' => 'Runnning bower dependencies installation',
                'line' => 'bower install',
                'progress' => true,
            ]
        ];
        // install batch
        $this->processIterator($batch1, $directory, $output);

        $batch2 = [
            'post-installation' => [
                'title' => 'Running post installation commands',
                'line' => [
                    'cp .env.example .env',
                    'php artisan key:generate',
                    'gulp'
                ],
                'progress' => false
            ]
        ];

        //post install batch
        $this->processIterator($batch2, $directory, $output);



        $output->writeln('');
        $output->writeln('<info>Dependencies installation completed.</info>');

        return $this;
    }
}
