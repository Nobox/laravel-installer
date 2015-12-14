<?php
namespace Nobox\Traits;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;


trait Github
{
    private function askForGithub(InputInterface $input, OutputInterface $output, $directory)
    {
        $helper = $this->getHelper('question');
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
                $emailQuestion = new Question('<question>What is your github email?</question> ');
                $githubEmail = $helper->ask($input, $output, $emailQuestion);
                $gitConfig['email'] = $githubEmail;
            }


            // Ask the user for the repository url
            $question = new Question('<question>What is the repository url (git@github.com:repo.git) ?</question> ');
            $repositoryUrl = $helper->ask($input, $output, $question);

            $gitConfig['url'] = $repositoryUrl;

            return $gitConfig;

        } else {
            return false;
        }
    }


    private function setupGitProject($directory, $output, $config)
    {
        $output->writeln('<info>Linking github repository to project.</info>');

        if (!$this->gitHubStatus) {
            $command =  [
                'github-setup' => [
                    'title' => 'Running post installation commands',
                    'line' => [
                        'cd ' . $directory,
                        'git config --global user.email "' . $config['email'] . '"',
                        'git init',
                        'git add .',
                        'git commit -m "Project Setup"',
                        'git remote add origin ' . $config['url'],
                        'git push -u origin master'
                    ]
                ]
            ];
        } else {
            $command =  [
                'github-setup' => [
                    'title' => 'Running post installation commands',
                    'line' => [
                        'cd ' . $directory,
                        'git init',
                        'git add .',
                        'git commit -m "Project Setup"',
                        'git remote add origin ' . $config['url'],
                        'git push -u origin master'
                    ]
                ]
            ];
        }


        $this->processIterator($command, $directory, $output);


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

}
