**Brief Produit – ToastIt**  
**Version 1.0 – Mars 2026**

### 1. Résumé exécutif / Elevator Pitch
**ToastIt** est une application web collaborative qui transforme un parking lot partagé (notes et to-do rapides) en un agenda de réunion intelligent et démocratique.

Les membres d’une équipe ajoutent des sujets, votent pour prioriser, et l’organisateur peut veto ou booster des items. Lors du meeting (one-shot ou récurrent), on documente les décisions, assigne responsables et dates. À la fin, un résumé généré par l’IA Grok (optionnel) est envoyé automatiquement par email à tous les participants.

Les réunions récurrentes bénéficient d’un carry-over intelligent : actions non traitées reviennent avec leurs votes, actions overdue passent en priorité haute.

**Objectif** : Réduire drastiquement le temps perdu en réunions improductives, améliorer le suivi des décisions et garantir que les sujets importants ne tombent jamais dans l’oubli.

### 2. Problème à résoudre
Les équipes font face à plusieurs frustrations récurrentes :
- Agendas imposés du haut ou construits à la dernière minute.
- Sujets importants oubliés ou mal priorisés.
- Manque de visibilité sur les votes/demandes des participants.
- Actions décidées en réunion qui disparaissent entre deux meetings.
- Temps perdu à reformuler les points non traités.
- Résumés manuels longs et incomplets, surtout pour les absents.

ToastIt propose un flux continu : **parking lot asynchrone → agenda démocratique → meeting live → décisions tracées → résumé IA + carry-over automatique**.

### 3. Objectifs et métriques de succès (SMART)
**Objectifs business**
- Aider les équipes à traiter 30 % plus de sujets prioritaires par réunion.
- Réduire le temps de préparation d’agenda de 70 %.
- Atteindre un taux de complétion des actions décidées > 80 %.

**Métriques clés (MVP)**
- Nombre de votes par meeting.
- Taux d’utilisation du résumé IA.
- Taux de rétention des équipes (au moins 3 réunions récurrentes).
- NPS utilisateur après 1 mois d’utilisation.
- Temps moyen entre création d’un item et sa discussion.

### 4. Personas principaux
- **Organisateur / Team Lead** : Crée l’équipe et les meetings récurrents, configure l’IA, exerce veto/boost, clôture les réunions.
- **Membre d’équipe** : Ajoute des items, vote, commente, consulte l’agenda en temps réel.
- **Invité ponctuel** : Accès limité au meeting one-shot, reçoit uniquement le résumé.
- **Admin organisation (v2)** : Gère plusieurs équipes, quotas API, facturation.

### 5. Fonctionnalités principales (MVP)

#### Parking Lot partagé
- Ajout rapide d’items (titre + description courte).
- Commentaires/questions sur chaque item.
- Vote : **1 vote par participant par item** (modifiable).

#### Gestion des Meetings
- Création : one-shot ou récurrent (hebdo, bi-hebdo, mensuel).
- Configuration : titre, date/heure, lien visioconférence (Zoom/Meet/etc.), invités.
- **Droits exclusifs de l’organisateur** :
    - **Veto** : écarter un item de l’agenda (visible avec mention “Veto par [Nom]”).
    - **Boost** : placer un item en priorité n°1 avec poids maximal (possibilité d’ordonner plusieurs boosts).

#### Agenda automatique
Ordre de traitement :
1. Items **boostés** (dans l’ordre défini par l’organisateur).
2. Items normaux triés par **nombre de votes** descendant.
3. Items **veto** exclus de l’ordre.

#### Mode Meeting Live
- Affichage de l’agenda ordonné en temps réel.
- Pour chaque item : commentaires, champ “Décision prise”, responsable, date d’échéance.
- Statut : Traité / Reporté.
- L’organisateur peut veto ou booster pendant la réunion.

#### Fin de Meeting & Récurrence
- **Carry-over intelligent** :
    - Items non traités → reportés avec **le même nombre de votes**.
    - Actions dont la date d’échéance est proche ou passée → **priorité haute** automatique.
    - Items veto → non reportés automatiquement.
    - Statut boost perdu après le meeting.
- **Résumé IA Grok (optionnel)** :
    - Configurable par équipe (clé API xAI stockée chiffrée).
    - Envoi automatique par email à tous les participants.
    - Contenu : synthèse courte, décisions avec responsables/deadlines, actions en cours, points reportés, agenda provisoire suivant.

#### Autres
- Historique des meetings (sujets passés, actions terminées, actions en cours).
- Tableau de bord : meetings à venir / passés, parking lot global.

### 6. Fonctionnalités hors scope (MVP)
- Intégrations calendriers (Google Calendar, Outlook) – v2.
- Notifications push / Slack / Teams en temps réel.
- Support multi-IA (OpenAI, Claude…).
- Export PDF / Confluence / Notion.
- Gestion avancée des permissions par organisation.
- Analyse statistique des réunions (temps par sujet, etc.).

### 7. Architecture technique recommandée
- **IA** : xAI Grok API (`https://api.x.ai/v1/chat/completions`) – modèle recommandé : grok-4 ou le plus récent disponible.
- **Sécurité** : Clé API Grok chiffrée, quotas par équipe, RLS (Row Level Security) sur la BDD.

### 8. Prompt système recommandé pour le résumé Grok
```text
Tu es un assistant de synthèse de réunion ultra-professionnel, concis et positif.

Meeting : "[Titre]" – [Date]

Participants : [liste]
Agenda traité :
[liste des items avec votes, commentaires, décision, responsable, deadline]

Actions précédentes overdue :
[liste]

Génère un email de résumé en français, ton professionnel mais amical, max 300-400 mots :

1. Synthèse courte du meeting (2-3 phrases)
2. Décisions clés (liste à puces avec responsable + deadline)
3. Actions à suivre / en cours
4. Points reportés au prochain meeting
5. Prochain meeting : [date] – agenda provisoire

Mentionne clairement les veto et boosts de l’organisateur.
Utilise du html lisible pour l’email.
```

### 10. Risques & hypothèses
- **Risque principal** : Adoption du résumé IA (dépendance à la qualité du prompt et à la clé API fournie par l’utilisateur).
- **Hypothèse** : Les équipes ont besoin d’un outil simple et visuel ; une interface trop complexe freinerait l’adoption.
- **Risque technique** : Gestion du realtime (votes, ordre agenda) pendant le meeting → utiliser un websocket plus tard

---

## 11. Etat d’avancement technique actuel

Le socle technique et produit suivant est déjà en place :

- Authentification email unique login/create account.
- OTP alphanumérique 6 caractères + lien magique.
- Emails stockés localement en développement dans `var/storage/mails`.
- Tous les emails passent par un template HTML standard.
- Couche Security Symfony active.
- Code PIN à 4 chiffres demandé après authentification email.
- Déverrouillage PIN requis pour accéder à l’application.
- Commande CLI `toastit:user:root` pour promouvoir un utilisateur ROOT.
- Web Debug Toolbar Symfony active en développement.
- Suite de tests unitaires et d’intégration exécutable via `make test`.

Sur le métier, un premier noyau est déjà implémenté :

- Création d’équipe.
- Invitation de membres dans une équipe.
- Création de meeting d’équipe.
- Création de meeting ad-hoc personnel.
- Invitation de participants à un meeting.
- Meetings affichés dans le dashboard sous forme de liste groupée par équipe.
- Meetings ad-hoc affichés aussi quand l’utilisateur en est organisateur ou invité.
- Page meeting dédiée.
- Ajout de parking lot items depuis la page d’un meeting uniquement.
- Votes sur les parking lot items en AJAX.
- Copie et transfert d’un parking lot item vers un autre meeting.
- Suppression d’un parking lot item par son auteur.
- Profil utilisateur avec prénom, nom, display name, initiales et gravatar.

## 12. Règles supplémentaires à conserver pour la suite

### Architecture

- Symfony reste le centre de gravité métier.
- Twig reste le mode de rendu principal.
- Alpine.js reste limité aux interactions légères.
- Les écrans applicatifs doivent rester server-first, pas SPA.

### UI / Design System

- Les pages doivent utiliser les composants du design system existant.
- Les icônes doivent toutes passer par le composant d’icône unique.
- Les nouveaux patterns visuels doivent être ajoutés à la page design system avant d’être dupliqués dans l’app.
- Les pages `/app` doivent rester dans une shell applicative stable :
  - top bar sticky
  - navigation principale
  - menu user
  - contenu centré dans un container responsive

### Mails

- En développement, aucun vrai mail ne doit partir.
- Tous les mails doivent être stockés localement dans `var/storage/mails`.
- Tous les mails doivent utiliser une base HTML commune.

### Sécurité

- Le login passe uniquement par email OTP / lien magique.
- Le PIN reste une seconde couche de déverrouillage et non un login primaire.
- Le rôle `ROLE_ROOT` est réservé aux privilèges d’administration futurs.

### Tests

- Toute fonctionnalité métier nouvelle doit être couverte au minimum par :
  - un test unitaire si de la logique pure est introduite
  - un test d’intégration si un flux HTTP / sécurité / persistence est concerné
- La référence unique pour exécuter la suite est `make test`.

## 13. Clarifications et écarts par rapport au brief initial

Les points suivants ont été précisés ou ajustés lors de l’implémentation :

### Authentification

- Le brief initial ne détaillait pas la couche d’authentification ; elle a été formalisée en :
  - email unique
  - OTP de secours
  - lien magique
  - PIN 4 chiffres

### Parking lot

- Le brief initial pouvait laisser penser à un parking lot plus global à l’équipe.
- La règle désormais actée est :
  - un parking lot item appartient toujours à un meeting
  - l’ajout se fait depuis la page d’un meeting
- Un item peut cependant être :
  - transféré vers un autre meeting
  - copié vers un autre meeting

### Dashboard

- Le tableau de bord a été cadré comme une vue groupée par équipe, avec les meetings affichés sous chaque équipe.
- Une seconde section liste les meetings ad-hoc.
- Ce choix sert mieux le flux de navigation réel actuel :
  - équipe
  - meeting
  - parking lot du meeting

### Meetings ad-hoc

- Les meetings ne sont plus forcément rattachés à une équipe.
- Un meeting peut être :
  - un meeting d’équipe
  - un meeting ad-hoc personnel
- Les meetings ad-hoc sont visibles si l’utilisateur en est :
  - organisateur
  - ou invité

### Invitations

- Les invitations d’équipe sont gérées via l’ajout de membres par email.
- Les invitations de meeting sont gérées séparément.
- Par défaut, les meetings d’équipe restent visibles via l’appartenance à l’équipe.
- Les meetings ad-hoc nécessitent un organisateur ou une invitation explicite.

### Identité utilisateur

- L’utilisateur peut maintenant définir :
  - prénom
  - nom
- L’affichage standard d’un utilisateur doit privilégier :
  - gravatar si disponible
  - sinon initiales prénom/nom
  - sinon fallback email

## 14. Next

Prochaines étapes produit recommandées :

1. Implémenter `boost` et `veto` par l’organisateur.
2. Construire l’agenda automatique d’un meeting :
   - boosts en tête
   - puis tri par votes
   - veto exclus
3. Ajouter une vraie page de meeting live avec :
   - statut traité / reporté
   - décision prise
   - responsable
   - date d’échéance
4. Introduire le carry-over intelligent entre meetings récurrents.
5. Préparer la couche résumé IA Grok sur les données de meeting clôturé.

Prochaines étapes techniques recommandées :

1. Ajouter des tests d’intégration sur :
   - invitations d’équipe
   - invitations de meeting
   - visibilité des meetings ad-hoc invités
2. Centraliser davantage les patterns UI dans des composants Twig dédiés :
   - dropdown d’actions
   - avatar + tooltip
   - table groupée
3. Ajouter des badges visuels explicites dans le dashboard :
   - Team
   - Ad-hoc
   - Organizer
   - Invited

### Votes

- Le brief ne spécifiait pas l’interaction technique de vote.
- Les votes sont maintenant gérés en AJAX, avec fallback serveur possible.

### Récurrence

- La récurrence a été structurée en UI avec :
  - une quantité
  - une unité
- Les unités actuellement disponibles sont :
  - jours
  - semaine
  - deux semaines
  - mois
  - deux mois
  - trimestre
  - semestre

### Résumé IA

- Le résumé IA Grok reste hors implémentation à ce stade.
- Le prompt de référence du brief est conservé comme base pour la phase suivante.
