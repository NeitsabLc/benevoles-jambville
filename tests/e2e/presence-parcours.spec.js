import { expect, test } from '@playwright/test';
import { choisirPeriode, comptes, jourDeSynthese, jourDuCalendrier, seConnecter } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test('création, modification et suppression d’une présence individuelle', async ({ page }) => {
  await seConnecter(page, comptes.benevole);
  await page.goto('/presences/ajouter');

  await choisirPeriode(page, '2094-01-10', '2094-01-11');
  await page.getByLabel('Thématique', { exact: true }).selectOption({ label: 'Accueil' });
  await page.getByLabel('Couchage').selectOption('DUR');
  await page.getByLabel('Nombre d’enfants').fill('2');
  await page.getByLabel(/Commentaire/).fill('Présence E2E initiale');
  await expect(page.locator('[data-repas-lignes] tr')).toHaveCount(2);
  await page.getByRole('button', { name: 'Ajouter la présence' }).click();

  await expect(page).toHaveURL(/\/?mois=2094-01$/);
  await expect(page.locator('.alerte-succes')).toContainText('présence a bien été ajoutée');
  await expect(jourDuCalendrier(page, '2094-01-10')).toContainText('Élodie P.');

  await jourDuCalendrier(page, '2094-01-10').locator('a[aria-label="Modifier Élodie Parcours"]').click();
  await expect(page.getByRole('heading', { name: 'Modifier une présence' })).toBeVisible();
  await page.getByLabel('Au', { exact: true }).fill('2094-01-12');
  await page.getByLabel('Au', { exact: true }).dispatchEvent('change');
  await page.getByLabel('Couchage').selectOption('TENTE');
  await page.getByLabel('Nombre d’enfants').fill('1');
  await page.getByLabel(/Commentaire/).fill('Présence E2E modifiée');
  await expect(page.locator('[data-repas-lignes] tr')).toHaveCount(3);
  await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();

  await expect(page.locator('.alerte-succes')).toContainText('présence a bien été modifiée');
  await expect(jourDuCalendrier(page, '2094-01-12')).toContainText('Élodie P.');

  await jourDuCalendrier(page, '2094-01-12').getByRole('button', { name: /Supprimer Élodie Parcours/ }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.getByRole('dialog').getByRole('button', { name: 'Supprimer la présence' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('présence a bien été supprimée');
  await expect(jourDuCalendrier(page, '2094-01-12')).not.toContainText('Élodie P.');
});

test('parcours compagnon visible dans le calendrier et la synthèse', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  await page.goto('/presences/ajouter');
  await page.getByRole('button', { name: /J’inscris une équipe compa/ }).click();

  await page.getByLabel('Nom de l’équipe compa').fill('Compas Parcours E2E');
  await page.getByLabel('Nombre de personnes').fill('4');
  await choisirPeriode(page, '2094-02-10', '2094-02-11');
  await page.getByLabel('Couchage').selectOption('TENTE');
  await page.getByLabel('Végétariens').fill('2');
  await page.getByLabel('Allergie aux œufs').fill('1');
  await page.getByLabel('Allergie aux arachides').fill('1');
  await page.getByLabel(/Commentaire/).fill('Équipe compa contrôlée en E2E');
  await page.getByRole('button', { name: 'Ajouter la présence' }).click();

  await expect(page).toHaveURL(/\/?mois=2094-02$/);
  await expect(jourDuCalendrier(page, '2094-02-10')).toContainText('Compas Parcours E2E');

  await page.goto('/synthese?debut=2094-02-10&fin=2094-02-11');
  const jour = jourDeSynthese(page, '2094-02-10');
  await expect(jour).toContainText('Compas Parcours E2E · 4');
  await expect(jour.locator('.repas-synthese strong')).toHaveText(['4', '4', '4']);
  await expect(jour.locator('.details-synthese h2', { hasText: 'En tente' })).toContainText('4');
  await expect(jour.locator('.regimes-synthese')).toContainText(/Végétariens\s*2/);
  await expect(jour.locator('.regimes-synthese')).toContainText(/Allergie œuf\s*1/);

  await page.goto('/?mois=2094-02');
  await jourDuCalendrier(page, '2094-02-10').getByRole('button', { name: /Supprimer Compas Parcours E2E/ }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Supprimer la présence' }).click();
  await expect(jourDuCalendrier(page, '2094-02-10')).not.toContainText('Compas Parcours E2E');
});
