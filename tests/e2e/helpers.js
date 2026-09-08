import { expect } from '@playwright/test';

export const motDePasse = 'Jambville2026!';

export const comptes = {
  benevole: { email: 'e2e.benevole@jambville.test', nom: 'Élodie Parcours' },
  accueil: { email: 'e2e.accueil@jambville.test', nom: 'Arthur Parcours' },
  pilote: { email: 'e2e.pilote@jambville.test', nom: 'Paul Parcours' },
  session: { email: 'e2e.session@jambville.test', nom: 'Sonia Session' },
};

export async function seConnecter(page, compte, password = motDePasse) {
  await page.goto('/connexion');
  await page.getByLabel('Adresse e-mail').fill(compte.email);
  await page.getByLabel('Mot de passe').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).not.toHaveURL(/\/connexion$/);
}

export async function seDeconnecter(page) {
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await expect(page).toHaveURL(/\/connexion$/);
}

export async function renseignerDate(champ, dateIso) {
  await expect(champ).toHaveAttribute('type', 'text');
  await champ.fill(dateIso.split('-').reverse().join('/'));
  await champ.press('Tab');
}

export async function choisirPeriode(page, debut, fin) {
  await renseignerDate(page.getByLabel('Du', { exact: true }), debut);
  await renseignerDate(page.getByLabel('Au', { exact: true }), fin);
}

export function jourDuCalendrier(page, date) {
  return page.locator(`article.jour-calendrier:has(time[datetime="${date}"])`);
}

export function jourDeSynthese(page, date) {
  return page.locator(`article.jour-synthese:has(time[datetime="${date}"])`);
}
