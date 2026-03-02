/**
 * French Schools Map — Gutenberg Block.
 *
 * @package French_Schools_Map
 */

(function (blocks, element, blockEditor, components, i18n) {
    'use strict';

    var el = element.createElement;
    var registerBlock = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var Placeholder = components.Placeholder;
    var __ = i18n.__;

    // Data passed from PHP (dropdown options).
    var blockData = window.fsmBlockData || { departments: [], academies: [], defaultDept: 'all', defaultAcad: 'all', restUrl: '', nonce: '' };

    // Build dropdown options.
    var deptOptions = [{ label: __('Tous', 'french-schools-map'), value: 'all' }];
    (blockData.departments || []).forEach(function (d) {
        deptOptions.push({ label: d, value: d });
    });

    var acadOptions = [{ label: __('Toutes', 'french-schools-map'), value: 'all' }];
    (blockData.academies || []).forEach(function (a) {
        acadOptions.push({ label: a, value: a });
    });

    // Global admin locks.
    var globalAcad = blockData.defaultAcad && blockData.defaultAcad !== 'all' ? blockData.defaultAcad : null;
    var globalDept = blockData.defaultDept && blockData.defaultDept !== 'all' ? blockData.defaultDept : null;
    var lockNotice = __('Verrouillé par les réglages globaux du plugin.', 'french-schools-map');

    // ── Clean circonscription name (same logic as frontend) ──────────
    function cleanNomCirconscription(value) {
        if (!value) return '';
        var cleaned = value;
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
        patterns.forEach(function (p) { cleaned = cleaned.replace(p, ''); });
        return cleaned.trim();
    }

    // ── Circonscription select component (fetches options on dept change) ─
    var useState  = element.useState;
    var useEffect = element.useEffect;

    function FSMCircoSelect(props) {
        var stateOpts = useState([{ label: __('Toutes', 'french-schools-map'), value: 'all' }]);
        var options   = stateOpts[0];
        var setOpts   = stateOpts[1];
        var stateLoad = useState(false);
        var loading   = stateLoad[0];
        var setLoad   = stateLoad[1];

        useEffect(function () {
            if (!props.dept || props.dept === 'all' || !blockData.restUrl) {
                setOpts([{ label: __('Toutes', 'french-schools-map'), value: 'all' }]);
                return;
            }
            setLoad(true);
            // Build URL carefully: restUrl may already contain '?' (plain permalinks).
            var base = blockData.restUrl + 'circonscriptions';
            var sep  = base.indexOf('?') !== -1 ? '&' : '?';
            var url  = base + sep + 'departement=' + encodeURIComponent(props.dept);
            fetch(url, { headers: { 'X-WP-Nonce': blockData.nonce } })
                .then(function (r) { return r.json(); })
                .then(function (circos) {
                    var opts = [{ label: __('Toutes', 'french-schools-map'), value: 'all' }];
                    (circos || []).forEach(function (c) {
                        opts.push({ label: cleanNomCirconscription(c), value: c });
                    });
                    setOpts(opts);
                    setLoad(false);
                })
                .catch(function () { setLoad(false); });
        }, [props.dept]);

        return el(SelectControl, {
            label: __('Circonscription', 'french-schools-map'),
            value: props.value,
            options: options,
            disabled: loading,
            onChange: props.onChange,
            help: loading ? __('Chargement…', 'french-schools-map') : undefined,
        });
    }

    registerBlock('french-schools-map/map', {
        title: __('French Schools Map', 'french-schools-map'),
        description: __('Carte interactive des établissements scolaires français.', 'french-schools-map'),
        icon: 'location-alt',
        category: 'embed',
        keywords: [
            __('carte', 'french-schools-map'),
            __('école', 'french-schools-map'),
            __('map', 'french-schools-map'),
        ],

        attributes: {
            height: { type: 'string', default: '600px' },
            center_lat: { type: 'string', default: '46.603354' },
            center_lng: { type: 'string', default: '1.888334' },
            zoom: { type: 'string', default: '6' },
            types: { type: 'string', default: 'all' },
            departement: { type: 'string', default: 'all' },
            academie: { type: 'string', default: 'all' },
            statut: { type: 'string', default: 'all' },
            circonscription: { type: 'string', default: 'all' },
            show_filters: { type: 'string', default: 'true' },
            show_search: { type: 'string', default: 'true' },
            show_filter_academie: { type: 'string', default: 'true' },
            show_filter_dept: { type: 'string', default: 'true' },
            show_filter_statut: { type: 'string', default: 'true' },
            show_filter_types: { type: 'string', default: 'true' },
            show_filter_ep: { type: 'string', default: 'true' },
            cluster: { type: 'string', default: 'false' },
        },

        edit: function (props) {
            var attrs = props.attributes;

            function setAttr(key) {
                return function (val) {
                    var o = {};
                    o[key] = val;
                    props.setAttributes(o);
                };
            }

            return el(
                element.Fragment,
                null,

                // Inspector panel
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Paramètres de la carte', 'french-schools-map'), initialOpen: true },
                        el(TextControl, {
                            label: __('Hauteur', 'french-schools-map'),
                            value: attrs.height,
                            onChange: setAttr('height'),
                            help: __('Ex: 600px, 80vh', 'french-schools-map'),
                        }),
                        el(TextControl, {
                            label: __('Zoom initial', 'french-schools-map'),
                            value: attrs.zoom,
                            onChange: setAttr('zoom'),
                            type: 'number',
                        }),
                        el(TextControl, {
                            label: __('Latitude centre', 'french-schools-map'),
                            value: attrs.center_lat,
                            onChange: setAttr('center_lat'),
                        }),
                        el(TextControl, {
                            label: __('Longitude centre', 'french-schools-map'),
                            value: attrs.center_lng,
                            onChange: setAttr('center_lng'),
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('Filtres', 'french-schools-map'), initialOpen: false },
                        el(SelectControl, {
                            label: __('Types d\'établissement', 'french-schools-map'),
                            value: attrs.types,
                            options: [
                                { label: __('Tous', 'french-schools-map'), value: 'all' },
                                { label: __('Écoles', 'french-schools-map'), value: 'Ecole' },
                                { label: __('Collèges', 'french-schools-map'), value: 'Collège' },
                                { label: __('Lycées', 'french-schools-map'), value: 'Lycée' },
                                { label: __('Écoles + Collèges', 'french-schools-map'), value: 'Ecole,Collège' },
                                { label: __('Collèges + Lycées', 'french-schools-map'), value: 'Collège,Lycée' },
                            ],
                            onChange: setAttr('types'),
                        }),
                        el(SelectControl, {
                            label: __('Statut', 'french-schools-map'),
                            value: attrs.statut,
                            options: [
                                { label: __('Tous', 'french-schools-map'), value: 'all' },
                                { label: __('Public', 'french-schools-map'), value: 'Public' },
                                { label: __('Privé', 'french-schools-map'), value: 'Privé' },
                            ],
                            onChange: setAttr('statut'),
                        }),
                        el(SelectControl, {
                            label: __('Académie', 'french-schools-map'),
                            value: globalAcad || attrs.academie,
                            options: acadOptions,
                            disabled: !!globalAcad,
                            onChange: function (val) {
                                var o = { academie: val };
                                if (val !== 'all') o.departement = 'all';
                                props.setAttributes(o);
                            },
                            help: globalAcad ? lockNotice : __('Priorité au département si les deux sont renseignés.', 'french-schools-map'),
                        }),
                        el(SelectControl, {
                            label: __('Département', 'french-schools-map'),
                            value: globalDept || attrs.departement,
                            options: deptOptions,
                            disabled: !!globalDept || !!globalAcad,
                            onChange: function (val) {
                                var o = { departement: val, circonscription: 'all' };
                                if (val !== 'all') o.academie = 'all';
                                props.setAttributes(o);
                            },
                            help: (globalDept || globalAcad) ? lockNotice : undefined,
                        }),
                        // Circonscription selector — only when a département is selected.
                        (function () {
                            var effectiveDept = globalDept || attrs.departement;
                            if (!effectiveDept || effectiveDept === 'all') return null;

                            // Use React state via element.useState to fetch options.
                            var useState  = element.useState;
                            var useEffect = element.useEffect;

                            return el(FSMCircoSelect, {
                                dept: effectiveDept,
                                value: attrs.circonscription || 'all',
                                onChange: setAttr('circonscription'),
                                restUrl: blockData.restUrl,
                                nonce: blockData.nonce,
                            });
                        })()
                    ),
                    el(
                        PanelBody,
                        { title: __('Affichage', 'french-schools-map'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Afficher la barre de filtres', 'french-schools-map'),
                            checked: attrs.show_filters === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filters: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Clustering', 'french-schools-map'),
                            checked: attrs.cluster === 'true',
                            onChange: function (val) {
                                props.setAttributes({ cluster: val ? 'true' : 'false' });
                            },
                        })
                    ),
                    // Per-filter visibility (only shown when filters bar is enabled).
                    attrs.show_filters === 'true' ? el(
                        PanelBody,
                        { title: __('Filtres visibles', 'french-schools-map'), initialOpen: false },
                        el('p', { style: { fontStyle: 'italic', color: '#757575', marginTop: 0 } },
                            __('Choisissez les filtres affichés sur la carte.', 'french-schools-map')
                        ),
                        el(ToggleControl, {
                            label: __('Recherche', 'french-schools-map'),
                            checked: attrs.show_search === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_search: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Académie', 'french-schools-map'),
                            checked: attrs.show_filter_academie === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filter_academie: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Département', 'french-schools-map'),
                            checked: attrs.show_filter_dept === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filter_dept: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Statut (Public/Privé)', 'french-schools-map'),
                            checked: attrs.show_filter_statut === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filter_statut: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Types (Écoles/Collèges/Lycées)', 'french-schools-map'),
                            checked: attrs.show_filter_types === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filter_types: val ? 'true' : 'false' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Éducation prioritaire', 'french-schools-map'),
                            checked: attrs.show_filter_ep === 'true',
                            onChange: function (val) {
                                props.setAttributes({ show_filter_ep: val ? 'true' : 'false' });
                            },
                        })
                    ) : null
                ),

                // Editor preview placeholder
                el(
                    Placeholder,
                    {
                        icon: 'location-alt',
                        label: __('French Schools Map', 'french-schools-map'),
                        instructions: __('La carte sera affichée en frontend. Configurez les paramètres dans le panneau latéral.', 'french-schools-map'),
                    },
                    el('div', { className: 'fsm-block-preview' },
                        el('p', null,
                            '🗺️ ',
                            attrs.types !== 'all' ? attrs.types : __('Tous les types', 'french-schools-map'),
                            ' · ',
                            attrs.statut !== 'all' ? attrs.statut : __('Tous statuts', 'french-schools-map'),
                            ' · ',
                            attrs.academie !== 'all' ? attrs.academie : (attrs.departement !== 'all' ? attrs.departement : __('Tous départements', 'french-schools-map')),
                            attrs.circonscription && attrs.circonscription !== 'all' ? ' · ' + cleanNomCirconscription(attrs.circonscription) : ''
                        ),
                        el('p', null,
                            __('Hauteur', 'french-schools-map'), ': ', attrs.height,
                            ' · Zoom: ', attrs.zoom
                        )
                    )
                )
            );
        },

        save: function () {
            // Dynamic block: rendered server-side.
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
