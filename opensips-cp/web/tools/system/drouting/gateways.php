<?php
/*
 * Copyright (C) 2011 OpenSIPS Project
 *
 * This file is part of opensips-cp, a free Web Control Panel Application for 
 * OpenSIPS SIP server.
 *
 * opensips-cp is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * opensips-cp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

 require("../../../common/cfg_comm.php");
 require("template/header.php");
 require ("../../../common/mi_comm.php");
 require("../../../../config/db.inc.php");
 require_once("lib/common.functions.inc.php");
 session_load();

 $table=get_settings_value("table_gateways");
 $current_page="current_page_gateways";
 
 include("lib/db_connect.php");

 if (isset($_POST['action'])) $action=$_POST['action'];
 else if (isset($_GET['action'])) $action=$_GET['action'];
      else $action="";

if (isset($_GET['page'])) $_SESSION[$current_page]=$_GET['page'];
else if (!isset($_SESSION[$current_page])) $_SESSION[$current_page]=1;

// CSRF validation - skip for csv_upload_dialog (GET request) and csv_upload (validates itself)
if ($action != "csv_upload_dialog" && $action != "csv_upload") {
	csrfguard_validate();
}

########################
# start csv upload dialog #
########################
if ($action=="csv_upload_dialog")
{
	require("lib/".$page_id.".functions.inc.php");
	require("template/".$page_id.".csv_upload.php");
	exit();
}
####################
# end csv upload dialog #
####################

####################
# start csv upload #
####################
if ($action=="csv_upload")
{
	// Validate CSRF token for file upload
	csrfguard_validate();
	
	$csv_upload_error = "";
	$csv_upload_success = "";
	$inserted_count = 0;
	$skipped_count = 0;
	$error_rows = array();
	
	if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
		$csv_upload_error = "Error: No file uploaded or upload error occurred.";
	} else {
		$file = $_FILES['csv_file'];
		$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		
		if ($file_ext != 'csv') {
			$csv_upload_error = "Error: File must be a CSV file (.csv extension).";
		} else {
			// Open and parse CSV file
			$handle = fopen($file['tmp_name'], 'r');
			if ($handle === false) {
				$csv_upload_error = "Error: Could not open uploaded file.";
			} else {
				// Read header row - fix PHP 8.5 deprecation warning
				$header = fgetcsv($handle, 0, ',', '"', '\\');
				if ($header === false || empty($header)) {
					$csv_upload_error = "Error: CSV file is empty or invalid.";
					fclose($handle);
				} else {
					// Normalize header (trim whitespace)
					$header_trimmed = array_map('trim', $header);
					
					// Create flexible column mapping - handle various column name formats
					$col_map = array();
					$header_lower = array();
					
					// Map common column name variations to standard names
					$column_aliases = array(
						'gwid' => array('gwid', 'gateway id', 'gatewayid', 'gw id'),
						'address' => array('address', 'sip address', 'sipaddress', 'ip address', 'ipaddress'),
						'pri_prefix' => array('pri prefix', 'priprefix', 'prefix', 'pri_prefix'),
						'description' => array('description', 'desc'),
						'type' => array('type', 'gw type', 'gwtype'),
						'strip' => array('strip', 'strip digits'),
						'probe_mode' => array('probe mode', 'probemode', 'probe_mode'),
						'socket' => array('socket'),
						'state' => array('state', 'db state', 'dbstate'),
						'attrs' => array('attrs', 'attributes', 'attr')
					);
					
					// Build column map with aliases
					foreach ($header_trimmed as $idx => $col) {
						$col_lower = strtolower($col);
						$header_lower[] = $col_lower;
						
						// Try to find matching standard column name
						foreach ($column_aliases as $std_name => $aliases) {
							if (in_array($col_lower, $aliases)) {
								$col_map[$std_name] = $idx;
								break;
							}
						}
						// Also store original column name mapping
						$col_map[$col_lower] = $idx;
					}
					
					// Check for required columns (gwid and address)
					$has_gwid = isset($col_map['gwid']);
					$has_address = isset($col_map['address']);
					
					if (!$has_gwid || !$has_address) {
						$csv_upload_error = "Error: CSV must contain 'GWID' (or 'Gateway ID') and 'SIP Address' (or 'Address') columns. Found columns: " . implode(', ', $header_trimmed);
						fclose($handle);
					} else {
						
						// Process data rows
						$row_num = 1; // Start at 1 since header is row 0
						while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
							$row_num++;
							
							// Skip empty rows
							if (empty(array_filter($data))) {
								continue;
							}
							
							// Extract values using flexible column mapping
							$gwid = '';
							$address = '';
							if (isset($col_map['gwid']) && isset($data[$col_map['gwid']])) {
								$gwid = trim($data[$col_map['gwid']]);
							}
							if (isset($col_map['address']) && isset($data[$col_map['address']])) {
								$address = trim($data[$col_map['address']]);
							}
							
							// Handle PRI Prefix - if it looks like a port number, combine with address
							$pri_prefix = '';
							$port_from_prefix = '';
							if (isset($col_map['pri_prefix']) && isset($data[$col_map['pri_prefix']])) {
								$pri_prefix_value = trim($data[$col_map['pri_prefix']]);
								// Check if PRI Prefix is actually a port number (4-5 digits)
								if (preg_match('/^\d{4,5}$/', $pri_prefix_value)) {
									// It's a port number, combine with address
									$port_from_prefix = $pri_prefix_value;
									// Don't set pri_prefix field (leave empty)
								} else {
									// It's a real prefix, use it as pri_prefix
									$pri_prefix = $pri_prefix_value;
								}
							}
							
							// Combine address with port if port was found in PRI Prefix
							if (!empty($address) && !empty($port_from_prefix)) {
								// Check if address already has a port
								if (strpos($address, ':') === false) {
									$address = $address . ':' . $port_from_prefix;
								}
							}
							
							// Optional fields
							$type = '';
							if (isset($col_map['type']) && isset($data[$col_map['type']])) {
								$type = trim($data[$col_map['type']]);
							}
							
							$description = '';
							if (isset($col_map['description']) && isset($data[$col_map['description']])) {
								$description = trim($data[$col_map['description']]);
							}
							
							$attrs = '';
							if (isset($col_map['attrs']) && isset($data[$col_map['attrs']])) {
								$attrs = trim($data[$col_map['attrs']]);
							}
							
							$strip = '0';
							if (isset($col_map['strip']) && isset($data[$col_map['strip']])) {
								$strip = trim($data[$col_map['strip']]);
							}
							
							$probe_mode = '0';
							if (isset($col_map['probe_mode']) && isset($data[$col_map['probe_mode']])) {
								$probe_mode = trim($data[$col_map['probe_mode']]);
							}
							
							$socket = '';
							if (isset($col_map['socket']) && isset($data[$col_map['socket']])) {
								$socket = trim($data[$col_map['socket']]);
							}
							
							$state = '0';
							if (isset($col_map['state']) && isset($data[$col_map['state']])) {
								$state = trim($data[$col_map['state']]);
							}
							
							// Validate required fields
							if (empty($gwid) || empty($address)) {
								$error_rows[] = "Row $row_num: Missing required fields (gwid or address)";
								continue;
							}
							
							// Validate gwid format
							if (!preg_match('/^[0-9a-zA-Z_\-]+$/', $gwid)) {
								$error_rows[] = "Row $row_num: Invalid gwid format (must contain alphanumeric chars, '_' or '-')";
								continue;
							}
							
							// Check if gateway already exists
							$sql = "SELECT count(*) FROM ".$table." WHERE gwid = ?";
							$stm = $link->prepare($sql);
							if ($stm === false) {
								$error_rows[] = "Row $row_num: Database error checking for duplicate";
								continue;
							}
							$stm->execute(array($gwid));
							$result = $stm->fetchColumn(0);
							if ($result > 0) {
								$skipped_count++;
								continue;
							}
							
							// Set defaults
							if (empty($type)) {
								$type = get_settings_value("default_gw_type");
							}
							if (empty($strip)) {
								$strip = "0";
							}
							if (!is_numeric($strip) || $strip < 0) {
								$error_rows[] = "Row $row_num: Invalid strip value (must be numeric and >= 0)";
								continue;
							}
							if (empty($probe_mode)) {
								$probe_mode = 0;
							}
							if (!is_numeric($probe_mode) || !in_array($probe_mode, array('0', '1', '2'))) {
								$probe_mode = 0;
							}
							if (empty($state)) {
								$state = 0;
							}
							if (!is_numeric($state) || !in_array($state, array('0', '1', '2'))) {
								$state = 0;
							}
							
							// Handle gateway attributes mode
							if (get_settings_value("gw_attributes_mode") == "params") {
								// For params mode, attrs should be built from extra columns if present
								// For simplicity in CSV, we'll use the attrs column directly if provided
								// Otherwise, build from extra_ columns if they exist in CSV
								$attrs_to_use = $attrs;
								if (empty($attrs_to_use)) {
									$attrs_to_use = "";
								}
							} else {
								$attrs_to_use = $attrs;
							}
							
							// Validate attributes if in input mode
							if (get_settings_value("gw_attributes_mode") == "input") {
								$gw_attributes = get_settings_value("gw_attributes");
								if (!empty($attrs_to_use) && isset($gw_attributes['validation_regexp']) && 
									!empty($gw_attributes['validation_regexp']) &&
									!preg_match('/'.$gw_attributes['validation_regexp'].'/i', $attrs_to_use)) {
									$error_rows[] = "Row $row_num: Invalid attributes format";
									continue;
								}
							}
							
							// Insert gateway
							$sql = "INSERT INTO ".$table." (gwid, type, address, attrs, strip, pri_prefix, probe_mode, socket, state, description) ".
								"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
							$stm = $link->prepare($sql);
							if ($stm === false) {
								$error_rows[] = "Row $row_num: Database error preparing insert";
								continue;
							}
							
							if ($stm->execute(array($gwid, $type, $address, $attrs_to_use, $strip, $pri_prefix, $probe_mode, $socket, $state, $description)) === false) {
								$error_rows[] = "Row $row_num: Database error inserting gateway: " . print_r($stm->errorInfo(), true);
								continue;
							}
							
							$inserted_count++;
						}
						fclose($handle);
						
						// Build success/error message
						if ($inserted_count > 0 || $skipped_count > 0) {
							$msg_parts = array();
							if ($inserted_count > 0) {
								$msg_parts[] = "Successfully imported $inserted_count gateway(s)";
							}
							if ($skipped_count > 0) {
								$msg_parts[] = "$skipped_count gateway(s) skipped (duplicates)";
							}
							$csv_upload_success = implode(". ", $msg_parts) . ".";
						}
						
						if (!empty($error_rows)) {
							$csv_upload_error = "Some rows had errors:<br>" . implode("<br>", array_slice($error_rows, 0, 10));
							if (count($error_rows) > 10) {
								$csv_upload_error .= "<br>... and " . (count($error_rows) - 10) . " more errors";
							}
						}
					}
				}
			}
		}
	}
	
	// Show result in dialog
	require("lib/".$page_id.".functions.inc.php");
	require("template/".$page_id.".csv_upload.php");
	exit();
}
##################
# end csv upload #
##################

#################
# start details #
#################
 if ($action=="details")
 {
  $sql = "select * from ".$table." where gwid=?";
  $stm = $link->prepare($sql);
  if ($stm === false) {
    die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
  }
  $stm->execute( array($_GET['gwid']) );
  $resultset = $stm->fetchAll(PDO::FETCH_ASSOC);
  require("lib/".$page_id.".functions.inc.php");
  require("template/".$page_id.".details.php");
  require("template/footer.php");
  exit();
 }
###############
# end details #
###############


######################
# start enable gw    #
######################
if ($action=="enablegw"){
	$mi_connectors=get_proxys_by_assoc_id(get_settings_value('talk_to_this_assoc_id'));

	$params = array("gw_id"=>$_GET['gwid'],"status"=>"1");
	if (get_settings_value("routing_partition") && get_settings_value("routing_partition") != "")
		$params['partition_name'] = get_settings_value("routing_partition");

    	for ($i=0;$i<count($mi_connectors);$i++){
		$message=mi_command("dr_gw_status", $params, $mi_connectors[$i], $errors);
	}
	if (!empty($errors))
		echo "Error while enabling gateway ".$_GET['gwid']." (".$errors[0].")";
}
##################
# end enable gw  #
##################


#######################
# start disable gw    #
#######################
if ($action=="disablegw"){
	$mi_connectors=get_proxys_by_assoc_id(get_settings_value('talk_to_this_assoc_id'));

	$params = array("gw_id"=>$_GET['gwid'],"status"=>"0");
	if (get_settings_value("routing_partition") && get_settings_value("routing_partition") != "")
		$params['partition_name'] = get_settings_value("routing_partition");

    	for ($i=0;$i<count($mi_connectors);$i++){
		$message=mi_command("dr_gw_status", $params, $mi_connectors[$i], $errors);
	}
	if (!empty($errors))
		echo "Error while enabling gateway ".$_GET['gwid']." (".$errors[0].")";
}
##################
# end disable gw  #
##################

######################
# start probing gw   #
######################
if ($action=="probegw"){
	$mi_connectors=get_proxys_by_assoc_id(get_settings_value('talk_to_this_assoc_id'));

	$params = array("gw_id"=>$_GET['gwid'],"status"=>"2");
	if (get_settings_value("routing_partition") && get_settings_value("routing_partition") != "")
		$params['partition_name'] = get_settings_value("routing_partition");

    	for ($i=0;$i<count($mi_connectors);$i++){
		$message=mi_command("dr_gw_status", $params, $mi_connectors[$i], $errors);
	}
	if (!empty($errors))
		echo "Error while enabling gateway ".$_GET['gwid']." (".$errors[0].")";
}
##################
# end probing gw #
##################


################
# start modify #
################
 if ($action=="modify")
 {
  require("lib/".$page_id.".test.inc.php");
  if ($form_valid) {
		if (!isset($type))
			$type = get_settings_value("default_gw_type");
		if (get_settings_value("gw_attributes_mode") == "params")
			$attrs = dr_build_attrs(get_settings_value("gw_attributes"));
                $sql = "update ".$table." set gwid=?, type=?, attrs=?, address=?, strip=?, pri_prefix=?, probe_mode=?, socket=?, state=?, description=? where id=?";
		$stm = $link->prepare($sql);
		if ($stm === false) {
			die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
		}
		if ($stm->execute( array($gwid,$type,$attrs,$address,$strip,$pri_prefix,$probe_mode,$socket,$state,$description,$_GET['id']) )==NULL)
			echo 'Gateway DB update failed : ' . print_r($stm->errorInfo(), true);
  }
  if ($form_valid) $action="";
   else $action="edit";
 }
##############
# end modify #
##############

##############
# start edit # 
##############
 if ($action=="edit")
 {
  $sql = "select * from ".$table." where id=?";
  $stm = $link->prepare($sql);
  if ($stm === false) {
    die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
  }
  $stm->execute( array($_GET['id']) );
  $resultset = $stm->fetchAll(PDO::FETCH_ASSOC);
  require("lib/".$page_id.".functions.inc.php");
  require("template/".$page_id.".edit.php");
  require("template/footer.php");
  exit();
 }
############
# end edit #
############

####################
# start add verify #
####################
 if ($action=="add_verify")
 {
  require("lib/".$page_id.".test.inc.php");
  if ($form_valid) {
	if (get_settings_value("gw_attributes_mode") == "params")
		$attrs = dr_build_attrs(get_settings_value("gw_attributes"));
	$_SESSION['gateways_search_gwid']="";
	$_SESSION['gateways_search_type']="";
	$_SESSION['gateways_search_address']="";
	$_SESSION['gateways_search_pri_prefix']="";
	$_SESSION['gateways_search_probe_mode']="";
	$_SESSION['gateways_search_description']="";
	$_SESSION['gateways_search_attrs']="";
	if (!isset($type))
		$type = get_settings_value("default_gw_type");
	$sql = "insert into ".$table." (gwid, type, address, attrs,strip, pri_prefix, probe_mode, socket, state, description) ".
		"values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stm = $link->prepare($sql);
	if ($stm === false) {
		die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
	}
	if ($stm->execute( array($gwid,$type,$address,$attrs,$strip,$pri_prefix,$probe_mode,$socket,$state,$description) )==NULL)
		echo 'Gateway DB update failed : ' . print_r($stm->errorInfo(), true);
  }
  if ($form_valid) $action="";
   else $action="add";
 }
##################
# end add verify #
##################

#################
# start add new # 
#################
 if ($action=="add")
 {
  if ($_POST['add']=="Add") extract($_POST);
   else $strip="0";
  require("lib/".$page_id.".functions.inc.php");
  require("template/".$page_id.".add.php");
  require("template/footer.php");
  exit();
 }
###############
# end add new #
###############

################
# start delete #
################
if ($action=="delete"){
	$del_id=$_GET['gwid'];
	$sql = "delete from ".$table." where gwid=?";
	$stm = $link->prepare($sql);
	if ($stm === false) {
		die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
	}
	$stm->execute( array($del_id) ); 

	$sql_regex = "'(^|,)".$del_id."(=|,|$)'";
	$repl_regex1 = "'(,".$del_id."(=[^,]+)?,)'";
	$repl_regex2 = "'((^|,)".$del_id."(=[^,]+)?(,|$))'";

	//remove GW from dr_rules
	if ($config->db_driver == "mysql") 
		$sql = "select ruleid,gwlist from ".get_settings_value("table_rules")." where gwlist regexp ";
	else if ($config->db_driver == "pgsql")
		$sql = "select ruleid,gwlist from ".get_settings_value("table_rules")." where gwlist ~* ?";

	$stm = $link->query($sql.$sql_regex);
	if ($stm === false) {
		die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
	}
	$resultset = $stm->fetchAll(PDO::FETCH_ASSOC);

	for($i=0;count($resultset)>$i;$i++){
		$list=$resultset[$i]['gwlist'];
		$new_list = preg_replace($repl_regex1,',',$list);
		$new_list = preg_replace($repl_regex2,'',$new_list);
		if ($new_list!=$list) {
			$sql = "update ".get_settings_value("table_rules")." set gwlist=? where ruleid=?";
			$stm = $link->prepare($sql);
			if ($stm === false) {
				die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
			}
			if ($stm->execute( array($new_list,$resultset[$i]['ruleid']) )==NULL)
				echo 'Rule DB update failed : ' . print_r($stm->errorInfo(), true);
		}
	}

	//remove GW from dr_carriers
	if ($config->db_driver == "mysql")
		$sql = "select carrierid,gwlist from ".get_settings_value("table_carriers")." where gwlist regexp ?";
	else if ($config->db_driver == "pgsql")
		$sql = "select carrierid,gwlist from ".get_settings_value("table_rules")." where gwlist ~* ?";

	$stm = $link->prepare($sql);
	if ($stm === false) {
		die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
	}
	$stm->execute( array($sql_regex) );
	$resultset = $stm->fetchAll(PDO::FETCH_ASSOC);

	for($i=0;count($resultset)>$i;$i++){
		$list = $resultset[$i]['gwlist'];
		$new_list = preg_replace($repl_regex1,',',$list);
		$new_list = preg_replace($repl_regex2,'',$new_list);
		if ($new_list!=$list) {
			$sql = "update ".get_settings_value("table_carriers")." set gwlist=? where carrierid=?";
			$stm = $link->prepare($sql);
			if ($stm === false) {
				die('Failed to issue query ['.$sql.'], error message : ' . print_r($link->errorInfo(), true));
			}
			if ($stm->execute( array($list,$resultset[$i]['carrierid']) )==NULL)
				echo 'Carrier DB update failed : ' . print_r($stm->errorInfo(), true);
		}
	}
}
##############
# end delete #
##############

################
# start search #
################
if ($action=="search") {

	$_SESSION[$current_page]=1;
	extract($_POST);

	if (isset($show_all) && $show_all=="Show All") {
		$_SESSION['gateways_search_gwid']="";
		$_SESSION['gateways_search_type']="";
		$_SESSION['gateways_search_address']="";
		$_SESSION['gateways_search_pri_prefix']="";
		$_SESSION['gateways_search_probe_mode']="";
		$_SESSION['gateways_search_description']="";
		$_SESSION['gateways_search_attrs']="";
	}
	else {
         $_SESSION['gateways_search_gwid']=isset($search_gwid) ? $search_gwid : "";
         $_SESSION['gateways_search_type']=isset($search_type) ? $search_type : "";
         $_SESSION['gateways_search_address']=isset($search_address) ? $search_address : "";
         $_SESSION['gateways_search_pri_prefix']=isset($search_pri_prefix) ? $search_pri_prefix : "";
		 $_SESSION['gateways_search_probe_mode']=isset($probe_mode) ? $probe_mode : "";
         $_SESSION['gateways_search_description']=isset($search_description) ? $search_description : "";
		 $_SESSION['gateways_search_attrs']=isset($search_attrs) ? $search_attrs : "";
	}
}
##############
# end search #
##############

##############
# start main #
##############
 require("lib/".$page_id.".functions.inc.php");
 require("template/".$page_id.".main.php");
 require("template/footer.php");
 exit();
############
# end main #
############
?>
