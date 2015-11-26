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

include_once("../../include/global.php");
include_once("../../lib/rrd.php");
include_once("top_general_header.php");

if (
    !isset(
        $_POST["ds_id"], $_POST["date_start"], $_POST["date_end"],  $_POST["tspan"],  $_POST["units"],
        $_POST["win"],   $_POST["type_ds"],    $_POST["type_pred"], $_POST["col_ds"], $_POST["col_pred"],
        $_POST["width"], $_POST["height"],     $_POST["host_id"]
    )
  )
{
    // The existing page refresh is not passing along the original parameters.  Most likely,
    // that's because the original parameters were passed in via a POST operation, so they are
    // not available in the URL that is refreshed via a GET operation.  (The page refresh interval
    // is set in Cacti > Console > User Management > {username} > Graph Settings > Page Refresh.)
    // Also note that you would probably want the page refresh to be more sophisticated than just
    // displaying the exact same graph:  you likely would want it to update the date_start and
    // date_end by incremental time since the last display.  So some rework is probably needed in
    // the top_general_header.php file, on both these counts.  It may be necessary for that file
    // to stop using the "<meta http-equiv=refresh>" mechanism, and switch to using some JavaScript
    // that will start a timer on page load, then at timer expiration, reload the page with a POST
    // operation using updated parameters.
    print "Page refresh has not passed on the data parameters.<br style='display: block; margin: 0.25em; line-height: 0.50em'>";
} else {
    // Initialize
    $ds_id       = $_SESSION["ds_id"]      = $_POST["ds_id"];
    $date_start  = $_SESSION["date_start"] = $_POST["date_start"];
    $date_end    = $_SESSION["date_end"]   = $_POST["date_end"];
    $tspan       = $_SESSION["tspan"]      = $_POST["tspan"];
    $units       = $_SESSION["units"]      = $_POST["units"];
    $win         = $_SESSION["win"]        = $_POST["win"];
    $type_ds     = $_SESSION["type_ds"]    = $_POST["type_ds"];
    $type_pred   = $_SESSION["type_pred"]  = $_POST["type_pred"];
    $col_ds      = $_SESSION["col_ds"]     = $_POST["col_ds"];
    $col_pred    = $_SESSION["col_pred"]   = $_POST["col_pred"];
    $width       = $_POST["width"];
    $height      = $_POST["height"];
    // To keep same host when going back
    $_SESSION["host_id"] = $_POST["host_id"];
    $time_period = array(
                    3600     => "hour",
                    86400    => "day",
                    604800   => "week",
                    2592000  => "month",
                    31536000 => "year",
                   );
    $req_db      = "SELECT name_cache,data_source_path,data_source_name 
                    FROM data_template_data,data_template_rrd 
                    WHERE data_template_data.local_data_id=data_template_rrd.local_data_id AND 
                          data_template_rrd.id=$ds_id";
    $ds_resreq   = db_fetch_row( $req_db );
    $ds_name     = $ds_resreq["name_cache"];
    $ds_path     =  str_replace('<path_rra>', $config['rra_path'], $ds_resreq["data_source_path"]);
    // For windows users
    $ds_path     =  str_replace( ':', '\:', $ds_path );
    $ds_var      = $ds_resreq["data_source_name"];

    // File name hashed if possible
    if ( extension_loaded( 'hash' ) ) {
        $hash_calc   = hash('md5', "$ds_id$date_start$date_end$tspan$units$win");
    } else {
        $hash_calc = preg_replace('/\W/','',"$ds_id$date_start$date_end$tspan$units$win");
    }
    $file_name   = 'f_' . $hash_calc . '.png';
    $file_rel    = $config['url_path'] . "plugins/predict/tmp/$file_name";
    $file_abs    = $_SESSION["file_del"] = 'tmp/' . $file_name;

    // Header
    print "<TABLE width='100%'>\n";
    print "    <TR bgcolor=" . $colors["header"] . ">\n";
    print "        <TD colspan='3' class='textHeaderDark'>\n";
    print "            <STRONG>数据源</STRONG> <I>" . htmlspecialchars($ds_name) . "</I> <STRONG>在 $date_start 到 $date_end 之间的特性</STRONG>\n";
    print "        </TD>\n";
    print "    </TR>\n";
    print "    <TR>\n";
    print "        <TD>\n";
    print "            <SMALL>预测结果基于前 $units " . $time_period[$tspan] . "(s), 窗口为 $win 秒</SMALL>\n";
    print "        </TD>\n";
    print "    </TR>\n";

    // Time values
    $start     = strtotime($date_start);
    $end       = strtotime($date_end);
    $start_a   = $start-($units*$tspan);
    // Display colors/types
    $disp_a    = "$type_ds:a#$col_ds";
    $disp_pred = "$type_pred:predict#$col_pred";
    // Must increment unit (current timespan is included)
    $units     = $units + 1;
    // For windows users (Thank you Praveen Kumar)
    $ds_name   = str_replace( " ", "", $ds_name );
    $ds_var    = str_replace( " ", "", $ds_var );
    // Build graph options
    $gr_ops   = "--imgformat=PNG --title='$ds_name/$ds_var' --height=$height --width=$width --alt-autoscale-max --slope-mode --start=$start --end=$end DEF:a=\"$ds_path\":$ds_var:AVERAGE:start=$start_a $disp_a:'采集数据' CDEF:predict=$tspan,-$units,$win,a,PREDICT $disp_pred:'预测值'";
    @rrdtool_execute("graph $file_abs $gr_ops", true, RRDTOOL_OUTPUT_NULL);
    print "    <TR>\n";
    print "        <TD>\n";
    print '            <IMG src="' . $file_rel . '" alt="">';
    print "        </TD>\n";
    print "    </TR>\n";

    
    print "<TR class='textHeaderDark' style='color:white;font-size=12px;background-color:#00438C;line-height:120%;'><TD>二维预测算法</TD></TR>";

    $src = $config['url_path']."graph_image.php?local_graph_id=45&amp;rra_id=0&amp;graph_height=".$height."&amp;graph_width=".$width."&amp;title_font_size=10&amp;view_type=tree&amp;graph_start=".$start."&amp;graph_end=".$end;
    
    print "    <TR>\n";
    print "        <TD>\n";
    print '            <IMG src="' . $src . '">';
    print "        </TD>\n";
    print "    </TR>\n";
    


    print "</TABLE>\n";




}



?>

<TABLE style="margin-top:2em;">
    <TR>
    <INPUT style="margin:0px;boder:0px;" type="button" name="button_back" value="返回" onclick="self.location.href='predict.php';"/>
    </TR>
</TABLE>
