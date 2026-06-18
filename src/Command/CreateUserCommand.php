<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\User\CreateService;
use Yiisoft\Rbac\ManagerInterface;

final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly CreateService $userCreateService,
        private readonly UserRepository $userRepository,
        private readonly ManagerInterface $authManager,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:create')
            ->setDescription('Create a new user')
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Role');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $username = $input->getArgument('username');
        $password = $input->getOption('password') ?? bin2hex(random_bytes(8));

        $result = $this->userCreateService->run($email, $username, $password);

        if ($result->isSuccess()) {
            $output->writeln("<info>User created: {$username} ({$email})</info>");
            $output->writeln("<comment>Password: {$password}</comment>");

            $role = $input->getOption('role');
            if ($role !== null) {
                $user = $this->userRepository->findByEmail($email);
                if ($user !== null) {
                    $userId = $user->getId() !== null ? (int) $user->getId() : 0;
                    $this->authManager->assign($role, $userId);
                    $output->writeln("<info>Role assigned: {$role}</info>");
                }
            }

            return Command::SUCCESS;
        }

        $output->writeln("<error>{$result->getMessage()}</error>");
        return Command::FAILURE;
    }
}
