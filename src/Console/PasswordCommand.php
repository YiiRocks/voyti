<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;

/**
 * Console command (`voyti:password`) that resets a user's password to a freshly generated one, looked
 * up via {@see UserLookupTrait}.
 */
final class PasswordCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private PasswordGeneratorInterface $passwordGenerator,
        private PasswordHistoryService $passwordHistoryService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:password')
            ->setDescription('Reset a user password');
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
        $user = $this->findUserFromInput($input, $output, 'voyti:password');
        if ($user === null) {
            return Command::FAILURE;
        }

        $password = $this->passwordGenerator->generate(16);
        $this->passwordHistoryService->applyPasswordChange($user, $password);

        $output->writeln('<info>Password reset.</info>');
        $output->writeln("<comment>New password: {$password}</comment>");
        return Command::SUCCESS;
    }
}
