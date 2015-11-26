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
?>
<?php
    //Initialization section
    include_once("../../include/global.php");
    include_once("../../lib/timespan_settings.php");
    include_once("top_general_header.php");
    if ($database_type === "mysql") {
        $link = mysql_connect($database_hostname,$database_username,$database_password) or die('Could not connect: ' . mysql_error());
        mysql_selectdb($database_default,$link) or die('Could not select database: ' . mysql_error());
    } else {
        // The PHP PostgreSQL error functions all seem to require a working connection resource in order to report error details, but there
        // is no such resource around in the event of a connection failure.  So we just punt and issue a standard message, without details.
        $link = pg_connect("host='$database_hostname' port='$database_port' dbname='$database_default' user='$database_username' password='$database_password' connect_timeout=10")
        or die( "Could not connect to $database_default database on $database_hostname port $database_port." );
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<HTML xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" >
    <HEAD>
        <META http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <SCRIPT TYPE="text/javascript" SRC="./js/jquery-1.11.3.min.js">
        </SCRIPT>
        <SCRIPT TYPE="text/javascript" SRC="../../include/layout.js">
        </SCRIPT>
        <SCRIPT TYPE="text/javascript" SRC="../../include/jscalendar/calendar.js">
        </SCRIPT>
        <SCRIPT TYPE="text/javascript" SRC="../../include/jscalendar/lang/calendar-en.js">
        </SCRIPT>
        <SCRIPT TYPE="text/javascript" SRC="../../include/jscalendar/calendar-setup.js">
        </SCRIPT>
        <SCRIPT TYPE="text/javascript">
            $(function(){
                $('#host_id').change(function(event){
                    var target = $('#ds_id');
                    // alert($(this).val());
                    var herfText = location.href;
                    // var pattern = /^()\?[a-zA-Z0-9]*$/;
                    herfText = herfText.replace(/\?hostrech=[0-9]+$/,'');
                    // alert(location.href);
                    location.replace(herfText+'?hostrech='+$(this).val()); 
                    // target.load("ds_sel.php?hostrech="+$(this).val());
                });
            });
            function valid_dates(date1, date2)
            {
                if (date2 <= date1) {
                    alert("End date must be after start date");
                } else {
                    document.laforme.submit();
                }
            }
        </SCRIPT>
    </HEAD>
    <BODY>
        <?php
            $current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);
            $sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
            $array_types  = array('AREA', 'LINE1', 'LINE2', 'LINE3'); 
            $array_period = array(
                                "Hour"  => 3600,
                                "Day"   => 86400,
                                "Week"  => 604800,
                                "Month" => 2592000,
                                "Year"  => 31536000
                            );
            $periods      = array_keys($array_period);
            // Default values
            $def_tspan    = $periods[read_config_option("predict_def_tspan")];
            $def_units    = read_config_option("predict_def_units");
            $def_window   = read_config_option("predict_def_window");
            $def_colds    = read_config_option("predict_def_colds");
            $def_typeds   = $array_types[read_config_option("predict_def_typeds")];
            $def_colpred  = read_config_option("predict_def_colpred");
            $def_typepred = $array_types[read_config_option("predict_def_typepred")];
            $def_height   = read_config_option("predict_def_height");
            $def_width    = read_config_option("predict_def_width");
            // Delete previously created file if exists
            if (isset($_SESSION["file_del"])) {
                unlink($_SESSION["file_del"]);
            }
            // Keep host in case of "back"
            if (isset($_GET['hostrech']))
                $_SESSION['host_id'] = $_GET['hostrech'];
            $hostrech = (isset($_SESSION['host_id'])) ? $_SESSION['host_id'] : 0;
        ?>
    <FORM NAME="laforme" id="laforme" method="post" action="predict_res.php">
    <TABLE CELLPADDING="1" CELLSPACING="0">
        <TR>
            <!-- Host zone : if changed, recalculate datasource box -->
            <TD WIDTH="10">&nbsp;主机:&nbsp;</TD>
            <TD width="1">
                <!-- <SELECT NAME="host_id" onChange="$('#ds_id').load('ds_sel.php?hostrech='+this.value);" ID="host_id"> -->
                <SELECT NAME="host_id" ID="host_id">

                        <?php
                            $selected = ($hostrech) ? '' : ' SELECTED';
                            print "<OPTION VALUE='0'$selected>None</OPTION>\n";
                            // Get allowed hosts list
                            $hosts = db_fetch_assoc("SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
                                FROM graph_templates_graph CROSS JOIN host
                                LEFT JOIN graph_local ON (graph_local.host_id=host.id)
                                LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
                                LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
                                WHERE graph_templates_graph.local_graph_id=graph_local.id " . (empty($sql_where) ? "" : "and $sql_where") . "
                                ORDER BY name");
                            // Display each one
                            foreach ( $hosts as $host ) {
                               $selected = ($host["id"]==$hostrech) ? ' SELECTED' : '';
                               print "<OPTION VALUE='" . $host["id"] . "'$selected>";
                               print title_trim(htmlspecialchars($host["name"]), 40) . "</OPTION>\n";
                            }
                        ?>
                </SELECT>
        </TR>
        <TR>
            <!-- DS zone: 0 for get unattached datasources -->
            <TD width="10"><div style="width:50">&nbsp;数据源:&nbsp;</div></TD>
            <TD width="1">
                <SELECT NAME="ds_id" ID="ds_id">
                    <?php
                        include('ds_sel.php');
                    ?>
                </SELECT>
            </TD>
        </TR>
        <TR>
    <TD>
        &nbsp;开始:&nbsp;
    </TD>
    <TD>
        <INPUT TYPE='text' NAME='date_start' ID='date_start' TITLE='Graph Begin Timestamp' SIZE='15' VALUE='<?php print date('Y-m-d H:i', strtotime("-1 day"));?>'>
        &nbsp;<INPUT TYPE='image' SRC='../../images/calendar.gif' ALIGN='middle' ALT='Start date selector' TITLE='Start date selector' ONCLICK="return showCalendar('date_start');">
    </TD>
    <TD>
        &nbsp;截止:&nbsp;
    </TD>
    <TD>
        <INPUT TYPE='text' NAME='date_end' ID='date_end' TITLE='Graph End Timestamp' SIZE='15' VALUE='<?php print date('Y-m-d H:i', strtotime("+1 day"));?>'>
    </TD>
    <TD>
        &nbsp;<INPUT TYPE='image' SRC='../../images/calendar.gif' ALIGN='middle' ALT='End date selector' TITLE='End date selector' ONCLICK="return showCalendar('date_end');">
    </TD>
    </TABLE>
    <BR />
    <BR />
        &nbsp;正常行为计算&nbsp;
    <BR />
    <TABLE>
    <TD>
        &nbsp;时间间隔:&nbsp;
    </TD>
    <TD>
        <SELECT NAME='tspan' ID='tspan'>
            <?php
                while ( list($key, $val) = each($array_period) ) {
                    $str_sel = ( $key == $def_tspan ) ? ' SELECTED' : '';
                    print "<OPTION $str_sel VALUE=$val>$key</OPTION>\n";
                }
            ?>
        </SELECT>
    </TD>
    <TD>
        &nbsp;颗粒度:&nbsp;
    </TD>
    <TD>
        <INPUT TYPE='text' NAME='units' ID='units' TITLE='Units' SIZE='3' VALUE=<?php print $def_units;?>>
    </TD>
    <TD>
        &nbsp;窗口:&nbsp;
    </TD>
    <TD>
        <INPUT TYPE='text' NAME='win' ID='win' TITLE='Window' SIZE='4' VALUE=<?php print $def_window;?>>
    </TD>
    </TABLE>
    <BR /><I>
        &nbsp;例如:计算在3个时间跨度的604800秒（即周）基础上300秒的周期行为<BR/>
        &nbsp;查看 <A href='http://oss.oetiker.ch/rrdtool/doc/rrdgraph_rpn.en.html'>这里</A> 获取更多解释</I>
    <BR />
    <BR />
    <BR />
        &nbsp;结果图参数&nbsp;
    <BR />
    <TABLE>
        <TR>
            <TD>&nbsp;数据源颜色:&nbsp;</TD>
            <TD>
                <SELECT NAME='col_ds' ID='col_ds' onChange='this.style.backgroundColor=this.options[this.selectedIndex].style.backgroundColor;'>
                <?php
                    $cols = db_fetch_assoc("select * from colors order by hex;");   
                    foreach ( $cols as $col ) {
                        $colhex = $col["hex"];
                        $str_sel = ( $col["id"] == $def_colds ) ? ' SELECTED' : '';
                        print "<OPTION $str_sel STYLE='background-color: #$colhex;'>$colhex</OPTION>\n";
                    }
                ?>
                </SELECT>
            </TD>
            <TD>&nbsp;数据源项目类型:&nbsp;</TD>
            <TD>
                <SELECT NAME='type_ds' ID='type_ds'>
                    <?php
                        foreach ($array_types as $type) {
                            $str_sel = ( $type == $def_typeds ) ? ' SELECTED' : '';
                            print "<OPTION $str_sel>$type</OPTION>\n";
                        }
                    ?>
                </SELECT>
            </TD> 
        </TR>
        <TR>
            <!-- A bit repetitive, I know... -->
            <TD>&nbsp;预测值颜色:&nbsp;</TD>
            <TD>
                <SELECT NAME='col_pred' ID='col_pred' onChange='this.style.backgroundColor=this.options[this.selectedIndex].style.backgroundColor;'>
                <?php
                    $cols = db_fetch_assoc("select * from colors order by hex desc;");   
                    foreach ( $cols as $col ) {
                        $colhex  = $col["hex"];
                        $str_sel = ( $col["id"] == $def_colpred ) ? ' SELECTED' : '';
                        print "<OPTION $str_sel STYLE='background-color: #$colhex;'>$colhex</OPTION>\n";
                    }
                ?>
                </SELECT>
            </TD>
            <TD>&nbsp;预测值项目类型:&nbsp;</TD>
            <TD>
                <SELECT NAME='type_pred' ID='type_pred'>
                    <?php
                        foreach ($array_types as $type) {
                            $str_sel = ( $type == $def_typepred ) ? ' SELECTED' : '';
                            print "<OPTION $str_sel>$type</OPTION>\n";
                        }
                    ?>
                </SELECT>
            </TD> 
        </TR>
    </TABLE>
    <TABLE>
    <TD>&nbsp;宽度:&nbsp;</TD>
    <TD>
        <INPUT TYPE='text' NAME='width' ID='width' TITLE='Width' SIZE='4' VALUE=<?php print $def_width;?>>
    </TD>
    <TD>&nbsp;高度:&nbsp;</TD>
    <TD>
        <INPUT TYPE='text' NAME='height' ID='height' TITLE='Height' SIZE='4' VALUE=<?php print $def_height;?>>
    </TD>
    </TABLE>
    <INPUT TYPE='button' ID='button-submit' VALUE='进入' ONCLICK='valid_dates(document.laforme.date_start.value, document.laforme.date_end.value);'>
    <INPUT type='reset' value='清除'>
    </FORM>
</BODY>
</HTML>
