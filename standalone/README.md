# GPV — version autonome

Un seul fichier HTML, à ouvrir dans Chrome ou Edge. Aucune installation, aucun
compte, aucun serveur : tout fonctionne hors ligne.

## Ouvrir

Double-clique sur `GPV-standalone.html`, puis active **Mode mural** sur le grand
écran.

## Ce qu'elle fait

- Domaines et sous-domaines imbriqués, avec gabarits de champs
- Vues Domaines, Kanban, Liste et Calendrier
- Éléments avec sous-étapes, récurrence, priorité et échéance
- Bloc-notes par domaine et par sous-domaine
- Boîte de dépôt : glisser un fichier texte, Markdown, CSV, Word, PDF ou Excel
- Mode Focus (Pomodoro et chronomètre) et mode Ambiance (sons et visuels)
- Thème clair, sombre ou automatique
- Export et import JSON

## Mode mural

Le mode mural **n'est pas le plein écran**. GPV occupe la fenêtre moins 1,4 mm
de pourtour, et garde l'écran allumé. Les sites externes s'ouvrent par-dessus et
se ferment sans jamais réduire GPV.

Le bouton ⛶ à côté déclenche le vrai plein écran, pour un écran d'affichage où
rien d'autre ne doit apparaître.

Pour une fenêtre sans barre d'adresse, lance le navigateur en mode application :

```
chrome --app="file:///chemin/vers/GPV-standalone.html"
msedge --app="file:///chemin/vers/GPV-standalone.html"
```

## Limites

- Les données restent dans ce navigateur, sur ce poste. Utilise **Exporter**
  pour les sauvegarder ou les transférer.
- L'import Word, PDF et Excel charge un lecteur depuis internet ; texte et CSV
  fonctionnent hors ligne.
- Pas de messagerie ni d'agenda : ils demandent la version connectée.
- Les images et vidéos d'ambiance ajoutées ne sont pas incluses dans l'export.
