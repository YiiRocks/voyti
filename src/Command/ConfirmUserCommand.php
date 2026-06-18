<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\User\ConfirmationService;

final class ConfirmUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConfirmationService $userConfirmationService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:confirm')
            ->setDescription('Confirm a user')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'ID');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = null;
        if ($id = $input->getOption('id')) {
            $user = $this->userRepository->findById((int)$id);
        } elseif ($email = $input->getOption('email')) {
            $user = $this->userRepository->findByEmail($email);
        } elseif ($username = $input->getOption('username')) {
            $user = $this->userRepository->findByUsername($username);
        }

        if ($user === null) {
            $output->writeln('<error>User not found</error>');
            return Command::FAILURE;
        }

        if ($this->userConfirmationService->run($user)) {
            $output->writeln('<info>User confirmed</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Unable to confirm user</error>');
        return Command::FAILURE;
    }
}
