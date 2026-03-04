/**
 * French Schools Map — Leaflet frontend.
 *
 * @package French_Schools_Map
 */

/* global L, fsmMapI18n */

(function () {
    'use strict';

    // ── i18n helper – falls back to English if fsmMapI18n not available ───
    var i18n = window.fsmMapI18n || {
        legend: 'Legend',
        loading: 'Loading…',
        loadError: 'Loading error.',
        moreInfo: 'More info…',
        error: 'Error',
        directions: 'Directions',
        exitFullscreen: 'Exit fullscreen',
        fullscreen: 'Fullscreen',
        statsFormat: '%1$s school(s) shown out of %2$s',
    };

    // ── Type config ──────────────────────────────────────────────────
    var TYPE_CONFIG = {
        1: { label: 'École', color: '#2ecc71', icon: '🏫' }, // green
        2: { label: 'Collège', color: '#e67e22', icon: '🏛️' }, // orange
        3: { label: 'Lycée', color: '#e74c3c', icon: '🎓' }, // red
        4: { label: 'Autre', color: '#9b59b6', icon: '📚' }, // purple
    };

    // ── Palette for circo zone colouring ──────────────────────────────
    var CIRCO_COLORS = [
        '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4',
        '#42d4f4', '#f032e6', '#bfef45', '#fabed4', '#469990',
        '#dcbeff', '#9A6324', '#800000', '#aaffc3', '#808000',
        '#000075', '#a9a9a9', '#e6beff', '#fffac8', '#ffd8b1',
    ];

    // ── Département name → INSEE code mapping ─────────────────────────
    var DEPT_CODES = {
        'Ain': '01', 'Aisne': '02', 'Allier': '03', 'Alpes-de-Haute-Provence': '04',
        'Hautes-Alpes': '05', 'Alpes-Maritimes': '06', 'Ardèche': '07', 'Ardennes': '08',
        'Ariège': '09', 'Aube': '10', 'Aude': '11', 'Aveyron': '12',
        'Bouches-du-Rhône': '13', 'Calvados': '14', 'Cantal': '15', 'Charente': '16',
        'Charente-Maritime': '17', 'Cher': '18', 'Corrèze': '19',
        'Corse-du-Sud': '2A', 'Haute-Corse': '2B',
        "Côte-d'Or": '21', "Côte-d\u2019Or": '21',
        "Côtes-d'Armor": '22', "Côtes-d\u2019Armor": '22',
        'Creuse': '23', 'Dordogne': '24',
        'Doubs': '25', 'Drôme': '26', 'Eure': '27', 'Eure-et-Loir': '28',
        'Finistère': '29', 'Gard': '30', 'Haute-Garonne': '31', 'Gers': '32',
        'Gironde': '33', 'Hérault': '34', 'Ille-et-Vilaine': '35', 'Indre': '36',
        'Indre-et-Loire': '37', 'Isère': '38', 'Jura': '39', 'Landes': '40',
        'Loir-et-Cher': '41', 'Loire': '42', 'Haute-Loire': '43', 'Loire-Atlantique': '44',
        'Loiret': '45', 'Lot': '46', 'Lot-et-Garonne': '47', 'Lozère': '48',
        'Maine-et-Loire': '49', 'Manche': '50', 'Marne': '51', 'Haute-Marne': '52',
        'Mayenne': '53', 'Meurthe-et-Moselle': '54', 'Meuse': '55', 'Morbihan': '56',
        'Moselle': '57', 'Nièvre': '58', 'Nord': '59', 'Oise': '60',
        'Orne': '61', 'Pas-de-Calais': '62', 'Puy-de-Dôme': '63',
        'Pyrénées-Atlantiques': '64', 'Hautes-Pyrénées': '65',
        'Pyrénées-Orientales': '66', 'Bas-Rhin': '67', 'Haut-Rhin': '68',
        'Rhône': '69', 'Haute-Saône': '70', 'Saône-et-Loire': '71', 'Sarthe': '72',
        'Savoie': '73', 'Haute-Savoie': '74', 'Paris': '75',
        'Seine-Maritime': '76', 'Seine-et-Marne': '77', 'Yvelines': '78',
        'Deux-Sèvres': '79', 'Somme': '80', 'Tarn': '81', 'Tarn-et-Garonne': '82',
        'Var': '83', 'Vaucluse': '84', 'Vendée': '85', 'Vienne': '86',
        'Haute-Vienne': '87', 'Vosges': '88', 'Yonne': '89',
        'Territoire de Belfort': '90', 'Essonne': '91', 'Hauts-de-Seine': '92',
        'Seine-Saint-Denis': '93', 'Val-de-Marne': '94',
        "Val-d'Oise": '95', "Val-d\u2019Oise": '95',
        'Guadeloupe': '971', 'Martinique': '972', 'Guyane': '973',
        'La Réunion': '974', 'Mayotte': '976',
        'Nouvelle Calédonie': '988', 'Polynésie Française': '987',
        'Saint-Barthélémy': '977', 'Saint-Martin': '978',
        'St-Pierre-et-Miquelon': '975',
    };

    // ── Marker icon factory ──────────────────────────────────────────
    function makeIcon(typeId) {
        var cfg = TYPE_CONFIG[typeId] || TYPE_CONFIG[4];
        return L.divIcon({
            className: 'fsm-marker fsm-marker-type-' + typeId,
            html: '<span style="background:' + cfg.color + ';" class="fsm-marker-dot"></span>',
            iconSize: [14, 14],
            iconAnchor: [7, 7],
            popupAnchor: [0, -10],
        });
    }

    // ── Helper: build a REST URL that works with both pretty and plain permalinks ─
    function buildUrl(base, path, params) {
        var url = base + path;
        var qs = params ? new URLSearchParams(params).toString() : '';
        if (!qs) return url;
        // If base already contains '?' (plain permalinks), append with '&'.
        return url + (url.indexOf('?') !== -1 ? '&' : '?') + qs;
    }

    // ── Helper: parse a fetch response as JSON, rejecting on HTTP errors ─
    function jsonResponse(response) {
        if (!response.ok) {
            return response.text().then(function (txt) {
                throw new Error('HTTP ' + response.status + ': ' + txt.substring(0, 200));
            });
        }
        return response.json();
    }

    // ── Helper: clean circonscription name ────────────────────────────
    // Removes standard prefixes so the dropdown shows short, readable names.
    // The raw value is kept as option.value for server-side filtering.
    function cleanNomCirconscription(value) {
        if (!value) return '';
        var cleaned = value;

        // Patterns to remove (order matters — longer patterns first)
        var patterns = [
            /^Circonscription d'inspection du 1er degré de\s+/i,
            /^Circonscription d'inspection du 1er degré du\s+/i,
            /^Circonscription d'inspection du 1er degré d'/i,
            /^Circonscription d'inspection du 1er degré\s+/i,
            /^Circonscription d'inspection du 1r degré de\s+/i,
            /^Circonscription d'inspection du 1r degré du\s+/i,
            /^Circonscription d'inspection du 1r degré d'/i,
            /^Circonscription d'inspection du 1r degré\s+/i,
            /^Circonscription\s+/i
        ];

        patterns.forEach(function (pattern) {
            cleaned = cleaned.replace(pattern, '');
        });

        return cleaned.trim();
    }

    // ── Helper: clean school name for tooltip ──────────────────────────
    // Strips leading type keywords (École, Collège, Lycée…) so the hover
    // tooltip shows a short, readable name.  Capitalises the first letter.
    function cleanSchoolName(name) {
        if (!name) return '';
        var cleaned = name;

        // Patterns to strip (order: longest first)
        var patterns = [
            /^[ÉE]COLE\s+DE\s+NIVEAU\s+[ÉE]L[ÉE]MENTAIRE\s+/i,
            /^[ÉE]COLE\s+[ÉE]L[ÉE]MENTAIRE\s+/i,
            /^[ÉE]COLE\s+MATERNELLE\s+/i,
            /^[ÉE]COLE\s+PRIMAIRE\s+/i,
            /^[ÉE]COLE\s+/i,
            /^COLL[ÈE]GE\s+/i,
            /^LYC[ÉE]E\s+/i
        ];

        patterns.forEach(function (p) {
            cleaned = cleaned.replace(p, '');
        });

        cleaned = cleaned.trim();
        if (!cleaned) return name; // safety: return original if nothing left

        // Capitalise first letter, lowercase the rest only if the whole
        // string is uppercase (preserve mixed-case names like "De Gaulle").
        if (cleaned === cleaned.toUpperCase()) {
            cleaned = cleaned.charAt(0).toUpperCase() + cleaned.slice(1).toLowerCase();
        } else {
            cleaned = cleaned.charAt(0).toUpperCase() + cleaned.slice(1);
        }

        return cleaned;
    }

    // ── Constructor ──────────────────────────────────────────────────
    window.FSM_Map = function (mapId, config) {
        this.mapId = mapId;
        this.config = config;
        this.markers = [];
        this.allData = [];
        this._userChangedGeo = false;
        this.map = null;
        this.cluster = null;
        this.wrapper = document.getElementById(mapId + '-wrapper');

        // School detail caches.
        this._schoolCache = {};
        this._markerById = {};

        // Circo zone layer.
        this._circoLayer = null;

        this.init();
    };

    FSM_Map.prototype.init = function () {
        var self = this;

        // Create map.
        this.map = L.map(this.mapId, {
            center: [this.config.centerLat, this.config.centerLng],
            zoom: this.config.zoom,
            maxZoom: this.config.maxZoom,
            zoomControl: true,
            scrollWheelZoom: false,
        });

        // Enable scroll-wheel zoom only while Ctrl is held down.
        var mapEl = document.getElementById(this.mapId);
        var leafletMap = this.map;
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Control') leafletMap.scrollWheelZoom.enable();
        });
        document.addEventListener('keyup', function (e) {
            if (e.key === 'Control') leafletMap.scrollWheelZoom.disable();
        });
        // Also disable when the window loses focus (user Alt-Tabs, etc.).
        window.addEventListener('blur', function () {
            leafletMap.scrollWheelZoom.disable();
        });

        // Tile layer.
        var tileUrl = this.config.tileUrl || 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
        var attribution = this.config.tileUrl
            ? ''
            : '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
        L.tileLayer(tileUrl, {
            attribution: attribution,
            maxZoom: this.config.maxZoom,
        }).addTo(this.map);

        // Public-transport overlay — IDFM (Île-de-France Mobilités).
        // Shows metro / RER / tramway / train lines and stations using
        // GeoJSON data from the open Explore API v2.  Rendered in a
        // dedicated pane between the base tiles and circo zones / markers.
        this.map.createPane('transportPane');
        this.map.getPane('transportPane').style.zIndex = 320;
        this.map.createPane('transportStationPane');
        this.map.getPane('transportStationPane').style.zIndex = 450;

        this._transportLinesLayer = null;   // L.geoJSON for line traces
        this._transportStationsLayer = null; // L.layerGroup for station markers
        this._transportDataLoaded = false;   // true once IDFM data has been fetched
        this._transportVisible = false;

        if (this.config.showTransport) {
            this._transportVisible = true;
            this._loadTransportData();
        }

        // Legend.
        this.addLegend();

        // Create a custom map pane for circo zones so they render below markers.
        this.map.createPane('circoPane');
        this.map.getPane('circoPane').style.zIndex = 350;

        // Cluster group — always created so it can serve as a safety net
        // when the result set is large, even if config.cluster is false.
        if (L.markerClusterGroup) {
            this.cluster = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                chunkedLoading: true,
                chunkInterval: 100,
                chunkDelay: 10,
                disableClusteringAtZoom: 18,
            });
            // Only add to map now if clustering is on by config; otherwise
            // renderMarkers adds/removes it dynamically based on data size.
            if (this.config.cluster) {
                this.map.addLayer(this.cluster);
                this._clusterOnMap = true;
            } else {
                this._clusterOnMap = false;
            }
        }

        // Bind filters.
        this.bindFilters();


        // Bind transport toggle button.
        this.bindTransportToggle();

        // Bind locate button.
        this.bindLocate();

        // Bind fullscreen button.
        this.bindFullscreen();

        // Load académies (sync — uses config data).
        this.loadAcademies();

        // Load departments (async fetch), then load markers once dropdown is ready.
        // fitView=true when a geographic default is pre-configured so the map
        // starts centred on that area.
        this.loadDepartments().then(function () {
            var hasDefault =
                (self.config.departement && self.config.departement !== 'all') ||
                (self.config.academie && self.config.academie !== 'all');

            // If a département default is pre-configured, load its circonscriptions
            // and wait for the dropdown to be populated before loading markers,
            // so the default circonscription value is included in the query.
            var circoReady = (self.config.departement && self.config.departement !== 'all')
                ? self.loadCirconscriptions(self.config.departement)
                : Promise.resolve();

            circoReady.then(function () {
                self.loadMarkers(hasDefault);
                // Load circo zones if a département is pre-configured.
                if (self.config.showCircoZones !== false &&
                    self.config.departement && self.config.departement !== 'all') {
                    self.loadCircoZones(self.config.departement);
                }
            });
        });

        // Handle map resize when container becomes visible.
        setTimeout(function () {
            self.map.invalidateSize();
        }, 200);
    };

    // ── Legend control ────────────────────────────────────────────────
    FSM_Map.prototype.addLegend = function () {
        var legend = L.control({ position: 'bottomright' });
        legend.onAdd = function () {
            var div = L.DomUtil.create('div', 'fsm-legend');
            var html = '<strong>' + i18n.legend + '</strong>';
            for (var id in TYPE_CONFIG) {
                var cfg = TYPE_CONFIG[id];
                html += '<div class="fsm-legend-item">'
                    + '<span class="fsm-legend-dot" style="background:' + cfg.color + ';"></span> '
                    + cfg.label + '</div>';
            }
            div.innerHTML = html;
            return div;
        };
        legend.addTo(this.map);
    };

    // ── Load académies ────────────────────────────────────────────────
    FSM_Map.prototype.loadAcademies = function () {
        var self = this;
        var select = this.wrapper.querySelector('.fsm-select-academie');
        if (!select) return;

        // Use the embedded mapping from PHP config.
        var academies = this.config.academies || {};
        var names = Object.keys(academies).sort();

        names.forEach(function (name) {
            var opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            select.appendChild(opt);
        });

        // Force pre-selection after all options are added.
        if (self.config.academie && self.config.academie !== 'all') {
            select.value = self.config.academie;
        }
    };

    // ── Load departments ─────────────────────────────────────────────
    FSM_Map.prototype.loadDepartments = function () {
        var self = this;
        var select = this.wrapper.querySelector('.fsm-select-dept');
        if (!select) return Promise.resolve();

        return fetch(buildUrl(this.config.restUrl, 'departments'), {
            headers: { 'X-WP-Nonce': this.config.nonce },
        })
            .then(jsonResponse)
            .then(function (depts) {
                depts.forEach(function (d) {
                    var opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    select.appendChild(opt);
                });

                // Force pre-selection after all options are added.
                if (self.config.departement && self.config.departement !== 'all') {
                    select.value = self.config.departement;
                }
            })
            .catch(function (err) {
                console.warn('[FSM] Failed to load departments', err);
            });
    };
    // ── Load circonscriptions ─────────────────────────────────────────────
    FSM_Map.prototype.loadCirconscriptions = function (dept) {
        var self = this;
        var select = this.wrapper.querySelector('.fsm-select-circo');
        var group = this.wrapper.querySelector('.fsm-filter-circo');
        if (!select || !group) return Promise.resolve();

        // Reset dropdown.
        select.length = 1; // keep "Toutes" option
        select.value = 'all';

        if (!dept || dept === 'all') {
            group.style.display = 'none';
            return Promise.resolve();
        }

        return fetch(buildUrl(this.config.restUrl, 'circonscriptions', { departement: dept }), {
            headers: { 'X-WP-Nonce': this.config.nonce },
        })
            .then(jsonResponse)
            .then(function (circos) {
                circos.forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = c;
                    opt.textContent = cleanNomCirconscription(c);
                    select.appendChild(opt);
                });
                group.style.display = circos.length > 0 ? '' : 'none';

                // Pre-select default circonscription if configured.
                if (self.config.circonscription && self.config.circonscription !== 'all') {
                    select.value = self.config.circonscription;
                }
            })
            .catch(function (err) {
                console.warn('[FSM] Failed to load circonscriptions', err);
                group.style.display = 'none';
            });
    };
    // ── Load markers ─────────────────────────────────────────────────
    // fitView: pass true to auto-zoom the map to fit the loaded markers.
    FSM_Map.prototype.loadMarkers = function (fitView) {
        var self = this;

        // Increment the generation counter so any in-flight response from a
        // previous call can detect it has been superseded and bail out.
        // This prevents slow initial-load responses from overriding a faster
        // user-triggered response (race condition on first académie change).
        if (!this._loadGen) this._loadGen = 0;
        var myGen = ++this._loadGen;

        var params = this.getFilterParams();
        var url = buildUrl(this.config.restUrl, 'markers', params);

        // Store params for use by loadSchoolDetails.
        this._lastParams = params;

        // Show loading.
        this.setStatsText(i18n.loading);

        fetch(url, {
            headers: { 'X-WP-Nonce': this.config.nonce },
        })
            .then(jsonResponse)
            .then(function (data) {
                // Drop stale responses.
                if (myGen !== self._loadGen) return;
                self.allData = data;
                self.renderMarkers(data, fitView, myGen);
            })
            .catch(function (err) {
                if (myGen !== self._loadGen) return;
                console.error('[FSM] Failed to load markers', err);
                self.setStatsText(i18n.loadError);
            });
    };

    // ── Render markers ───────────────────────────────────────────────
    // fitView: when true, the map auto-fits to show all visible markers.
    // gen: generation token from loadMarkers; fitBounds is skipped if superseded.
    FSM_Map.prototype.renderMarkers = function (data, fitView, gen) {
        var self = this;

        // Decide whether to use the cluster layer for this render.
        // Force clustering whenever data.length > 1500, regardless of config,
        // to prevent the browser from rendering 60k+ individual DOM nodes.
        var CLUSTER_THRESHOLD = 1500;
        var needsCluster = this.config.cluster || data.length > CLUSTER_THRESHOLD;

        // Dynamically add or remove the cluster layer from the map.
        if (this.cluster) {
            this.cluster.clearLayers();
            if (needsCluster && !this._clusterOnMap) {
                this.map.addLayer(this.cluster);
                this._clusterOnMap = true;
            } else if (!needsCluster && this._clusterOnMap) {
                this.map.removeLayer(this.cluster);
                this._clusterOnMap = false;
            }
        }
        // When not using cluster, remove individually tracked markers.
        if (!needsCluster) {
            this.markers.forEach(function (m) { self.map.removeLayer(m); });
        }
        this.markers = [];

        // Reset marker-by-id lookup for this render.
        self._markerById = {};

        // When doing a full reload (gen defined), clear the school cache so
        // stale details from the previous filter are not carried over.
        if (gen !== undefined) {
            self._schoolCache = {};
        }

        // Apply client-side type filter (checkbox).
        var activeTypes = this.getActiveTypes();
        var filtered = data.filter(function (item) {
            return activeTypes.indexOf(item[2]) !== -1;
        });

        // Build markers.
        var markersArray = [];
        filtered.forEach(function (item) {
            // item: [lat, lng, type_id, id, name, city, statut]
            var lat = item[0];
            var lng = item[1];
            var typeId = item[2];
            var id = item[3];
            var name = item[4];
            var city = item[5];
            var statut = item[6];
            var typeCfg = TYPE_CONFIG[typeId] || TYPE_CONFIG[4];

            var tooltip = name + ' \u2013 ' + city;
            var marker = L.marker([lat, lng], { icon: makeIcon(typeId), title: tooltip });

            // Popup: use pre-fetched rich details if available; otherwise lightweight.
            var cached = self._schoolCache[id];
            if (cached) {
                marker.bindPopup(self.buildDetailPopup(cached), { maxWidth: 300 });
            } else {
                // Lightweight popup with a "details" link.
                marker.bindPopup(
                    '<div class="fsm-popup">'
                    + '<strong>' + self.esc(name) + '</strong><br>'
                    + '<span class="fsm-popup-type" style="color:' + typeCfg.color + ';">' + typeCfg.label + '</span>'
                    + ' — ' + self.esc(statut) + '<br>'
                    + '<em>' + self.esc(city) + '</em><br>'
                    + '<a href="#" class="fsm-popup-details" data-id="' + id + '">' + i18n.moreInfo + '</a>'
                    + '</div>',
                    { maxWidth: 300 }
                );
            }

            // Track id → marker for post-fetch popup upgrades.
            self._markerById[id] = marker;

            markersArray.push(marker);
        });

        self.markers = markersArray;

        if (needsCluster && self.cluster) {
            self.cluster.addLayers(markersArray);
        } else {
            markersArray.forEach(function (m) { m.addTo(self.map); });
        }

        // Auto-fit the map to show all visible schools.
        // Only when explicitly requested (fitView flag) to avoid unwanted
        // repositioning when the user toggles type checkboxes.
        // Use setTimeout so fitBounds fires AFTER the cluster's chunked
        // loading has settled (chunkInterval is 100 ms).
        // Also guard with the generation token: if another loadMarkers call
        // started during the 150 ms delay, discard this fitBounds.
        if (fitView && filtered.length > 0) {
            var snapshot = filtered.map(function (item) {
                return [item[0], item[1]];
            });
            var capturedGen = gen;
            setTimeout(function () {
                if (capturedGen !== self._loadGen) return; // superseded
                self.map.fitBounds(
                    L.latLngBounds(snapshot).pad(0.08),
                    { maxZoom: 14 }
                );
            }, 150);
        }

        // Update stats.
        var shownStr = filtered.length.toLocaleString();
        var totalStr = data.length.toLocaleString();
        self.setStatsText(
            i18n.statsFormat
                .replace('%1$s', shownStr)
                .replace('%2$s', totalStr)
        );

        // Bulk-fetch full school details when result set is small enough,
        // so popups open with rich content without requiring a click.
        if (gen !== undefined && data.length > 0 && data.length < 1500) {
            self.loadSchoolDetails(gen);
        }

        // Bind detail links (event delegation on map container).
        self.bindPopupDetails();
    };

    // ── Bulk school detail pre-fetch ─────────────────────────────────
    /**
     * Fetch full details for all schools in the current filter set and
     * upgrade existing marker popups in-place.
     * Only called when data.length < 1500 to keep payload reasonable.
     */
    FSM_Map.prototype.loadSchoolDetails = function (gen) {
        var self = this;
        var url = buildUrl(this.config.restUrl, 'schools', this._lastParams || {});

        fetch(url, {
            headers: { 'X-WP-Nonce': this.config.nonce },
        })
            .then(jsonResponse)
            .then(function (schools) {
                if (gen !== self._loadGen) return; // superseded

                // Populate cache.
                schools.forEach(function (s) {
                    self._schoolCache[s.id] = s;
                });

                // Upgrade popup content for every visible marker.
                Object.keys(self._markerById).forEach(function (id) {
                    var school = self._schoolCache[id];
                    if (school) {
                        self._markerById[id].setPopupContent(self.buildDetailPopup(school));
                    }
                });
            })
            .catch(function (err) {
                console.warn('[FSM] Could not bulk-load school details.', err);
            });
    };

    // ── Popup detail loading ─────────────────────────────────────────
    FSM_Map.prototype.bindPopupDetails = function () {
        var self = this;
        var container = document.getElementById(this.mapId);

        // Remove previous handler.
        if (this._popupHandler) {
            container.removeEventListener('click', this._popupHandler);
        }

        this._popupHandler = function (e) {
            if (!e.target.classList.contains('fsm-popup-details')) return;
            e.preventDefault();
            var id = parseInt(e.target.getAttribute('data-id'), 10);
            if (!id) return;

            // If school is already in the pre-fetch cache, show immediately.
            var cached = self._schoolCache[id];
            if (cached) {
                var popup = self.map._popup;
                if (popup) {
                    popup.setContent(self.buildDetailPopup(cached));
                    popup.update();
                }
                return;
            }

            // Fall back to individual fetch.
            e.target.textContent = i18n.loading;

            fetch(buildUrl(self.config.restUrl, 'school/' + id), {
                headers: { 'X-WP-Nonce': self.config.nonce },
            })
                .then(jsonResponse)
                .then(function (school) {
                    // Cache the result for future popups.
                    self._schoolCache[school.id] = school;
                    var popup = self.map._popup;
                    if (!popup) return;
                    popup.setContent(self.buildDetailPopup(school));
                    popup.update();
                })
                .catch(function () {
                    e.target.textContent = i18n.error;
                });
        };

        container.addEventListener('click', this._popupHandler);
    };

    FSM_Map.prototype.buildDetailPopup = function (s) {
        var typeCfg = TYPE_CONFIG[this.typeToId(s.type_etablissement)] || TYPE_CONFIG[4];
        var html = '<div class="fsm-popup fsm-popup-detail">';
        html += '<strong>' + this.esc(s.nom_etablissement) + '</strong><br>';
        html += '<span class="fsm-popup-type" style="color:' + typeCfg.color + ';">' + this.esc(s.libelle_nature || s.type_etablissement) + '</span>';
        html += ' — ' + this.esc(s.statut_public_prive) + '<br>';
        html += '<span class="fsm-popup-addr">' + this.esc(s.adresse) + '<br>' + this.esc(s.code_postal) + ' ' + this.esc(s.nom_commune) + '</span><br>';

        if (s.telephone) {
            html += '📞 <a href="tel:' + this.esc(s.telephone) + '">' + this.esc(s.telephone) + '</a><br>';
        }
        if (s.mail) {
            html += '✉️ <a href="mailto:' + this.esc(s.mail) + '">' + this.esc(s.mail) + '</a><br>';
        }
        if (s.education_prioritaire && s.education_prioritaire !== 'NON') {
            html += '🏷️ ' + this.esc(s.education_prioritaire) + '<br>';
        }
        if (s.nom_circonscription) {
            html += '🏫 Circonscription : ' + this.esc(cleanNomCirconscription(s.nom_circonscription)) + '<br>';
        }

        // Directions link (OpenStreetMap).
        if (s.latitude && s.longitude) {
            html += '<a href="https://www.openstreetmap.org/directions?from=&to=' + s.latitude + '%2C' + s.longitude + '" target="_blank" rel="noopener">🗺️ ' + i18n.directions + '</a>';
        }
        html += '</div>';
        return html;
    };

    // ── Filters ──────────────────────────────────────────────────────
    FSM_Map.prototype.bindFilters = function () {
        var self = this;

        var acadSelect = this.wrapper.querySelector('.fsm-select-academie');
        var deptSelect = this.wrapper.querySelector('.fsm-select-dept');

        // Académie and département each get a dedicated listener that resets
        // the other dropdown BEFORE calling loadMarkers, guaranteeing that
        // getFilterParams sees consistent DOM state. The old approach used two
        // separate listeners (one generic loadMarkers + one mutual-exclusion),
        // which raced because the mutual-exclusion reset happened after
        // getFilterParams had already read the stale value.
        if (acadSelect) {
            acadSelect.addEventListener('change', function () {
                self._userChangedGeo = true;
                if (acadSelect.value !== 'all' && deptSelect) {
                    deptSelect.value = 'all';
                }
                // Hide circonscription dropdown when switching to académie.
                self.loadCirconscriptions('all');
                self.removeCircoZones();
                self.loadMarkers(true);
            });
        }
        if (deptSelect) {
            deptSelect.addEventListener('change', function () {
                self._userChangedGeo = true;
                if (deptSelect.value !== 'all' && acadSelect) {
                    acadSelect.value = 'all';
                }
                // Load circonscriptions for the selected département.
                self.loadCirconscriptions(deptSelect.value);
                // Load / remove circo zones.
                if (self.config.showCircoZones !== false) {
                    self.loadCircoZones(deptSelect.value);
                }
                self.loadMarkers(true);
            });
        }

        // Circonscription filter.
        var circoSelect = this.wrapper.querySelector('.fsm-select-circo');
        if (circoSelect) {
            circoSelect.addEventListener('change', function () {
                self.loadMarkers(true);
            });
        }

        // Other server-side filters (statut, EP) — no mutual exclusion needed.
        var otherSelects = this.wrapper.querySelectorAll('.fsm-select-statut, .fsm-select-ep');
        otherSelects.forEach(function (el) {
            el.addEventListener('change', function () {
                self.loadMarkers(true);
            });
        });

        // Search (debounced).
        var searchInput = this.wrapper.querySelector('.fsm-search-input');
        if (searchInput) {
            var timer;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    self.loadMarkers();
                }, 400);
            });
        }

        // Type checkboxes (client-side filter only).
        var typeCbs = this.wrapper.querySelectorAll('.fsm-type-cb');
        typeCbs.forEach(function (cb) {
            cb.addEventListener('change', function () {
                self.renderMarkers(self.allData);
            });
        });
    };

    FSM_Map.prototype.getFilterParams = function () {
        var params = {};
        var acad = this.wrapper.querySelector('.fsm-select-academie');
        var dept = this.wrapper.querySelector('.fsm-select-dept');
        var statut = this.wrapper.querySelector('.fsm-select-statut');
        var ep = this.wrapper.querySelector('.fsm-select-ep');
        var search = this.wrapper.querySelector('.fsm-search-input');
        var circo = this.wrapper.querySelector('.fsm-select-circo');

        // Académie and département are mutually exclusive; département takes priority.
        // Check UI dropdowns first, then fall back to config defaults.
        var deptVal = dept ? dept.value : 'all';
        var acadVal = acad ? acad.value : 'all';

        // Enforce mutual exclusion immediately in code, not just via the DOM
        // listener. The 'change' listeners that reset the other dropdown run
        // AFTER this synchronous call, so on the first académie selection
        // deptVal would still hold the config-preset value and incorrectly
        // override the chosen académie. Overriding here fixes the race.
        if (acadVal !== 'all') deptVal = 'all';
        if (deptVal !== 'all') acadVal = 'all';

        // Apply config defaults only on the initial load (before the user has
        // interacted with the geographic dropdowns). Once the user actively
        // selects "tous", _userChangedGeo is true and we skip the fallback
        // so that all schools are loaded as expected.
        if (!this._userChangedGeo && deptVal === 'all' && acadVal === 'all') {
            if (this.config.departement && this.config.departement !== 'all') {
                deptVal = this.config.departement;
            } else if (this.config.academie && this.config.academie !== 'all') {
                acadVal = this.config.academie;
            }
        }

        // Département takes priority over académie.
        if (deptVal !== 'all') {
            params.departement = deptVal;
        } else if (acadVal !== 'all') {
            params.academie = acadVal;
        }

        if (statut && statut.value !== 'all') params.statut = statut.value;
        if (ep && ep.value !== 'all') params.ep = ep.value;
        if (circo && circo.value !== 'all' && deptVal !== 'all') params.circonscription = circo.value;
        if (search && search.value.length >= 2) params.search = search.value;

        // Fallback for config defaults when UI widgets are absent.
        if (!statut && this.config.statut !== 'all') params.statut = this.config.statut;
        if (!ep && this.config.educationPrioritaire && this.config.educationPrioritaire !== 'all') params.ep = this.config.educationPrioritaire;

        // Fallback for types when checkboxes are absent (show_filter_types="false").
        var typeCbs = this.wrapper.querySelectorAll('.fsm-type-cb');
        if (!typeCbs.length && this.config.types && this.config.types !== 'all') {
            params.types = this.config.types;
        }

        return params;
    };

    /**
     * Check whether a geographic filter (département or académie) is currently active,
     * either from the UI dropdowns or from the config defaults.
     */
    FSM_Map.prototype.hasActiveGeoFilter = function () {
        var acad = this.wrapper.querySelector('.fsm-select-academie');
        var dept = this.wrapper.querySelector('.fsm-select-dept');

        if (dept && dept.value !== 'all') return true;
        if (acad && acad.value !== 'all') return true;

        // No filter widgets but config defaults set.
        if (!dept && !acad && this.config.departement !== 'all') return true;
        if (!dept && !acad && this.config.academie !== 'all') return true;

        return false;
    };

    FSM_Map.prototype.getActiveTypes = function () {
        var typeCbs = this.wrapper.querySelectorAll('.fsm-type-cb');
        if (!typeCbs.length) {
            // No filter UI → show all.
            return [1, 2, 3, 4];
        }

        var active = [];
        var typeNameToId = { 'Ecole': 1, 'Collège': 2, 'Lycée': 3 };
        var hasAny = false;

        typeCbs.forEach(function (cb) {
            if (cb.checked) {
                hasAny = true;
                var id = typeNameToId[cb.value];
                if (id) active.push(id);
            }
        });

        // Always include type 4 (Autre) if at least one checkbox is checked.
        if (hasAny) active.push(4);

        return active;
    };


    // ── Transport layer toggle ────────────────────────────────────────
    FSM_Map.prototype.bindTransportToggle = function () {
        var self = this;
        var btn = this.wrapper.querySelector('.fsm-btn-transport');
        if (!btn) return;

        // Sync button visual state on init.
        if (this._transportVisible) btn.classList.add('fsm-btn-active');

        btn.addEventListener('click', function () {
            if (self._transportVisible) {
                self._hideTransportLayers();
                self._transportVisible = false;
                btn.classList.remove('fsm-btn-active');
                btn.title = i18n.showTransport || 'Show public transport';
            } else {
                self._transportVisible = true;
                btn.classList.add('fsm-btn-active');
                btn.title = i18n.hideTransport || 'Hide public transport';
                if (!self._transportDataLoaded) {
                    self._loadTransportData();
                } else {
                    self._showTransportLayers();
                }
            }
        });
    };

    // ── Hide transport GeoJSON layers ─────────────────────────────────
    FSM_Map.prototype._hideTransportLayers = function () {
        if (this._transportLinesLayer) this.map.removeLayer(this._transportLinesLayer);
        if (this._transportStationsLayer) this.map.removeLayer(this._transportStationsLayer);
    };

    // ── Show transport GeoJSON layers ─────────────────────────────────
    FSM_Map.prototype._showTransportLayers = function () {
        if (this._transportLinesLayer) this._transportLinesLayer.addTo(this.map);
        if (this._transportStationsLayer) this._transportStationsLayer.addTo(this.map);
    };

    // ── Mode → colour fallback (when colourweb_hexa is missing) ──────
    var TRANSPORT_MODE_COLORS = {
        METRO: '#003CA6',
        RER: '#18B04B',
        TRAMWAY: '#000000',
        TRAIN: '#7B4339',
        NAVETTE: '#999999',
    };

    // ── Mode → readable label ─────────────────────────────────────────
    var TRANSPORT_MODE_LABELS = {
        METRO: 'Métro',
        RER: 'RER',
        TRAMWAY: 'Tramway',
        TRAIN: 'Transilien / TER',
        NAVETTE: 'Navette / Val',
    };

    // ── Load GeoJSON data from IDFM Explore API v2 ───────────────────
    // Loads both line traces and station points, then adds them to the
    // map.  Data is fetched once and cached; subsequent toggles simply
    // add / remove the existing layers.
    FSM_Map.prototype._loadTransportData = function () {
        var self = this;
        if (this._transportDataLoaded) {
            this._showTransportLayers();
            return;
        }

        var baseUrl = 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/';

        // ── 1. Line traces (Métro, RER, Tramway, Train, Navette) ──────
        var linesUrl = baseUrl
            + 'traces-du-reseau-ferre-idf/records'
            + '?limit=100&offset=0'
            + '&select=geo_shape,mode,indice_lig,colourweb_hexa,res_com';

        // ── 2. Station points ─────────────────────────────────────────
        var stationsUrl = baseUrl
            + 'emplacement-des-gares-idf/records'
            + '?limit=100&offset=0'
            + '&select=geo_point_2d,nom_gares,mode,indice_lig';

        // Fetch all line records (paginated at 100 per page).
        var allLines = [];
        var allStations = [];

        function fetchAllPages(url, accumulator) {
            return fetch(url).then(function (r) { return r.json(); }).then(function (data) {
                if (data.results) {
                    accumulator.push.apply(accumulator, data.results);
                }
                // If there are more pages, fetch the next one.
                if (data.results && data.results.length === 100 && accumulator.length < data.total_count) {
                    var nextUrl = url.replace(/offset=\d+/, 'offset=' + accumulator.length);
                    return fetchAllPages(nextUrl, accumulator);
                }
                return accumulator;
            });
        }

        Promise.all([
            fetchAllPages(linesUrl, allLines),
            fetchAllPages(stationsUrl, allStations),
        ]).then(function (results) {
            var lines = results[0];
            var stations = results[1];

            self._transportDataLoaded = true;

            // ── Build line GeoJSON features ───────────────────────────
            var lineFeatures = [];
            for (var i = 0; i < lines.length; i++) {
                var rec = lines[i];
                if (!rec.geo_shape || !rec.geo_shape.geometry) continue;
                lineFeatures.push({
                    type: 'Feature',
                    geometry: rec.geo_shape.geometry,
                    properties: {
                        mode: rec.mode || '',
                        line: rec.indice_lig || '',
                        color: rec.colourweb_hexa ? '#' + rec.colourweb_hexa : null,
                        name: rec.res_com || '',
                    },
                });
            }
            var linesGeoJSON = { type: 'FeatureCollection', features: lineFeatures };

            // Create Leaflet GeoJSON layer for lines.
            self._transportLinesLayer = L.geoJSON(linesGeoJSON, {
                pane: 'transportPane',
                style: function (feature) {
                    var p = feature.properties;
                    var color = p.color || TRANSPORT_MODE_COLORS[p.mode] || '#666';
                    var weight = (p.mode === 'METRO') ? 4
                        : (p.mode === 'RER') ? 3.5
                            : (p.mode === 'TRAMWAY') ? 3
                                : 2.5;
                    return {
                        color: color,
                        weight: weight,
                        opacity: 0.85,
                        lineJoin: 'round',
                        lineCap: 'round',
                    };
                },
                onEachFeature: function (feature, layer) {
                    var p = feature.properties;
                    var modeLabel = TRANSPORT_MODE_LABELS[p.mode] || p.mode;
                    layer.bindTooltip(
                        '<strong>' + modeLabel + '</strong>' + (p.line ? ' ' + p.line : ''),
                        { sticky: true, className: 'fsm-transport-tooltip' }
                    );
                },
                attribution: '&copy; <a href="https://data.iledefrance-mobilites.fr/">Île-de-France Mobilités</a> — Licence Ouverte',
            });

            // ── Build station markers ─────────────────────────────────
            // The per-line dataset has one record per (station × line).
            // We group by station name so each physical station gets a
            // single marker whose tooltip lists every line serving it.
            var stationMap = {};  // key = nom_gares → { lat, lon, lines: [{mode,line}] }
            for (var j = 0; j < stations.length; j++) {
                var st = stations[j];
                if (!st.geo_point_2d || !st.nom_gares) continue;
                var key = st.nom_gares;
                if (!stationMap[key]) {
                    stationMap[key] = {
                        lat: st.geo_point_2d.lat,
                        lon: st.geo_point_2d.lon,
                        lines: [],
                    };
                }
                var modeUp = (st.mode || '').toUpperCase();
                var lineLabel = (TRANSPORT_MODE_LABELS[modeUp] || st.mode || '')
                    + (st.indice_lig ? ' ' + st.indice_lig : '');
                // Avoid duplicates (same label already added).
                if (stationMap[key].lines.indexOf(lineLabel) === -1) {
                    stationMap[key].lines.push(lineLabel);
                }
                // Keep the first mode for the dot colour.
                if (!stationMap[key].mode) stationMap[key].mode = modeUp;
            }

            self._transportStationsLayer = L.layerGroup([], { pane: 'transportStationPane' });
            var stationNames = Object.keys(stationMap);
            for (var k = 0; k < stationNames.length; k++) {
                var info = stationMap[stationNames[k]];
                var mColor = TRANSPORT_MODE_COLORS[info.mode] || '#666';
                var marker = L.circleMarker(
                    [info.lat, info.lon],
                    {
                        pane: 'transportStationPane',
                        radius: 4,
                        color: '#fff',
                        weight: 1.5,
                        fillColor: mColor,
                        fillOpacity: 0.9,
                    }
                );
                marker.bindTooltip(
                    '<strong>' + stationNames[k] + '</strong><br>' + info.lines.join(', '),
                    { className: 'fsm-transport-tooltip' }
                );
                self._transportStationsLayer.addLayer(marker);
            }

            // Add to map if transport is still toggled on.
            if (self._transportVisible) {
                self._showTransportLayers();
            }
        }).catch(function (err) {
            console.warn('FSM: failed to load transport data from IDFM', err);
        });
    };

    // ── Geolocation ──────────────────────────────────────────────────
    FSM_Map.prototype.bindLocate = function () {
        var self = this;
        var btn = this.wrapper.querySelector('.fsm-btn-locate');
        if (!btn) return;

        btn.addEventListener('click', function () {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(function (pos) {
                self.map.setView([pos.coords.latitude, pos.coords.longitude], 13);
            });
        });
    };

    // ── Fullscreen ─────────────────────────────────────────────────
    FSM_Map.prototype.bindFullscreen = function () {
        var self = this;
        var btn = this.wrapper.querySelector('.fsm-btn-fullscreen');
        if (!btn) return;

        this._isFullscreen = false;

        btn.addEventListener('click', function () {
            self.toggleFullscreen();
        });

        // Escape key exits fullscreen (fallback when native Fullscreen API is unavailable).
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self._isFullscreen
                && !document.fullscreenElement && !document.webkitFullscreenElement) {
                self.toggleFullscreen();
            }
        });

        // Sync UI when native fullscreen is exited by the browser (e.g. ESC key).
        var onFsChange = function () {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && self._isFullscreen) {
                self._setFullscreenUI(false);
                setTimeout(function () { self.map.invalidateSize(); }, 100);
            }
        };
        document.addEventListener('fullscreenchange', onFsChange);
        document.addEventListener('webkitfullscreenchange', onFsChange);
    };

    /**
     * Update DOM classes, icon and button title for fullscreen state.
     */
    FSM_Map.prototype._setFullscreenUI = function (enter) {
        var btn = this.wrapper.querySelector('.fsm-btn-fullscreen');
        var icon = btn ? btn.querySelector('.dashicons') : null;

        this._isFullscreen = enter;

        if (enter) {
            this.wrapper.classList.add('fsm-fullscreen');
            document.body.classList.add('fsm-body-fullscreen');
            if (icon) {
                icon.classList.remove('dashicons-fullscreen-alt');
                icon.classList.add('dashicons-fullscreen-exit-alt');
            }
            if (btn) btn.title = i18n.exitFullscreen;
        } else {
            this.wrapper.classList.remove('fsm-fullscreen');
            document.body.classList.remove('fsm-body-fullscreen');
            if (icon) {
                icon.classList.remove('dashicons-fullscreen-exit-alt');
                icon.classList.add('dashicons-fullscreen-alt');
            }
            if (btn) btn.title = i18n.fullscreen;
        }
    };

    FSM_Map.prototype.toggleFullscreen = function () {
        var self = this;

        if (!this._isFullscreen) {
            // ── Enter fullscreen ──
            this._setFullscreenUI(true);

            // Native Fullscreen API places the element in the browser top-layer,
            // above every other element (headers, menus, admin-bars…).
            if (this.wrapper.requestFullscreen) {
                this.wrapper.requestFullscreen().catch(function () {});
            } else if (this.wrapper.webkitRequestFullscreen) {
                this.wrapper.webkitRequestFullscreen();
            }
        } else {
            // ── Exit fullscreen ──
            this._setFullscreenUI(false);

            if (document.fullscreenElement || document.webkitFullscreenElement) {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(function () {});
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        }

        // Let the DOM reflow then tell Leaflet to recalculate size.
        setTimeout(function () {
            self.map.invalidateSize();
        }, 100);
    };

    // ── Helpers ──────────────────────────────────────────────────────
    FSM_Map.prototype.setStatsText = function (text) {
        var el = this.wrapper.querySelector('.fsm-stats-count');
        if (el) el.textContent = text;
    };

    FSM_Map.prototype.typeToId = function (typeName) {
        var map = { 'Ecole': 1, 'Collège': 2, 'Lycée': 3 };
        return map[typeName] || 4;
    };

    FSM_Map.prototype.esc = function (str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    };

    // ── Polygon clipping helpers (Voronoi sub-division) ─────────────
    // Used to split a commune polygon into sub-zones when it contains
    // multiple circonscriptions.

    /**
     * Sutherland-Hodgman: clip polygon to the half-plane
     *   (p - M) · N >= 0
     * where M = (mx,my), N = (nx,ny).
     * @param {number[][]} poly  Array of [x,y] vertices (open ring).
     * @returns {number[][]} Clipped polygon vertices.
     */
    function clipPolygonByHalfPlane(poly, mx, my, nx, ny) {
        if (!poly.length) return [];
        var output = [];
        var n = poly.length;

        for (var i = 0; i < n; i++) {
            var cur = poly[i];
            var nxt = poly[(i + 1) % n];

            var dCur = (cur[0] - mx) * nx + (cur[1] - my) * ny;
            var dNxt = (nxt[0] - mx) * nx + (nxt[1] - my) * ny;

            if (dCur >= 0) {
                output.push(cur);
                if (dNxt < 0) {
                    var t = dCur / (dCur - dNxt);
                    output.push([
                        cur[0] + t * (nxt[0] - cur[0]),
                        cur[1] + t * (nxt[1] - cur[1]),
                    ]);
                }
            } else if (dNxt >= 0) {
                var t = dCur / (dCur - dNxt);
                output.push([
                    cur[0] + t * (nxt[0] - cur[0]),
                    cur[1] + t * (nxt[1] - cur[1]),
                ]);
            }
        }
        return output;
    }

    /**
     * Compute the Voronoi cell for centroid `idx` among `centroids`,
     * clipped to `polygon`.
     * Each centroid is [lng, lat] (GeoJSON order).
     * @returns {number[][]} polygon vertices (may be empty).
     */
    function voronoiCell(polygon, centroids, idx) {
        var cell = polygon.slice();
        var ci = centroids[idx];

        for (var j = 0; j < centroids.length; j++) {
            if (j === idx) continue;
            var cj = centroids[j];

            // Midpoint (perpendicular bisector passes through it).
            var mx = (ci[0] + cj[0]) / 2;
            var my = (ci[1] + cj[1]) / 2;

            // Normal pointing toward ci.
            var nx = ci[0] - cj[0];
            var ny = ci[1] - cj[1];

            cell = clipPolygonByHalfPlane(cell, mx, my, nx, ny);
            if (!cell.length) break;
        }
        return cell;
    }

    /**
     * Extract the outer ring of a GeoJSON geometry as an array of [lng, lat].
     * Handles Polygon and MultiPolygon (uses largest ring).
     */
    function extractOuterRing(geometry) {
        if (geometry.type === 'Polygon') {
            return geometry.coordinates[0];
        }
        if (geometry.type === 'MultiPolygon') {
            // Pick the ring with the most vertices (largest polygon).
            var best = null, bestLen = 0;
            geometry.coordinates.forEach(function (poly) {
                if (poly[0].length > bestLen) {
                    bestLen = poly[0].length;
                    best = poly[0];
                }
            });
            return best || [];
        }
        return [];
    }

    // ── Circo zone overlay ───────────────────────────────────────────
    /**
     * Load commune contours for a département and colour them by circo.
     * @param {string} dept  Département label, e.g. "Haute-Garonne".
     */
    FSM_Map.prototype.loadCircoZones = function (dept) {
        var self = this;
        this.removeCircoZones();

        if (!dept || dept === 'all') return;

        var deptCode = DEPT_CODES[dept];
        if (!deptCode) {
            console.warn('[FSM] No code for département:', dept);
            return;
        }

        // Fetch in parallel: commune→circo mapping + GeoJSON contours.
        var mapUrl = buildUrl(this.config.restUrl, 'commune-circo-map', { departement: dept });
        var geoUrl = 'https://geo.api.gouv.fr/departements/' + deptCode + '/communes?format=geojson&geometry=contour';

        Promise.all([
            fetch(mapUrl, { headers: { 'X-WP-Nonce': this.config.nonce } }).then(jsonResponse),
            fetch(geoUrl).then(jsonResponse),
        ])
            .then(function (results) {
                var communeCircoMap = results[0]; // { code: "circoName" | [{circo,lat,lng}, ...] }
                var geoData = results[1];          // GeoJSON FeatureCollection

                if (!geoData || !geoData.features) return;

                // ── Helper: get the primary circo name for a commune entry ──
                function primaryCirco(entry) {
                    if (typeof entry === 'string') return entry;
                    if (Array.isArray(entry) && entry.length) return entry[0].circo;
                    return null;
                }

                // ── Fill gaps: assign unmapped communes to nearest mapped commune ──
                // Compute centroids for all GeoJSON features.
                var geoCentroids = {};
                geoData.features.forEach(function (f) {
                    var coords = f.geometry.type === 'MultiPolygon'
                        ? f.geometry.coordinates[0][0]
                        : (f.geometry.type === 'Polygon' ? f.geometry.coordinates[0] : []);
                    if (!coords.length) return;
                    var sumLng = 0, sumLat = 0;
                    coords.forEach(function (c) { sumLng += c[0]; sumLat += c[1]; });
                    geoCentroids[f.properties.code] = [sumLng / coords.length, sumLat / coords.length];
                });

                // Collect mapped codes with their geographic centroids.
                var mappedCentroids = [];
                Object.keys(communeCircoMap).forEach(function (code) {
                    if (geoCentroids[code]) {
                        mappedCentroids.push({
                            code: code,
                            c: geoCentroids[code],
                            circo: primaryCirco(communeCircoMap[code]),
                        });
                    }
                });

                // For each unmapped commune, assign to nearest mapped commune's primary circo.
                if (mappedCentroids.length > 0) {
                    geoData.features.forEach(function (f) {
                        var code = f.properties.code;
                        if (communeCircoMap[code] || !geoCentroids[code]) return;
                        var pt = geoCentroids[code];
                        var bestDist = Infinity, bestCirco = null;
                        mappedCentroids.forEach(function (m) {
                            var dx = pt[0] - m.c[0], dy = pt[1] - m.c[1];
                            var d = dx * dx + dy * dy;
                            if (d < bestDist) { bestDist = d; bestCirco = m.circo; }
                        });
                        if (bestCirco) communeCircoMap[code] = bestCirco;
                    });
                }

                // ── Build a global circo → colour index ──
                var circoNames = [];
                Object.keys(communeCircoMap).forEach(function (code) {
                    var entry = communeCircoMap[code];
                    if (typeof entry === 'string') {
                        if (circoNames.indexOf(entry) === -1) circoNames.push(entry);
                    } else if (Array.isArray(entry)) {
                        entry.forEach(function (e) {
                            if (circoNames.indexOf(e.circo) === -1) circoNames.push(e.circo);
                        });
                    }
                });
                circoNames.sort();
                var circoColorMap = {};
                circoNames.forEach(function (name, i) {
                    circoColorMap[name] = CIRCO_COLORS[i % CIRCO_COLORS.length];
                });

                // ── Build GeoJSON features ──
                // For multi-circo communes we split the polygon into Voronoi
                // sub-zones (one per circo).  Single-circo communes keep the
                // original polygon.
                var features = [];

                geoData.features.forEach(function (f) {
                    var code = f.properties.code;
                    var entry = communeCircoMap[code];

                    if (!entry) {
                        // Unmapped — keep original feature with no circo.
                        features.push({ type: 'Feature', properties: { code: code, circo: null }, geometry: f.geometry });
                        return;
                    }

                    // Single circo (string) — keep the original polygon.
                    if (typeof entry === 'string') {
                        features.push({ type: 'Feature', properties: { code: code, circo: entry }, geometry: f.geometry });
                        return;
                    }

                    // Multi-circo (array) — split the polygon into Voronoi sub-zones.
                    var outerRing = extractOuterRing(f.geometry);
                    if (!outerRing || outerRing.length < 3) {
                        // Fallback: use primary circo.
                        features.push({ type: 'Feature', properties: { code: code, circo: entry[0].circo }, geometry: f.geometry });
                        return;
                    }

                    // Remove the closing vertex if it duplicates the first (GeoJSON convention).
                    var ring = outerRing.slice();
                    var last = ring[ring.length - 1];
                    if (ring.length > 1 && last[0] === ring[0][0] && last[1] === ring[0][1]) {
                        ring.pop();
                    }

                    // Centroids in [lng, lat] (GeoJSON order).
                    var circoCentroids = entry.map(function (e) { return [e.lng, e.lat]; });

                    entry.forEach(function (e, idx) {
                        var cell = voronoiCell(ring, circoCentroids, idx);
                        if (cell.length >= 3) {
                            // Close the ring for GeoJSON.
                            var closed = cell.slice();
                            closed.push(closed[0]);
                            features.push({
                                type: 'Feature',
                                properties: { code: code, circo: e.circo },
                                geometry: { type: 'Polygon', coordinates: [closed] },
                            });
                        }
                    });
                });

                var splitGeoData = { type: 'FeatureCollection', features: features };

                // Build the Leaflet GeoJSON layer.
                self._circoLayer = L.geoJSON(splitGeoData, {
                    pane: 'circoPane',
                    style: function (feature) {
                        var circo = feature.properties.circo;
                        var color = circo ? circoColorMap[circo] : '#cccccc';
                        return {
                            color: color,
                            weight: 1,
                            opacity: circo ? 0.5 : 0.15,
                            fillColor: color,
                            fillOpacity: circo ? 0.18 : 0.03,
                        };
                    },
                    onEachFeature: function (feature, layer) {
                        var circo = feature.properties.circo;
                        if (circo) {
                            layer.bindTooltip(cleanNomCirconscription(circo), {
                                sticky: true,
                                className: 'fsm-circo-tooltip',
                            });
                        }
                    },
                }).addTo(self.map);
            })
            .catch(function (err) {
                console.warn('[FSM] Failed to load circo zones', err);
            });
    };

    /**
     * Remove the current circo zone overlay from the map.
     */
    FSM_Map.prototype.removeCircoZones = function () {
        if (this._circoLayer) {
            this.map.removeLayer(this._circoLayer);
            this._circoLayer = null;
        }
    };

})();
