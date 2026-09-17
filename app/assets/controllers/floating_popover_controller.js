import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'panel'];

    connect() {
        this.repositionner = () => {
            if (this.panelTarget.matches(':popover-open')) this.positionner();
        };
        this.surBascule = (event) => {
            if (event.newState === 'open') requestAnimationFrame(() => this.positionner());
        };

        this.panelTarget.addEventListener('toggle', this.surBascule);
        window.addEventListener('resize', this.repositionner);
        window.addEventListener('scroll', this.repositionner, true);
    }

    disconnect() {
        this.panelTarget.removeEventListener('toggle', this.surBascule);
        window.removeEventListener('resize', this.repositionner);
        window.removeEventListener('scroll', this.repositionner, true);
    }

    positionner() {
        const marge = 8;
        const espace = 5;
        const bouton = this.triggerTarget.getBoundingClientRect();
        const panneau = this.panelTarget.getBoundingClientRect();
        let gauche = bouton.right - panneau.width;
        let haut = bouton.bottom + espace;

        if (haut + panneau.height > window.innerHeight - marge) {
            haut = bouton.top - panneau.height - espace;
        }

        gauche = Math.min(Math.max(marge, gauche), window.innerWidth - panneau.width - marge);
        haut = Math.min(Math.max(marge, haut), window.innerHeight - panneau.height - marge);
        this.panelTarget.style.left = `${gauche}px`;
        this.panelTarget.style.top = `${haut}px`;
    }
}
