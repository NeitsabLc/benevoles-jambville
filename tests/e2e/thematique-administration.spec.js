import { expect, test } from '@playwright/test';
import { choisirPeriode, comptes, seConnecter } from './helpers.js';

test('création, transformation en événement exclusif et activation d’une thématique', async ({ page }) => {
  const nomInitial = 'Parcours E2E thématique permanente';
  const nomEvenement = 'Parcours E2E événement exclusif';
  await seConnecter(page, comptes.pilote);
  await page.goto('/administration/thematiques/ajouter');

  await page.getByLabel('Nom').fill(nomInitial);
  await page.getByLabel('Ordre d’affichage').fill('1');
  await page.getByLabel('Du', { exact: true }).fill('2094-05-10');
  await page.getByRole('button', { name: 'Ajouter la thématique' }).click();
  await expect(page.locator('.alerte-erreur')).toContainText('Renseignez les deux dates');

  await page.getByLabel('Au', { exact: true }).fill('2094-05-12');
  await page.getByRole('button', { name: 'Ajouter la thématique' }).click();
  await expect(page).toHaveURL(/\/administration\/thematiques$/);
  await expect(page.locator('.alerte-succes')).toContainText('thématique a bien été ajoutée');
  let carte = page.locator('[data-carte-thematique]', { hasText: nomInitial });
  await expect(carte).toContainText('Événement');
  await expect(carte).not.toContainText('Exclusive');

  await carte.getByRole('link', { name: `Modifier ${nomInitial}` }).click();
  await page.getByLabel('Nom').fill(nomEvenement);
  await page.getByLabel('Thématique exclusive sur cette période').check();
  await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
  carte = page.locator('[data-carte-thematique]', { hasText: nomEvenement });
  await expect(carte).toContainText('Événement');
  await expect(carte).toContainText('Exclusive');

  await page.goto('/presences/ajouter');
  await choisirPeriode(page, '2094-05-09', '2094-05-11');
  await expect(page.locator('[data-erreur-periode-exclusive]')).toContainText('Inscription impossible');
  await expect(page.getByLabel('Thématique').locator('option', { hasText: nomEvenement })).toHaveCount(0);

  await choisirPeriode(page, '2094-05-10', '2094-05-12');
  await expect(page.getByLabel('Thématique').locator('option', { hasText: nomEvenement })).toHaveCount(1);
  await expect(page.getByLabel('Thématique').locator('option')).toHaveCount(2);

  await page.goto('/administration/thematiques');
  await page.getByRole('button', { name: `Désactiver ${nomEvenement}` }).click();
  await expect(page.locator('.alerte-succes')).toContainText('désactivée');
  await expect(page.locator('[data-carte-thematique]', { hasText: nomEvenement })).toContainText('Inactive');
  await page.goto('/presences/ajouter');
  await choisirPeriode(page, '2094-05-10', '2094-05-12');
  await expect(page.getByLabel('Thématique').locator('option', { hasText: nomEvenement })).toHaveCount(0);

  await page.goto('/administration/thematiques');
  await page.getByRole('button', { name: `Réactiver ${nomEvenement}` }).click();
  await expect(page.locator('.alerte-succes')).toContainText('réactivée');
});
