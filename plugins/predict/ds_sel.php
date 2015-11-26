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
*/
// This program is whether called with arg "hostrech" to display hostrech's 
// datasources, whether without it and then display session var host_id's DS
// (this var means previously selected host) if this var exists,
// if it does not display unattached DS
//
include_once("../../include/global.php");

// hostrech is searched host's id
if ( isset( $_GET['hostrech'] ) ) {
    $hostrech = $_GET['hostrech'];
} else {
    if ( isset( $_SESSION['host_id'] ) ) {
        $hostrech = $_SESSION['host_id'];
    } else {
        $hostrech = 0;
    }
}

if ($database_type === "mysql") {
    $link = mysql_connect( $database_hostname, $database_username, $database_password ) or die( 'Could not connect: ' . mysql_error() );
    mysql_selectdb( $database_default, $link ) or die('Could not select database: '.mysql_error());
} else {
    // The PHP PostgreSQL error functions all seem to require a working connection resource in order to report error details, but there
    // is no such resource around in the event of a connection failure.  So we just punt and issue a standard message, without details.
    $link = pg_connect("host='$database_hostname' port='$database_port' dbname='$database_default' user='$database_username' password='$database_password' connect_timeout=10")
    or die( "Could not connect to $database_default database on $database_hostname port $database_port." );
}
// Get only DS about allowed graphs
$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
$ds_list =  db_fetch_assoc("SELECT DISTINCT data_template_rrd.id,data_template_data.name_cache,data_template_rrd.data_source_name
                FROM data_template_data CROSS JOIN data_template_rrd CROSS JOIN graph_templates_item CROSS JOIN graph_local CROSS JOIN graph_templates_graph
                LEFT JOIN host ON (host.id=graph_local.host_id)
                LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
                LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
                WHERE graph_templates_item.task_item_id=data_template_rrd.id AND data_template_rrd.local_data_id=data_template_data.local_data_id AND graph_templates_item.local_graph_id=graph_local.id AND graph_local.id=graph_templates_graph.local_graph_id AND graph_local.host_id=$hostrech " . (empty($sql_where) ? "" : "AND $sql_where") . 
                " ORDER BY name_cache,data_source_name");

//Display each allowed datasource as OPTION in SELECT box
foreach ( $ds_list as $ds ) {
     print "<OPTION VALUE='" . $ds["id"] . "'>". title_trim(htmlspecialchars($ds["data_source_name"]) . ' ' . htmlspecialchars($ds["name_cache"]), 80) ."</OPTION>\n";
}
?>
