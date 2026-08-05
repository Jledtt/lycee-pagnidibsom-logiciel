import path from 'node:path';
import { existsSync } from 'node:fs';

export const serverPort = process.env.E2E_PORT ?? '8010';
export const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${serverPort}`;
export const databasePath = path.resolve('database/e2e.sqlite');
const windowsSolver = path.resolve('.venv-timetable/Scripts/python.exe');
const solverPython = process.env.TIMETABLE_SOLVER_PYTHON
    ?? (process.platform === 'win32' && existsSync(windowsSolver) ? windowsSolver : 'python3');

export const appEnvironment = {
    ...process.env,
    APP_ENV: 'e2e',
    APP_DEBUG: 'false',
    APP_URL: baseURL,
    APP_KEY: 'base64:dIOXQB4B0pR5bTZedm6+WdGrI8X70smZzjtP8LM1Nlk=',
    BCRYPT_ROUNDS: '4',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    DB_URL: '',
    MAIL_MAILER: 'array',
    LPP_ADMIN_PASSWORD: 'e2e-admin-secret',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    TIMETABLE_SOLVER_PYTHON: solverPython,
};
