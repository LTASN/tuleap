<?php
//
// CodeX: Breaking Down the Barriers to Source Code Sharing inside Xerox
// Copyright (c) Xerox Corporation, CodeX/CodeX Team, 2001. All Rights Reserved
// http://codex.xerox.com
//
// $Id$

require ('pre.php');
require('../bug_data.php');
require('../bug_utils.php');
$is_admin_page='y';

if ($group_id && (user_ismember($group_id,'B2') || user_ismember($group_id,'A'))) {

    // Initialize global bug structures
    bug_init($group_id);

    if ($post_changes) {
	// A form of some sort was posted to update or create
	// an existing value

	if ($create_value) {
	    // A form was posted to update a field value
	    bug_data_create_value($field,$group_id, htmlspecialchars($value),
				  htmlspecialchars($description),$order_id,'A');
	    
	} else if ($update_value) {
	    // A form was posted to update a field value
	    
	    bug_data_update_value($fv_id, $field, $group_id,
				  htmlspecialchars($value),
				  htmlspecialchars($description),
				  $order_id,$status);
	    
	} else if ($create_canned) {

	    // A form was posted to create a canned response
	    $sql="INSERT INTO bug_canned_responses (group_id,title,body) ".
		" VALUES ('$group_id','". htmlspecialchars($title) . 
		"','". htmlspecialchars($body) ."')";
	    $result=db_query($sql);
	    if (!$result) {
		$feedback .= ' Error inserting canned bug response! ';
		$feedback .= ' - '.db_error();
	    } else {
		$feedback .= ' Canned bug response inserted ';
	    }	    

	} else if ($update_canned) {

	    // A form was posted to update a canned response
	    $sql="UPDATE bug_canned_responses".
		"SET title='". htmlspecialchars($title) ."', body='". htmlspecialchars($body).
		"' WHERE group_id='$group_id' AND bug_canned_id='$bug_canned_id'";
	    $result=db_query($sql);
	    if (!$result) {
		$feedback .= ' Error updating canned bug response! ';
		$feedback .= ' - '.db_error();
	    } else {
		$feedback .= ' Canned bug response updated ';
	    }	    
	}

    } /* End of post_changes */


    // Display the UI form

    if ($list_value) {

	// Display the List of values for a given bug field

	bug_header_admin(array ('title'=>'Create/Modify Field Values'));

	echo "<H2>Create/Modify Field Values for  '".bug_data_get_label($field)."'</H2>";

	// First check that this field is used by the project and
	// it is in the project scope
	if ( bug_data_get_field_id($field) && 
	     bug_data_is_project_scope($field)) {

	    $result = bug_data_get_field_predefined_values($field, $group_id,false,false,false);
	    $rows = db_numrows($result);

	    if ($result && $rows > 0) {
		echo "\n<H3>Existing Values</H3> (Click to modify)";

		$title_arr=array();
		$title_arr[]='Value';
		$title_arr[]='Description';
		$title_arr[]='Rank';
		$title_arr[]='Status';
		
		
		$hdr = html_build_list_table_top ($title_arr);

		$ia = $ih = 0;
		$status_stg = array('A' => 'Active', 'P' => 'Permanent', 'H' => 'Hidden');

		// Display the list of values in 2 blocks : active first
		// Hidden second
		while ( $fld_val = db_fetch_array($result) ) {

		    $bug_fv_id = $fld_val['bug_fv_id'];
		    $status = $fld_val['status'];	
		    $value_id = $fld_val['value_id'];
		    $value = $fld_val['value'];
		    $description = $fld_val['description'];
		    $order_id = $fld_val['order_id'];

		    $html = '';

		    // keep the rank of the 'None' value in mind if any (see below)
		    if ($value == 100) { $none_rk = $order_id; }

		    // The permanent values can't be modified (No link)
		    if ($status == 'P') {
			$html .= '<td>'.$value.'</td>';
		    } else {
			$html .= '<td><A HREF="'.$PHP_SELF.'?update_value=1'.
			    '&fv_id='.$bug_fv_id.'&field='.$field.
			    '&group_id='.$group_id.'">'.$value.'</A></td>';
		    }

		    $html .= '<TD>'.$description.'&nbsp;</TD>'.
			'<TD align="center">'.$order_id.'</TD>'.
			'<TD align="center">'.$status_stg[$status].'</TD>';

		    if ($status == 'A' || $status == 'P') {
			$html = '<TR BGCOLOR="'. 
			util_get_alt_row_color($ia) .'">'.$html.'</tr>';
			$ia++;
			$ha .= $html;
		    } else {
			$html = '<TR BGCOLOR="'. 
			util_get_alt_row_color($ih) .'">'.$html.'</tr>';
			$ih++;
			$hh .= $html;
		    }

		}

		//Display the list of values now
		if ($ia == 0) {
		    $hdr = '<p>No Active value for this field. Create one or reactivate a hidden value (if any)<p>'.$hdr;
		} else {
		    $ha = '<tr><td colspan="4"><center><b>---- ACTIVE VALUES ----</b></center></tr>'.$ha;		    
		}
		if ($ih) {
		    $hh = '<tr><td colspan="4"> &nbsp;</td></tr>'.
		'<tr><td colspan="4"><center><b>---- HIDDEN VALUES ----</b></center></tr>'.$hh;
		}

		echo $hdr.$ha.$hh.'</TABLE>';
		
	    } else {
		echo "\n<H3>No values defined yet for ".bug_data_get_label($field)."</H3>";
	    }

?>

      <P><BR>
      <H3>Create a new field value</H3>
<?php
	   if ($ih) {
	       echo "<P>Before you create a new value make sure there isn't one in the hidden list that suits your needs.";
		   }
?>
      <FORM ACTION="<?php echo $PHP_SELF ?>" METHOD="POST">
      <INPUT TYPE="HIDDEN" NAME="post_changes" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="create_value" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="list_value" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="field" VALUE="<?php echo $field; ?>">
      <INPUT TYPE="HIDDEN" NAME="group_id" VALUE="<?php echo $group_id; ?>">
      <P><B>Value:</B><BR>
      <INPUT TYPE="TEXT" NAME="value" VALUE="" SIZE="30" MAXLENGTH="60">
      &nbsp;&nbsp;
      <B>Rank:</B>
      <INPUT TYPE="TEXT" NAME="order_id" VALUE="" SIZE="6" MAXLENGTH="6">
<?php
	   if (isset($none_rk)) {
	       echo "&nbsp;&nbsp;<b> (must be &gt; $none_rk)</b><BR>";
	   }
?>
      <P>
      <B>Description:</B> (optional)<BR>
      <TEXTAREA NAME="description" ROWS="4" COLS="65" WRAP="HARD"></TEXTAREA>
      <P>
      <INPUT TYPE="SUBMIT" NAME="SUBMIT" VALUE="SUBMIT">
      </FORM>
	   <P><b>Help:</b>
      <ul type="compact">
          <li><b>Value</b>:  an empty value is not permitted.<BR>
      <li><b>Rank</b>:  the rank number allows you to insert the new value at a given
      place in the list. Tip: When you create new values leave some space in between 2 rank numbers (e.g use 100, 200, 300,...) to make future insertion easier.

<?php
	   if (isset($none_rk)) {
	       echo "The rank number must be greater than the 'None' rank number (here $none_rk).<BR>";
	   }
?>
    <li><b>Status</b>: tells you whether a value is being used or not. When <i>Active</i> the value shows up in the pull down menus. When <i>Hidden</i> it does not show up in pull down menus. <i>Permanent</i> means that this value is forever active and you cannot hide it. The Status can be modified back and forth at any time in the life in the project.
      <li><b>Description</b>: it is optional and allows you to describe the meaning of a value.
      </ul>
		       
<?php

           } else {

               echo '<H3>The Bug field you requested \''.$field.'\' is not used by your project or you are not allowed to customize it';
           }


    } else if ($update_value) {
	// Show the form to update an existing field_value
	// Display the List of values for a given bug field

	bug_header_admin(array ('title'=>'Add/Change Field Values'));

	// Get all attributes of this value
	$res = bug_data_get_field_value($fv_id);
?>
      <H2>Update a field value</H2>
      <FORM ACTION="<?php echo $PHP_SELF ?>" METHOD="POST">
      <INPUT TYPE="HIDDEN" NAME="post_changes" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="update_value" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="list_value" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="fv_id" VALUE="<?php echo $fv_id; ?>">
      <INPUT TYPE="HIDDEN" NAME="field" VALUE="<?php echo $field; ?>">
      <INPUT TYPE="HIDDEN" NAME="group_id" VALUE="<?php echo $group_id; ?>">
      <P><B>Value:</B><BR>
      <INPUT TYPE="TEXT" NAME="value" VALUE="<?php echo db_result($res,0,'value'); ?>" SIZE="30" MAXLENGTH="60">
      &nbsp;&nbsp;
      <B>Rank:</B>
      <INPUT TYPE="TEXT" NAME="order_id" VALUE="<?php echo db_result($res,0,'order_id'); ?>" SIZE="6" MAXLENGTH="6">
      &nbsp;&nbsp;
      <B>Status:</B>
      <SELECT NAME="status">
	   <OPTION VALUE="A">Active</OPTION>
	   <OPTION VALUE="H" <?php echo ((db_result($res,0,'status') == 'H') ? ' SELECTED':'') ?> >Hidden</OPTION>
      </SELECT>
      <P>
      <B>Description:</B> (optional)<BR>
      <TEXTAREA NAME="description" ROWS="4" COLS="65" WRAP="SOFT"><?php echo db_result($res,0,'description'); ?></TEXTAREA>
      <P>
      <INPUT TYPE="SUBMIT" NAME="SUBMIT" VALUE="SUBMIT">
      </FORM>
      <B>Help:</B>
      <ul type="compact">
      <li><b>Value</b>: an empty value is not permitted.<BR>
      <li><b>Rank</b>: the rank number allows you to insert the new value at a given
      place in the list. Tip: leave some space in between 2 rank numbers (e.g use 100, 200, 300,...) to make future insertion of new values easier.
	   <li><b>Status</b>: set it to Active if you want the value to appear in the pull down menus. Hidden means it wont show up in the pull down menus (but bugs already using this value will continue to display ok.
           <li><b>Description</b>: it is optional and allows you to describe the meaning of this value.
      </ul>
      <P>		       
<?php


    } else if ($create_canned) {
	/*
	  Show existing responses and UI form
	*/
	bug_header_admin(array ('title'=>'Create/Modify Canned Responses'));

	echo "<H2>Create/Modify Canned Responses</H2>";
	
	$sql="SELECT bug_canned_id,title,body FROM bug_canned_responses WHERE group_id='$group_id'";
	$result=db_query($sql);
	$rows=db_numrows($result);
	echo "<P>";

	if($result && $rows > 0) {
	    /*
	      Links to update pages
	    */
	    echo "\n<H3>Existing Responses:</H3><P>";

	    $title_arr=array();
	    $title_arr[]='Title';
	    $title_arr[]='Body (extract)';
		
	    echo html_build_list_table_top ($title_arr);

	    for ($i=0; $i < $rows; $i++) {
		echo '<TR BGCOLOR="'. util_get_alt_row_color($i) .'">'.
		    '<TD><A HREF="'.$PHP_SELF.'?update_canned=1&bug_canned_id='.
		    db_result($result, $i, 'bug_canned_id').'&group_id='.$group_id.'">'.
		    db_result($result, $i, 'title').'</A></TD>'.
		    '<TD>'.substr(db_result($result, $i, 'body'),0,160).
		    '<b>...</b></TD></TR>';
	    }
	    echo '</TABLE>';

	} else {
	    echo "\n<H3>No canned bug responses set up yet</H3>";
	}
	/*
	  Escape to print the add response form
	*/
?>
     <h3>Create a new response</h3>
     <P>
     Creating generic quick responses can save a lot of time when giving common responses.
     <P>
     <FORM ACTION="<?php echo $PHP_SELF; ?>" METHOD="POST">
     <INPUT TYPE="HIDDEN" NAME="create_canned" VALUE="y">
     <INPUT TYPE="HIDDEN" NAME="group_id" VALUE="<?php echo $group_id; ?>">
     <INPUT TYPE="HIDDEN" NAME="post_changes" VALUE="y">
     <B>Title:</B><BR>
     <INPUT TYPE="TEXT" NAME="title" VALUE="" SIZE="50" MAXLENGTH="50">
     <P>
     <B>Message Body:</B><BR>
     <TEXTAREA NAME="body" ROWS="20" COLS="65" WRAP="HARD"></TEXTAREA>
     <P>
     <INPUT TYPE="SUBMIT" NAME="SUBMIT" VALUE="SUBMIT">
     </FORM>
     <?php

	
    } else if ($update_canned) {
	/*
	  Allow change of canned responses
	*/
	bug_header_admin(array ('title'=>'Modify Canned Response'));

	echo "<H2>Modify Canned Response</H2>";

	$sql="SELECT bug_canned_id,title,body FROM bug_canned_responses WHERE ".
	    "group_id='$group_id' AND bug_canned_id='$bug_canned_id'";

	$result=db_query($sql);
	echo "<P>";
	if (!$result || db_numrows($result) < 1) {
	    echo "\n<H2>No such response!</H2>";
	} else {
	    /*
	      Escape to print update form
	    */
    ?>
      <P>
      Creating generic messages can save you a lot of time when giving common responses.
      <P>
      <FORM ACTION="<?php echo $PHP_SELF; ?>" METHOD="POST">
      <INPUT TYPE="HIDDEN" NAME="update_canned" VALUE="y">
      <INPUT TYPE="HIDDEN" NAME="group_id" VALUE="<?php echo $group_id; ?>">
      <INPUT TYPE="HIDDEN" NAME="bug_canned_id" VALUE="<?php echo $bug_canned_id; ?>">
      <INPUT TYPE="HIDDEN" NAME="post_changes" VALUE="y">
      <B>Title:</B><BR>
      <INPUT TYPE="TEXT" NAME="title" VALUE="<?php echo db_result($result,0,'title'); ?>" SIZE="50" MAXLENGTH="50">
      <P>
      <B>Message Body:</B><BR>
      <TEXTAREA NAME="body" ROWS="20" COLS="65" WRAP="HARD"><?php echo db_result($result,0,'body'); ?></TEXTAREA>
      <P>
      <INPUT TYPE="SUBMIT" NAME="SUBMIT" VALUE="SUBMIT">
      </FORM>

<?php
       }

    } else {

	bug_header_admin(array ('title'=>'Bug Administration - Field Values Management'));
	
	echo '<H2>Manage Field values</H2>';
	echo 'The CodeX bug tracking system allows you to define your own values for soome of the fields you have decided to use (see Field Usage above). To customize the set of predefined values for a given field, simply click on the correpsonding field below<P>';

	// Loop through the list of all used fields that are project manageable
	$i=0;
	$title_arr=array();
	$title_arr[]='Field Label';
	$title_arr[]='Description';
	echo html_build_list_table_top ($title_arr);
	while ( $field_name = bug_list_all_fields() ) {

	    if (bug_data_is_project_scope($field_name)
		&& bug_data_is_used($field_name) ) {
		echo '<TR BGCOLOR="'. util_get_alt_row_color($i) .'">'.
		    '<TD><A HREF="'.$PHP_SELF.'?group_id='.$group_id.'&list_value=1&field='.$field_name.'">'.bug_data_get_label($field_name).'</A></td>'.
		    "\n<td>".bug_data_get_description($field_name).'</td>'.
		    '</tr>';
		$i++;
	    }	
	}

	// Now the special canned response field
	echo "<td><A HREF=\"$PHP_SELF?group_id=$group_id&create_canned=1\">Canned Responses</A></td>";
	echo "\n<td>Create or Change generic quick response messages for the bug tracking tool. Theses pre-written messages can then be used to quickly reply to bug submission. </td>";
	echo '</TABLE>';
    }

    bug_footer(array());

} else {

    //browse for group first message
    if (!$group_id) {
	exit_no_group();
    } else {
	exit_permission_denied();
    }

}
?>
