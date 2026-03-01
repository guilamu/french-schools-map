<?php
/**
 * Académies data: mapping of French académies to their départements.
 *
 * @package French_Schools_Map
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSM_Academies
{
    /**
     * Get the full mapping of académie → départements.
     *
     * Source: Ministère de l'Éducation Nationale (2024).
     * Uses département labels as they appear in the annuaire Open Data.
     *
     * @return array Associative array: académie name => array of département names.
     */
    public static function get_map()
    {
        return array(
            'Aix-Marseille' => array(
                'Alpes-de-Haute-Provence',
                'Hautes-Alpes',
                'Bouches-du-Rhône',
                'Vaucluse',
            ),
            'Amiens' => array(
                'Aisne',
                'Oise',
                'Somme',
            ),
            'Besançon' => array(
                'Doubs',
                'Jura',
                'Haute-Saône',
                'Territoire de Belfort',
            ),
            'Bordeaux' => array(
                'Dordogne',
                'Gironde',
                'Landes',
                'Lot-et-Garonne',
                'Pyrénées-Atlantiques',
            ),
            'Clermont-Ferrand' => array(
                'Allier',
                'Cantal',
                'Haute-Loire',
                'Puy-de-Dôme',
            ),
            'Corse' => array(
                'Corse-du-Sud',
                'Haute-Corse',
            ),
            'Créteil' => array(
                'Seine-et-Marne',
                'Seine-Saint-Denis',
                'Val-de-Marne',
            ),
            'Dijon' => array(
                'Côte-d\'Or',
                'Nièvre',
                'Saône-et-Loire',
                'Yonne',
            ),
            'Grenoble' => array(
                'Ardèche',
                'Drôme',
                'Isère',
                'Savoie',
                'Haute-Savoie',
            ),
            'Guadeloupe' => array(
                'Guadeloupe',
            ),
            'Guyane' => array(
                'Guyane',
            ),
            'Lille' => array(
                'Nord',
                'Pas-de-Calais',
            ),
            'Limoges' => array(
                'Corrèze',
                'Creuse',
                'Haute-Vienne',
            ),
            'Lyon' => array(
                'Ain',
                'Loire',
                'Rhône',
            ),
            'Martinique' => array(
                'Martinique',
            ),
            'Mayotte' => array(
                'Mayotte',
            ),
            'Montpellier' => array(
                'Aude',
                'Gard',
                'Hérault',
                'Lozère',
                'Pyrénées-Orientales',
            ),
            'Nancy-Metz' => array(
                'Meurthe-et-Moselle',
                'Meuse',
                'Moselle',
                'Vosges',
            ),
            'Nantes' => array(
                'Loire-Atlantique',
                'Maine-et-Loire',
                'Mayenne',
                'Sarthe',
                'Vendée',
            ),
            'Nice' => array(
                'Alpes-Maritimes',
                'Var',
            ),
            'Normandie' => array(
                'Calvados',
                'Eure',
                'Manche',
                'Orne',
                'Seine-Maritime',
            ),
            'Orléans-Tours' => array(
                'Cher',
                'Eure-et-Loir',
                'Indre',
                'Indre-et-Loire',
                'Loir-et-Cher',
                'Loiret',
            ),
            'Paris' => array(
                'Paris',
            ),
            'Poitiers' => array(
                'Charente',
                'Charente-Maritime',
                'Deux-Sèvres',
                'Vienne',
            ),
            'Reims' => array(
                'Ardennes',
                'Aube',
                'Marne',
                'Haute-Marne',
            ),
            'Rennes' => array(
                'Côtes-d\'Armor',
                'Finistère',
                'Ille-et-Vilaine',
                'Morbihan',
            ),
            'Réunion' => array(
                'La Réunion',
            ),
            'Strasbourg' => array(
                'Bas-Rhin',
                'Haut-Rhin',
            ),
            'Toulouse' => array(
                'Ariège',
                'Aveyron',
                'Haute-Garonne',
                'Gers',
                'Lot',
                'Hautes-Pyrénées',
                'Tarn',
                'Tarn-et-Garonne',
            ),
            'Versailles' => array(
                'Yvelines',
                'Essonne',
                'Hauts-de-Seine',
                'Val-d\'Oise',
            ),
        );
    }

    /**
     * Get sorted list of académie names.
     *
     * @return array
     */
    public static function get_names()
    {
        $names = array_keys(self::get_map());
        sort($names, SORT_LOCALE_STRING);
        return $names;
    }

    /**
     * Get départements for a given académie.
     *
     * @param string $academie Académie name.
     * @return array List of département names, empty if not found.
     */
    public static function get_departments($academie)
    {
        $map = self::get_map();
        return $map[$academie] ?? array();
    }

    /**
     * Find the académie for a given département.
     *
     * @param string $departement Département name.
     * @return string|null Académie name or null.
     */
    public static function find_by_department($departement)
    {
        foreach (self::get_map() as $acad => $depts) {
            if (in_array($departement, $depts, true)) {
                return $acad;
            }
        }
        return null;
    }
}
