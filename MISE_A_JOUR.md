# Mise à jour : sécurité, comptabilité et reçus PDF

## SMS : DexNotify (20/11)

Tous les SMS (code de vérification, code de retrait, confirmations) passent désormais par **DexNotify**
(`POST /v1/sms/messages`, en-tête `x-api-key`). **L'ancienne API Dexchange reste en secours** : si DexNotify
échoue (panne, délai dépassé, clé refusée), le même SMS part automatiquement par Dexchange.

```
SMS_DRIVERS=dexnotify,dexchange        # ordre d'essai ; "dexnotify" seul pour désactiver le secours
DEXNOTIFY_URL=https://api.dexnotify.com/api
DEXNOTIFY_API_KEY=dxn_...              # clé staging = envois simulés, aucun débit
DEXNOTIFY_SENDER=DEXCHANGE
```

- Chaque SMS porte un **Idempotency-Key** (`mt-otp-…`, `mt-wcode-…`, `mt-confirm-…`, `mt-paid-…`, `mt-cancel-…`) :
  si un envoi est rejoué, DexNotify ne l'envoie pas deux fois. Un nouveau code de retrait reçoit une nouvelle clé.
- Le contenu des SMS n'est jamais écrit dans les logs : on n'y trouve que le prestataire, le numéro et l'identifiant du message.
- **OTP** : nous gardons notre propre code de vérification (chiffré, 10 min, 5 essais) envoyé en SMS simple.
  L'endpoint `/v1/sms/otp` de DexNotify n'est pas utilisé, car la documentation fournie n'indique pas comment vérifier le code.
- **WhatsApp (facultatif)** : une copie du code de retrait peut être envoyée par WhatsApp. Il faut d'abord créer et faire valider
  un modèle Meta avec 4 variables (montant, expéditeur, référence, code), puis renseigner `DEXNOTIFY_WHATSAPP_ACCOUNT_ID`
  et `DEXNOTIFY_WHATSAPP_WITHDRAWAL_TEMPLATE`. Sans ces valeurs, rien n'est envoyé sur WhatsApp.
- Pour ajouter un autre prestataire plus tard : une classe dans `app/Services/Sms/` et son nom dans `SMS_DRIVERS`.

## Lot 2 (19/11) : application client, pré-envois, Docker dans `backend/`

### Ce qui change

- **L'app mobile est désormais destinée aux clients.** Les agents travaillent sur le back-office web.
- Le client s'inscrit avec son numéro (code SMS), choisit un **code PIN à 4 chiffres** et se connecte ensuite avec ce PIN.
  Après 5 PIN faux, le compte est bloqué 15 minutes. « Code PIN oublié » renvoie un code SMS.
- Le client **prépare son envoi** dans l'app et reçoit un **code de paiement** à 6 caractères, valable 48 h.
  Il paie en espèces chez un agent, qui saisit le code dans le back-office (Transactions → « Pré-envoi client »).
  C'est à ce moment que l'argent bouge et que le bénéficiaire reçoit son code de retrait par SMS.
- Le client suit le statut de ses envois (à payer, envoyé, retiré, annulé, expiré), télécharge ses reçus PDF,
  réutilise ses bénéficiaires récents et peut annuler un envoi tant qu'il ne l'a pas payé.
- Un client déjà servi au guichet qui s'inscrit avec le même numéro retrouve son historique.
- Les jetons client et agent sont séparés : un jeton client est refusé (403) sur les routes agents, et inversement.
- **Correction d'un bug présent depuis le début du projet** : les routes JSON du back-office web (`/api/...` dans `web.php`)
  écrasaient celles de l'API mobile, et un appel avec jeton Bearer recevait 401. Elles sont maintenant sous `/bo-api/...`.

### À faire

```bash
cd backend
composer require barryvdh/laravel-dompdf:^2.0   # manque encore dans composer.json : sans lui, les reçus plantent
php artisan migrate                              # 3 nouvelles migrations
php artisan test                                 # 26 tests doivent passer

cd ../mobile
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

Pour tester sans SMS, ajoutez `CLIENT_OTP_DEBUG=true` dans `.env` : le code s'affiche dans l'app.
**Ne jamais l'activer en production.**

### Docker

La configuration est maintenant dans `backend/` (le Docker ne concerne que le backend) :

```bash
cd backend
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
```

- Le back-office est sur http://localhost:8000. Un conteneur `scheduler` lance le planificateur Laravel.
- Le port 9000 (php-fpm) n'est plus exposé. La base MySQL du compose, que le projet n'utilisait pas
  (la base est PostgreSQL sur la machine hôte), ne démarre qu'avec `docker compose --profile mysql up -d`.

### API client (`/api/client/...`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/countries` | pays |
| POST | `/check` | ce numéro a-t-il un compte ? |
| POST | `/otp` | envoi du code SMS (`purpose` : register / reset_pin) |
| POST | `/register` · `/login` · `/pin/reset` | inscription, connexion, PIN oublié |
| GET/PATCH | `/me` · POST `/pin` · POST `/logout` | profil, changement de PIN, déconnexion |
| POST | `/quote` | frais d'un montant |
| GET | `/beneficiaries` | bénéficiaires récents |
| GET/POST | `/transfers` | mes envois / préparer un envoi |
| GET | `/transfers/{uuid}` · POST `/transfers/{uuid}/cancel` | détail, annulation |
| GET | `/transfers/{uuid}/receipt/{deposit\|withdrawal}` | reçus PDF |

Côté agent : `POST /api/preorders/lookup` et `POST /api/preorders/confirm` (`payment_code`, pièce d'identité facultative).

---

## Lot 1 : à faire une fois après récupération du code

### Backend (dossier `backend/`)

```bash
cd backend
composer require barryvdh/laravel-dompdf:^2.0 doctrine/dbal:^3.0   # PDF + modification de colonnes
php artisan migrate                                                # 6 nouvelles migrations
php artisan db:seed --class=TariffSeeder                           # seulement si la grille est vide
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan test                                                   # 15 tests doivent passer
```

À ajouter dans `.env` (voir `.env.example`) :

```
COMPANY_NAME="Nom de votre société"
COMPANY_ADDRESS="Adresse, Ville"
COMPANY_PHONE="+221 ..."
COMPANY_LEGAL="NINEA ... - RCCM ..."
SANCTUM_TOKEN_EXPIRATION=10080
CORS_ALLOWED_ORIGINS=https://votre-domaine
TRANSFER_SHOW_CODE_TO_AGENT=false
```

En production : `APP_DEBUG=false` et `APP_ENV=production`.

Planificateur (expiration et remboursement des envois non retirés), à mettre dans la crontab du serveur :

```
* * * * * cd /chemin/backend && php artisan schedule:run >> /dev/null 2>&1
```

### ⚠️ Clé Dexchange exposée

`backend/.env.mysql` et `backend/.env.postgres` sont suivis par git et contiennent la clé API Dexchange
(et un mot de passe de base de données). Il faut :

1. **régénérer la clé** dans votre espace Dexchange, puis la mettre uniquement dans `.env` ;
2. retirer ces fichiers de git : `git rm --cached backend/.env.mysql backend/.env.postgres`
   (ils sont maintenant dans `.gitignore`).

### Mobile (dossier `mobile/`)

```bash
cd mobile
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api   # émulateur Android + backend local
```

Sans `--dart-define`, l'app utilise `https://findsolution.site/money_transfer_app/api`.

## Ce qui change pour les utilisateurs

| Avant | Maintenant |
|---|---|
| Le code de retrait (6 caractères) était affiché à l'agent expéditeur et stocké en clair | Code à 8 chiffres envoyé **uniquement par SMS au bénéficiaire**, stocké haché, valable 30 jours, à usage unique |
| `reference` = code secret | `reference` = numéro de suivi public (ex. `MT261021-K7Q2PX`), imprimé sur les reçus |
| Retrait avec le code seul | Retrait en 2 étapes : vérification du code, puis **pièce d'identité du bénéficiaire obligatoire** |
| N'importe qui pouvait créer un compte via `/api/register` | Les agents sont créés par l'admin : `POST /api/admin/agents` |
| Pas de moyen d'alimenter le solde d'un agent | `POST /api/admin/agents/{id}/topup` (admin) |
| Frais débités puis perdus | Frais crédités sur un compte de commissions (portefeuille `revenue` par devise) |
| Pas d'annulation | Annulation et remboursement (agent expéditeur ou admin), expiration automatique |
| Pas de reçu | **Reçu d'envoi** et **reçu de retrait** en PDF, 2 exemplaires (client et agence), DUPLICATA dès la 2e impression |

Les anciens envois « en attente » restent retirables avec leur ancien code : la migration conserve son empreinte.

## Reçus PDF

- `GET /api/transactions/{uuid}/receipt/deposit` : reçu d'envoi, à remettre à l'expéditeur
- `GET /api/transactions/{uuid}/receipt/withdrawal` : reçu de retrait, à faire signer par le bénéficiaire (disponible après paiement)
- Back-office web : même adresse sans `/api`, avec des liens dans le tableau de bord et après chaque opération
- Ils sont accessibles à l'agent expéditeur, à l'agent payeur et aux admins. Chaque impression est enregistrée dans `audit_logs`.
- Chaque reçu porte une **empreinte de contrôle** (HMAC du montant, de la référence et de la date). Elle permet de repérer un reçu falsifié.

## Nouvelles routes API (mobile)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/countries` | pays (destination) |
| GET | `/api/transactions/{uuid}` | détail |
| POST | `/api/transactions/{uuid}/cancel` | annuler et rembourser (`reason`) |
| POST | `/api/transactions/{uuid}/resend-code` | nouveau code au bénéficiaire (3 par heure) |
| POST | `/api/withdraw` | désormais `code` + `recipient_id_type` + `recipient_id_number` |
| GET/POST/PATCH | `/api/admin/agents…` | gestion des agents (admin) |

`POST /api/transfer` attend désormais : `client_name`, `client_phone`, `client_country_id?`,
`sender_id_type?`, `sender_id_number?`, `amount`, `destination_country_id`, `recipient_name`, `recipient_phone`.

## Limites connues (prochaines étapes)

- Pas de conversion de devises : un transfert en XOF ne peut être payé que par un agent dont le portefeuille est en XOF.
- Les commissions vont entièrement à la société : il n'y a pas de partage avec les agents.
- Pas d'écran d'administration web pour les agents et les rechargements (l'API est prête).
