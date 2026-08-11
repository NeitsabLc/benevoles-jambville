import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const impactsBloquants = new Set(['critical', 'serious']);

const pagesPubliques = [
  '/connexion',
  '/conditions-utilisation',
  '/politique-confidentialite',
];

const pagesBenevole = [
  '/',
  '/presences/ajouter',
  '/mon-profil',
];

const pagesAccueil = [
  '/',
  '/synthese',
  '/administration/calendrier',
  '/mon-profil',
];

const pagesPilote = [
  '/',
  '/synthese',
  '/administration/calendrier',
  '/administration/thematiques',
  '/administration/benevoles',
  '/administration/benevoles/ajouter',
  '/administration/benevoles/importer',
  '/mon-profil',
];

function formaterViolations(violations) {
  return violations.map((violation) => ({
    id: violation.id,
    impact: violation.impact,
    aide: violation.help,
    url: violation.helpUrl,
    elements: violation.nodes.map((node) => node.target.join(' ')),
  }));
}

async function verifierPage(page, chemin) {
  const reponse = await page.goto(chemin);
  expect(reponse?.ok(), `La page ${chemin} doit repondre sans erreur`).toBeTruthy();

  const resultat = await new AxeBuilder({ page })
    .exclude('.sf-toolbar')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'])
    .analyze();

  const violationsBloquantes = resultat.violations.filter((violation) =>
    impactsBloquants.has(violation.impact),
  );
  const violationsInformatives = resultat.violations.filter((violation) =>
    !impactsBloquants.has(violation.impact),
  );

  if (violationsInformatives.length > 0) {
    console.warn(
      `Violations non bloquantes sur ${chemin}:\n${JSON.stringify(formaterViolations(violationsInformatives), null, 2)}`,
    );
  }

  expect(
    violationsBloquantes.length,
    `Violations d'accessibilite bloquantes sur ${chemin}:\n${JSON.stringify(formaterViolations(violationsBloquantes), null, 2)}`,
  ).toBe(0);
}

async function seConnecter(page, email) {
  await page.goto('/connexion');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe').fill('Jambville2026!');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).not.toHaveURL(/\/connexion$/);
}

test('les pages publiques ne presentent pas de violation serieuse ou critique', async ({ page }) => {
  for (const chemin of pagesPubliques) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});

test('les pages du benevole ne presentent pas de violation serieuse ou critique', async ({ page }) => {
  await seConnecter(page, 'benevole@jambville.test');

  for (const chemin of pagesBenevole) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});

test("les pages de l'accueil ne presentent pas de violation serieuse ou critique", async ({ page }) => {
  await seConnecter(page, 'accueil@jambville.test');

  for (const chemin of pagesAccueil) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});

test("les pages de l'equipe pilote ne presentent pas de violation serieuse ou critique", async ({ page }) => {
  await seConnecter(page, 'pilote@jambville.test');

  for (const chemin of pagesPilote) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});
