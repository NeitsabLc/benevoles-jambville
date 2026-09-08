import { expect, test } from '@playwright/test';
import { comptes, motDePasse, seConnecter, seDeconnecter } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test('modification du profil et du mot de passe avec reconnexion', async ({ page }) => {
  const nouveauMotDePasse = 'Nouvelle phrase E2E 2026!';
  await seConnecter(page, comptes.benevole);
  await page.goto('/mon-profil');

  await page.getByLabel('Téléphone').fill('06 98 76 54 32');
  await page.getByLabel('Régime végétarien').check();
  await page.getByLabel('Allergie aux œufs').check();
  await page.getByLabel('Autre régime ou allergie alimentaire').fill('Sans lactose E2E');
  await page.getByLabel('Besoin spécifique de couchage').fill('Chambre au rez-de-chaussée E2E');
  await page.getByLabel('Mot de passe actuel').fill(motDePasse);
  await page.getByLabel('Nouveau mot de passe', { exact: true }).fill(nouveauMotDePasse);
  await page.getByLabel('Confirmer le nouveau mot de passe').fill(nouveauMotDePasse);
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();

  await expect(page.locator('.alerte-succes')).toContainText('profil a bien été mis à jour');
  await expect(page.getByLabel('Téléphone')).toHaveValue('06 98 76 54 32');
  await expect(page.getByLabel('Régime végétarien')).toBeChecked();
  await expect(page.getByLabel('Autre régime ou allergie alimentaire')).toHaveValue('Sans lactose E2E');

  await seDeconnecter(page);
  await seConnecter(page, comptes.benevole, nouveauMotDePasse);
  await expect(page.getByRole('heading', { name: /Qui sera à Jambville/ })).toBeVisible();

  // Restaure le mot de passe partagé pour que les scénarios suivants restent indépendants.
  await page.goto('/mon-profil');
  await page.getByLabel('Mot de passe actuel').fill(nouveauMotDePasse);
  await page.getByLabel('Nouveau mot de passe', { exact: true }).fill(motDePasse);
  await page.getByLabel('Confirmer le nouveau mot de passe').fill(motDePasse);
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();
  await expect(page.locator('.alerte-succes')).toBeVisible();
});

test('première connexion puis collecte séparée des informations pratiques', async ({ page }) => {
  const motDePasseActivation = 'Phrase activation E2E 2026!';
  await page.goto('/premiere-connexion/activation-e2e-parcours-premiere-connexion');

  await expect(page.getByRole('heading', { name: /Bienvenue, Alice Activation/ })).toBeVisible();
  await expect(page.getByLabel('Régime végétarien')).toHaveCount(0);
  await page.getByLabel('Créer un mot de passe').fill(motDePasseActivation);
  await page.getByLabel('Confirmer le mot de passe').fill(motDePasseActivation);
  await page.getByRole('button', { name: 'Activer mon espace' }).click();
  await expect(page).toHaveURL(/\/connexion$/);
  await expect(page.locator('.alerte-succes')).toContainText('mot de passe est créé');

  await seConnecter(page, { email: 'e2e.activation@jambville.test' }, motDePasseActivation);
  await expect(page).toHaveURL(/\/bienvenue\/informations-pratiques$/);
  await expect(page.getByRole('heading', { name: 'Préparons votre accueil' })).toBeVisible();
  await page.getByLabel('Régime végétarien').check();
  await page.getByLabel('Allergie aux arachides').check();
  await page.getByLabel('Autre régime ou allergie alimentaire').fill('Régime activation E2E');
  await page.getByLabel('Information particulière de couchage').fill('Besoin activation E2E');
  await page.getByRole('button', { name: 'Enregistrer et continuer' }).click();

  await expect(page).toHaveURL(/\/$/);
  await expect(page.locator('.alerte-succes')).toContainText('informations pratiques');
  await page.goto('/mon-profil');
  await expect(page.getByLabel('Régime végétarien')).toBeChecked();
  await expect(page.getByLabel('Allergie aux arachides')).toBeChecked();
  await expect(page.getByLabel('Autre régime ou allergie alimentaire')).toHaveValue('Régime activation E2E');
});
