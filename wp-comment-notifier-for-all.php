<?php
/*
 Plugin Name: WP Comment Notifier For All
 Plugin URI: http://faycaltirich.blogspot.com/1979/01/wp-comment-notifier-for-all.html
 Description: Notify all Wordpress users (and not only the admin) on on comment approval.
 Version: 2.4.1
 Author: Fayçal Tirich
 Author URI: http://faycaltirich.blogspot.com
 */

define("WCNFA_ACTIVATION-DATE", "wp-comment-notifier-for-all_first-activation-date");
define("WCNFA_NOTIFIED-COMMENTS", "wp-comment-notifier-for-all_notified-comments");
define("WCNFA_EXCLUDE", "wp-comment-notifier-for-all_exclude");
define("WCNFA_FROM-TPL", "wp-comment-notifier-for-all_from-tpl");
define("WCNFA_SUBJECT-TPL", "wp-comment-notifier-for-all_subject-tpl");
define("WCNFA_BODY-TPL", "wp-comment-notifier-for-all_body-tpl");

$cnfa_from_tpl = "Name <iLove@gmail.com>";
$cnfa_subject_tpl = "[BLOG_NAME] - [COMMENT_AUTHOR] just published a new comment for : [TITLE]";
$cnfa_body_tpl=<<<EOD
<center>[LOGO]http://www.example.org/logo.png[/LOGO]</center><br />
[COMMENT_AUTHOR] just commented the post:<br />
<h3>[TITLE_LINK]</h3>
In: [CATEGORIES]<br /><br />
<i>[COMMENT_CONTENT]</i><br /><br />
Good reading !<br />
EOD;

function cnfa_get_users() {
    global $wpdb;
    $blog_users = array();
    $users = get_users();
    foreach($users as $user) {
        $object = new stdClass();
        $object->ID = $user->ID;
        $object->user_login = $user->user_login;
        $object->display_name = $user->display_name;
        $object->user_email = $user->user_email;
        $blog_users[$user->ID]=$object;
        $isExcluded = 0;
        $savedIsExcluded = get_user_meta($user->ID, constant("WCNFA_EXCLUDE"), true);
        if(!empty($savedIsExcluded) && $savedIsExcluded==1){
            $isExcluded = 1;
        }
        $object->isExcluded = $isExcluded;
    }
    return $blog_users;
}

$cnfa_otions_msg = '';
if ( isset($_POST['cnfa_submit']) ) {
    update_option(constant("WCNFA_FROM-TPL"), htmlentities(stripslashes_deep(trim($_POST['cnfa_from'])),ENT_QUOTES, "UTF-8"));
    update_option(constant("WCNFA_SUBJECT-TPL"), htmlentities(stripslashes_deep(trim($_POST['cnfa_subject'])),ENT_QUOTES, "UTF-8"));
    update_option(constant("WCNFA_BODY-TPL"), htmlentities(stripslashes_deep(trim($_POST['cnfa_body'])),ENT_QUOTES, "UTF-8"));
    $cnfa_otions_msg = '<span style="color:green">'.__('Options updated').'</span><br />';
}

$cnfa_from = get_option(constant("WCNFA_FROM-TPL"), $cnfa_from_tpl);
$cnfa_subject = get_option(constant("WCNFA_SUBJECT-TPL"), $cnfa_subject_tpl);
$cnfa_body = get_option(constant("WCNFA_BODY-TPL"), $cnfa_body_tpl);

//for blog users's comments
function cnfa_comment_post($comment_ID) {
    $process = 0;
    $post = get_post($comment_ID);
    //check if the post was already notified
    $notified_comments = get_option(constant("WCNFA_NOTIFIED-COMMENTS"));
    if (!is_array($notified_comments)){
        $notified_comments = array();
        update_option(constant("WCNFA_NOTIFIED-COMMENTS"), $notified_comments);
        $process = 1;
    } else {
        if (in_array($comment_ID, $notified_comments)) {
            $process = 0;
        } else {
            $process = 1;
        }
    }
    
    if ($process==1){
        $comment = get_comment($comment_ID);
        if ($comment->user_id != 0) {
            $process = 1;
        }else {
            $process = 0;
        }
    }
    
    if ($process==1) {
        cnfa_notification_email($comment, $notified_comments);
    }
    return $comment_ID;
}

//when changing a comment status (for others)
function cnfa_set_comment_status($comment_ID, $status) {
    if ($status=='approve') {
        $comment = get_comment($comment_ID);
        $process = 0;
        $options =  get_option(constant("WCNFA_NOTIFIED-COMMENTS"));
        if (!is_array($options)) {
            $options = array ();
            update_option(constant("WCNFA_NOTIFIED-COMMENTS"), $options);
            $process = 1;
        } else {
            if (in_array($comment_ID, $options)) {
                $process = 0;
            } else {
                $process = 1;
            }
        }
        if ($process==1)
        {
            cnfa_notification_email($comment, $options);
        }
    }
    return $comment_ID;
}

function cnfa_notification_email($comment, $notified_comments){
    global $cnfa_from, $cnfa_subject, $cnfa_body;
    $users = cnfa_get_users();
    foreach($users as $user) {
        if (!$user->isExcluded) {
            $emails[] = $user->user_email;
        }
    }

    $cnfa_subject = str_replace('[COMMENT_AUTHOR]', htmlspecialchars_decode($comment->comment_author), $cnfa_subject);
    $cnfa_subject = str_replace('[BLOG_NAME]',html_entity_decode(get_bloginfo('name'), ENT_QUOTES), $cnfa_subject);
    $post = get_post($comment->comment_post_ID);
    $cnfa_subject = str_replace('[TITLE]',  htmlspecialchars_decode($post->post_title), $cnfa_subject);

    $cnfa_body = str_replace('[COMMENT_AUTHOR]', htmlspecialchars_decode($comment->comment_author), $cnfa_body);

    $link = '<a style="color: #2D83D5" href="'.get_permalink($comment->comment_post_ID).'#comment-'.$comment->comment_ID.'">'.$post->post_title.'</a>';
    $cnfa_body = str_replace('[TITLE_LINK]', $link, $cnfa_body);
    $cnfa_body = str_replace('[COMMENT_CONTENT]', nl2br(htmlspecialchars_decode($comment->comment_content)), $cnfa_body);
    $cnfa_body = str_replace('[BLOG_NAME]',html_entity_decode(get_bloginfo('name'), ENT_QUOTES), $cnfa_body);
    
    $post_categories = wp_get_post_categories( $comment->comment_post_ID , array('fields' => 'all'));
    $cats = '';
    foreach ($post_categories as $cat){
    	$cats .= $cat->name.', ';
    }
    $cats = substr($cats,0,-2);
    $cnfa_body = str_replace('[CATEGORIES]', htmlspecialchars_decode($cats), $cnfa_body);
    
    //

    //
    $cnfa_from = html_entity_decode($cnfa_from, ENT_QUOTES, "UTF-8");
    $message_headers = "From: ".$cnfa_from."\n";
    $message_headers .= "MIME-Version: 1.0\n";
    $message_headers .= "Content-type: text/html; charset=UTF-8\r\n"; 

    $message .= "<html>\n";
    $message .= "<body style=\"font-family:Verdana, Verdana, Geneva, sans-serif; font-size:12px; color:#666666;\">\n";
    $cnfa_body = html_entity_decode($cnfa_body);
    $message .= $cnfa_body;
    $message .= "\n\n";
    $message .= "</body>\n";
    $message .= "</html>\n";
    
    add_filter('wp_mail_charset', 'cnfa_get_mail_charset');
    foreach ( $emails as $email ){
        @wp_mail($email, $cnfa_subject, $message, $message_headers );
    }
    remove_filter('wp_mail_charset', 'cnfa_get_mail_charset');
    
    $notified_comments[] = $comment->comment_ID;
    sort($notified_comments);
    update_option(constant("WCNFA_NOTIFIED-COMMENTS"), $notified_comments);
}

function cnfa_get_mail_charset(){
    return "UTF-8";
}

// Options page
function cnfa_options() {
    global $cnfa_from_tpl, $cnfa_from, $cnfa_body_tpl, $cnfa_subject_tpl, $cnfa_body, $cnfa_subject, $cnfa_otions_msg ;
    if ( isset($_POST['cnfa_exclude_submit']) ) {
        $users_to_exclude_array = array();
        if(isset($_POST['cnfa_excluded_users'])) {
            $users_to_exclude_array = $_POST['cnfa_excluded_users'];
        }
        $users = cnfa_get_users();
        $log = '';
        foreach($users as $user) {
            if (in_array($user->ID, $users_to_exclude_array)) {
                if (!$user->isExcluded){
                    update_user_meta($user->ID,constant("WCNFA_EXCLUDE"),1);
                    $log = $log .'<span style="color:green">'.$user->display_name.' excluded</span><br />';
                }
            } else {
                if ($user->isExcluded){
                    update_user_meta($user->ID,constant("WCNFA_EXCLUDE"),0);
                    $log = $log .'<span style="color:green">'.$user->display_name.' will be notified</span><br />';
                }
            }
        }
        if ($log!=''){
            $cnfa_otions_msg = $log;
        }
    }
    if(!empty($cnfa_otions_msg)) {
        ?>
<!-- Last Action -->
<div id="message" class="updated fade">
	<p>
	<?php echo $cnfa_otions_msg; ?>
	</p>
</div>
	<?php
    }
    ?>
<style type="text/css">
  .excluded {
  		background-color : #FF9999;
  		}
  .defaultText {
  		font-size : smaller !important;
  		margin-bottom: 20px !important;
  		}
</style>
<div class="wrap" >
<?php screen_icon(); ?>
	<h2>Comment Notifier For All</h2>
	<form method="post" action="">
		<table class="widefat">
			<thead>
				<tr>
					<th>Email Notification Template</th>
				</tr>
			</thead>
			<tbody>
				<tr>
            		<td>
            			<div>
            				<label for="cnfa_from"><strong>Email "From" Template</strong> </label>
            				<br /> <input type="text" size="150" id="cnfa_from" name="cnfa_from"
            					value="<?php echo $cnfa_from; ?>" />
            				<p class="defaultText">
            					Default:<br />
            					<?php
            					$temp = $cnfa_from_tpl ;
            					$temp = str_replace("<","&lt;",$temp);
            					$temp = str_replace(">","&gt;",$temp);
            					echo nl2br($temp);
            					?>
            				</p>
            			</div>
            			<div>
            				<label for="cnfa_subject"><strong>Email "Subject" Template</strong>
            				</label> <br /> <input type="text" size="150" id="cnfa_subject"
            					name="cnfa_subject" value="<?php echo $cnfa_subject; ?>" />
            				<p class="defaultText">
            					Default:<br />
            					<?php echo $cnfa_subject_tpl; ?>
            				</p>
            			</div>
            			<div>
            				<label for="cnfa_body"><strong>Email "Body" Template</strong> </label>
            				<br />
            				<textarea style="width: 90%; font-size: 12px;" rows="8" cols="60" id="cnfa_body" name="cnfa_body"><?php echo $cnfa_body; ?></textarea>
            				<p class="defaultText">
            					Default:<br />
            					<?php
            					$temp = $cnfa_body_tpl ;
            					$temp = str_replace("<","&lt;",$temp);
            					$temp = str_replace(">","&gt;",$temp);
            					echo nl2br($temp);
            					?>
            				</p>
            			</div>
            			<p class="submit">
            				<input class="button-primary" type="submit" name="cnfa_submit"
            					class="button" value="<?php _e('Save Changes'); ?>" />
            			</p>
            		</td>
            	</tr>
			</tbody>
		</table>
	</form>
	<br />
	<?php include 'donate.php';?>
	<h2>Exclude users</h2>
	<form method="post" action="">
		<table class="widefat fixed" cellspacing="0">
			<thead>
				<tr class="thead">
					<th id="cb" class="manage-column column-cb column-exclude" style=""
						scope="col"><?php echo __('Exclude'); ?>?</th>
					<th id="username" class="manage-column column-username" style=""
						scope="col"><?php echo __('Username'); ?>
					</th>
					<th id="email" class="manage-column column-email" style=""
						scope="col"><?php echo __('Email'); ?>
					</th>
				</tr>
			</thead>

			<tfoot>
				<tr class="thead">
					<th id="cb" class="manage-column column-cb column-exclude" style=""
						scope="col"><?php echo __('Exclude'); ?>?</th>
					<th id="username" class="manage-column column-username" style=""
						scope="col"><?php echo __('Username'); ?>
					</th>
					<th id="email" class="manage-column column-email" style=""
						scope="col"><?php echo __('Email'); ?>
					</th>
				</tr>
			</tfoot>

			<tbody id="users" class="list:user user-list">
			<?php
			$style = '';
			$users = cnfa_get_users();
			foreach($users as $user) {
			    $normalStyle = ( ' class="alternate"' == $normalStyle ) ? '' : ' class="alternate"';
			    $excludedStyle = ' class="alternate excluded" ';
			    if($normalStyle==''){
    		        $excludedStyle = ' class="excluded" ';
			    }
			    ?>
				<tr id='user-<?php echo $user->ID; ?>' <?php echo ($user->isExcluded)?$excludedStyle:$normalStyle ?>>
					<th scope='row' class='check-column'><input type='checkbox'
						name='cnfa_excluded_users[]' id='user_<?php echo $user->ID; ?>'
						<?php echo ($user->isExcluded)?"checked":""; ?>
						value='<?php echo $user->ID; ?>' />
					</th>
					<td><?php echo $user->user_login; ?></td>
					<td><?php echo $user->user_email; ?></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<p class="submit">
			<input class="button-primary" type="submit"
				name="cnfa_exclude_submit" class="button"
				value="<?php _e('Save Changes'); ?>" />
		</p>
	</form>
	<script type="text/javascript">
			jQuery(document).ready(function() { 
				jQuery("[name='cnfa_excluded_users[]']").click(function(){
					if(jQuery(this).is(":checked")){
						jQuery(this).parents("tr").addClass("excluded");
					}else {
						jQuery(this).parents("tr").removeClass("excluded");
					}
				});
			});
	</script>					
</div>
			<?php
}

function cnfa_user_options() {
    global $user_ID;
    $isExcluded = (bool) get_user_meta($user_ID,constant("WCNFA_EXCLUDE"),true);
    $text = '';
    get_currentuserinfo();
    if ( isset($_POST['pnfa_user_submit']) ) {
        if(isset($_POST['cnfa_user_active']) && $_POST['cnfa_user_active']=='true') {
            if (!$isExcluded){
                $isExcluded = 1;
            }
        } else {
            if ($isExcluded){
                $isExcluded = 0;
            }
        }
        update_user_meta($user_ID, constant("WCNFA_EXCLUDE"), $isExcluded);
        $text = '<span style="color:green">'.__('Option updated').'</span><br />';
    }
    if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; }
    ?>
<div class="wrap">
<?php screen_icon(); ?>
	<h2>Comment Notifier</h2>
	<br /> <br />
	<form method="post" action="">
		<table class="widefat">
			<thead>
				<tr>
					<th>Disable new posts notification</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><input type="checkbox" name="cnfa_user_active" value="true"
					<?php if($isExcluded) echo ' checked="checked"'; ?> />&nbsp;<?php _e('Check to disable further notifications'); ?>
						<p class="submit">
							<input class="button-primary" type="submit"
								name="pnfa_user_submit" class="button"
								value="<?php _e('Save Changes'); ?>" />
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
<?php
}

function cnfa_menu() {
    if (function_exists('add_options_page')) {
        if( current_user_can('manage_options') ) {
            add_options_page(__('Comment Notifier'), __('Comment Notifier'), 'manage_options', __FILE__, cnfa_options) ;
        }
    }
    if (function_exists('add_submenu_page')) {
        add_submenu_page('users.php', __('Comment Notifier'), __('Comment Notifier'), 'read', __FILE__, cnfa_user_options);
     }
}

add_action('admin_menu', 'cnfa_menu');
add_action('wp_set_comment_status', 'cnfa_set_comment_status',10,2);
add_action('comment_post', 'cnfa_comment_post');
?>