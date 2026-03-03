# French Schools Map

Extension WordPress affichant une carte interactive des établissements scolaires français, basée sur **OpenStreetMap** (Leaflet.js) et alimentée par les données **open data** du Ministère de l'Éducation Nationale.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple) ![License](https://img.shields.io/badge/License-AGPL--3.0-green)

## Description

Ce plugin affiche une carte interactive des ~69 000 établissements scolaires français (écoles, collèges, lycées) directement dans vos pages ou articles WordPress. Les données proviennent de l'[Annuaire de l'Éducation Nationale](https://data.education.gouv.fr/explore/dataset/fr-en-annuaire-education/) et sont synchronisées automatiquement chaque mois.

### Fonctionnalités

- 🗺️ **Carte interactive** OpenStreetMap avec Leaflet.js (aucune clé API requise)
- 📍 **~69 000 établissements** géolocalisés sur la carte
- 🔍 **Filtres dynamiques** : type (école/collège/lycée), statut (public/privé), département, éducation prioritaire
- 🔎 **Recherche textuelle** par nom d'établissement ou ville
- 📊 **Clustering intelligent** pour des performances optimales (Leaflet.markercluster)
- 🎨 **Marqueurs colorés** par type d'établissement
- 📱 **Responsive** : adapté mobile et desktop
- 🌐 **Académies & Départements** : filtres géographiques avec zoom automatique sur la zone sélectionnée
- 📋 **Popup détaillée** : nom, adresse, téléphone, email, statut REP/REP+, itinéraire (chargement automatique pour < 1 500 établissements)
- 📦 **Shortcode** et **bloc Gutenberg** disponibles
- 🔄 **Synchronisation mensuelle** automatique via WP-Cron
- 📍 **Géolocalisation** : bouton "Me localiser"

## Key Features

- **Open Data:** Données officielles du Ministère de l'Éducation Nationale (~69 000 établissements)
- **No API Key:** OpenStreetMap/Leaflet.js — aucune clé API requise
- **Multilingual:** Fonctionne avec tout contenu linguistique
- **Translation-Ready:** Toutes les chaînes sont internationalisées
- **Secure:** Requêtes REST authentifiées par nonce WordPress, données sanitisées
- **GitHub Updates:** Mises à jour automatiques depuis les releases GitHub

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher

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
Non. Le plugin utilise OpenStreetMap (gratuit, sans clé API) pour les tuiles cartographiques et l'API Open Data du Ministère de l'Éducation Nationale (également gratuite et sans clé).

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

## Project Structure

```
.
├── french-schools-map.php        # Main plugin file
├── uninstall.php                 # Cleanup on uninstall
├── README.md
├── assets
│   ├── css
│   │   └── fsm-map.css           # Frontend map styles
│   └── js
│       ├── fsm-map.js            # Frontend map logic (Leaflet)
│       └── fsm-block.js          # Gutenberg block editor script
└── includes
    ├── class-fsm-academies.php   # Académies data & département mapping
    ├── class-fsm-admin.php       # Admin settings page
    ├── class-fsm-local-db.php    # Local DB sync & REST helpers
    ├── class-fsm-rest-api.php    # REST API endpoints
    └── class-github-updater.php  # GitHub auto-updates
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
| `cluster` | `true` | Activer le clustering des marqueurs |
| `max_zoom` | `18` | Zoom maximal |
| `tile_url` | _(vide)_ | URL personnalisée pour les tuiles cartographiques |

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

### Réglages par défaut

Dans **Réglages → French Schools Map**, vous pouvez définir un département ou une académie par défaut. Ces valeurs seront utilisées automatiquement si le shortcode ne précise rien.

### Bloc Gutenberg

Le bloc **French Schools Map** est disponible dans l'éditeur. Les mêmes paramètres sont configurables via l'inspecteur de bloc.

## Source de données

Les données proviennent du portail Open Data du Ministère de l'Éducation Nationale :

- **Dataset** : [Annuaire de l'éducation](https://data.education.gouv.fr/explore/dataset/fr-en-annuaire-education/)
- **API** : OpenDataSoft API v2.1
- **Fréquence** : Synchronisation mensuelle automatique

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

## Administration

La page **Réglages → French Schools Map** permet de :

- Voir le statut de la synchronisation (dernière date, nombre d'enregistrements)
- Lancer une synchronisation manuelle
- Définir le département ou l'académie affichés par défaut

## API REST

Le plugin expose des endpoints REST pour les développeurs :

| Endpoint | Description |
|----------|-------------|
| `GET /wp-json/fsm/v1/markers` | Tous les marqueurs (format compact) |
| `GET /wp-json/fsm/v1/schools` | Détails complets (utilisé auto. quand < 1 500 résultats) |
| `GET /wp-json/fsm/v1/school/{id}` | Détails d'un établissement |
| `GET /wp-json/fsm/v1/departments` | Liste des départements |
| `GET /wp-json/fsm/v1/academies` | Carte académies → départements |
| `GET /wp-json/fsm/v1/stats` | Statistiques globales |

### Paramètres de filtrage (endpoint markers)

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

## Known Issues

- La synchronisation initiale peut prendre plusieurs minutes selon les performances du serveur (téléchargement de ~69 000 enregistrements).
- Sur les hébergements mutualisés avec un `max_execution_time` très court, la synchronisation peut échouer et nécessiter plusieurs tentatives.

## Changelog

### 1.1.3 - 2026-03-03
- Ajout d'une infobulle (tooltip) au survol de chaque marqueur : affiche le nom court de l'établissement (sans le préfixe de type : École, Collège, Lycée…) suivi du nom de la ville

### 1.1.2 - 2026-03-02
- Ajout de la circonscription par défaut dans le bloc Gutenberg (s'affiche uniquement si un département est sélectionné)

### 1.1.1 - 2026-03-02
- Affichage du nom de la circonscription (nettoyé) dans la popup de détail de chaque école

### 1.1.0 - 2026-03-02
- Ajout du filtre « Circonscription » : un menu déroulant apparaît automatiquement lorsqu'un département est sélectionné
- Nettoyage automatique des noms de circonscriptions (suppression des préfixes « Circonscription d'inspection du 1er degré de/du/d' », etc.)
- Nouvel endpoint REST `GET /fsm/v1/circonscriptions?departement=...`
- Le paramètre `circonscription` est pris en charge par les endpoints `/markers` et `/schools`

### 1.0.0
- Initial release
- Carte interactive avec Leaflet.js et OpenStreetMap
- Filtres : type, statut, département, académie, éducation prioritaire
- Recherche textuelle par nom ou ville
- Clustering intelligent (Leaflet.markercluster)
- Synchronisation mensuelle automatique via WP-Cron
- Endpoints REST API
- Bloc Gutenberg et shortcode
- Géolocalisation (bouton "Me localiser")
- Mises à jour automatiques depuis GitHub

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

## Crédits

- [Leaflet.js](https://leafletjs.com/) — Bibliothèque cartographique
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) — Plugin de clustering
- [OpenStreetMap](https://www.openstreetmap.org/) — Tuiles cartographiques
- [Ministère de l'Éducation Nationale](https://data.education.gouv.fr/) — Données open data

---

<p align="center">
  Made with love for the WordPress community
</p>
