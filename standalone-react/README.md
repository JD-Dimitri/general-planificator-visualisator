# GPV autonome, en React

Port de `standalone/GPV-standalone.html` vers React, avec le même résultat :
**un seul fichier HTML**, ouvert d'un double-clic, qui fonctionne hors ligne,
sans serveur ni installation.

```bash
npm install
npm run dev     # http://127.0.0.1:5173
npm run build   # produit dist/index.html, autonome et complet
```

`vite-plugin-singlefile` replie le code, les styles et les dix images
d'ambiance dans ce seul fichier.

---

## État : la couche de données est écrite, l'interface reste à faire

`src/store/` est terminé et passe le contrôle de types. `src/ui/` est vide.

### Ce qui est fait

| Fichier | Rôle |
| --- | --- |
| `types.ts` | Le format de données, **identique à celui du fichier autonome actuel** |
| `dates.ts` | Dates en chaînes « AAAA-MM-JJ », écarts, formats |
| `recurrence.ts` | Occurrence suivante : ancrée, rattrapée, sans dérive |
| `seed.ts` | Domaines, sous-domaines et exemples d'un GPV neuf |
| `store.ts` | Magasin externe avec `useSyncExternalStore`, écriture différée |
| `selectors.ts` | Arbre des sous-domaines, progression, tri, urgences, statistiques |
| `actions.ts` | Toutes les écritures, en un seul endroit |

### Deux contraintes à ne pas casser

**La clé de stockage est `gpv.standalone.v1`, celle du fichier actuel.** Le
format des champs est le même, aux mêmes noms. Ouvrir cette version reprend
donc les données déjà saisies, et les deux fichiers lisent les mêmes exports.
Changer un nom de champ ferait perdre ses données à l'utilisateur.

**Tout doit tenir dans un fichier et marcher hors ligne.** Pas d'appel réseau,
pas de dépendance chargée à l'exécution.

### Ce qu'il reste à écrire : `src/ui/`

À porter depuis `standalone/GPV-standalone.html`, qui est la référence
fonctionnelle. Toute la logique est déjà dans `src/store/` : l'interface ne
fait que l'afficher et appeler les actions.

- [ ] Bandeau : horloge, compteurs, anneau de progression, thème, recherche
- [ ] Colonne gauche : planning 7 jours, à traiter en priorité, boîte de dépôt
- [ ] Colonne centrale : cartes de domaines, détail, arbre de sous-domaines
- [ ] Vues Kanban (glisser-déposer), Liste triable, Calendrier mensuel
- [ ] Colonne droite : accès directs, notes en vrac, sauvegarde
- [ ] Formulaires : élément, domaine, sous-domaine, accès, vacation
- [ ] Mode Focus : Pomodoro, chronomètre, écran agrandi, statistiques
- [ ] Mode Ambiance : sons synthétisés en Web Audio, mélangeur
- [ ] **Galerie de visuels** : plusieurs images ou vidéos par ambiance,
      vignettes, ajout par glisser-déposer. Les dix images livrées sont dans
      `src/media/` et partent dans le fichier ; les médias ajoutés par
      l'utilisateur vont dans IndexedDB, jamais dans le HTML.
- [ ] Mode mural : marge de 1,4 mm, écran maintenu allumé, **sans plein écran**
- [ ] Import de fichiers : txt, md, csv, docx, pdf, xlsx vers la boîte de dépôt

### Trois pièges déjà rencontrés, à ne pas refaire

1. **Le mode mural n'appelle pas le plein écran.** En plein écran, aucune autre
   fenêtre ne peut s'afficher par-dessus, ce qui est l'inverse du besoin.
2. **Les accès s'ouvrent avec `window.open` et des options de taille**, pour
   obtenir une fenêtre posée au-dessus de GPV. Un onglet masquerait GPV.
3. **Les récurrences mensuelles ont besoin de `anchorDay`.** Sans lui, « le 31
   de chaque mois » ramené au 28 février reste au 28 les mois suivants.
