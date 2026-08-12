import { expect, test } from '@playwright/test';

async function seConnecter(page) {
  await page.goto('/connexion');
  await page.getByLabel('Adresse e-mail').fill('pilote@jambville.test');
  await page.getByLabel('Mot de passe').fill('Jambville2026!');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).not.toHaveURL(/\/connexion$/);
}

async function choisirPeriode(page, debut, fin) {
  await page.getByLabel('Du', { exact: true }).fill(debut);
  await page.getByLabel('Au', { exact: true }).fill(fin);
  await page.getByLabel('Au', { exact: true }).press('Tab');
}

test.beforeEach(async ({ page }) => {
  await seConnecter(page);
  await page.goto('/presences/ajouter');
});

test('une thematique evenementielle apparait seulement pour une presence entierement incluse', async ({ page }) => {
  const thematiques = page.getByLabel('Thématique', { exact: true });
  const evenement = thematiques.locator('option', { hasText: 'Événement E2E standard' });

  await expect(evenement).toHaveCount(0);

  await choisirPeriode(page, '2091-07-09', '2091-07-11');
  await expect(evenement).toHaveCount(0);

  await choisirPeriode(page, '2091-07-10', '2091-07-12');
  await expect(evenement).toHaveCount(1);
  await expect(thematiques.locator('option', { hasText: 'Accueil' })).toHaveCount(1);

  await choisirPeriode(page, '2091-07-11', '2091-07-11');
  await expect(evenement).toHaveCount(1);

  await choisirPeriode(page, '2091-07-11', '2091-07-13');
  await expect(evenement).toHaveCount(0);
});

test('une thematique evenementielle exclusive impose sa periode puis masque les autres choix', async ({ page }) => {
  const thematiques = page.getByLabel('Thématique', { exact: true });
  const evenementExclusif = thematiques.locator('option', { hasText: 'Événement E2E exclusif' });
  const thematiquePermanente = thematiques.locator('option', { hasText: 'Accueil' });
  const erreurExclusive = page.locator('[data-erreur-periode-exclusive]');

  await expect(evenementExclusif).toHaveCount(0);
  await expect(thematiquePermanente).toHaveCount(1);

  await choisirPeriode(page, '2091-08-19', '2091-08-21');
  await expect(evenementExclusif).toHaveCount(0);
  await expect(thematiquePermanente).toHaveCount(0);
  await expect(erreurExclusive).toContainText('Choisissez uniquement des dates comprises dans sa période');

  await choisirPeriode(page, '2091-08-20', '2091-08-22');
  await expect(evenementExclusif).toHaveCount(1);
  await expect(thematiquePermanente).toHaveCount(0);
  await expect(thematiques.locator('option')).toHaveCount(2);
  await expect(erreurExclusive).toBeHidden();

  await choisirPeriode(page, '2091-08-23', '2091-08-24');
  await expect(evenementExclusif).toHaveCount(0);
  await expect(thematiquePermanente).toHaveCount(1);
});
