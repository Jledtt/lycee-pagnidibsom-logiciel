import path from 'node:path';

export const serverPort = process.env.E2E_PORT ?? '8010';
export const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${serverPort}`;
export const databasePath = path.resolve('database/e2e.sqlite');

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
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
};
