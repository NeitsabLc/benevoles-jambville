import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

function environnementCompose() {
  const environnement = { ...process.env };
  const fichier = readFileSync(new URL('../../.env', import.meta.url), 'utf8');
  for (const ligne of fichier.split(/\r?\n/)) {
    const correspondance = ligne.match(/^([A-Z][A-Z0-9_]*)=(.*)$/);
    if (correspondance && !environnement[correspondance[1]]) {
      environnement[correspondance[1]] = correspondance[2];
    }
  }
  environnement.POSTGRES_MIGRATOR_PASSWORD ||= environnement.POSTGRES_PASSWORD;
  environnement.POSTGRES_HEALTHCHECK_PASSWORD ||= environnement.POSTGRES_PASSWORD;

  return environnement;
}

function reinitialiser() {
  if (process.env.E2E_SKIP_RESET === '1') return;

  const sql = readFileSync(new URL('./reset.sql', import.meta.url));
  execFileSync(
    'docker',
    [
      'compose', 'exec', '--no-TTY', 'database', 'sh', '-c',
      'exec psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --set=ON_ERROR_STOP=1',
    ],
    { env: environnementCompose(), input: sql, stdio: ['pipe', 'inherit', 'inherit'] },
  );
}

export default function globalSetup() {
  reinitialiser();
}
