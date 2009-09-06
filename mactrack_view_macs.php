<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
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

$guest_account = true;

chdir('../../');
include("./include/auth.php");
include_once("./include/global_arrays.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

$mactrack_view_macs_actions = array(
	1 => "Authorize",
	2 => "Revoke"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel_x"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'actions':
	form_actions();

	break;
default:
	if (isset($_REQUEST["export_macs_x"])) {
		mactrack_view_export_macs();
	}else{
		$title = "Device Tracking - MAC to IP Report View";
		include_once("./include/top_graph_header.php");
		mactrack_view_macs();
		include("./include/bottom_footer.php");
	}

	break;
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $mactrack_view_macs_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* Authorize */
			for ($i=0; $i<count($selected_items); $i++) {
				/* clean up the mac_address */
				$selected_items[$i] = sanitize_search_string($selected_items[$i]);

				api_mactrack_authorize_mac_addresses($selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "2") { /* Revoke */
			$errors = "";
			for ($i=0;($i<count($selected_items));$i++) {
				/* clean up the mac_address */
				$selected_items[$i] = sanitize_search_string($selected_items[$i]);

				$mac_found = db_fetch_cell("SELECT mac_address FROM mac_track_macauth WHERE mac_address='$selected_items[$i]'");

				if ($mac_found) {
					api_mactrack_revoke_mac_addresses($selected_items[$i], $i, $_POST["title_format"]);
				}else{
					$errors .= ", $selected_items[$i]";
				}
			}

			if ($errors) {
				$_SESSION["sess_messages"] = "The following MAC Addresses Could not be revoked because they are members of Group Authorizations" . $errors;
			}
		}

		header("Location: mactrack_view.php");
		exit;
	}

	/* setup some variables */
	$mac_address_list = ""; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (substr($var,0,4) == "chk_") {
			$matches = substr($var,4);

			/* clean up the mac_address */
			if (isset($matches)) {
				$matches = sanitize_search_string($matches);
			}

			$mac_address_list .= "<li>" . $matches . "<br>";
			$mac_address_array[$i] = $matches;
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $mactrack_view_macs_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_view.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Authorize Macs */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to Authorize the following MAC Addresses?</p>
					<p>$mac_address_list</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* Revoke Macs */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to Revoke the following MAC Addresses?</p>
					<p>$mac_address_list</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($mac_address_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one MAC Address.</span></td></tr>\n";
		$save_html = "";
	}else if (!mactrack_check_user_realm(22)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You are not permitted to change Mac Authorizations.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($mac_address_array) ? serialize($mac_address_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function api_mactrack_authorize_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='1' WHERE mac_address='$mac_address'");
	db_execute("REPLACE INTO mac_track_macauth SET mac_address='$mac_address', description='Added from MacView', added_by='" . $_SESSION["sess_user_id"] . "'");
}

function api_mactrack_revoke_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='0' WHERE mac_address='$mac_address'");
	db_execute("DELETE FROM mac_track_macauth WHERE mac_address='$mac_address'");
}

function mactrack_view_export_macs() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up filter string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

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

	if (isset($_REQUEST["mac_filter_type_id"])) {
		if ($_REQUEST["mac_filter_type_id"] == 1) {
			unset($_REQUEST["mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["scan_date"])) {
		$_REQUEST["scan_date"] = sanitize_search_string(get_request_var("scan_date"));
	}

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("scan_date", "sess_mactrack_view_macs_scan_date", "2");
	load_current_session_value("filter", "sess_mactrack_view_macs_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("mac_filter", "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("ip_filter_type_id", "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("ip_filter", "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("rows", "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("site_id", "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("device_id", "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_macs_sort_direction", "ASC");

	$sql_where = "";

	$port_results = mactrack_view_get_mac_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"vlan_id","vlan_name","mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","port_name","scan_date"');

	if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if ($_REQUEST["scan_date"] == 1) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['vlan_id'] . '","' . $port_result['vlan_name'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['vendor_name'] . '","' .
			$port_result['ip_address'] . '","' . $port_result['dns_hostname'] . '","' .
			$port_result['port_number'] . '","' . $port_result['port_name'] . '","' .
			$scan_date . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_port_macs_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_mac_records(&$sql_where, $apply_limits = TRUE, $row_limit = -1) {
	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"]) > 0) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["mac_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ports.mac_address='" . $_REQUEST["mac_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ports.mac_address LIKE '%%" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ports.mac_address LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
		}
	}

	if ((strlen($_REQUEST["ip_filter"]) > 0)||($_REQUEST["ip_filter_type_id"] > 5)) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["ip_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ports.ip_address='" . $_REQUEST["ip_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ports.ip_address LIKE '%%" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ports.ip_address LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "7": /* is null */
				$sql_where .= " mac_track_ports.ip_address = ''";
				break;
			case "8": /* is not null */
				$sql_where .= " mac_track_ports.ip_address != ''";
		}
	}

	if (strlen($_REQUEST["filter"])) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			$sql_where .= " (mac_track_ports.dns_hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.port_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["filter"] . "%')";
		}else{
			$sql_where .= " (mac_track_ports.port_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["filter"] . "%')";
		}
	}

	if (!($_REQUEST["authorized"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.authorized=" . $_REQUEST["authorized"];
	}

	if (!($_REQUEST["site_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.site_id=" . $_REQUEST["site_id"];
	}

	if (!($_REQUEST["vlan"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.vlan_id=" . $_REQUEST["vlan"];
	}

	if (!($_REQUEST["device_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.device_id=" . $_REQUEST["device_id"];
	}

	if (($_REQUEST["scan_date"] != "1") && ($_REQUEST["scan_date"] != "2")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.scan_date='" . $_REQUEST["scan_date"] . "'";
	}

	if ($_REQUEST["scan_date"] == 1) {
		$query_string = "SELECT
			site_name, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}else{
		$query_string = "SELECT
			site_name, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(scan_date) as max_scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			GROUP BY device_id, mac_address, port_number, ip_address
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}

	if (strlen($sql_where) == 0) {
		return array();
	}else{
		return db_fetch_assoc($query_string);
	}
}

function mactrack_view_macs() {
	global $title, $report, $colors, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_macs_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("authorized"));
	input_validate_input_number(get_request_var_request("vlan"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up filter string */
	if (isset($_REQUEST["ip_filter"])) {
		$_REQUEST["ip_filter"] = sanitize_search_string(get_request_var("ip_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["mac_filter"])) {
		$_REQUEST["mac_filter"] = sanitize_search_string(get_request_var("mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["mac_filter_type_id"])) {
		if ($_REQUEST["mac_filter_type_id"] == 1) {
			unset($_REQUEST["mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["scan_date"])) {
		$_REQUEST["scan_date"] = sanitize_search_string(get_request_var("scan_date"));
	}

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_view_macs_current_page");
		kill_session_var("sess_mactrack_view_macs_rowstoshow");
		kill_session_var("sess_mactrack_view_macs_filter");
		kill_session_var("sess_mactrack_view_macs_mac_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_mac_filter");
		kill_session_var("sess_mactrack_view_macs_ip_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_ip_filter");
		kill_session_var("sess_mactrack_view_macs_rows_selector");
		kill_session_var("sess_mactrack_view_macs_site_id");
		kill_session_var("sess_mactrack_view_macs_vlan_id");
		kill_session_var("sess_mactrack_view_macs_authorized");
		kill_session_var("sess_mactrack_view_macs_device_id");
		kill_session_var("sess_mactrack_view_macs_sort_column");
		kill_session_var("sess_mactrack_view_macs_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["scan_date"]);
		unset($_REQUEST["mac_filter"]);
		unset($_REQUEST["mac_filter_type_id"]);
		unset($_REQUEST["ip_filter"]);
		unset($_REQUEST["ip_filter_type_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["site_id"]);
		unset($_REQUEST["vlan"]);
		unset($_REQUEST["authorized"]);
		unset($_REQUEST["device_id"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("scan_date",          "sess_mactrack_view_macs_rowstoshow");
		$changed += mactrack_check_changed("mac_filter",         "sess_mactrack_view_macs_filter");
		$changed += mactrack_check_changed("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id");
		$changed += mactrack_check_changed("ip_filter",          "sess_mactrack_view_macs_mac_ip_filter");
		$changed += mactrack_check_changed("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id");
		$changed += mactrack_check_changed("filter",             "sess_mactrack_view_macs_ip_filter");
		$changed += mactrack_check_changed("rows",               "sess_mactrack_view_macs_rows_selector");
		$changed += mactrack_check_changed("site_id",            "sess_mactrack_view_macs_site_id");
		$changed += mactrack_check_changed("vlan",               "sess_mactrack_view_macs_vlan_id");
		$changed += mactrack_check_changed("authorized",         "sess_mactrack_view_macs_authorized");
		$changed += mactrack_check_changed("device_id",          "sess_mactrack_view_macs_device_id");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* reset some things if the user has made changes */
	if ((!empty($_REQUEST["site_id"]))&&(!empty($_SESSION["sess_mactrack_view_macs_site_id"]))) {
		if ($_REQUEST["site_id"] <> $_SESSION["sess_mactrack_view_macs_site_id"]) {
			$_REQUEST["device_id"] = "-1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("report",             "sess_mactrack_view_report", "macs");
	load_current_session_value("page",               "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("scan_date",          "sess_mactrack_view_macs_rowstoshow", "2");
	load_current_session_value("mac_filter",         "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("ip_filter",          "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("filter",             "sess_mactrack_view_macs_filter", "");
	load_current_session_value("rows",               "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("site_id",            "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("vlan",               "sess_mactrack_view_macs_vlan_id", "-1");
	load_current_session_value("authorized",         "sess_mactrack_view_macs_authorized", "-1");
	load_current_session_value("device_id",          "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column",        "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction",     "sess_mactrack_view_macs_sort_direction", "ASC");

	mactrack_tabs();

	mactrack_view_header();

	include("./plugins/mactrack/html/inc_mactrack_view_mac_filter_table.php");

	mactrack_view_footer();

	$sql_where = "";

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	$port_results = mactrack_view_get_mac_records($sql_where, TRUE, $row_limit);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	if ($_REQUEST["rows"] == 1) {
		$rows_query_string = "SELECT
			COUNT(mac_track_ports.device_id)
			FROM mac_track_ports
			$sql_where";

		if (strlen($sql_where) == 0) {
			$total_rows = 0;
		}else{
			$total_rows = db_fetch_cell($rows_query_string);
		}
	}else{
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ports
			$sql_where";

		if (strlen($sql_where) == 0) {
			$total_rows = 0;
		}else{
			$total_rows = db_fetch_cell($rows_query_string);
		}
	}

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_macs.php?report=macs");

	if (isset($config["base_path"])) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
					<td colspan='12'>
						<table width='100%' cellspacing='0' cellpadding='0' border='0'>
							<tr>
								<td align='left' class='textHeaderDark'>
									<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
								</td>\n
								<td align='center' class='textHeaderDark'>
									Showing Rows " . ($total_rows == 0 ? "None" : (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
								</td>\n
								<td align='right' class='textHeaderDark'>
									<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
								</td>\n
							</tr>
						</table>
					</td>
				</tr>\n";
	}else{
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, 13, "mactrack_view_macs.php?report=macs");
	}

	print $nav;

	if (strlen(read_config_option("mt_reverse_dns")) > 0) {
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"max_scan_date" => array("Last Scan Date", "DESC"));
		}else{
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"scan_date" => array("Last Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(22)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}else{
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"max_scan_date" => array("Last Scan Date", "DESC"));
		}else{
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Device", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"scan_date" => array("Last Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(22)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}

	$i = 0;
	if (sizeof($port_results) > 0) {
		foreach ($port_results as $port_result) {
			if ($_REQUEST["rows"] == 1) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td width=60></td>
			<td><?php print $port_result["device_name"];?></td>
			<td><?php print $port_result["hostname"];?></td>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["ip_address"]) : $port_result["ip_address"]);?></td>
			<?php
			if (strlen(read_config_option("mt_reverse_dns")) > 0) {?>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["dns_hostname"]) : $port_result["dns_hostname"]);?></td>
			<?php }?>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["mac_address"]) : $port_result["mac_address"]);?></td>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vendor_name"]) : $port_result["vendor_name"]);?></td>
			<td><?php print $port_result["port_number"];?></td>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["port_name"]) : $port_result["port_name"]);?></td>
			<td><?php print $port_result["vlan_id"];?></td>
			<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vlan_name"]) : $port_result["vlan_name"]);?></td>
			<td><?php print $scan_date;?></td>
			<?php if (mactrack_check_user_realm(22)) { ?>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $port_result["mac_address"];?>' title="<?php print $port_result["mac_address"];?>">
			</td>
			<?php } ?>
			</tr>
			<?php
		}
	}else{
		print "<tr><td colspan='10'><em>No MacTrack Port Results</em></td></tr>";
	}

	print $nav;

	html_end_box(false);

	if (mactrack_check_user_realm(2122)) {
		/* draw the dropdown containing a list of available actions for this form */
		mactrack_draw_actions_dropdown($mactrack_view_macs_actions);
	}
}

?>