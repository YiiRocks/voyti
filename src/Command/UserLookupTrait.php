<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Entity\User;

trait UserLookupTrait
{
    private function configureUserOptions(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'ID');
    }

    private function findUserFromInput(InputInterface $input, OutputInterface $output, string $commandName): ?User
    {
        $id = $input->getOption('id');
        $email = $input->getOption('email');
        $username = $input->getOption('username');

        $user = null;
        if (is_string($id) && $id !== '') {
            $user = $this->userRepository->findById((int) $id);
        } elseif (is_string($email) && $email !== '') {
            $user = $this->userRepository->findByEmail($email);
        } elseif (is_string($username) && $username !== '') {
            $user = $this->userRepository->findByUsername($username);
        }

        if ($user === null) {
            if ($id === null && $email === null && $username === null) {
                $output->writeln('<error>No identifying option provided.</error>');
                $output->writeln('');
                $output->writeln("Usage: {$commandName} [options]");
                $output->writeln('');
                $output->writeln('Options:');
                $output->writeln('  --email=<email>        Email');
                $output->writeln('  --username=<username>  Username');
                $output->writeln('  --id=<id>              ID');
            } else {
                $output->writeln('<error>User not found.</error>');
            }
        }

        return $user;
    }
}
