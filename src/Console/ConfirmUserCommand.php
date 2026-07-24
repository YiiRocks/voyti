<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Console;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Console command (`voyti:confirm`) that marks a user account as confirmed, looked up via
 * {@see UserLookupTrait}.
 */
final class ConfirmUserCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private ConfirmationService $userConfirmationService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:confirm')
            ->setDescription('Confirm a user');
        $this->configureUserOptions();
    }

    /**
     * @return int
     *
     * @psalm-return 0|1|64|67
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->findUserFromInput($input, $output, 'voyti:confirm');
        if ($user === null) {
            return $this->getLookupFailureExitCode();
        }

        if ($this->userConfirmationService->run($user)) {
            $output->writeln('<info>User confirmed.</info>');
            return ExitCode::OK;
        }

        $output->writeln('<error>Unable to confirm user.</error>');
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
