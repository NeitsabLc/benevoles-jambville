import { expect, test } from '@playwright/test';
import { choisirPeriode, jourDuCalendrier, seConnecter, seDeconnecter } from '../e2e/helpers.js';

const compte = { email: process.env.RECETTE_EMAIL };
const motDePasse = process.env.RECETTE_MOT_DE_PASSE;
const repere = 'Contrôle recette automatique';
const jour = '2094-03-15';
const mois = '2094-03';

test.describe.configure({ mode: 'serial' });

// Surveille les réponses serveur en erreur pendant un test.
function surveillerErreursServeur(page) {
  const erreurs = [];
  page.on('response', (reponse) => {
    if (reponse.status() >= 500) {
      erreurs.push(`${reponse.status()} ${new URL(reponse.url()).pathname}`);
    }
  });
  return erreurs;
}

async function verifierPage(page, chemin, titre) {
  const reponse = await page.goto(chemin);
  expect(reponse.status(), `${chemin} répond`).toBeLessThan(500);
  await expect(page.getByRole('heading', { level: 1 })).toContainText(titre);
  await expect(page.locator('body')).not.toContainText(/Erreur 500|Exception|Stack trace/i);
}

// Supprime toute présence du compte portant le repère (le compte n’a de contrôles que sur ses propres présences).
async function nettoyerPresences(page) {
  await page.goto(`/?mois=${mois}`);
  await expect(page).toHaveURL(new RegExp(`[?&]mois=${mois}`));
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Qui sera à Jambville');
  const liens = await page.locator('a[aria-label^="Modifier"]').evaluateAll((noeuds) => noeuds.map((n) => n.getAttribute('href')));
  for (const lien of liens) {
    await page.goto(lien);
    const commentaire = await page.getByLabel(/Commentaire/).inputValue();
    if (!commentaire.includes(repere)) {
      continue;
    }
    await page.getByRole('button', { name: 'Supprimer la présence' }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Supprimer la présence' }).click();
    await expect(page.locator('.alerte-succes')).toContainText('présence a bien été supprimée');
  }
}

test('lecture : parcours des pages d’un bénévole', async ({ page }) => {
  const erreurs = surveillerErreursServeur(page);
  await seConnecter(page, compte, motDePasse);

  await verifierPage(page, '/', 'Qui sera à Jambville');
  await verifierPage(page, '/mon-profil', 'Mon profil');
  await verifierPage(page, '/presences/ajouter', 'Ajouter');

  // La synthèse est réservée à l'équipe : un bénévole doit être refusé, sans erreur serveur.
  const synthese = await page.goto('/synthese');
  expect(synthese.status()).toBe(403);

  await page.goto('/');
  await seDeconnecter(page);
  expect(erreurs).toEqual([]);
});

test.describe('écriture : présence à la journée', () => {
  test.afterEach(async ({ page }) => {
    // Nettoyage garanti, même si le test a échoué (nouvelle session si besoin).
    await page.goto('/connexion');
    if (new URL(page.url()).pathname === '/connexion') {
      await seConnecter(page, compte, motDePasse);
    }
    await nettoyerPresences(page);
    await page.goto(`/?mois=${mois}`);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Qui sera à Jambville');
    await expect(page.locator('a[aria-label^="Modifier"]')).toHaveCount(0);
  });

  test('création, modification et suppression', async ({ page }) => {
    const erreurs = surveillerErreursServeur(page);
    await seConnecter(page, compte, motDePasse);
    await nettoyerPresences(page);

    await page.goto('/presences/ajouter');
    await choisirPeriode(page, jour, jour);
    const thematique = page.getByLabel('Thématique', { exact: true });
    await expect(thematique.locator('option'), 'aucune thématique active sans dates en recette').not.toHaveCount(1);
    await thematique.selectOption({ index: 1 });
    expect(await thematique.inputValue(), 'aucune thématique active sans dates en recette').not.toBe('');
    await page.getByLabel('Nombre d’enfants').fill('0');
    await page.getByLabel(/Commentaire/).fill(`${repere} initial`);
    await page.getByRole('button', { name: 'Ajouter la présence' }).click();

    await expect(page.locator('.alerte-succes')).toContainText('présence a bien été ajoutée');
    await page.goto(`/?mois=${mois}`);
    const jourCalendrier = jourDuCalendrier(page, jour);
    await expect(jourCalendrier.locator('a[aria-label^="Modifier"]')).toHaveCount(1);

    await jourCalendrier.locator('a[aria-label^="Modifier"]').click();
    await expect(page.getByRole('heading', { name: 'Modifier une présence' })).toBeVisible();
    await page.getByLabel(/Commentaire/).fill(`${repere} modifié`);
    await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
    await expect(page.locator('.alerte-succes')).toContainText('présence a bien été modifiée');

    await page.goto(`/?mois=${mois}`);
    await jourCalendrier.locator('a[aria-label^="Modifier"]').click();
    await expect(page.getByLabel(/Commentaire/)).toHaveValue(`${repere} modifié`);
    await page.getByRole('button', { name: 'Supprimer la présence' }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Supprimer la présence' }).click();
    await expect(page.locator('.alerte-succes')).toContainText('présence a bien été supprimée');

    await page.goto(`/?mois=${mois}`);
    await expect(jourCalendrier.locator('a[aria-label^="Modifier"]')).toHaveCount(0);
    expect(erreurs).toEqual([]);
  });
});
