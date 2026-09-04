import { expect, test } from '@playwright/test';
import { choisirPeriode, comptes, seConnecter } from './helpers.js';

test('@compatibilite connexion et JavaScript métier fonctionnent sur le navigateur', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  await page.goto('/presences/ajouter');
  await choisirPeriode(page, '2091-07-10', '2091-07-12');

  await expect(page.locator('[data-repas-lignes] tr')).toHaveCount(3);
  await expect(page.locator('[data-repas-lignes] input[type="checkbox"]')).toHaveCount(9);
  await expect(page.getByLabel('Thématique').locator('option', { hasText: 'Événement E2E standard' })).toHaveCount(1);
});
