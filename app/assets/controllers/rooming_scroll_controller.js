import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { storageKey: String };

    connect() {
        let position;

        try {
            position = JSON.parse(sessionStorage.getItem(this.storageKeyValue));
            sessionStorage.removeItem(this.storageKeyValue);
        } catch {
            return;
        }

        if (!position) return;

        requestAnimationFrame(() => requestAnimationFrame(() => {
            this.element.scrollLeft = position.left;
            const decalage = this.element.getBoundingClientRect().top - position.top;
            window.scrollBy({ top: decalage, behavior: 'instant' });
        }));
    }

    memoriser() {
        const position = {
            left: this.element.scrollLeft,
            top: this.element.getBoundingClientRect().top,
        };

        try {
            sessionStorage.setItem(this.storageKeyValue, JSON.stringify(position));
        } catch {
            // L'affectation reste fonctionnelle si le stockage du navigateur est indisponible.
        }
    }
}
