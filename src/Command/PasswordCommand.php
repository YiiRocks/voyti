<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Security\PasswordHasher;

final readonly class PasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:password')
            ->setDescription('Reset a user password')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'ID');
    }

    /**
     * @return int
     *
     * @psalm-return 0|1
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
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
                $output->writeln('Usage: voyti:password [options]');
                $output->writeln('');
                $output->writeln('Options:');
                $output->writeln('  --email=<email>        Email');
                $output->writeln('  --username=<username>  Username');
                $output->writeln('  --id=<id>              ID');
            } else {
                $output->writeln('<error>User not found.</error>');
            }
            return Command::FAILURE;
        }

        $password = bin2hex(random_bytes(8));
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $output->writeln('<info>Password reset.</info>');
        $output->writeln("<comment>New password: {$password}</comment>");
        return Command::SUCCESS;
    }
}
