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
    protected $projectName;
    protected $linkedGithubAccount;
    protected $githubConfig;

    use Traits\Files, Traits\Environment, Traits\Github, Traits\ProcessHelper;

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
        $this->projectName = $input->getArgument('name');

        $directory = getcwd() . '/' . $this->projectName;

        // assert that the forlder doesn't already exist
        $this->assertApplicationDoesNotExist($directory, $output);

        $this->githubConfig = $this->askForGithub($input, $output, $directory);


        $output->writeln('<comment>Crafting application...</comment>');



        $this->download($zipFile = $this->makeFileName(), $output)
             ->extract($zipFile, $directory, $output)
             ->rename($directory)
             ->cleanUp($zipFile)
             ->install($directory, $output);



        if (is_array($this->githubConfig)) {
            // Proceed to github setup
            $this->setupGitProject($directory, $output, $this->githubConfig);

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
     * Download repository zip file
     * @param  [path] $zipFile Destination path
     */
    private function download($zipFile, $output)
    {
        $output->write('<comment>Downloading repository...</comment> ');

        $response = $this->client->get('https://github.com/Nobox/laravel/archive/master.zip')->getBody();

        file_put_contents($zipFile, $response);
        $output->write('<info>√ done</info>', 1);

        return $this;
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
