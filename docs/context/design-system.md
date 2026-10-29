# Design system — Bénévoles Jambville

> Charte graphique Scouts et Guides de France (SGDF), commune à Campement, Bénévoles Jambville et Scout Market.
> Référence : charte graphique SGDF « identité visuelle » (couleurs p. 18, typographies p. 19-21, logo p. 8-10).
> Source des valeurs : `app/assets/styles/app.css` (`:root`). Toute nouvelle valeur passe d'abord par une variable.
> Les écarts propres à l'application sont listés en §10.

## 01 Identité et logo

| Fichier | Usage |
|---|---|
| `app/assets/images/sgdf-logo-horizontal.png` | En-tête, page de connexion |
| `app/assets/images/jambville-chateau.jpg` | Visuel d'accueil (avec `.message-accueil`), décoratif : `alt=""` |
| `app/assets/images/favicon.svg` | Icône d'onglet |

- Logo en bleu institutionnel sur fond blanc, en blanc sur fond bleu institutionnel.
- Zone de respiration vierge autour du logo : aucun texte, visuel ni partie de photo.
- Respecter la taille minimale de la charte (1,5 cm et 3,2 cm selon la version) ; à l'écran, ne jamais réduire
  le logo au point de rendre le texte illisible.
- Interdits : couleur non autorisée, déformation, modification, suppression d'un élément ou du symbole.
- Toujours avec un texte alternatif (`alt="Scouts et Guides de France"`).

## 02 Couleurs

### Couleurs de la charte

| Variable | Valeur | Rôle dans la charte | Contraste sur blanc |
|---|---|---|---|
| `--sgdf-blue` | `#003a5d` | **Couleur principale** (bleu institutionnel), présente sur tous les supports | 12 : 1 |
| `--sgdf-blue-light` | `#0077b3` | Scouts et guides | 4,9 : 1 |
| `--sgdf-green` | `#007254` | Compagnons | 5,9 : 1 |
| `--sgdf-violet` | `#6e74aa` | Vent du large | 4,6 : 1 |
| `--sgdf-red` | `#d03f15` | Pionniers et caravelles | 5,2 : 1 |
| `--sgdf-mint` | `#65bc99` | Farfadets | 2,3 : 1 |
| `--sgdf-orange` | `#ff8300` | Louveteaux et jeannettes | 2,4 : 1 |

- Les couleurs secondaires accompagnent le bleu institutionnel « en touches » : jamais à sa place.
- Aucune autre teinte de marque : une couleur hors de ce tableau est interdite (logo compris).

### Couleurs d'interface

| Variable | Valeur | Usage |
|---|---|---|
| `--surface` | `#ffffff` | Fond des cartes et formulaires |
| `--surface-muted` | `#f4f7f9` | Fond de page, survol des lignes |
| `--border` | `#d9e2e7` | Bordures, séparateurs |
| `--text` | `#003a5d` | Texte courant (bleu institutionnel, exigé par la charte sur fond clair) |
| `--text-muted` | `#526875` | Texte secondaire |
| `--link` | `#0077b3` | Liens, éléments actifs |
| `--success` | `#007254` | Succès |
| `--danger` | `#d03f15` (rouge de la charte) | Erreurs, actions destructives |
| `--danger-surface` | `#fef3f2` | Fond des messages d'erreur |
| `--focus` | `#0077b3` | Contour de focus clavier |

- Contraste minimal 4.5:1 pour le texte (3:1 pour les grands titres, les icônes et le contour de focus).
- `#65bc99` et `#ff8300` n'atteignent pas 3:1 sur blanc : décor et aplats uniquement, jamais pour du texte,
  une icône seule ou le focus.
- Ne jamais transmettre une information par la couleur seule (ajouter texte ou icône).
- Aucune couleur codée en dur : remplacer celles qui existent quand on touche le bloc.

## 03 Typographie

| Rôle (charte) | Police | Variable | Repli |
|---|---|---|---|
| Texte courant, formulaires | Sarabun (400, 700, italique) | `--font-body` | "Segoe UI", Arial, sans-serif |
| Titres et sous-titres, navigation, boutons | Raleway (400, 700) | `--font-title` | "Segoe UI", Arial, sans-serif |

- Sarabun en bleu institutionnel sur fond clair, en blanc sur fond foncé (règle de la charte).
- Caveat Brush, prévue par la charte pour le titrage, n'est pas utilisée dans les applications.
- Polices auto-hébergées dans `app/assets/fonts/` : aucun appel à un service externe.
- E-mails : polices système (`Arial, Helvetica, sans-serif`), jamais chargées depuis un service externe.
- Tailles en `rem` ; texte courant ≥ 1rem, mentions et métadonnées ≥ 0.75rem (la charte interdit moins de 6 pt).

## 04 Espacements et rayons

| Élément | Valeur |
|---|---|
| Rayon des champs et boutons | `0.3rem` |
| Rayon des cartes | `0.45rem` |
| Pastilles | `50%` |
| Ombre | `--shadow`, réservée aux éléments flottants (dialogues, menus) |
| Espacements | Échelle `0.25 / 0.5 / 1 / 1.5 / 2 / 3rem`, aucune autre valeur |

## 05 Icônes

- SVG en ligne, `fill`/`stroke` à `currentColor`, taille alignée sur le texte (1em à 1.25rem).
- Icône décorative : `aria-hidden="true"` ; bouton composé d'une seule icône : `aria-label` obligatoire.
- Réutiliser une icône existante avant d'en dessiner une ; style linéaire, trait régulier.

## 06 Composants

| Élément | Règle |
|---|---|
| Boutons | `.button`, `.button--primary` (bleu SGDF), `.button--danger-outline` ; un seul bouton primaire par zone |
| Focus | `outline: 3px solid var(--focus)` + `outline-offset: 2px`, jamais supprimé |
| Popovers | Contrôleur `floating_popover_controller.js` (API Popover native) |

## 07 Messages et retours utilisateur

| Type | Affichage | Durée |
|---|---|---|
| Succès, information | Pop-up en haut à droite (`role="status"`) | Disparaît après **3 s** |
| Erreur générale | Pop-up en haut à droite (`role="alert"`) | Disparaît après **3 s** |
| Erreur de champ | Sous le champ, liée par `aria-describedby`, champ en `aria-invalid="true"` | Reste jusqu'à correction |

- Implémentation : conteneur `[data-notifications-flottantes]` (`base.html.twig`, `aria-live="polite"`)
  et `initialiserNotificationsFlottantes` dans `app/assets/app.js` ; les `.alerte` y sont déplacées automatiquement.
- Bouton de fermeture, pause du minuteur au survol et au focus, empilement de haut en bas.
- Messages courts, en français, orientés action (« Saisissez une date de fin postérieure au début »).

## 08 Accessibilité

- [ ] Cible WCAG 2.2 AA / RGAA, contrôlée par Axe.
- [ ] Chaque champ a un `<label>` visible ; les erreurs sont annoncées.
- [ ] Navigation complète au clavier, ordre logique, focus visible.
- [ ] Zones cliquables d'au moins 24 × 24 px (44 px recommandé sur mobile).

## 09 Points de rupture

| Nom | Largeur | Comportement |
|---|---|---|
| Mobile | < 48rem | Une colonne |
| Tablette | 48–70rem | Selon la page |
| Bureau | > 70rem | Selon la page |

- Navigation mobile sous `760px`.

## 10 Écarts propres à l'application

| Élément | Écart | Motif |
|---|---|---|
| Variables | Noms en français (`--bleu-sgdf`, `--menthe-sgdf`, `--fond`, `--texte-secondaire`) | À renommer vers les noms communs de §02 |
| Couleurs | Menthe `#48b994` et rouge `#e4003b`, hors charte ; fond `#f1f5f7` | Remplacer par les couleurs de la charte (§02) |
| Erreurs | Couleurs codées en dur (`#7d2424`, `#fff0ef`, `#f2c1bd`) | À passer en variables `--danger*` |
| Typographie | Sarabun non auto-hébergée (police système), pas de Raleway pour les titres | À aligner sur §03 |
| E-mails | Polices chargées depuis Google Fonts (Sarabun, Raleway, Caveat Brush) | À remplacer par des polices système (§03) |
| Composants | Bouton `.bouton-principal` (hauteur ≥ 3.25rem, rayon `0.55rem`), cartes `0.75rem`, focus `2px` en menthe | À aligner sur §04 et §06 (focus `--focus`) |
| Points de rupture | `900px`, `800px`, `760px`, `520px` | À aligner sur §09 |
| Messages | Disparition après 5 s (`DELAI_DISPARITION_ALERTE`), sans bouton de fermeture ni pause | À passer à 3 s avec fermeture et pause (`etat.md`) |
