import { spawnSync } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';
import { appEnvironment, databasePath } from './environment.mjs';

export default function globalSetup() {
    rmSync(databasePath, { force: true });
    writeFileSync(databasePath, '');

    const migration = spawnSync(
        'php',
        ['artisan', 'migrate:fresh', '--seed', '--force'],
        {
            env: appEnvironment,
            stdio: 'inherit',
        },
    );

    if (migration.status !== 0) {
        throw new Error('La préparation de la base Playwright a échoué.');
    }
}
