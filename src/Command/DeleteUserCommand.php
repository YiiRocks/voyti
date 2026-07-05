<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;

final class DeleteUserCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:delete')
            ->setDescription('Delete a user');
        $this->configureUserOptions();
    }

    /**
     * @return int
     *
     * @psalm-return 0|1
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->findUserFromInput($input, $output, 'voyti:delete');
        if ($user === null) {
            return Command::FAILURE;
        }

        $this->userRepository->delete($user);
        $output->writeln('<info>User deleted.</info>');
        return Command::SUCCESS;
    }
}
