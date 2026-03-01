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
        // _schoolCache: id → full school object, pre-fetched when result < 1500.
        // _markerById:  id → L.marker, rebuilt on each full render.
        this._schoolCache = {};
        this._markerById = {};

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

        // Legend.
        this.addLegend();

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
            self.loadMarkers(hasDefault);
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

            var marker = L.marker([lat, lng], { icon: makeIcon(typeId) });

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
                self.loadMarkers(true);
            });
        }
        if (deptSelect) {
            deptSelect.addEventListener('change', function () {
                self._userChangedGeo = true;
                if (deptSelect.value !== 'all' && acadSelect) {
                    acadSelect.value = 'all';
                }
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
        if (search && search.value.length >= 2) params.search = search.value;

        // Fallback for statut config default when no widget.
        if (!statut && this.config.statut !== 'all') params.statut = this.config.statut;

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

        // Escape key exits fullscreen.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self._isFullscreen) {
                self.toggleFullscreen();
            }
        });
    };

    FSM_Map.prototype.toggleFullscreen = function () {
        var btn = this.wrapper.querySelector('.fsm-btn-fullscreen');
        var icon = btn ? btn.querySelector('.dashicons') : null;

        this._isFullscreen = !this._isFullscreen;

        if (this._isFullscreen) {
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

        // Let the DOM reflow then tell Leaflet to recalculate size.
        var self = this;
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

})();
