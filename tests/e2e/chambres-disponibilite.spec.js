import { expect, test } from '@playwright/test';
import { comptes, seConnecter } from './helpers.js';

test('modification des disponibilités sans rechargement de page', async ({ page }) => {
  await page.clock.install();
  await seConnecter(page, comptes.pilote);
  await page.goto('/administration/chambres');

  const carte = page.locator('[data-chambres-repere="chambre-patrouille"]');
  const formulaireMode = carte.locator('[data-chambres-repere="mode-patrouille"]');

  if (await carte.getByRole('button', { name: 'Rendre disponible en permanence' }).isVisible()) {
    await carte.getByRole('button', { name: 'Rendre disponible en permanence' }).click();
    await expect(carte).toContainText('Disponible en permanence');
  }

  await page.clock.fastForward(5500);
  await formulaireMode.scrollIntoViewIfNeeded();
  await page.evaluate(() => { window.__pageChambresInitiale = true; });
  const positionAvant = await page.evaluate(() => window.scrollY);

  await carte.getByRole('button', { name: 'Limiter aux périodes indiquées' }).click();

  await expect(carte).toContainText('Disponible par périodes');
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(positionAvant);
  expect(await page.evaluate(() => window.__pageChambresInitiale)).toBe(true);
  await expect(carte.getByLabel('Du')).toHaveAttribute('type', 'text');

  const alerte = page.locator('[data-notifications-flottantes] .alerte');
  await expect(alerte).toContainText('uniquement pendant les périodes');
  await page.clock.fastForward(5500);
  await expect(alerte).toHaveCount(0);

  await carte.getByRole('button', { name: 'Rendre disponible en permanence' }).click();
  await expect(carte).toContainText('Disponible en permanence');
});
