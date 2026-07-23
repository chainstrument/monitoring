<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Doctrine\Entity\ProbeResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:probe-result:purge',
    description: 'Supprime les résultats de sonde plus anciens que la durée de rétention configurée',
)]
final class PurgeProbeResultsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(int:PROBE_RESULT_RETENTION_DAYS)%')]
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = new \DateTimeImmutable(\sprintf('-%d days', $this->retentionDays));

        $affectedRows = $this->entityManager->createQuery(
            \sprintf('DELETE FROM %s r WHERE r.checkedAt < :threshold', ProbeResult::class),
        )->setParameter('threshold', $threshold)->execute();
        $deleted = \is_int($affectedRows) ? $affectedRows : 0;

        new SymfonyStyle($input, $output)->success(
            \sprintf('%d résultat(s) de sonde supprimé(s) (plus vieux que %d jours).', $deleted, $this->retentionDays),
        );

        return Command::SUCCESS;
    }
}
