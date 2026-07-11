<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Service\User\ApiTokenService;

final class RevokeApiTokenCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private ApiTokenService $apiTokenService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:api-token:revoke')
            ->setDescription('Revoke all API access tokens for a user');
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
        $user = $this->findUserFromInput($input, $output, 'voyti:api-token:revoke');
        if ($user === null) {
            return Command::FAILURE;
        }

        $count = $this->apiTokenService->revokeAll($user);

        $output->writeln("<info>Revoked {$count} API token(s).</info>");
        return Command::SUCCESS;
    }
}
