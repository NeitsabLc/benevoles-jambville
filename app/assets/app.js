import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const initialiserFormulairePresence = () => {
    document.querySelectorAll('[data-presence-form]').forEach((formulaire) => {
        if (formulaire.dataset.initialise === 'true') {
            return;
        }
        formulaire.dataset.initialise = 'true';

        const selectionRepas = formulaire.querySelector('[data-selection-repas]');
        const selectThematique = formulaire.querySelector('select[name="thematique"]');
        const dateDebutThematique = formulaire.querySelector('#date_debut');
        const dateFinThematique = formulaire.querySelector('#date_fin');
        if (selectThematique && dateDebutThematique && dateFinThematique) {
            const invitation = selectThematique.options[0];
            const options = [...selectThematique.options].slice(1);
            const erreurExclusive = formulaire.querySelector('[data-erreur-periode-exclusive]');
            const actualiserThematiques = () => {
                const debut = dateDebutThematique.value;
                const fin = dateFinThematique.value;
                const periodeValide = debut && fin && fin >= debut;
                const exclusivesChevauchantes = options.filter((option) => option.dataset.exclusive === 'true' && periodeValide && debut <= option.dataset.fin && fin >= option.dataset.debut);
                const compatibles = options.filter((option) => option.dataset.evenement !== 'true' || (periodeValide && debut >= option.dataset.debut && fin <= option.dataset.fin));
                const visibles = exclusivesChevauchantes.length > 0
                    ? exclusivesChevauchantes.filter((option) => debut >= option.dataset.debut && fin <= option.dataset.fin)
                    : compatibles;
                const valeur = selectThematique.value;
                const ordonnees = [...visibles].sort((a, b) => (b.dataset.evenement === 'true') - (a.dataset.evenement === 'true'));
                selectThematique.replaceChildren(invitation, ...ordonnees);
                selectThematique.value = ordonnees.some((option) => option.value === valeur) ? valeur : '';
                if (erreurExclusive) {
                    const exclusivePartielle = exclusivesChevauchantes.find((option) => debut < option.dataset.debut || fin > option.dataset.fin);
                    erreurExclusive.hidden = !exclusivePartielle;
                    if (exclusivePartielle) {
                        const formaterDate = (dateIso) => new Intl.DateTimeFormat('fr-FR').format(new Date(`${dateIso}T12:00:00`));
                        erreurExclusive.textContent = `Inscription impossible : les dates chevauchent la période exclusive de l’événement « ${exclusivePartielle.textContent} ». Choisissez uniquement des dates comprises dans sa période (du ${formaterDate(exclusivePartielle.dataset.debut)} au ${formaterDate(exclusivePartielle.dataset.fin)}).`;
                    } else {
                        erreurExclusive.textContent = '';
                    }
                }
            };
            dateDebutThematique.addEventListener('change', actualiserThematiques);
            dateFinThematique.addEventListener('change', actualiserThematiques);
            actualiserThematiques();
        }
        if (selectionRepas) {
            const lignes = selectionRepas.querySelector('[data-repas-lignes]');
            const dateDebut = formulaire.querySelector('#date_debut');
            const dateFin = formulaire.querySelector('#date_fin');
            const selectionInitiale = new Set(JSON.parse(selectionRepas.dataset.selectionnes || '[]'));
            const repasConfigures = selectionRepas.dataset.configures === 'true';
            const libelles = [
                ['PETIT_DEJEUNER', 'Petit-déjeuner'],
                ['DEJEUNER', 'Déjeuner'],
                ['DINER', 'Dîner'],
            ];
            const marqueur = document.createElement('input');
            marqueur.type = 'hidden';
            marqueur.name = 'repas_configures';
            marqueur.value = '1';
            formulaire.querySelector('form').appendChild(marqueur);

            const genererLignesRepas = () => {
                const etatCourant = new Map([...lignes.querySelectorAll('input[type="checkbox"]')].map((caseRepas) => [caseRepas.dataset.cle, caseRepas.checked]));
                lignes.replaceChildren();
                if (!dateDebut.value || !dateFin.value || dateFin.value < dateDebut.value) {
                    const ligne = document.createElement('tr');
                    const cellule = document.createElement('td');
                    cellule.colSpan = 4;
                    cellule.className = 'repas-vide';
                    cellule.textContent = 'Choisissez une période valide pour afficher les repas.';
                    ligne.appendChild(cellule);
                    lignes.appendChild(ligne);
                    return;
                }

                let date = new Date(`${dateDebut.value}T12:00:00`);
                const fin = new Date(`${dateFin.value}T12:00:00`);
                while (date <= fin) {
                    const dateIso = date.toISOString().slice(0, 10);
                    const ligne = document.createElement('tr');
                    const jour = document.createElement('th');
                    jour.scope = 'row';
                    jour.textContent = new Intl.DateTimeFormat('fr-FR', {weekday: 'short', day: 'numeric', month: 'short'}).format(date);
                    ligne.appendChild(jour);
                    libelles.forEach(([type, libelle]) => {
                        const cellule = document.createElement('td');
                        const caseRepas = document.createElement('input');
                        const cle = `${dateIso}|${type}`;
                        caseRepas.type = 'checkbox';
                        caseRepas.name = `repas[${dateIso}][]`;
                        caseRepas.value = type;
                        caseRepas.dataset.cle = cle;
                        caseRepas.checked = etatCourant.has(cle) ? etatCourant.get(cle) : (repasConfigures ? selectionInitiale.has(cle) : true);
                        caseRepas.setAttribute('aria-label', `${libelle} du ${jour.textContent}`);
                        cellule.appendChild(caseRepas);
                        ligne.appendChild(cellule);
                    });
                    lignes.appendChild(ligne);
                    date.setDate(date.getDate() + 1);
                }
            };

            dateDebut.addEventListener('change', genererLignesRepas);
            dateFin.addEventListener('change', genererLignesRepas);
            selectionRepas.querySelector('[data-repas-tous]').addEventListener('click', () => lignes.querySelectorAll('input[type="checkbox"]').forEach((caseRepas) => { caseRepas.checked = true; }));
            selectionRepas.querySelector('[data-repas-aucun]').addEventListener('click', () => lignes.querySelectorAll('input[type="checkbox"]').forEach((caseRepas) => { caseRepas.checked = false; }));
            genererLignesRepas();
        }

        formulaire.querySelectorAll('[data-mode-button]').forEach((bouton) => {
            bouton.addEventListener('click', () => {
                const mode = bouton.dataset.modeButton;
                formulaire.dataset.mode = mode;
                formulaire.querySelector('[data-mode-input]').value = mode;
                formulaire.querySelectorAll('[data-mode-button]').forEach((element) => element.classList.toggle('actif', element === bouton));
                formulaire.querySelectorAll('[data-mode-panel]').forEach((panneau) => {
                    const visible = panneau.dataset.modePanel === mode;
                    panneau.hidden = !visible;
                    panneau.querySelectorAll('input, select, textarea').forEach((champ) => { champ.disabled = !visible; });
                });
                formulaire.querySelectorAll('[data-mode-detail]').forEach((detail) => {
                    const visible = detail.dataset.modeDetail === mode;
                    detail.hidden = !visible;
                    if (detail.matches('input, select, textarea')) detail.disabled = !visible;
                    detail.querySelectorAll?.('input, select, textarea').forEach((champ) => { champ.disabled = !visible; });
                });
            });
        });
    });
};

const initialiserSuppressionPresence = () => {
    const dialog = document.querySelector('[data-dialog-suppression-presence]');
    if (!dialog || dialog.dataset.initialise === 'true') return;
    dialog.dataset.initialise = 'true';
    const formulaire = dialog.querySelector('[data-form-suppression-presence]');
    const nom = dialog.querySelector('[data-nom-suppression-presence]');
    const periode = dialog.querySelector('[data-periode-suppression-presence]');
    const token = dialog.querySelector('[data-token-suppression-presence]');

    document.querySelectorAll('[data-suppression-presence]').forEach((bouton) => bouton.addEventListener('click', () => {
        nom.textContent = bouton.dataset.presenceNom;
        periode.textContent = bouton.dataset.presencePeriode;
        formulaire.action = bouton.dataset.suppressionUrl;
        token.value = bouton.dataset.suppressionToken;
        dialog.showModal();
    }));
    dialog.querySelector('[data-fermer-suppression-presence]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
};

const initialiserMenuMobile = () => {
    const bouton = document.querySelector('[data-menu-mobile]');
    const navigation = document.querySelector('[data-navigation-mobile]');
    if (!bouton || !navigation || bouton.dataset.initialise === 'true') return;
    bouton.dataset.initialise = 'true';
    const definirOuverture = (ouvert) => {
        document.body.classList.toggle('menu-mobile-ouvert', ouvert);
        bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    };
    bouton.addEventListener('click', () => definirOuverture(bouton.getAttribute('aria-expanded') !== 'true'));
    document.querySelectorAll('[data-fermer-menu-mobile]').forEach((element) => element.addEventListener('click', () => definirOuverture(false)));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') definirOuverture(false); });
};

const initialiserLignesPresence = () => {
    document.querySelectorAll('[data-modification-url]').forEach((ligne) => {
        if (ligne.dataset.initialise === 'true') return;
        ligne.dataset.initialise = 'true';
        const ouvrir = () => { if (ligne.hasAttribute('data-carte-thematique') || window.matchMedia('(max-width: 760px)').matches) window.location.href = ligne.dataset.modificationUrl; };
        ligne.addEventListener('click', (event) => {
            if (ligne.dataset.glissement === 'true') {
                ligne.dataset.glissement = 'false';
                event.preventDefault();
                return;
            }
            if (!event.target.closest('a, button')) ouvrir();
        });
        ligne.addEventListener('keydown', (event) => { if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button')) { event.preventDefault(); ouvrir(); } });
    });
};

const initialiserGlissieresThematiques = () => {
    document.querySelectorAll('[data-glissiere-thematique]').forEach((glissiere) => {
        if (glissiere.dataset.initialise === 'true') return;
        glissiere.dataset.initialise = 'true';
        const ligne = glissiere.querySelector('[data-carte-thematique]');
        if (!ligne) return;
        let departX = 0;
        let departY = 0;
        let glissementHorizontal = false;

        ligne.addEventListener('pointerdown', (event) => {
            if (!window.matchMedia('(max-width: 760px)').matches || event.target.closest('a, button')) return;
            departX = event.clientX;
            departY = event.clientY;
            glissementHorizontal = false;
        });
        ligne.addEventListener('pointermove', (event) => {
            if (!departX) return;
            const deltaX = event.clientX - departX;
            const deltaY = event.clientY - departY;
            if (Math.abs(deltaX) > 12 && Math.abs(deltaX) > Math.abs(deltaY)) glissementHorizontal = true;
        });
        ligne.addEventListener('pointerup', (event) => {
            if (!departX) return;
            const deltaX = event.clientX - departX;
            if (glissementHorizontal && deltaX < -45) {
                document.querySelectorAll('[data-glissiere-thematique].ouverte').forEach((autre) => { if (autre !== glissiere) autre.classList.remove('ouverte'); });
                glissiere.classList.add('ouverte');
                ligne.dataset.glissement = 'true';
            } else if (glissementHorizontal && deltaX > 35) {
                glissiere.classList.remove('ouverte');
                ligne.dataset.glissement = 'true';
            }
            departX = 0;
            departY = 0;
        });
        ligne.addEventListener('pointercancel', () => {
            departX = 0;
            departY = 0;
            glissementHorizontal = false;
        });
    });
};

const initialiserValidationTelephone = () => {
    document.querySelectorAll('[data-telephone-francais]').forEach((champ) => {
        if (champ.dataset.initialise === 'true') return;
        champ.dataset.initialise = 'true';
        const erreur = document.querySelector('[data-erreur-telephone]');
        const formatTelephoneFrancais = /^(?:(?:\+33|0033)[ .-]?[1-9]|0[1-9])(?:[ .-]?\d{2}){4}$/;

        const valider = () => {
            const invalide = champ.value.trim() !== '' && !formatTelephoneFrancais.test(champ.value.trim());
            champ.setCustomValidity(invalide ? 'Indiquez un numéro français valide.' : '');
            champ.setAttribute('aria-invalid', invalide ? 'true' : 'false');
            if (erreur) erreur.hidden = !invalide;
        };

        champ.addEventListener('blur', valider);
        champ.addEventListener('input', () => {
            if (champ.getAttribute('aria-invalid') === 'true') valider();
        });
    });
};

document.addEventListener('DOMContentLoaded', initialiserFormulairePresence);
document.addEventListener('turbo:load', initialiserFormulairePresence);
document.addEventListener('DOMContentLoaded', initialiserSuppressionPresence);
document.addEventListener('turbo:load', initialiserSuppressionPresence);
document.addEventListener('DOMContentLoaded', initialiserMenuMobile);
document.addEventListener('turbo:load', initialiserMenuMobile);
document.addEventListener('DOMContentLoaded', initialiserLignesPresence);
document.addEventListener('turbo:load', initialiserLignesPresence);
document.addEventListener('DOMContentLoaded', initialiserGlissieresThematiques);
document.addEventListener('turbo:load', initialiserGlissieresThematiques);
document.addEventListener('DOMContentLoaded', initialiserValidationTelephone);
document.addEventListener('turbo:load', initialiserValidationTelephone);
