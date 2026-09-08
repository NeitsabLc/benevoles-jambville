import { expect, test } from '@playwright/test';
import { comptes, seConnecter } from './helpers.js';

async function glisserVersLaGauche(element) {
  await element.dispatchEvent('pointerdown', { pointerType: 'touch', clientX: 300, clientY: 100 });
  await element.dispatchEvent('pointermove', { pointerType: 'touch', clientX: 180, clientY: 102 });
  await element.dispatchEvent('pointerup', { pointerType: 'touch', clientX: 180, clientY: 102 });
}

test('@mobile menu adaptatif et interactions tactiles', async ({ page }) => {
  await seConnecter(page, comptes.pilote);
  const boutonMenu = page.getByRole('button', { name: 'Menu', exact: true });
  await expect(boutonMenu).toBeVisible();
  await boutonMenu.tap();
  await expect(boutonMenu).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('body')).toHaveClass(/menu-mobile-ouvert/);
  await page.getByRole('button', { name: 'Fermer le menu' }).first().tap();
  await expect(boutonMenu).toHaveAttribute('aria-expanded', 'false');

  await page.goto('/administration/thematiques');
  const carteThematique = page.locator('[data-carte-thematique]', { hasText: 'Événement E2E standard' });
  await glisserVersLaGauche(carteThematique);
  await expect(carteThematique.locator('..')).toHaveClass(/ouverte/);
  await expect(carteThematique.locator('..').getByRole('button', { name: 'Désactiver' })).toBeVisible();

  await page.goto('/administration/benevoles');
  const ligneBenevole = page.getByRole('link', { name: `Voir le profil de ${comptes.session.nom}` });
  await glisserVersLaGauche(ligneBenevole);
  await expect(ligneBenevole.locator('..')).toHaveClass(/ouverte/);
  await expect(ligneBenevole.locator('..').getByRole('button', { name: 'Désactiver' })).toBeVisible();
});
