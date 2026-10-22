# Design system — Bénévoles Jambville

> Charte graphique Scouts et Guides de France (SGDF), déjà appliquée dans l'application.
> Source des valeurs : `app/assets/styles/app.css` (`:root`). Toute nouvelle valeur passe d'abord par une variable.
> Même charte que Campement et Scout Market ; les noms de variables sont ici en français.

## 01 Logo et visuels

| Fichier | Usage |
|---|---|
| `app/assets/images/sgdf-logo-horizontal.png` | En-tête, connexion |
| `app/assets/images/jambville-chateau.jpg` | Visuel d'accueil (avec `.message-accueil`) |
| `app/assets/images/favicon.svg` | Icône d'onglet |

- Ne pas déformer, recolorer ni recadrer le logo ; garder une zone de respiration autour.
- Toujours avec un texte alternatif ; image décorative : `alt=""`.

## 02 Typographie

| Rôle | Police | Repli |
|---|---|---|
| Texte et titres | Sarabun | system-ui, sans-serif |
| E-mails | Sarabun, Raleway (titres), Caveat Brush (accroche) via Google Fonts | Arial, Helvetica |

- **Écart connu** : Sarabun n'est pas auto-hébergée dans l'application web (le navigateur utilise la police système).
  À aligner sur Campement : polices dans `app/assets/fonts/`, Raleway pour les titres (voir `etat.md`).
- Tailles en `rem` ; texte courant ≥ 1rem, mentions ≥ 0.75rem.

## 03 Couleurs

| Variable | Valeur | Usage |
|---|---|---|
| `--bleu-sgdf` | `#003a5d` | Couleur principale : titres, navigation, bouton principal |
| `--menthe-sgdf` | `#48b994` | Accent, focus clavier, succès |
| `--rouge-sgdf` | `#e4003b` | Alerte forte, à réserver aux erreurs et suppressions |
| `--fond` | `#f1f5f7` | Fond de page |
| `--texte-secondaire` | `#526a76` | Texte secondaire, pied de page |

- Erreur : texte `#7d2424` sur `#fff0ef`, bordure `#f2c1bd` (à passer en variables).
- Contraste minimal 4.5:1 pour le texte (3:1 pour les grands titres et les icônes).
- Ne jamais transmettre une information par la couleur seule.

## 04 Icônes

- SVG en ligne dans les gabarits, couleur `currentColor`, taille alignée sur le texte.
- Icône décorative : `aria-hidden="true"` ; bouton composé d'une seule icône : `aria-label` obligatoire.
- Réutiliser une icône existante avant d'en ajouter ; style linéaire, trait régulier.

## 05 Composants et mise en page

| Élément | Règle |
|---|---|
| Bouton principal | `.bouton-principal` : fond `--bleu-sgdf`, hauteur ≥ 3.25rem, rayon `0.55rem` ; un seul par zone |
| Bouton secondaire | `.bouton-texte` |
| Rayons | `0.55rem` (boutons, champs), `0.75rem` (cartes), `50%` (avatars) |
| Focus | `outline: 2px solid var(--menthe-sgdf)` + décalage 2–3px, jamais supprimé |
| Popovers | contrôleur `floating_popover_controller.js` (API Popover native) |
| Points de rupture | `900px`, `800px`, `760px` (navigation mobile), `520px` |

## 06 Messages et retours utilisateur

| Type | Affichage | Durée |
|---|---|---|
| Succès, information | Pop-up en haut à droite (`.alerte-succes`, `role="status"`) | Disparaît après **3 s** |
| Erreur générale | Pop-up en haut à droite (`.alerte-erreur`, `role="alert"`) | Disparaît après **3 s** |
| Erreur de champ | Sous le champ concerné, liée par `aria-describedby`, champ en `aria-invalid="true"` | Reste jusqu'à correction |

- Implémentation : conteneur `[data-notifications-flottantes]` (`base.html.twig`, `aria-live="polite"`)
  et `initialiserNotificationsFlottantes` dans `app/assets/app.js`. Les `.alerte` y sont déplacées automatiquement.
- **Écarts à corriger** : délai actuel de 5 s (`DELAI_DISPARITION_ALERTE`) au lieu de 3 s ; pas de bouton de fermeture
  ni de pause au survol/focus (voir `etat.md`).
- Messages courts, en français, orientés action.

## 07 Accessibilité

- [ ] Cible WCAG 2.2 AA / RGAA ; contrôlée par `make test-accessibility` (Axe).
- [ ] Chaque champ a un `<label>` visible ; les erreurs sont annoncées.
- [ ] Navigation complète au clavier, ordre logique, focus visible.
- [ ] Zones cliquables d'au moins 24 × 24 px (44 px recommandé sur mobile).
