<?php

namespace App\Console\Commands;

use App\Services\DataIntegrityAuditService;
use Illuminate\Console\Command;

class AuditDataIntegrity extends Command
{
    protected $signature = 'lpp:audit-data-integrity {--json : Produire un rapport JSON exploitable par un script}';

    protected $description = 'Audite les données sensibles avant l’ajout de contraintes d’intégrité';

    public function handle(DataIntegrityAuditService $audit): int
    {
        $report = $audit->run();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $report['safe_for_constraints'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Audit d’intégrité en lecture seule');
        $this->line('Connexion : '.$report['connection']);
        $this->line('Généré le : '.$report['generated_at']);
        $this->newLine();

        $this->table(
            ['Niveau', 'Contrôle', 'Anomalies', 'Échantillon d’identifiants'],
            collect($report['checks'])
                ->filter(fn (array $check): bool => $check['count'] > 0)
                ->map(fn (array $check): array => [
                    $check['severity'] === 'blocking' ? 'BLOQUANT' : 'AVERTISSEMENT',
                    $check['label'],
                    $check['count'],
                    $check['samples'] === [] ? '-' : implode(', ', $check['samples']),
                ]),
        );

        if ($report['blocker_count'] > 0) {
            $this->error($report['blocker_count'].' anomalie(s) bloquante(s). Ne lancez aucune migration de contraintes.');
            $this->warn('Aucune donnée n’a été modifiée. Corrigez et relancez cette commande.');

            return self::FAILURE;
        }

        $this->info('Aucune anomalie bloquante. Les contraintes peuvent être préparées.');

        if ($report['warning_count'] > 0) {
            $this->warn($report['warning_count'].' avertissement(s) à examiner manuellement avant déploiement.');
        }

        $this->line('Aucune donnée n’a été modifiée.');

        return self::SUCCESS;
    }
}
