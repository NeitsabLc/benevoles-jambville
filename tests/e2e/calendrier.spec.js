import { expect, test } from '@playwright/test';
import { comptes, jourDuCalendrier, seConnecter } from './helpers.js';

test('modification des permanences et commentaires puis contrôle du calendrier', async ({ page }) => {
  await seConnecter(page, comptes.accueil);
  await page.goto('/administration/calendrier');

  const formulairePermanence = page.locator('form', { has: page.getByRole('button', { name: 'Appliquer la permanence' }) });
  await formulairePermanence.getByLabel('Personne').selectOption({ label: 'Alex de permanence' });
  await formulairePermanence.getByLabel('Du').fill('2094-04-10');
  await formulairePermanence.getByLabel('Au').fill('2094-04-11');
  await formulairePermanence.getByRole('button', { name: 'Appliquer la permanence' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('2 jours ont été mis à jour');

  const formulaireCommentaire = page.locator('form', { has: page.getByRole('button', { name: 'Appliquer le commentaire' }) });
  await formulaireCommentaire.getByLabel(/Commentaire/).fill('Accueil E2E déplacé au château');
  await formulaireCommentaire.getByLabel('Du').fill('2094-04-10');
  await formulaireCommentaire.getByLabel('Au').fill('2094-04-11');
  await formulaireCommentaire.getByLabel('Remplacer l’existant').check();
  page.once('dialog', (dialog) => dialog.accept());
  await formulaireCommentaire.getByRole('button', { name: 'Appliquer le commentaire' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('2 jours ont été mis à jour');

  await page.goto('/?mois=2094-04');
  for (const date of ['2094-04-10', '2094-04-11']) {
    await expect(jourDuCalendrier(page, date)).toContainText('Alex');
    await expect(jourDuCalendrier(page, date)).toContainText('Accueil E2E déplacé au château');
  }
});
