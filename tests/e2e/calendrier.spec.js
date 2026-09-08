import { expect, test } from '@playwright/test';
import { comptes, jourDuCalendrier, renseignerDate, seConnecter } from './helpers.js';

test('les sélecteurs de date affichent la semaine française à partir du lundi', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  await page.goto('/presences/ajouter');

  await page.getByLabel('Du', { exact: true }).click();

  await expect(page.locator('.flatpickr-calendar.open .flatpickr-weekday')).toHaveText([
    'lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim',
  ]);
});

test('modification des permanences et commentaires puis contrôle du calendrier', async ({ page }) => {
  await seConnecter(page, comptes.accueil);
  await page.goto('/administration/calendrier');

  const formulairePermanence = page.locator('form', { has: page.getByRole('button', { name: 'Appliquer la permanence' }) });
  await formulairePermanence.getByLabel('Personne').selectOption({ label: 'Alex de permanence' });
  await renseignerDate(formulairePermanence.getByLabel('Du'), '2094-04-10');
  await renseignerDate(formulairePermanence.getByLabel('Au'), '2094-04-11');
  await formulairePermanence.getByRole('button', { name: 'Appliquer la permanence' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('2 jours ont été mis à jour');

  const formulaireCommentaire = page.locator('form', { has: page.getByRole('button', { name: 'Appliquer le commentaire' }) });
  await formulaireCommentaire.getByLabel(/Commentaire/).fill('Accueil E2E déplacé au château');
  await renseignerDate(formulaireCommentaire.getByLabel('Du'), '2094-04-10');
  await renseignerDate(formulaireCommentaire.getByLabel('Au'), '2094-04-11');
  await formulaireCommentaire.getByLabel('Remplacer l’existant').check();
  page.once('dialog', (dialog) => dialog.accept());
  await formulaireCommentaire.getByRole('button', { name: 'Appliquer le commentaire' }).click();
  await expect(page.locator('.alerte-succes')).toContainText('2 jours ont été mis à jour avec ce commentaire');

  await page.goto('/?mois=2094-04');
  for (const date of ['2094-04-10', '2094-04-11']) {
    await expect(jourDuCalendrier(page, date)).toContainText('Alex');
    await expect(jourDuCalendrier(page, date)).toContainText('Accueil E2E déplacé au château');
  }
});
