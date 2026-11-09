# GPV — version claude.ai

`gpv.html` est la version de GPV qui tourne comme artifact sur claude.ai. Elle
utilise les services de la page claude.ai (`window.claude`) : base de données
partagée, lecture d'Outlook par le connecteur Microsoft 365, identité de
l'utilisateur, envoi de fichiers, téléchargements, résumé par IA. Ouverte seule
dans un navigateur, elle s'affiche mais n'enregistre rien.

Elle réunit trois sources :

- **B**, la version construite sur claude.ai (tableau de bord, capture, coffre-fort…) ;
- **A**, `standalone/GPV-standalone.html` de ce dépôt ;
- les données d'exemple et la démarche AP-HP de A, importées dans la base de l'artifact.

## Fonctionnalités de A (GPV autonome) et où elles sont dans la version claude.ai

| Fonctionnalité de A | Présente dans B avant la fusion | Dans la version claude.ai |
|---|---|---|
| Domaines avec objectif, couleur, ordre, jauge | oui | oui |
| Sous-domaines imbriqués (Famille › Parents › Maman) avec fil d'Ariane | non | ajoutés |
| Gabarits par sous-domaine : Tâche, Rendez-vous, Cours, Formation, Programme, Habitude, Démarche, avec leurs champs | non | ajoutés |
| Statut À faire / En cours / En attente / Terminé | non (progression seule) | ajouté, synchronisé avec la progression |
| Récurrence quotidienne, jours ouvrés, hebdo, mensuelle, avec recréation automatique | non | ajoutée |
| Heure d'échéance, « Demain », vacation de nuit sur deux jours | partiel | ajoutés |
| Vues Kanban (glisser-déposer), Liste triable, Calendrier mensuel | non | ajoutées |
| Bloc-notes par domaine, par sous-domaine et « en vrac », envoi ligne par ligne vers la capture | non | ajoutés |
| Minuteur Focus : Pomodoro 25/5/15 réglable, enchaînement, chronomètre, bip, notification, temps compté par tâche et par domaine | non | ajouté (bandeau + écran Focus) |
| Générateur d'ambiance : pluie, mer, nature, cheminée, bibliothèque, bureau, café, bruits blanc/rose/brun | non | ajouté, sons générés dans le navigateur |
| Mixeur de couches, mélanges enregistrés, baisse du son pendant les pauses | non | ajouté |
| Visuel d'ambiance en fond, image ou vidéo personnelle, réglage de visibilité | non | ajouté (fichiers stockés dans l'artifact) |
| Thème auto / clair / sombre | auto seul | ajouté |
| Mode mural (marges de 1,4 mm, écran gardé allumé) et plein écran | non | ajoutés |
| Accès ouverts dans une fenêtre posée au-dessus de GPV | non | ajouté (bouton onglet / fenêtre) |
| Recherche globale Ctrl+K | recherche sans raccourci | raccourci ajouté |
| Import du planning PDF de l'employeur (G2S / Comète) : les vacations du mois vont dans le planning et le calendrier, un nouveau planning du même mois remplace l'ancien | non | ajouté (bouton « Importer le planning (PDF) », ou glisser le PDF) |
| Export / import JSON, lecture des sauvegardes de A | non | ajoutés |
| « Effacer les exemples » | non | ajouté |
| Boîte d'entrée | oui (Capture, plus complète) | inchangée |
| Vacations de sécurité | oui | inchangées |
| Matrice d'Eisenhower, priorités Haute / Moyenne / Basse / Nulle | B seulement | inchangées |

Ce que B avait déjà et que A n'a pas : capture des mails Outlook, imports WhatsApp /
SMS / PDF / Word / Excel avec proposition de domaine et de date, Atelier, Journal
privé, revues quotidienne et hebdomadaire, écran Focus des 3 actions, coffre-fort
chiffré (AES-GCM, TOTP, générateur, réinitialisation par codes e-mail et clé de
secours), agenda Outlook dans le planning.

## Données reprises de A

- Domaines **Spiritualité** et **Famille** ; objectifs de Santé, Sport et Religion.
  Formation et Sécurité de A correspondent à « Formation DWWM » et « Travail sécurité ».
- Sous-domaines : Rendez-vous médicaux, Traitements, Programme sportif, Cours,
  Pratiques régulières, Lectures, Formation (exemple), Parents › Maman, Parents › Papa, Administratif.
- La démarche réelle « Payer 30,52 € — créance AP-HP » avec ses champs, ses 5 étapes et ses notes.
- Les exemples de A (marqués « Exemple », effaçables d'un clic).
- Accès : Google Drive, Doctolib, Ameli, Créances publiques, AP-HP duplicatas.

## Version commerciale

La version vendue (comptes, mot de passe oublié, forfaits Free, Moyen et
Supérieur) se construit dans `backend/` (Symfony) et `frontend/` (React) de ce
dépôt. Ce fichier sert de référence fonctionnelle pour les tranches à porter.
