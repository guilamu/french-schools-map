# French Schools Map

[![Latest Release](https://img.shields.io/github/v/release/guilamu/french-schools-map?color=blue)](https://github.com/guilamu/french-schools-map/releases) [![License: AGPL-3.0](https://img.shields.io/badge/license-AGPL--3.0-green.svg)](LICENSE) [![WordPress: 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org) [![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)

Carte interactive des établissements scolaires français, alimentée par les données open data du Ministère de l'Éducation Nationale.

## Description

Ce plugin affiche une carte interactive des ~69 000 établissements scolaires français (écoles, collèges, lycées) directement dans vos pages ou articles WordPress. Les données proviennent de l'[Annuaire de l'Éducation Nationale](https://data.education.gouv.fr/explore/dataset/fr-en-annuaire-education/) et sont synchronisées automatiquement chaque mois.

### Fonctionnalités

- 🗺️ **Carte interactive** OpenStreetMap avec Leaflet.js (aucune clé API requise)
- 📍 **~69 000 établissements** géolocalisés sur la carte
- 🔍 **Filtres dynamiques** : type (école/collège/lycée), statut (public/privé), département, éducation prioritaire
- 🔎 **Recherche textuelle** par nom d'établissement ou ville
- 📊 **Clustering intelligent** pour des performances optimales (Leaflet.markercluster)
- 🎨 **Marqueurs colorés** par type d'établissement
- 📱 **Adaptatif** : adapté aux mobiles et aux ordinateurs de bureau
- 🌐 **Académies & Départements** : filtres géographiques avec zoom automatique sur la zone sélectionnée
- 📋 **Popup détaillée** : nom, adresse, téléphone, email, statut REP/REP+, itinéraire (chargement automatique pour < 1 500 établissements)
- 📦 **Shortcode** et **bloc Gutenberg** disponibles
- 🔄 **Synchronisation mensuelle** automatique via WP-Cron
- 📍 **Géolocalisation** : bouton "Me localiser"

## Fonctionnalités principales

- **Données ouvertes :** Données officielles du Ministère de l'Éducation Nationale (~69 000 établissements)
- **Aucune clé API :** OpenStreetMap/Leaflet.js par défaut — aucune clé API requise (une clé CARTO gratuite est possible en option)
- **Multilingue :** Fonctionne avec tout contenu linguistique
- **Prêt pour la traduction :** Toutes les chaînes sont internationalisées
- **Sécurisé :** Requêtes REST authentifiées par nonce WordPress, données sanitisées
- **Mises à jour GitHub :** Mises à jour automatiques depuis les releases GitHub

## Prérequis

- WordPress 5.8 ou version ultérieure
- PHP 7.4 ou version ultérieure

## Installation

1. Téléchargez le dossier `french-schools-map`
2. Uploadez-le dans `/wp-content/plugins/`
3. Activez le plugin via le menu **Extensions**
4. Rendez-vous dans **Réglages → French Schools Map** pour lancer la première synchronisation
5. Ajoutez le shortcode `[french_schools_map]` dans une page ou utilisez le bloc Gutenberg

## FAQ

### Aucune école ne s'affiche sur la carte ?
Vérifiez que la synchronisation a bien été effectuée : allez dans **Réglages → French Schools Map** et cliquez sur "Synchroniser maintenant". La première synchronisation peut prendre quelques minutes.

### Le plugin nécessite-t-il une clé API ?
Non par défaut : tuiles OpenStreetMap et API Open Data, toutes deux gratuites et sans clé. Une clé CARTO (gratuite, sur <https://carto.com/basemaps/apikey/>) n'est requise que pour les fonds Voyager, Positron ou Dark Matter, qui affichent sinon un filigrane « API KEY REQUIRED ».

### `departement` et `academie` sont-ils cumulables ?
Non. Si les deux sont renseignés, `departement` est prioritaire. La valeur configurée dans les réglages globaux prend également le dessus sur les attributs du bloc Gutenberg pour éviter des affichages incohérents.

### Puis-je personnaliser les marqueurs ?
Oui, utilisez le filtre `fsm_marker_color` :
```php
add_filter( 'fsm_marker_color', function( $color, $type ) {
    return $color;
}, 10, 2 );
```

### Les données sont-elles à jour ?
Les données sont synchronisées automatiquement chaque mois depuis l'Annuaire de l'Éducation Nationale. Vous pouvez aussi forcer une synchronisation manuelle depuis la page de réglages.

## Structure du projet

```
.
├── french-schools-map.php             # Fichier principal de l’extension (shortcode, bloc, résolution du fond de carte)
├── uninstall.php                      # Nettoyage lors de la désinstallation
├── README.md
├── assets
│   ├── css
│   │   ├── fsm-admin.css              # Styles de la page de réglages de l’administration
│   │   └── fsm-map.css                # Styles de la carte côté visiteur
│   └── js
│       ├── fsm-admin.js               # Script de la page de réglages de l’administration
│       ├── fsm-block.js               # Script de l’éditeur de blocs Gutenberg
│       └── fsm-map.js                 # Logique de la carte côté visiteur (Leaflet)
├── includes
│   ├── class-fsm-academies.php        # Données des académies et correspondance avec les départements
│   ├── class-fsm-admin.php            # Page de réglages de l’administration
│   ├── class-fsm-local-db.php         # Synchronisation de la base locale et utilitaires REST
│   ├── class-fsm-rest-api.php         # Points de terminaison de l’API REST
│   ├── class-github-updater.php       # Mises à jour automatiques depuis GitHub
│   └── Parsedown.php                  # Analyseur Markdown pour la fenêtre « Voir les détails »
└── languages
    ├── french-schools-map-fr_FR.mo    # Traduction française (binaire)
    ├── french-schools-map-fr_FR.po    # Traduction française (source)
    └── french-schools-map.pot         # Modèle de traduction
```

## Utilisation

### Shortcode

```
[french_schools_map]
```

### Attributs disponibles

| Attribut | Défaut | Description |
|----------|--------|-------------|
| `height` | `600px` | Hauteur de la carte |
| `center_lat` | `46.603354` | Latitude du centre initial |
| `center_lng` | `1.888334` | Longitude du centre initial |
| `zoom` | `6` | Niveau de zoom initial (1-18) |
| `types` | `all` | Types à afficher : `Ecole`, `Collège`, `Lycée` (séparés par des virgules) |
| `departement` | `all` | Filtrer par département |
| `academie` | `all` | Filtrer par académie (ex: `Lyon`, `Versailles`) |
| `statut` | `all` | `Public`, `Privé` ou `all` |
| `education_prioritaire` | `all` | `REP`, `REP+` ou `all` |
| `show_filters` | `true` | Afficher le panneau de filtres |
| `show_search` | `true` | Afficher la barre de recherche |
| `show_filter_academie` | `true` | Afficher le filtre Académie |
| `show_filter_dept` | `true` | Afficher le filtre Département |
| `show_filter_statut` | `true` | Afficher le filtre Statut (Public/Privé) |
| `show_filter_types` | `true` | Afficher le filtre Types (École/Collège/Lycée) |
| `show_filter_ep` | `true` | Afficher le filtre Éducation prioritaire |
| `show_circo_zones` | `true` | Afficher les zones colorées par circonscription IEN |
| `cluster` | `true` | Activer le clustering des marqueurs |
| `max_zoom` | `18` | Zoom maximal |
| `tile_url` | _(vide)_ | URL personnalisée pour les tuiles cartographiques (prioritaire sur le fond de carte des réglages) |

> **Note :** `departement` et `academie` sont mutuellement exclusifs. Si les deux sont renseignés, `departement` est prioritaire.

### Exemples

Carte des collèges publics de Paris :
```
[french_schools_map types="Collège" statut="Public" departement="Paris" zoom="12" center_lat="48.8566" center_lng="2.3522"]
```

Carte des lycées sans filtres :
```
[french_schools_map types="Lycée" show_filters="false" height="400px"]
```

Carte de l'académie de Lyon :
```
[french_schools_map academie="Lyon" zoom="8" center_lat="45.764" center_lng="4.8357"]
```

Carte d'un département avec zones de circonscription :
```
[french_schools_map departement="Haute-Garonne" show_circo_zones="true"]
```

### Réglages par défaut

Dans **Réglages → French Schools Map**, vous pouvez définir un département ou une académie par défaut. Ces valeurs seront utilisées automatiquement si le shortcode ne précise rien.

### Fond de carte

Toujours dans **Réglages → French Schools Map**, section **Fond de carte** :

| Réglage | Rôle |
| --- | --- |
| Clé API CARTO | Laissée vide, la carte utilise OpenStreetMap. Renseignée, elle active les fonds CARTO. [Obtenir une clé gratuite](https://carto.com/basemaps/apikey/) |
| Style CARTO | Voyager, Positron (gris clair) ou Dark Matter, avec ou sans libellés. Utilisé uniquement si une clé est renseignée |

Ordre de priorité du fond de carte : attribut `tile_url` du shortcode > CARTO (si une clé est enregistrée) > OpenStreetMap.

La clé est transmise au navigateur des visiteurs à chaque requête de tuile, comme toute clé de fond de carte côté client : restreignez-la à votre domaine depuis votre compte CARTO.

### Bloc Gutenberg

Le bloc **French Schools Map** est disponible dans l'éditeur. Les mêmes paramètres sont configurables via l'inspecteur de bloc.

## Source de données

Les données proviennent du portail Open Data du Ministère de l'Éducation Nationale :

- **Jeu de données** : [Annuaire de l'éducation](https://data.education.gouv.fr/explore/dataset/fr-en-annuaire-education/)
- **API** : OpenDataSoft API v2.1
- **Fréquence** : Synchronisation mensuelle automatique
- **Contours communaux** : [geo.api.gouv.fr](https://geo.api.gouv.fr/) (pour les zones colorées par circonscription)

### Données affichées

| Champ | Description |
|-------|-------------|
| Nom | Nom de l'établissement |
| Type | École, Collège, Lycée |
| Nature | Maternelle, Élémentaire, etc. |
| Statut | Public / Privé |
| Adresse | Adresse postale complète |
| Téléphone | Numéro de contact |
| Email | Adresse email |
| Éducation prioritaire | REP, REP+ ou non |
| Circonscription | Circonscription IEN (1er degré) |

## Administration

La page **Réglages → French Schools Map** permet de :

- Voir le statut de la synchronisation (dernière date, nombre d'enregistrements)
- Lancer une synchronisation manuelle
- Définir le département ou l'académie affichés par défaut

## API REST

Le plugin expose des endpoints REST pour les développeurs :

| Point de terminaison | Description |
|----------|-------------|
| `GET /wp-json/fsm/v1/markers` | Tous les marqueurs (format compact) |
| `GET /wp-json/fsm/v1/schools` | Détails complets (utilisé auto. quand < 1 500 résultats) |
| `GET /wp-json/fsm/v1/school/{id}` | Détails d'un établissement |
| `GET /wp-json/fsm/v1/departments` | Liste des départements |
| `GET /wp-json/fsm/v1/academies` | Carte académies → départements |
| `GET /wp-json/fsm/v1/circonscriptions` | Circonscriptions d'un département |
| `GET /wp-json/fsm/v1/commune-circo-map` | Correspondance commune → circonscription |
| `GET /wp-json/fsm/v1/stats` | Statistiques globales |

### Paramètres de filtrage (points de terminaison des marqueurs)

- `types` : Type d'établissement (ex: `Ecole,Collège`)
- `departement` : Nom du département
- `statut` : `Public` ou `Privé`
- `ep` : `REP` ou `REP+`
- `search` : Recherche textuelle

## Performance

Le plugin gère ~69 000 points grâce à :

- **Base de données locale** : les données sont stockées dans une table WordPress dédiée
- **Clustering** : Leaflet.markercluster regroupe les marqueurs proches
- **Cache** : les réponses API sont mises en cache (transients WordPress + headers HTTP)
- **Format compact** : les marqueurs sont transmis en tableaux d'arrays (pas d'objets JSON verbeux)
- **Chargement asynchrone** : les détails ne sont récupérés qu'au clic sur un marqueur

## Problèmes connus

- La synchronisation initiale peut prendre plusieurs minutes selon les performances du serveur (téléchargement de ~69 000 enregistrements).
- Sur les hébergements mutualisés avec un `max_execution_time` très court, la synchronisation peut échouer et nécessiter plusieurs tentatives.
- Les zones de circonscription pour les communes sans école sont approximées par la circonscription la plus proche géographiquement.

## Historique des versions

### 1.5.1 - 2026-08-31

- **Amélioration :** traduction en français de l’ensemble du README

### 1.5.0 - 2026-08-31

- **Correction :** Le fond de carte par défaut passe de CARTO (cartocdn.com) aux tuiles standard OpenStreetMap. CARTO refuse désormais les requêtes anonymes et affichait un filigrane « API KEY REQUIRED » sur toute la carte
- **Nouveauté :** Prise en charge des fonds de carte CARTO via une clé API, avec un réglage **Fond de carte** (clé + choix du style Voyager / Positron / Dark Matter) et un lien direct vers <https://carto.com/basemaps/apikey/>
- **Amélioration :** L'attribution du fond de carte est désormais construite côté serveur et suit le fournisseur réellement utilisé

### 1.4.0 - 2026-04-17
- **Correction :** Erreur « Duplicate entry » lors de la synchronisation mensuelle — les identifiants en double dans le CSV source (ex. 9840265R en Polynésie Française) sont désormais gérés via `ON DUPLICATE KEY UPDATE`
- **Amélioration :** Le popup « Voir les détails » affiche désormais un bandeau géométrique CSS (sans image externe) et prépend le changelog de la release GitHub lorsqu'une mise à jour est disponible
- **Amélioration :** Ajout d'un log au démarrage de la synchronisation pour faciliter le débogage

### 1.3.8 - 2026-03-30
- **Amélioration :** Réécriture du système de mise à jour GitHub : fenêtre « Voir les détails » avec onglets Description, Installation, FAQ et Changelog parsés depuis le README.md local (via Parsedown)
- **Nouveauté :** Lien "Voir les détails" (thickbox) dans la liste des extensions
- **Nouveauté :** Conversion des tableaux Markdown en structures div/span compatibles wp_kses
- **Nouveauté :** Injection CSS via admin_head pour le style de la modale d'informations du plugin

### 1.3.7 - 2026-03-09
- **Amélioration :** Ajout du préfixe "Circonscription de " dans l'infobulle au survol des zones de circonscription sur la carte

### 1.3.6 - 2026-03-09
- **Correction :** Correction d'une PHP Notice : suppression de l'appel de traduction dans le filtre cron_schedules pour éviter que _load_textdomain_just_in_time ne soit déclenché avant init.

### 1.3.5 - 2026-03-04
- **Correction :** les attributs `statut`, `types` et `education_prioritaire` du shortcode sont désormais pris en compte (pré-sélection des filtres HTML côté serveur)
- **Correction :** normalisation des noms de types dans le shortcode (ex. `Écoles` → `Ecole`, `Collèges` → `Collège`, `Lycées` → `Lycée`)
- **Correction :** les valeurs `statut`, `types` et `education_prioritaire` de la configuration sont envoyées au REST API même lorsque les widgets de filtre sont masqués
- **Nouveauté :** constructeur de shortcode interactif sur la page de réglages (génération et copie du shortcode en temps réel)

### 1.3.0 - 2026-03-04
- **Transports en commun (Île-de-France)** : calque optionnel affichant les lignes de métro, RER, tramway et train ainsi que les gares/stations
  - Données officielles Île-de-France Mobilités (API Explore v2, Licence Ouverte)
  - Lignes colorées avec les couleurs officielles de chaque ligne
  - Stations affichées en cercles avec infobulle au survol (nom + lignes desservies, ex. « RER E »)
  - Bouton 🚇 dans la barre d'outils pour activer/désactiver
  - Attribut `show_transport` (défaut `false`) dans le shortcode et le bloc Gutenberg

### 1.2.0 - 2026-03-04
- **Zones de circonscription IEN** : fond de couleur par circonscription affiché lorsqu'un département est sélectionné
  - Contours communaux via l'API geo.api.gouv.fr, colorés par circonscription
  - Infobulle au survol avec le nom de la circonscription
  - Les communes sans école sont assignées à la circonscription la plus proche
  - Attribut `show_circo_zones` (défaut `true`) pour activer/désactiver
- Nouveau point de terminaison REST `GET /fsm/v1/commune-circo-map?departement=...`
- Ajout de `code_commune` dans le schéma de la base de données
- Correction du schéma DB lors de la synchronisation (appel `dbDelta` avant import)
- Correction du cron : la prochaine synchronisation s'affiche correctement (1 mois après le dernier sync)
- Correction i18n : toutes les chaînes admin utilisent désormais des msgids anglais conformes au fichier .pot

### 1.1.3 - 2026-03-03
- Ajout d'une infobulle (tooltip) au survol de chaque marqueur : affiche le nom court de l'établissement (sans le préfixe de type : École, Collège, Lycée…) suivi du nom de la ville

### 1.1.2 - 2026-03-02
- Ajout de la circonscription par défaut dans le bloc Gutenberg (s'affiche uniquement si un département est sélectionné)

### 1.1.1 - 2026-03-02
- Affichage du nom de la circonscription (nettoyé) dans la fenêtre de détail de chaque école

### 1.1.0 - 2026-03-02
- Ajout du filtre « Circonscription » : un menu déroulant apparaît automatiquement lorsqu'un département est sélectionné
- Nettoyage automatique des noms de circonscriptions (suppression des préfixes « Circonscription d'inspection du 1er degré de/du/d' », etc.)
- Nouveau point de terminaison REST `GET /fsm/v1/circonscriptions?departement=...`
- Le paramètre `circonscription` est pris en charge par les points de terminaison `/markers` et `/schools`

### 1.0.0
- Version initiale
- Carte interactive avec Leaflet.js et OpenStreetMap
- Filtres : type, statut, département, académie, éducation prioritaire
- Recherche textuelle par nom ou ville
- Clustering intelligent (Leaflet.markercluster)
- Synchronisation mensuelle automatique via WP-Cron
- Endpoints REST API
- Bloc Gutenberg et shortcode
- Géolocalisation (bouton "Me localiser")
- Mises à jour automatiques depuis GitHub

## Remerciements

- [Leaflet.js](https://leafletjs.com/) — Bibliothèque cartographique
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) — Plugin de clustering
- [OpenStreetMap](https://www.openstreetmap.org/) — Tuiles cartographiques
- [Ministère de l'Éducation Nationale](https://data.education.gouv.fr/) — Données open data

## Sécurité

Si vous découvrez une vulnérabilité de sécurité dans cette extension, veuillez la signaler de manière responsable via les [avis de sécurité GitHub](https://github.com/guilamu/french-schools-map/security/advisories/new). N’ouvrez pas de ticket public pour les signalements de sécurité.

## Contribution

Les contributions sont les bienvenues ! Ouvrez un ticket ou soumettez une demande de fusion sur [GitHub](https://github.com/guilamu/french-schools-map).

Pour les traductions, l’extension utilise l’internationalisation de WordPress. Vous pouvez contribuer en modifiant les fichiers `.po` du répertoire `languages/` et en générant les fichiers `.mo` correspondants avec les commandes CLI `wp i18n`.

## Licence

Ce projet est distribué sous licence GNU Affero General Public License v3.0 (AGPL-3.0). Consultez le fichier [LICENSE](LICENSE) pour plus de détails.

---

Conçu avec passion pour la communauté WordPress
