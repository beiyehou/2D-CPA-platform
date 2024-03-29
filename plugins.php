<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include("./include/auth.php");

define("MAX_DISPLAY_PAGES", 21);

$actions = array("install" => "Install",
	"enable" => "Enable",
	"disable" => "Disable",
	"uninstall" => "Uninstall",
//	"check" => "Check"
);

$status_names = array(
	-2 => '停用',
	-1 => '启用',
	0 => '未安装',
	1 => '启用',
	2 => '等待配置',
	3 => '等待升级',
	4 => '已安装'
);

/* get the comprehensive list of plugins */
$pluginslist = retrieve_plugin_list();

/* Check to see if we are installing, etc... */
$modes = array('installold', 'uninstallold', 'install', 'uninstall', 'disable', 'enable', 'check', 'moveup', 'movedown');

if (isset($_GET['mode']) && in_array($_GET['mode'], $modes)  && isset($_GET['id'])) {
	input_validate_input_regex(get_request_var("id"), "^([a-zA-Z0-9]+)$");

	$mode = $_GET['mode'];
	$id   = sanitize_search_string($_GET['id']);

	switch ($mode) {
		case 'installold':
			api_plugin_install_old($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'uninstallold':
			api_plugin_uninstall_old($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'install':
			api_plugin_install($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'uninstall':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_uninstall($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'disable':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_disable($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'enable':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_enable($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'check':
			if (!in_array($id, $pluginslist)) break;
			break;
		case 'moveup':
			if (!in_array($id, $pluginslist)) break;
			if (is_system_plugin($id)) break;
			api_plugin_moveup($id);
			header("Location: plugins.php");
			exit;
			break;
		case 'movedown':
			if (!in_array($id, $pluginslist)) break;
			if (is_system_plugin($id)) break;
			api_plugin_movedown($id);
			header("Location: plugins.php");
			exit;
			break;
	}
}

function retrieve_plugin_list () {
	$pluginslist = array();
	$temp = db_fetch_assoc('SELECT directory FROM plugin_config ORDER BY name');
	foreach ($temp as $t) {
		$pluginslist[] = $t['directory'];
	}
	return $pluginslist;
}

include("./include/top_header.php");

update_show_current();

include("./include/bottom_footer.php");

function api_plugin_install_old ($plugin) {
	global $config;
	if (!file_exists($config['base_path'] . "/plugins/$plugin/setup.php")) {
		return false;
	}
	$oldplugins = read_config_option('oldplugins');
	if (strlen(trim($oldplugins))) {
	$oldplugins = explode(',', $oldplugins);
	}else{
		$oldplugins = array();
	}
	if (!in_array($plugin, $oldplugins)) {
		include_once($config['base_path'] . "/plugins/$plugin/setup.php");
		$function = 'plugin_init_' . $plugin;
		if (function_exists($function)){
			$oldplugins[] = $plugin;
			$oldplugins   = implode(',', $oldplugins);
			set_config_option('oldplugins', $oldplugins);
			unset($_SESSION['sess_config_array']['oldplugins']);
			return true;
		} else {
			return false;
		}
	}
	return false;
}

function api_plugin_uninstall_old ($plugin) {
	global $config;
	$oldplugins = read_config_option('oldplugins');
	if (strlen(trim($oldplugins))) {
	$oldplugins = explode(',', $oldplugins);
	}else{
		$oldplugins = array();
	}
	if (!empty($oldplugins)) {
		if (in_array($plugin, $oldplugins)) {
			for ($a = 0; $a < count($oldplugins); $a++) {
				if ($oldplugins[$a] == $plugin) {
					unset($oldplugins[$a]);
					break;
				}
			}
			$oldplugins = implode(',', $oldplugins);
			set_config_option('oldplugins', $oldplugins);
			unset($_SESSION['sess_config_array']['oldplugins']);
			return true;
		}
	}
	return false;
}

function update_show_updates () {
	global $pluginslist, $config, $plugin_architecture;

	$cinfo = array();
	sort($pluginslist);

	$cinfo = update_get_plugin_info ();

	$x = 0;

	$info = update_get_cached_plugin_info();

	$cactinew = update_check_if_newer($cinfo['cacti']['version'], $info['cacti']['version']) ;
	if (isset($cinfo['cacti_plugin_arch']['version'])) {
		$archnew =  update_check_if_newer($cinfo['cacti_plugin_arch']['version'], $info['cacti_plugin_arch']['version']);
	} else {
		$archnew = 0;
	}

	if ($cactinew) {
		$x++;
		print "<tr><td width='25%' valign=top><table width='100%'>";
		html_header(array("Cacti"), 2);
		form_alternate_row('', true);
		print "<td width='25%'><strong>版本:</strong></td><td>" . $config["cacti_version"] . "</td></tr>";
		form_alternate_row('', true);
		print "<td valign=top><strong>修订:</strong></td><td>" . str_replace("\n", '<br>', $info['cacti']['changes']) . "</td></tr></table>";
	}
	if (isset($plugin_architecture['version']) && $archnew) {
		$x++;
		print "<table width='100%'>";
		html_header(array("插件架构"), 2);
		form_alternate_row('', true);
		print "<td width='25%'><strong>版本:</strong></td><td>" . $plugin_architecture['version'] . "</td>";
		form_alternate_row('', true);
		print "<td valign=top><strong>修订:</strong></td><td>" . str_replace("\n", '<br>', $info['cacti_plugin_arch']['changes']) . "</td></tr></table>";
	}
	print "<table width='100%' cellspacing=0 cellpadding=3>";

	foreach ($pluginslist as $plugin) {
		if (isset($cinfo[$plugin]) && update_check_if_newer($cinfo[$plugin]['version'], $info[$plugin]['version'])) {
			$x++;
			print "<table width='100%'>";
			html_header(array((isset($cinfo[$plugin]['longname']) ? $cinfo[$plugin]['longname'] : $plugin)), 2);
			form_alternate_row('', true);
			print "<td width='50%'><strong>目录:</strong></td><td>$plugin</td>";
			form_alternate_row('', true);
			print "<td><strong>版本:</strong></td><td>" . $info[$plugin]['version'] . "</td>";
			form_alternate_row('', true);
			print "<td><strong>作者:</strong></td><td>" . (isset($cinfo[$plugin]['author']) && $cinfo[$plugin]['author'] != '' ? (isset($cinfo[$plugin]['email']) && $cinfo[$plugin]['email'] != '' ? "<a href='mailto:" . $cinfo[$plugin]['email'] . "'>" . $cinfo[$plugin]['author'] . "</a>"  : $cinfo[$plugin]['author']) : "") . "</td>";
			form_alternate_row('', true);
			print "<td><strong>主页:</strong></td><td>" . (isset($cinfo[$plugin]['webpage']) && $cinfo[$plugin]['webpage'] != '' ? "<a href='" . $cinfo[$plugin]['webpage'] . "'>" . $cinfo[$plugin]['webpage'] . "</a>" : "") . "</td>";
			form_alternate_row('', true);
			print "<td valign=top><strong>修订:</strong></td><td>" . str_replace("\n", '<br>', $info[$plugin]['changes']) . "</td>";

			print "</tr></table>";
		}
	}
	if ($x == 0)
		print "<br><center><b>当前无更新!</b></center><br>";
	print "</table>";
	html_end_box(TRUE);
}

function update_check_if_newer() {
	return false;
}

function plugins_temp_table_exists($table) {
	return sizeof(db_fetch_row("SHOW TABLES LIKE '$table'"));
}

function plugins_load_temp_table() {
	global $config, $plugins;

	$pluginslist = retrieve_plugin_list();

	if (isset($_SESSION["plugin_temp_table"])) {
		$table = $_SESSION["plugin_temp_table"];
	}else{
		$table = "plugin_temp_table_" . rand();
	}
	$x = 0;
	while ($x < 30) {
		if (!plugins_temp_table_exists($table)) {
			$_SESSION["plugin_temp_table"] = $table;
			db_execute("CREATE TEMPORARY TABLE IF NOT EXISTS $table LIKE plugin_config");
			db_execute("TRUNCATE $table");
			db_execute("INSERT INTO $table SELECT * FROM plugin_config");
			break;
		}else{
			$table = "plugin_temp_table_" . rand();
		}
		$x++;
	}

	$path = $config['base_path'] . '/plugins/';
	$dh = opendir($path);
	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if ((is_dir("$path/$file")) && (file_exists("$path/$file/setup.php")) && (!in_array($file, $pluginslist))) {
				include_once("$path/$file/setup.php");
				if (!function_exists('plugin_' . $file . '_install') && function_exists($file . '_version')) {
					$function = $file . '_version';
					$cinfo[$file] = $function();
					if (!isset($cinfo[$file]['author']))   $cinfo[$file]['author']   = 'Unknown';
					if (!isset($cinfo[$file]['homepage'])) $cinfo[$file]['homepage'] = 'Not Stated';
					if (isset($cinfo[$file]['webpage']))   $cinfo[$file]['homepage'] = $cinfo[$file]['webpage'];
					if (!isset($cinfo[$file]['longname'])) $cinfo[$file]['longname'] = ucfirst($file);
					$cinfo[$file]['status'] = -2;
					if (in_array($file, $plugins)) {
						$cinfo[$file]['status'] = -1;
					}
					db_execute("REPLACE INTO $table (directory, name, status, author, webpage, version)
						VALUES ('" .
							$file . "', '" .
							$cinfo[$file]['longname'] . "', '" .
							$cinfo[$file]['status']   . "', '" .
							$cinfo[$file]['author']   . "', '" .
							$cinfo[$file]['homepage'] . "', '" .
							$cinfo[$file]['version']  . "')");
					$pluginslist[] = $file;
				} elseif (function_exists('plugin_' . $file . '_install') && function_exists('plugin_' . $file . '_version')) {
					$function               = $file . '_version';
					$cinfo[$file]           = $function();
					$cinfo[$file]['status'] = 0;
					if (!isset($cinfo[$file]['author']))   $cinfo[$file]['author']   = 'Unknown';
					if (!isset($cinfo[$file]['homepage'])) $cinfo[$file]['homepage'] = 'Not Stated';
					if (isset($cinfo[$file]['webpage']))   $cinfo[$file]['homepage'] = $cinfo[$file]['webpage'];
					if (!isset($cinfo[$file]['longname'])) $cinfo[$file]['homepage'] = ucfirst($file);

					/* see if it's been installed as old, if so, remove from oldplugins array and session */
					$oldplugins = read_config_option("oldplugins");
					if (substr_count($oldplugins, $file)) {
						$oldplugins = str_replace($file, "", $oldplugins);
						$oldplugins = str_replace(",,", ",", $oldplugins);
						$oldplugins = trim($oldplugins, ",");
						set_config_option('oldplugins', $oldplugins);
						$_SESSION['sess_config_array']['oldplugins'] = $oldplugins;
					}

					db_execute("REPLACE INTO $table (directory, name, status, author, webpage, version)
						VALUES ('" .
							$file . "', '" .
							$cinfo[$file]['longname'] . "', '" .
							$cinfo[$file]['status'] . "', '" .
							$cinfo[$file]['author'] . "', '" .
							$cinfo[$file]['homepage'] . "', '" .
							$cinfo[$file]['version'] . "')");
					$pluginslist[] = $file;
				}
			}
		}
		closedir($dh);
	}
	return $table;
}

function update_show_current () {
	global $plugins, $pluginslist, $plugin_architecture, $config, $status_names, $actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */
	
	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_plugins_filter");
		kill_session_var("sess_plugins_rows");
		kill_session_var("sess_plugins_sort_column");
		kill_session_var("sess_plugins_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		$_REQUEST["page"] = 1;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_plugins_filter", "");
	load_current_session_value("rows", "sess_plugins_rows", "-1");
	load_current_session_value("sort_column", "sess_plugins_sort_column", "name");
	load_current_session_value("sort_direction", "sess_plugins_sort_direction", "ASC");
	load_current_session_value("page", "sess_plugins_current_page", "1");

	$table = plugins_load_temp_table();
	?>
	<script type="text/javascript">
	<!--
	function applyFilterChange(objForm) {
		strURL = '?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php
	html_start_box("<strong>插件管理</strong> (Cacti 版本: " . $config["cacti_version"] .
		(isset($plugin_architecture['version']) ? ", 插件架构版本: " . $plugin_architecture['version']:"") .
		")", "100%", "", "3", "center", "");

	?>
	<tr class='even noprint'>
		<td class="noprint">
		<form name="form_plugins" method="get" action="plugins.php">
			<table cellpadding="0" cellspacing="0">
				<tr class="noprint">
					<td nowrap style='white-space: nowrap;' width="50">
						查找:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print get_request_var_request("filter");?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;行:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyFilterChange(document.form_plugins)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>默认</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" value="提交" title="设置/刷新">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" name="clear_x" value="清除" title="清除">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='plugins.php'>\n";

	html_start_box("", "100%", "", "3", "center", "");

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE ($table.name LIKE '%%" . get_request_var_request("filter") . "%%')";

	if (get_request_var_request("sort_column") == "version") {
		$sortc = "version+0";
	}else{
		$sortc = get_request_var_request("sort_column");
	}

	if (get_request_var_request("sort_column") == "id") {
		$sortd = "ASC";
	}else{
		$sortd = get_request_var_request("sort_direction");
	}

	if ($_REQUEST['rows'] == '-1') {
		$rows = read_config_option('num_rows_device');
	}else{
		$rows = get_request_var_request('rows');
	}

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM $table
		$sql_where");

	$plugins = db_fetch_assoc("SELECT *
		FROM $table
		$sql_where
		ORDER BY " . $sortc . " " . $sortd . "
		LIMIT " . ($rows*(get_request_var_request("page")-1)) . "," . $rows);

	db_execute("DROP TABLE $table");

	$nav = html_nav_bar("plugins.php?filter=" . get_request_var_request("filter"), MAX_DISPLAY_PAGES, get_request_var_request("page"), $rows, $total_rows, 8);

	print $nav;

	$display_text = array(
		"nosort" => array("动作", ""),
		"directory" => array("名称", "ASC"),
		"version" => array("版本", "ASC"),
		"id" => array("加载顺序", "ASC"),
		"name" => array("描述", "ASC"),
		"nosort1" => array("类型", "ASC"),
		"status" => array("状态", "ASC"),
		"author" => array("作者", "ASC"));

	html_header_sort($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), 1);

	$i = 0;
	if (sizeof($plugins)) {
		if (get_request_var_request("sort_column") == "id") {
			$inst_system_plugins = get_system_plugins($plugins);
			if (sizeof($inst_system_plugins)) {
				foreach($inst_system_plugins as $plugin) {
					form_alternate_row('', true);
					print format_plugin_row($plugin, false, false, true);
				}
			}
		}

		$j = 0;
		foreach ($plugins as $plugin) {
			if ((isset($plugins[$j+1]) && $plugins[$j+1]['status'] < 0) || (!isset($plugins[$j+1]))) {
				$last_plugin = true;
			}else{
				$last_plugin = false;
			}
			if ($plugin['status'] <= 0 || is_system_plugin($plugin) || (get_request_var_request('sort_column') != 'id')) {
				$load_ordering = false;
			}else{
				$load_ordering = true;
			}

			if (get_request_var_request("sort_column") == "id") {
				if (!is_system_plugin($plugin)) {
					form_alternate_row('', true);
					print format_plugin_row($plugin, $last_plugin, $load_ordering, false);
					$i++;
				}
			}else{
				form_alternate_row('', true);
				print format_plugin_row($plugin, $last_plugin, $load_ordering, is_system_plugin($plugin));
				$i++;
			}

			$j++;
		}

		print $nav;
	}else{
		print "<tr><td><em>没有找到插件</em></td></tr>";
	}

	html_end_box(false);

	html_start_box("", "100%", "", "3", "center", "");
	echo "<tr><td colspan=10><strong>注意:</strong> 请按'加载顺序' 排序以更改插件加载顺序.<br><strong>注意:</strong> 系统插件不能排序.</td></tr>";
	html_end_box();

	print "</form>\n";
}

function format_plugin_row($plugin, $last_plugin, $include_ordering, $system_plugin) {
	global $status_names;
	static $first_plugin = true;

	$row = plugin_actions($plugin);
	$row .= "<td><a href='" . htmlspecialchars($plugin["webpage"]) . "' target='_blank'><strong>" . (strlen(get_request_var_request("filter")) ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", ucfirst($plugin["directory"])) : ucfirst($plugin["directory"])) . "</strong></a></td>";
	$row .= "<td>" . $plugin["version"] . "</td>\n";
	if ($include_ordering) {
		$row .= "<td style='white-space:nowrap;'>";
		if (!$first_plugin) {
			$row .= "<a href='" . htmlspecialchars("plugins.php?mode=moveup&id=" . $plugin['directory']) . "' title='在上一个插件之前加载' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/move_up.gif'></a>";
		}else{
			$row .= "<a href='#' title='不能减小加载顺序' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'></a>";
		}
		if (!$last_plugin) {
			$row .= "<a href='" . htmlspecialchars("plugins.php?mode=movedown&id=" . $plugin['directory']) . "' title='在下一个插件之后加载' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/move_down.gif'></a>";
		}else{
			$row .= "<a href='#' title='可以增加加载顺序' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'></a>";
		}
		$row .= "</td>\n";
	}else{
		$row .= "<td></td>\n";
	}

	$row .= "<td style='white-space:nowrap;'>" . (strlen(get_request_var_request("filter")) ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $plugin["name"]) : $plugin["name"]) . "</td>\n";
	$row .= "<td style='white-space:nowrap;'>" . ($system_plugin ? "System": ($plugin['status'] < 0 ? "Old PIA":"General")) . "</td>\n";
	$row .= "<td style='white-space:nowrap;'>" . $status_names[$plugin["status"]] . "</td>\n";
	$row .= "<td style='white-space:nowrap;'>" . $plugin["author"] . "</td>\n";
	$row .= "</tr>\n";

	if ($include_ordering) {
		$first_plugin = false;
	}

	return $row;
}

function plugin_actions($plugin) {
	$link = "<td>";
	switch ($plugin['status']) {
		case "-2": // Old PA Not Installed
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=installold&id=" . $plugin['directory']) . "' title='安装就得插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/install_icon.png'></a>";
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case "-1":	// Old PA Currently Active
			$oldplugins = read_config_option('oldplugins');
			if (strlen(trim($oldplugins))) {
				$oldplugins = explode(',', $oldplugins);
			}else{
				$oldplugins = array();
			}
			if (in_array($plugin['directory'], $oldplugins)) {
				$link .= "<a href='" . htmlspecialchars("plugins.php?mode=uninstallold&id=" . $plugin['directory']) . "' title='卸载旧的插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			} else {
				$link .= "<a href='#' title='请从config.php卸载' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/install_icon_disabled.png'></a>";
			}
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case "0": // Not Installed
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=install&id=" . $plugin['directory']) . "' title='安装插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/install_icon.png'></a>";
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case "1":	// Currently Active
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=uninstall&id=" . $plugin['directory']) . "' title='卸载插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=disable&id=" . $plugin['directory']) . "' title='停用插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/disable_icon.png'></a>";
			break;
		case "4":	// Installed but not active
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=uninstall&id=" . $plugin['directory']) . "' title='卸载插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			$link .= "<a href='" . htmlspecialchars("plugins.php?mode=enable&id=" . $plugin['directory']) . "' title='启用插件' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/enable_icon.png'></a>";
			break;
		default: // Old PIA
			$link .= "<a href='#' title='请从config.php安装/卸载' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/install_icon_disabled.png'></a>";
			$link .= "<a href='#' title='不支持从用户界面启用' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/enable_icon_disabled.png'></a>";
			break;
	}
	$link .= "</td>";

	return $link;
}

function is_system_plugin($plugin) {
	global $plugins_system;

	if (is_array($plugin)) {
		$plugin = $plugin["directory"];
	}

	if (!in_array($plugin, $plugins_system)) {
		return false;
	}else{
		return true;
	}
}

function get_system_plugins($plugins) {
	$inst_system_plugins = array();

	if (sizeof($plugins)) {
		foreach($plugins as $plugin) {
			if (is_system_plugin($plugin)) {
				$inst_system_plugins[] = $plugin;
			}
		}
	}

	return $inst_system_plugins;
}


