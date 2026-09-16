import { Controller } from '@hotwired/stimulus';

const DELAI_DISPARITION_ALERTE = 5000;
const DUREE_TRANSITION_ALERTE = 220;

export default class extends Controller {
    connect() {
        this.envoiEnCours = false;
        this.minuteriesAlertes = new Set();
        this.programmerDisparitionAlertes();
    }

    disconnect() {
        this.annulerMinuteriesAlertes();
    }

    async envoyer(event) {
        event.preventDefault();

        const formulaire = event.target;
        if (!(formulaire instanceof HTMLFormElement) || this.envoiEnCours) return;

        const donnees = event.submitter
            ? new FormData(formulaire, event.submitter)
            : new FormData(formulaire);
        const url = formulaire.getAttribute('action') || window.location.href;
        const positionVerticale = document.scrollingElement?.scrollTop ?? window.scrollY;
        const bouton = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
        let contenuActualise = false;

        this.envoiEnCours = true;
        this.element.setAttribute('aria-busy', 'true');
        if (bouton) bouton.disabled = true;

        try {
            const reponse = await fetch(url, {
                method: formulaire.method || 'POST',
                body: donnees,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!reponse.ok) throw new Error(`Réponse HTTP ${reponse.status}`);

            const documentRecu = new DOMParser().parseFromString(await reponse.text(), 'text/html');
            const contenuRecu = documentRecu.querySelector('.page-administration-chambres');
            if (!contenuRecu) throw new Error('Contenu de la page introuvable');

            this.annulerMinuteriesAlertes();
            document.dispatchEvent(new CustomEvent('application:avant-mise-a-jour'));
            this.element.innerHTML = contenuRecu.innerHTML;
            contenuActualise = true;
            document.dispatchEvent(new CustomEvent('application:contenu-mis-a-jour'));
            this.programmerDisparitionAlertes();
        } catch (erreur) {
            console.error('La disponibilité de la chambre n’a pas pu être actualisée.', erreur);
            if (!contenuActualise) this.afficherErreurTechnique();
        } finally {
            this.restaurerPosition(positionVerticale);
            this.envoiEnCours = false;
            this.element.removeAttribute('aria-busy');
            if (bouton?.isConnected) bouton.disabled = false;
        }
    }

    programmerDisparitionAlertes() {
        this.element.querySelectorAll('[data-chambres-alerte]').forEach((alerte) => {
            if (alerte.dataset.disparitionProgrammee === 'true') return;
            alerte.dataset.disparitionProgrammee = 'true';
            const minuterie = window.setTimeout(() => {
                alerte.classList.add('alerte-en-disparition');
                const minuterieSuppression = window.setTimeout(() => alerte.remove(), DUREE_TRANSITION_ALERTE);
                this.minuteriesAlertes.add(minuterieSuppression);
            }, DELAI_DISPARITION_ALERTE);
            this.minuteriesAlertes.add(minuterie);
        });
    }

    annulerMinuteriesAlertes() {
        this.minuteriesAlertes?.forEach((minuterie) => window.clearTimeout(minuterie));
        this.minuteriesAlertes?.clear();
    }

    restaurerPosition(positionVerticale) {
        const replacer = () => {
            if (document.scrollingElement) document.scrollingElement.scrollTop = positionVerticale;
            window.scrollTo(0, positionVerticale);
        };

        replacer();
        requestAnimationFrame(() => requestAnimationFrame(replacer));
    }

    afficherErreurTechnique() {
        const conteneur = this.element.querySelector('.notifications-chambres');
        if (!conteneur) return;

        const alerte = document.createElement('div');
        alerte.className = 'alerte alerte-erreur';
        alerte.dataset.chambresAlerte = '';
        alerte.setAttribute('role', 'alert');
        alerte.textContent = 'La modification n’a pas pu être enregistrée. Vérifiez votre connexion puis réessayez.';
        conteneur.appendChild(alerte);
        this.programmerDisparitionAlertes();
    }
}
