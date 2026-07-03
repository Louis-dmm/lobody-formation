# Formation Lobody Perfect

Application web de gestion de formations, développée pendant mon stage chez **KPI CREATION**.

La plateforme sert deux publics. Côté stagiaire, chacun gère son compte et dépose les documents demandés. Côté administration, un back-office permet de valider ces documents, de gérer les utilisateurs et de suivre les inscriptions.

Ce dépôt est la version publique du projet. J'en ai retiré tous les identifiants sensibles (base de données, e-mail, clés) pour pouvoir le partager. Le code métier, lui, est celui qui tourne en production.

![PHP](https://img.shields.io/badge/PHP-PDO-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)

## Contexte du projet

Réalisé lors de mon stage chez KPI CREATION, une agence web, pour un organisme de formation client. J'ai travaillé sur l'ensemble de l'application : l'authentification, l'espace utilisateur, le back-office d'administration et l'envoi des e-mails automatiques. Le site a été mis en ligne et utilisé en conditions réelles.

## Ce que fait l'application

- Connexion sécurisée, création de compte et réinitialisation de mot de passe par e-mail
- Protection contre les robots avec Cloudflare Turnstile sur les formulaires sensibles
- Espace personnel où l'utilisateur consulte son profil et dépose ses documents
- Validation des documents par un administrateur, avec une validation automatique passé un certain délai
- Back-office pour gérer les utilisateurs, les formations et les modèles d'e-mails
- E-mails transactionnels envoyés via PHPMailer et le service SMTP de Brevo
- Une API interne (`api_reception.php`) qui reçoit des inscriptions depuis un autre service, filtrée par IP et par clé

## Choix techniques

| Élément | Technologie |
|---|---|
| Langage | PHP, requêtes préparées via PDO |
| Base de données | MySQL |
| E-mails | PHPMailer + Brevo |
| Anti-robot | Cloudflare Turnstile |
| Front | HTML et CSS, sans framework |

Les mots de passe utilisateurs sont hachés avec `password_hash`, et toutes les requêtes SQL passent par des requêtes préparées pour éviter les injections.

## Sécurité et gestion des secrets

Aucun identifiant n'est écrit en dur dans le code. Les valeurs sensibles (accès base de données, SMTP, clés) vivent dans un fichier `.env` qui n'est pas versionné. Un petit chargeur maison (`config/env.php`) les lit au démarrage, sans dépendance externe.

Le fichier `.gitignore` exclut ce `.env`, et le dépôt a été reconstruit avec un historique propre pour qu'aucun secret n'apparaisse dans les anciens commits.

## Organisation du code

```
├── index.html              Page d'accueil et connexion
├── config/                 Connexion à la base et chargement du .env
├── auth/                   login, logout, mot de passe oublié et réinitialisation
├── user/                   Espace utilisateur (profil, paramètres)
├── admin/                  Back-office (gestion, édition, suppression)
├── api_reception.php       Réception d'inscriptions externes
├── includes/PHPMailer/     Librairie d'envoi d'e-mails
├── database.sql            Structure de la base + données d'exemple
└── assets/                 CSS, polices et images
```

## Installation en local, étape par étape

Le projet tourne entièrement sur ton poste. Il ne dépend d'aucun serveur externe : tu héberges la base et le site chez toi.

### Prérequis

- PHP 7.4 ou plus récent
- MySQL ou MariaDB
- Un serveur web local suffit largement : XAMPP, WAMP ou Laragon regroupent PHP et MySQL en une installation. Le serveur intégré de PHP marche aussi.

### Étape 1 : récupérer le code

```bash
git clone https://github.com/Louis-dmm/lobody-formation.git
cd lobody-formation
```

### Étape 2 : créer la base de données

Le fichier `database.sql` crée la base `formation`, ses tables et un jeu de données d'exemple (formations, lieux, modèles d'e-mails). Il ne contient aucune donnée personnelle.

En ligne de commande :

```bash
mysql -u root -p < database.sql
```

Depuis phpMyAdmin : ouvre l'onglet **Importer**, choisis `database.sql`, puis lance.

Un compte administrateur de démonstration est déjà présent :

- Adresse : `admin@example.com`
- Mot de passe : `admin123`

### Étape 3 : créer le fichier `.env`

À la racine du projet, crée un fichier nommé `.env` et colle ceci :

```env
# Adresse du site en local
APP_URL=http://localhost:8000

# Base de données locale
DB_HOST=localhost
DB_NAME=formation
DB_USER=root
DB_PASSWORD=root

# E-mail expéditeur
MAIL_FROM=contact@example.com

# SMTP (facultatif, pour l'envoi réel d'e-mails)
SMTP_HOST=smtp-relay.brevo.com
SMTP_USERNAME=
SMTP_PASSWORD=

# Cloudflare Turnstile (facultatif)
TURNSTILE_SECRET=

# API de réception
API_KEY=une-cle-de-ton-choix
API_ALLOWED_IP=
```

Adapte `DB_USER` et `DB_PASSWORD` aux identifiants MySQL de ton poste. Sur XAMPP, c'est souvent `root` sans mot de passe (laisse alors `DB_PASSWORD` vide).

### Étape 4 : lancer le site

Depuis le dossier du projet :

```bash
php -S localhost:8000
```

Ouvre ensuite <http://localhost:8000>. Connexion à l'espace admin via `admin@example.com` / `admin123`.

### Ce qui marche sans configuration supplémentaire

Le site, la connexion, l'espace utilisateur et le back-office fonctionnent avec la base seule. Les parties facultatives dépendent de services externes que tu peux brancher plus tard :

- L'envoi d'e-mails (invitation, mot de passe oublié) a besoin d'un compte SMTP dans `SMTP_USERNAME` et `SMTP_PASSWORD`. Sans ça, le reste du site tourne quand même.
- Le champ anti-robot Turnstile n'est actif que si tu renseignes une clé. En local, tu peux le laisser vide.
- `APP_URL` sert à construire les liens contenus dans les e-mails. En local, `http://localhost:8000` convient.

### Adapter à un autre hébergement

Le code ne contient plus aucune adresse en dur. Pour déployer ailleurs, il suffit de changer les valeurs du `.env` : `APP_URL` pour la nouvelle adresse, les accès `DB_*` pour la nouvelle base, et `MAIL_FROM` pour l'expéditeur. Le fichier `.env` n'est jamais versionné : sur un serveur, tu le déposes à la main à la racine.

## À propos

Projet développé pendant un stage chez KPI CREATION. Le code est publié à titre de démonstration de mon travail. Les droits appartiennent à KPI CREATION et à son client.
