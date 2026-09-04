import { expect, test } from '@playwright/test';
import { comptes, seConnecter, seDeconnecter } from './helpers.js';

test('refus d’accès côté navigateur selon les rôles', async ({ page }) => {
  await seConnecter(page, comptes.benevole);
  for (const chemin of ['/synthese', '/administration/calendrier', '/administration/thematiques', '/administration/benevoles', '/presences/ajouter?mode=compa']) {
    const reponse = await page.goto(chemin);
    expect(reponse?.status(), `${chemin} doit être interdit au bénévole`).toBe(403);
  }

  await page.goto('/');
  await seDeconnecter(page);
  await seConnecter(page, comptes.accueil);
  for (const chemin of ['/administration/thematiques', '/administration/benevoles', '/presences/ajouter']) {
    const reponse = await page.goto(chemin);
    expect(reponse?.status(), `${chemin} doit être interdit au salarié accueil`).toBe(403);
  }
  await expect((await page.goto('/synthese'))?.status()).toBe(200);
  await expect((await page.goto('/administration/calendrier'))?.status()).toBe(200);
});

test('déconnexion, identifiants invalides et pages inexistantes', async ({ page }) => {
  await page.goto('/connexion');
  await page.getByLabel('Adresse e-mail').fill(comptes.benevole.email);
  await page.getByLabel('Mot de passe').fill('mot-de-passe-incorrect');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.locator('[role="alert"]')).toBeVisible();

  await page.goto('/page-e2e-inexistante');
  await expect(page).toHaveURL(/\/connexion$/);
  await seConnecter(page, comptes.benevole);
  await page.goto('/autre-page-e2e-inexistante');
  await expect(page).toHaveURL(/\/$/);
  await seDeconnecter(page);
  await page.goto('/');
  await expect(page).toHaveURL(/\/connexion$/);
});

test('une désactivation révoque une session déjà ouverte', async ({ browser, page }) => {
  const contexteSession = await browser.newContext();
  const pageSession = await contexteSession.newPage();
  await seConnecter(pageSession, comptes.session);
  await expect(pageSession).toHaveURL(/\/$/);

  await seConnecter(page, comptes.pilote);
  await page.goto('/administration/benevoles');
  await page.getByRole('button', { name: `Désactiver ${comptes.session.nom}` }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Désactiver le compte' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('désactivé');

  await pageSession.reload();
  await expect(pageSession).toHaveURL(/\/connexion$/);

  await page.getByRole('button', { name: `Réactiver ${comptes.session.nom}` }).click();
  await expect(page.locator('.alerte-succes')).toContainText('réactivé');
  await contexteSession.close();
});
