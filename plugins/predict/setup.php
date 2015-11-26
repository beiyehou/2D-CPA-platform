<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 Herve Donati <herve.donati@ac-caen.fr>               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 |                                                                         |
 |  Author    : Herve Donati                                               |
 |  Contact   : herve.donati@ac-caen.fr                                    |
 |  Home Site : http://www.ac-caen.fr/                                     |
 |  Program   : predict                                                    |
 |  Version   : 1.0.0                                                      |
 |  Purpose   : Predict datasource behaviour                               |
 +-------------------------------------------------------------------------+
*/


function plugin_predict_install () {
    global $database_type;
    $rrd_ver = read_config_option("rrdtool_version");
    if ( $rrd_ver < "rrd-1.4" ) {
        die( "<br/>rrdtool must be at least 1.4 to use predict. Your version is $rrd_ver<br />Please go back to cacti within your browser<br/>" );
    } else {
        api_plugin_register_hook('predict', 'top_header_tabs', 'predict_show_tab', 'setup.php');
        api_plugin_register_hook('predict', 'top_graph_header_tabs', 'predict_show_tab', 'setup.php');
        api_plugin_register_hook('predict', 'draw_navigation_text', 'predict_draw_navigation_text', 'setup.php');
        api_plugin_register_hook('predict', 'config_settings', 'predict_config_settings', 'setup.php');
        api_plugin_register_realm('predict', 'predict.php', 'Plugin -> Predict', 1);
        $sql = "INSERT INTO settings VALUES ( 'predict_def_tspan', 2 ), ( 'predict_def_units', 3 ), ( 'predict_def_window', 300 ), ( 'predict_def_typeds', 0 ), ( 'predict_def_typepred', 2 ), ( 'predict_def_colds', 7 ), ( 'predict_def_colpred', 9 ), ( 'predict_def_width', 700 ), ( 'predict_def_height', 300 )";
        if ($database_type === "mysql") {
            $result = mysql_query($sql) or die (mysql_error());
        } else {
            $result = pg_query($sql) or die (pg_last_error());
        }
    }
}

function plugin_predict_uninstall () {
    db_execute("DELETE FROM settings WHERE name LIKE 'predict_%'");
}

function plugin_predict_version () {
    return array ( 'name'     => 'predict',
                   'version'  => '1.0.0',
                   'longname' => 'Predict datasource normal value',
                   'author'   => 'Herve Donati',
                   'homepage' => 'http://www.cacti.net',
                   'email'    => 'herve.donati@ac-caen.fr',
                   'url'      => ''
                 );
}

function predict_version () {
    return plugin_predict_version();
}

function predict_config_settings()
{
    global $tabs, $settings;
    $tabs["misc"] = "Misc";

    // Fill defaults
    $def_tspan    = read_config_option("predict_def_tspan");
    if (!isset($def_tspan)) { $def_tspan='Week'; };
    $def_units    = read_config_option("predict_def_units");
    if (!isset($def_units)) { $def_units=3; };
    $def_window   = read_config_option("predict_def_window");
    if (!isset($def_window)) { $def_window=300; };
    $def_typeds   = read_config_option("predict_def_typeds");
    if (!isset($def_typeds)) { $def_typeds='AREA'; };
    $def_typepred = read_config_option("predict_def_typepred");
    if (!isset($def_typepred)) { $def_typepred='LINE2'; };
    $def_colds    = read_config_option("predict_def_colds");
    if (!isset($def_colds)) { $def_colds='2175D9'; };
    $def_colpred  = read_config_option("predict_def_colpred");
    if (!isset($def_colpred)) { $def_colpred='FF0033'; };
    $def_width    = read_config_option("predict_def_width");
    if (!isset($def_width)) { $def_width=700; };
    $def_height   = read_config_option("predict_def_height");
    if (!isset($def_height)) { $def_height=300; };
    // Get colors
    $sql_colors = db_fetch_assoc("SELECT * FROM colors ORDER BY hex");
    $tab_colors=array();
    foreach ($sql_colors as $color) {
        $tab_colors[$color['id']]=$color['hex'];
    }
    $temp = array (
        "predict_header" => array (
            "friendly_name" => "Predict default values",
            "method" => "spacer",
        ),
        "predict_def_tspan"    => array (
            "friendly_name"    => "Timespan",
            "description"      => "Timespan to calculate prediction",
            "method"           => "drop_array",
            "default"          => $def_tspan,
            "array"            => array (
                0 => "Hour",
                1 => "Day",
                2 => "Week",
                3 => "Month",
                4 => "Year"
            )
        ),
        "predict_def_units"    => array(
            "friendly_name"    => "Units",
            "description"      => "Number of timespans to calculate prediction",
            "method"           => "textbox",
            "default"          => $def_units,
            "max_length"       => 3
        ),
        "predict_def_window"   => array(
            "friendly_name"    => "Window",
            "description"      => "Window used to calculate prediction (in seconds)",
            "method"           => "textbox",
            "default"          => $def_window,
            "max_length"       => 6
        ),
        "predict_def_typeds"   => array(
            "friendly_name"    => "Typeds",
            "description"      => "Datasource type in built graph", 
            "method"           => "drop_array",
            "default"          => $def_typeds,
            "array"            => array (
                0 => "AREA",
                1 => "LINE1",
                2 => "LINE2",
                3 => "LINE3"
            )
        ),
        "predict_def_colds"    => array(
            "friendly_name"    => "Colds",
            "description"      => "Datasource color in built graph", 
            "method"           => "drop_array",
            "default"          => $def_colds,
            "array"            => $tab_colors
        ),
        "predict_def_typepred" => array(
            "friendly_name"    => "Typepred",
            "description"      => "Prediction type in built graph", 
            "method"           => "drop_array",
            "default"          => $def_typepred,
            "array"            => array (
                0 => "AREA",
                1 => "LINE1",
                2 => "LINE2",
                3 => "LINE3"
            )
        ),
        "predict_def_colpred"  => array(
            "friendly_name"    => "Colpred",
            "description"      => "Prediction color in built graph", 
            "method"           => "drop_array",
            "default"          => $def_colpred,
            "array"            => $tab_colors
        ),
        "predict_def_width"    => array(
            "friendly_name"    => "Width",
            "description"      => "Prediction graph width",
            "method"           => "textbox",
            "default"          => $def_width,
            "max_length"       => 4
        ),
        "predict_def_height"   => array(
            "friendly_name"    => "Height",
            "description"      => "Prediction graph height",
            "method"           => "textbox",
            "default"          => $def_height,
            "max_length"       => 3
        )
    );
    if (isset($settings["misc"])) {
        $settings["misc"] = array_merge($settings["misc"], $temp);
    } else {
        $settings["misc"] = $temp;
    }
}

function plugin_predict_check_config () {
    return true;
}

function predict_show_tab () {
    global $config;
    if (api_user_realm_auth('predict.php')) {
        if (!isset($_SERVER['REQUEST_URI']))
            {
            $_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'],0 );
            if (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != "")
                {
                $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
                }
            }
        if (substr_count($_SERVER["REQUEST_URI"], "predict")) {
            print '<a href="' . $config['url_path'] . 'plugins/predict/predict.php"><img src="' . $config['url_path'] . 'plugins/predict/images/tab_predict_down.png" alt="predict" align="absmiddle" border="0"></a>';
        } else {
            print '<a href="' . $config['url_path'] . 'plugins/predict/predict.php"><img src="' . $config['url_path'] . 'plugins/predict/images/tab_predict.png" alt="predict" align="absmiddle" border="0"></a>';
        }
    }
}

function predict_draw_navigation_text($nav)
{
    $nav["predict.php:"] = array("title" => "predict", "mapping" => "", "url" => "predict.php", "level" => "0");
    $nav["predict_res.php:"] = array("title" => "predict Result", "mapping" => "", "url" => "predict_res.php", "level" => "0");
    return $nav;
}
?>
