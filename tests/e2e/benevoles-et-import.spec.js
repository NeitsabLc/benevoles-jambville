import { expect, test } from '@playwright/test';
import { comptes, seConnecter } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test('création puis désactivation et réactivation d’un bénévole', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  await page.goto('/administration/benevoles/ajouter');

  await page.getByLabel('Code adhérent').fill('E2E-CREATED-UI');
  await page.getByLabel('Adresse email').fill('e2e.created.ui@jambville.test');
  await page.getByLabel('Prénom').fill('Cécile');
  await page.getByLabel('Nom', { exact: true }).fill('Création E2E');
  await page.getByLabel(/Téléphone/).fill('06 11 22 33 44');
  await page.locator('input[name="role"][value="BENEVOLE"]').check();
  expect(await page.locator(':invalid').evaluateAll((elements) => elements.map((element) => element.getAttribute('name')))).toEqual([]);
  await page.getByRole('button', { name: 'Créer et envoyer l’invitation' }).click();

  await expect(page).toHaveURL(/\/administration\/benevoles$/, { timeout: 15_000 });
  await expect(page.locator('.alerte-succes, .alerte-erreur')).toContainText('compte de Cécile Création E2E a été créé');
  await expect(page.getByRole('link', { name: 'Voir le profil de Cécile Création E2E' })).toBeVisible();

  await page.getByRole('button', { name: 'Désactiver Cécile Création E2E' }).click();
  await expect(page.getByRole('dialog')).toContainText('Cécile Création E2E');
  await page.getByRole('dialog').getByRole('button', { name: 'Désactiver le compte' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('désactivé');
  await expect(page.getByRole('link', { name: 'Voir le profil de Cécile Création E2E' })).toContainText('Inactif');

  await page.getByRole('button', { name: 'Réactiver Cécile Création E2E' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('réactivé');
});

test('prévisualisation, confirmation et application d’un import CSV', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  await page.goto('/administration/benevoles/importer');
  const csv = [
    'code_adherent;nom;prenom;email;telephone;code_fonction;code_structure',
    'E2E-IMPORT-UI;Importé E2E;Zoé;e2e.import.ui@jambville.test;0612345678;;',
  ].join('\n');

  await page.getByLabel('Choisir un fichier CSV').setInputFiles({
    name: 'benevoles-e2e.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from(csv, 'utf8'),
  });
  await expect(page.locator('[data-nom-fichier-selectionne]')).toHaveText('benevoles-e2e.csv');
  await page.getByRole('button', { name: 'Prévisualiser l’import' }).click();

  await expect(page.locator('#previsualisation-import')).toContainText('E2E-IMPORT-UI');
  await expect(page.locator('.statut-creation')).toContainText('Création');
  await expect(page.locator('.note-apercu-import')).toContainText('aucune donnée n’a encore été modifiée');
  await page.getByRole('button', { name: 'Valider et importer' }).click();
  await expect(page.getByRole('dialog')).toContainText('1 compte');
  await page.getByRole('dialog').getByRole('button', { name: 'Confirmer l’import' }).click();

  await expect(page.locator('.carte-resultat-import')).toContainText('Import appliqué');
  await expect(page.locator('.carte-resultat-import')).toContainText('1 création');
  await page.getByRole('link', { name: 'Voir les bénévoles' }).click();
  await expect(page.getByRole('link', { name: 'Voir le profil de Zoé Importé E2E' })).toBeVisible();
});
