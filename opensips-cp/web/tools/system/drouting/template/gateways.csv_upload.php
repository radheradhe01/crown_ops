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

if (isset($csv_upload_error)) {
	echo('<tr align="center"><td colspan="2" class="dataRecord"><div class="formError">'.$csv_upload_error.'</div></td></tr>');
}
if (isset($csv_upload_success)) {
	echo('<tr align="center"><td colspan="2" class="dataRecord"><div class="formSuccess">'.$csv_upload_success.'</div></td></tr>');
}
?>

<form action="<?=$page_name?>?action=csv_upload" method="post" enctype="multipart/form-data">
<?php csrfguard_generate(); ?>
<table width="500" cellspacing="2" cellpadding="2" border="0" bgcolor="white" style="border: 1px solid #ccc;">
 <tr align="center">
  <td colspan="2" class="mainTitle" style="background-color: #4a6fa5; color: white; padding: 8px;">Upload CSV File</td>
 </tr>
 <tr>
  <td colspan="2" class="dataRecord" style="padding: 15px; background-color: #f9f9f9;">
   <div style="margin-bottom: 15px;">
    <b style="color: #4a6fa5;">CSV Format Requirements:</b>
   </div>
   <ul style="text-align: left; margin: 10px 0 15px 25px; padding: 0; line-height: 1.6;">
    <li style="margin-bottom: 5px;">CSV file must have a <b>header row</b> with column names</li>
    <li style="margin-bottom: 5px;"><b>Required columns:</b> <span style="color: #d00;">GWID</span> (or Gateway ID), <span style="color: #d00;">SIP Address</span> (or Address)</li>
    <li style="margin-bottom: 5px;"><b>Optional columns:</b> PRI Prefix (port will be combined with address if numeric), Description, Type, Strip, Probe Mode, Socket, State, Attributes</li>
    <li style="margin-bottom: 5px;">Duplicate gateways (same gwid) will be <b>automatically skipped</b></li>
    <li style="margin-bottom: 5px;"><b>Note:</b> If PRI Prefix contains a port number (4-5 digits), it will be combined with SIP Address as "IP:port"</li>
   </ul>
   <div style="margin-top: 15px; margin-bottom: 10px;">
    <b style="color: #4a6fa5;">Example CSV format:</b>
   </div>
   <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 11px; overflow-x: auto; border-radius: 3px;">
GWID,SIP Address,PRI Prefix,Description
1626,162.221.43.67,5062,USA
1627,12.215.240.84,5063,USA
   </div>
  </td>
 </tr>
 <tr>
  <td class="dataRecord" width="150" align="right" style="padding: 10px; vertical-align: middle;"><b>Select CSV File:</b></td>
  <td class="dataRecord" width="350" style="padding: 10px;">
   <input type="file" name="csv_file" accept=".csv" required class="dataInput" style="width: 100%; padding: 5px;">
  </td>
 </tr>
 <tr>
  <td colspan="2" class="dataRecord" align="center" style="padding: 20px 10px 15px 10px; border-top: 1px solid #ddd;">
   <?php if (isset($csv_upload_success) && !empty($csv_upload_success)) { ?>
    <input onclick="window.location.reload();" class="formButton" value="Close & Refresh" type="button"/>
   <?php } else { ?>
    <input type="submit" name="upload" value="Upload" class="formButton"> &nbsp;&nbsp;&nbsp;
    <input onclick="closeDialog();" class="formButton" value="Cancel" type="button"/>
   <?php } ?>
  </td>
 </tr>
</table>
</form>
