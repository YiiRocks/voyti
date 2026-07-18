<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\User\CreateService;
use Yiisoft\Rbac\ManagerInterface;

/**
 * Console command (`voyti:create`) that creates a new user account from the CLI, auto-generating a
 * password when `--password` is omitted and optionally assigning an RBAC role via `--role`.
 */
final class CreateUserCommand extends Command
{
    public function __construct(
        private CreateService $userCreateService,
        private ManagerInterface $authManager,
        private PasswordGeneratorInterface $passwordGenerator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:create')
            ->setDescription('Create a new user')
            ->addArgument('email', InputArgument::OPTIONAL, 'Email')
            ->addArgument('username', InputArgument::OPTIONAL, 'Username')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Role');
    }

    /**
     * @return int
     *
     * @psalm-return 0|1|2
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $rawEmail */
        $rawEmail = $input->getArgument('email');
        /** @var mixed $rawUsername */
        $rawUsername = $input->getArgument('username');

        if (!is_string($rawEmail) || !is_string($rawUsername) || $rawEmail === '' || $rawUsername === '') {
            $output->writeln('<error>Missing required arguments.</error>');
            $output->writeln('');
            $output->writeln('Usage: voyti:create [options] [--] <email> <username>');
            $output->writeln('');
            $output->writeln('  email      Email');
            $output->writeln('  username   Username');
            $output->writeln('');
            $output->writeln('Options:');
            $output->writeln('  -p, --password   Password (auto-generated if omitted)');
            $output->writeln('  -r, --role       Role to assign');
            return Command::INVALID;
        }

        /** @var mixed $optionPassword */
        $optionPassword = $input->getOption('password');
        $password = is_string($optionPassword) && $optionPassword !== ''
            ? $optionPassword
            : $this->passwordGenerator->generate(16);

        $result = $this->userCreateService->run($rawEmail, $rawUsername, $password);

        if ($result->isSuccess()) {
            $output->writeln("<info>User created: {$rawUsername} ({$rawEmail})</info>");
            $output->writeln("<comment>Password: {$password}</comment>");

            /** @var mixed $optionRole */
            $optionRole = $input->getOption('role');
            if (is_string($optionRole) && $optionRole !== '') {
                $user = User::findByEmail($rawEmail);
                if ($user !== null) {
                    $this->authManager->assign($optionRole, $user->getIdOrZero());
                    $output->writeln("<info>Role assigned: {$optionRole}</info>");
                }
            }

            return Command::SUCCESS;
        }

        $output->writeln("<error>{$result->getMessage()}</error>");
        return Command::FAILURE;
    }
}
