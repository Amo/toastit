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

## 15. Cible V1 désormais cadrée

La V1 ne doit pas essayer de livrer tout le brief historique en une seule itération.

La cible réaliste et cohérente avec le socle actuel est la suivante :

1. Priorisation organisateur sur les sujets d’un meeting :
   - boost
   - veto
   - ordre d’agenda calculé côté serveur
2. Passage du meeting d’une simple page de préparation à une page de conduite :
   - sujet en cours
   - sujet traité ou reporté
   - décision capturée
   - responsable
   - échéance
3. Récurrence utile :
   - report automatique des sujets non traités
   - perte du boost en fin de meeting
   - exclusion des veto du carry-over
4. Préparation de la couche email de clôture :
   - résumé standard sans IA possible en premier
   - résumé Grok branchable ensuite sans refonte du flux

En pratique, la V1 doit d’abord réussir le cycle complet suivant :

1. Un meeting est créé.
2. Les participants ajoutent et votent sur les sujets.
3. L’organisateur ordonne réellement l’agenda avec boost et veto.
4. Le meeting est animé dans l’application.
5. Les décisions et suites sont conservées.
6. Le meeting suivant repart sur une base propre mais utile.

## 16. Modèle métier recommandé pour la suite immédiate

Pour rester compatible avec le code actuel et limiter le coût de migration, la suite du MVP doit prolonger les entités existantes au lieu d’introduire trop tôt un modèle plus complexe.

### Meeting

Le meeting doit gagner un vrai cycle de vie :

- `scheduled` : meeting préparé mais non démarré
- `live` : meeting en cours
- `closed` : meeting terminé

Il doit aussi pouvoir stocker :

- une date de démarrage réelle
- une date de clôture réelle
- une date théorique de prochain meeting si récurrent
- un indicateur `summary_sent_at` pour éviter les doubles envois

### ParkingLotItem

Le parking lot item doit devenir l’unité de travail de V1.

Au-delà du titre, de la description, du statut et des votes déjà présents, il devra porter :

- `priority_mode` : `normal`, `boosted`, `vetoed`, `carry_over`
- `boost_rank` : entier nullable pour ordonner plusieurs boosts
- `vetoed_by` et `vetoed_at`
- `agenda_position_snapshot` calculé à la fermeture de l’ordre
- `meeting_outcome` : `treated`, `postponed`, `dropped`
- `decision_summary`
- `owner_user`
- `due_at`
- `carried_from_item`

Pour la V1, il est préférable de garder ces champs sur `ParkingLotItem` plutôt que de créer tout de suite une entité d’actions séparée.
La séparation en `ActionItem` pourra venir plus tard si un sujet doit produire plusieurs actions.

## 17. Règles de gestion à figer avant implémentation

Les règles suivantes doivent être considérées comme normatives pour éviter les ambiguïtés produit.

### Boost

- Seul l’organisateur peut booster.
- Un item boosté remonte avant tous les items normaux.
- Plusieurs boosts sont possibles et ordonnés explicitement.
- Un boost n’ajoute pas de votes artificiels ; il remplace seulement l’ordre naturel.
- Le boost est valable uniquement pour le meeting courant.

### Veto

- Seul l’organisateur peut veto.
- Un veto retire le sujet de l’agenda actif.
- Le sujet reste visible dans l’interface avec la mention du veto.
- Un item vetoé ne doit pas être carry-over automatiquement.

### Votes

- Le vote reste limité à 1 vote par utilisateur et par item.
- Le vote reste modifiable tant que le meeting n’est pas clôturé.
- Le vote doit continuer à fonctionner sans JavaScript via fallback HTTP.

### Clôture d’un meeting

- Un item traité reste dans l’historique du meeting clôturé.
- Un item reporté peut être recopié dans le prochain meeting récurrent.
- Un item supprimé manuellement n’est jamais carry-over.
- Un item boosté perd son boost après clôture.

## 18. Découpage de livraison recommandé

### Lot 1. Agenda et pouvoir organisateur

- Ajouter boost et veto sur `ParkingLotItem`.
- Calculer l’ordre d’agenda côté serveur.
- Afficher les badges et états dans la page meeting.
- Ajouter les tests d’intégration correspondants.

Critère de sortie :
Un organisateur peut transformer une liste de sujets votés en agenda exploitable sans sortir de l’écran du meeting.

### Lot 2. Meeting live

- Ajouter le statut du meeting (`scheduled`, `live`, `closed`).
- Ajouter pour chaque item :
  - traité / reporté
  - décision
  - responsable
  - échéance
- Ajouter une vue de conduite de réunion cohérente avec le design system existant.

Critère de sortie :
Le meeting peut être mené du début à la fin dans Toastit sans prise de notes externe obligatoire.

### Lot 3. Carry-over récurrent

- Générer le prochain meeting à partir d’un meeting récurrent clôturé ou rattacher les reports au meeting suivant existant.
- Reporter les items non traités avec continuité des votes.
- Mettre en priorité haute les sujets proches de l’échéance ou déjà en retard.

Critère de sortie :
La récurrence apporte un vrai bénéfice de continuité sans créer de doublons ou de comportements opaques.

### Lot 4. Résumé de clôture

- Générer un résumé structuré à partir des données métier déjà persistées.
- Envoyer un email de clôture à tous les participants.
- Brancher ensuite Grok comme enrichissement optionnel et non comme dépendance bloquante.

Critère de sortie :
Chaque meeting clôturé produit un compte-rendu exploitable, même sans IA configurée.

## 19. Critères d’acceptation produit pour la prochaine itération

La prochaine itération pourra être considérée comme réussie si les conditions suivantes sont vraies :

1. Sur un meeting d’équipe, un organisateur peut booster et veto un sujet.
2. L’ordre affiché à l’écran correspond exactement aux règles produit :
   - boosts d’abord
   - puis votes décroissants
   - veto exclus
3. Un participant non organisateur ne peut ni booster ni veto.
4. Les sujets restent visibles avec des badges compréhensibles et cohérents avec le design system.
5. Les scénarios essentiels sont couverts par des tests d’intégration :
   - accès meeting équipe
   - accès meeting ad-hoc invité
   - invitation équipe
   - invitation meeting
   - vote
   - boost
   - veto

## 20. Questions encore ouvertes

Ces points doivent être tranchés avant la couche carry-over et résumé IA :

1. Le prochain meeting récurrent est-il créé automatiquement à la clôture ou à la création initiale de la série ?
2. Un item reporté conserve-t-il strictement son identité ou devient-il une copie liée au précédent ?
3. La priorité haute due à une échéance proche doit-elle rester visuelle ou influer réellement sur l’ordre serveur ?
4. Le résumé de clôture doit-il partir automatiquement à la fermeture ou après validation explicite de l’organisateur ?
5. Les invités ad-hoc peuvent-ils voter et commenter au même niveau que les membres d’équipe, ou faut-il une restriction produit explicite ?

## 21. Spécification fonctionnelle détaillée du Lot 1

Le Lot 1 doit rester centré sur une seule promesse :
permettre à l’organisateur de transformer le parking lot d’un meeting en agenda priorisé et explicite.

### Vue utilisateur attendue

Sur la page d’un meeting, chaque item doit afficher de manière visible :

- son titre
- sa description courte éventuelle
- son nombre de votes
- son auteur
- son état de priorité
- les actions disponibles pour l’utilisateur courant

Les états de priorité visibles en V1 sont :

- normal
- boosté
- vetoé

### Actions disponibles par rôle

#### Organisateur

L’organisateur peut :

- ajouter un item
- voter ou retirer son vote
- inviter un participant au meeting
- booster un item
- retirer un boost
- veto un item
- lever un veto
- réordonner les boosts si plusieurs items sont boostés

#### Participant standard

Un participant standard peut :

- ajouter un item
- voter ou retirer son vote
- consulter l’ordre courant de l’agenda

Un participant standard ne peut pas :

- booster
- veto
- réordonner les boosts

#### Utilisateur hors périmètre du meeting

Un utilisateur hors périmètre du meeting :

- ne voit pas le meeting
- ne voit pas les items
- ne peut pas appeler les actions HTTP associées

## 22. Comportement attendu de l’ordre d’agenda

L’ordre d’agenda doit être calculé côté serveur à partir des données persistées et non dépendre du navigateur.

### Règle de tri normative

L’ordre doit être :

1. tous les items boostés, triés par `boost_rank` croissant
2. tous les items normaux, triés par nombre de votes décroissant
3. en cas d’égalité de votes :
   - d’abord le plus ancien `created_at`
   - puis l’ID croissant comme dernier tie-breaker stable
4. tous les items vetoés sont exclus de la liste d’agenda active

### Conséquences UI

- La page meeting doit pouvoir afficher à la fois :
  - l’agenda actif ordonné
  - les items vetoés dans une zone séparée ou clairement identifiée
- L’utilisateur ne doit jamais avoir l’impression qu’un item a disparu silencieusement.

## 23. Flux utilisateur cible du Lot 1

### Flux A. Préparation normale d’un meeting

1. L’organisateur ouvre le meeting.
2. Les membres ajoutent des sujets.
3. Les membres votent.
4. L’organisateur applique éventuellement un ou plusieurs boosts.
5. L’organisateur veto les sujets hors scope.
6. L’écran affiche l’ordre final de passage.

Résultat attendu :
avant même de démarrer le meeting live, l’équipe a déjà un agenda lisible et partagé.

### Flux B. Ajustement de dernière minute

1. Un sujet reçoit plusieurs votes tardivement.
2. L’organisateur garde cet ordre naturel ou le surclasse avec un boost.
3. Un sujet devenu obsolète est vetoé.

Résultat attendu :
l’écran réagit immédiatement et l’ordre affiché reste la référence unique.

### Flux C. Consultation par un invité ad-hoc

1. Un utilisateur invité à un meeting ad-hoc ouvre le lien interne.
2. Il voit uniquement le meeting auquel il a accès.
3. Il peut voter et contribuer selon les règles produit retenues.
4. Il ne peut pas agir sur les pouvoirs de l’organisateur.

Résultat attendu :
le modèle d’accès est cohérent entre lecture, rendu page et endpoints d’action.

## 24. Endpoints et sécurité à prévoir

Sans figer encore le naming final, la prochaine itération va probablement nécessiter des endpoints dédiés pour :

- booster un item
- retirer un boost
- veto un item
- lever un veto
- réordonner les boosts

Contraintes :

- tous les endpoints doivent vérifier l’accès au meeting avant toute mutation
- tous les endpoints organisateur doivent vérifier explicitement que l’utilisateur courant est l’organisateur du meeting
- les réponses doivent supporter :
  - fallback HTTP classique
  - JSON pour les interactions progressives

Le comportement attendu doit rester cohérent avec le vote AJAX déjà présent dans l’application.

## 25. Cas limites à traiter explicitement

Les cas suivants doivent être prévus dans la logique et dans les tests :

1. Un organisateur booste un item déjà boosté.
   Résultat attendu : pas de duplication, simple mise à jour éventuelle.
2. Un organisateur veto un item déjà vetoé.
   Résultat attendu : opération idempotente.
3. Un participant tente un POST direct sur un endpoint de boost ou veto.
   Résultat attendu : refus d’accès.
4. Un item vetoé continue de recevoir des votes.
   Résultat attendu : à décider, mais la règle doit être explicite.
5. Un item déplacé ou copié vers un autre meeting possède déjà un état de priorité.
   Résultat attendu : à cadrer pour éviter de transporter des boosts ou veto inattendus.
6. Deux items ont le même nombre de votes.
   Résultat attendu : ordre stable et prévisible.

## 26. Position recommandée sur les points encore flous

Pour avancer sans bloquer la prochaine implémentation, la recommandation la plus simple est :

1. Un item vetoé reste votable, mais ses votes n’influencent plus l’agenda tant que le veto est actif.
2. Une copie d’item vers un autre meeting repart en état `normal`.
3. Un déplacement d’item vers un autre meeting repart lui aussi en état `normal`.
4. Les invités ad-hoc ont les mêmes droits de contribution qu’un participant standard :
   - voir
   - ajouter un item
   - voter
5. Le résumé de clôture sera déclenché après action explicite de l’organisateur dans un second temps, pas automatiquement en Lot 1.

Cette position minimise les surprises produit et simplifie fortement la logique métier initiale.

## 27. Critères de test recommandés pour le Lot 1

### Intégration HTTP

- un organisateur d’équipe peut booster un item d’un meeting de son équipe
- un organisateur d’équipe peut veto un item d’un meeting de son équipe
- un membre d’équipe non organisateur ne peut pas booster
- un membre d’équipe non organisateur ne peut pas veto
- un invité de meeting ad-hoc non organisateur ne peut pas booster
- un item boosté apparaît avant un item plus voté mais non boosté
- un item vetoé n’apparaît plus dans l’agenda actif

### Unitaires

Si le calcul d’ordre est extrait dans un service dédié, il doit être testé unitairement sur au moins :

- tri par boost
- tri par votes
- tie-break stable
- exclusion des veto

## 28. Décision de cadrage recommandée

Si aucune autre décision produit n’est prise immédiatement, la prochaine implémentation doit prendre comme périmètre officiel :

1. boost
2. veto
3. ordre d’agenda serveur
4. badges et états UI
5. tests de permissions et de tri

Le meeting live complet, le carry-over et le résumé doivent rester dans les lots suivants pour éviter de mélanger trop de changements métier dans une même tranche.
