<?php
/*
  Plugin Name: Event Espresso - Permissions
  Plugin URI: http://www.eventespresso.com
  Description: Provides support for allowing members of the espreesso_event_admin role to administer events.
  Version: 1.5
  Author: Event Espresso
  Author URI: http://www.eventespresso.com
  Copyright 2011  Event Espresso  (email : support@eventespresso.com)
 */
include("includes/functions.php");

//Define the version of the plugin
function espresso_manager_version() {
	return '1.5';
}

define("ESPRESSO_MANAGER_VERSION", espresso_manager_version());

//Define the plugin directory and path
define("ESPRESSO_MANAGER_PLUGINPATH", "/" . plugin_basename(dirname(__FILE__)) . "/");
define("ESPRESSO_MANAGER_PLUGINFULLPATH", WP_PLUGIN_DIR . ESPRESSO_MANAGER_PLUGINPATH);
define("ESPRESSO_MANAGER_PLUGINFULLURL", WP_PLUGIN_URL . ESPRESSO_MANAGER_PLUGINPATH);

//Globals
global $espresso_manager;
$espresso_manager = get_option('espresso_manager_settings');

//Install the plugin
function espresso_manager_install() {
	//Install Event Manager Options
	$espresso_manager = array(
			'espresso_manager_general' => "administrator",
			'espresso_manager_events' => "administrator",
			'espresso_manager_categories' => "administrator",
			'espresso_manager_discounts' => "administrator",
			'espresso_manager_groupons' => "administrator",
			'espresso_manager_form_builder' => "administrator",
			'espresso_manager_form_groups' => "administrator",
			'espresso_manager_event_emails' => "administrator",
			'espresso_manager_payment_gateways' => "administrator",
			'espresso_manager_members' => "administrator",
			'espresso_manager_calendar' => "administrator",
			'espresso_manager_social' => "administrator",
			'espresso_manager_addons' => "administrator",
			'espresso_manager_support' => "administrator",
			'espresso_manager_venue_manager' => "administrator",
			'espresso_manager_personnel_manager' => "administrator",
			'event_manager_approval' => "N",
			'event_manager_venue' => 'Y',
			'event_manager_staff' => 'Y',
			'event_manager_create_post' => 'Y',
			'event_manager_share_cats' => 'Y',
			'can_accept_payments' => 'N',
	);
	add_option('espresso_manager_settings', $espresso_manager);
	// add more capabilities to the subscriber role only for this plugin
	$result = add_role('espresso_event_admin', 'Espresso Master Admin', array(
			'read' => true, // True allows that capability
			'edit_posts' => false,
			'espresso_user' => true,
			'espresso_group_admin' => false,
			'espresso_event_admin' => true,
			'espresso_event_manager' => true,
			'delete_posts' => false, // Use false to explicitly deny
					));
}

register_activation_hook(__FILE__, 'espresso_manager_install');

function espresso_permissions_init() {

	if (espresso_is_admin()) {
		add_filter('filter_hook_espresso_event_editor_email_attendees_class', 'espresso_event_editor_email_attendees_class');
		add_action('action_hook_espresso_event_editor_overview_add', 'espresso_event_editor_overview_add');
		add_filter('filter_hook_espresso_category_list_sql', 'espresso_permissions_category_list_sql_filter', 20);
	}
}

add_action('init', 'espresso_permissions_init', 10);

function espresso_permissions_category_list_sql_filter($sql) {
	if (espresso_member_data('role') == 'espresso_event_manager' || espresso_member_data('role') == 'espresso_group_admin') {
		$sql .= " JOIN $wpdb->users u on u.ID = c.wp_user WHERE c.wp_user = " . espresso_member_data('id');
	}
	return $sql;
}

function espresso_event_editor_email_attendees_class($class) {
	return $class . " misc-pub-section-last";
}

function espresso_event_editor_overview_add($event) {
	$event->wp_user = $event->wp_user == $event->event_meta['originally_submitted_by'] ? $event->wp_user : $event->event_meta['originally_submitted_by'];
	$user_name = espresso_user_meta($event->wp_user, 'user_firstname') != '' ? espresso_user_meta($event->wp_user, 'user_firstname') . ' ' . espresso_user_meta($event->wp_user, 'user_lastname') : espresso_user_meta($event->wp_user, 'display_name');
	$user_company = espresso_user_meta($event->wp_user, 'company') != '' ? espresso_user_meta($event->wp_user, 'company') : '';
	$user_organization = espresso_user_meta($event->wp_user, 'organization') != '' ? espresso_user_meta($event->wp_user, 'organization') : '';
	$user_co_org = $user_company != '' ? $user_company : $user_organization;
	?>
	<div class="misc-pub-section misc-pub-section-last" id="visibility3">
		<ul>
			<?php do_action('action_hook_espresso_event_editor_overview_add_li', $event); ?>
			<li><strong><?php _e('Submitted By:', 'event_espresso'); ?></strong><?php echo $user_name; ?></li>
			<li><strong><?php _e('Email:', 'event_espresso'); ?></strong><?php echo espresso_user_meta($event->wp_user, 'user_email'); ?></li>
			<?php
			if (!empty($user_co_org)) {
				?>
				<li><strong><?php _e('Organization:', 'event_espresso'); ?></strong><?php echo espresso_user_meta($event->wp_user, 'company'); ?></li>
			<?php } ?>
			<li><strong><?php _e('Date Submitted:', 'event_espresso'); ?></strong><?php echo $event->submitted; ?></li>
		</ul>
	</div>
	<?php
}

function espresso_add_permissions_functions() {

	function espresso_can_view_event($event_id) {
		if (current_user_can('espresso_event_admin') == true || espresso_is_my_event($event_id)) {
			return true;
		}
	}

	function espresso_is_admin() {
		if (espresso_member_data('role') == 'espresso_event_admin' || current_user_can('administrator')) {
			return true;
		}
	}

	function espresso_is_my_event($event_id) {
		global $wpdb;
		if (current_user_can('administrator') || espresso_member_data('role') == 'espresso_event_admin') {
			return true;
		}
	}

}

add_action('plugins_loaded', 'espresso_add_permissions_functions', 20);

//Returns the id, capability, and role of a user
//Core only
function espresso_member_data($type = '') {
	global $current_user;
	wp_get_current_user();

	$curauth = wp_get_current_user();
	$user_id = $curauth->ID;
	$user = new WP_User($user_id);

	switch ($type) {
		case 'id':
			return $user_id;
			break;
		case 'cap':
			if (!empty($user->allcaps) && is_array($user->allcaps)) {
				$str = array();
				foreach ($user->allcaps as $k => $v) {
					$str[] = $k;
				}
				return implode("|", $str);
			}
			break;
		case 'role';
			if (!empty($user->roles) && is_array($user->roles)) {
				foreach ($user->roles as $role)
					return $role;
			}
			break;
		default:
			return $user;
			break;
	}
}

//Returns the user meta
//Core only
if (!function_exists('espresso_user_meta')) {

	function espresso_user_meta($user_id, $key) {
		$user = new WP_User($user_id);
		//print_r($user);
		//echo array_key_exists($key, $user);
		if (array_key_exists($key, $user)) {
			return esc_attr($user->$key);
		}
	}

}



//This function is previously declared in functions/main.php. Credit goes to Justin Tadlock (http://justintadlock.com/archives/2009/09/18/custom-capabilities-in-plugins-and-themes)
//This function simply returns a custom capability, nothing else. Can be used to change admin capability of the Event Manager menu without the admin losing rights to certain menus.
//Core only
if (!function_exists('espresso_management_capability')) {

	function espresso_management_capability($default, $custom) {
		return $custom;
	}

	add_filter('filter_hook_espresso_management_capability', 'espresso_management_capability', 10, 3);
}

//Add a settings link to the Plugins page, so people can go straight from the plugin page to the settings page.
//Core only
function espresso_manager_plugin_actions($links, $file) {
	// Static so we don't call plugin_basename on every plugin row.
	static $this_plugin;
	if (!$this_plugin)
		$this_plugin = plugin_basename(__FILE__);

	if ($file == $this_plugin) {
		$org_settings_link = '<a href="admin.php?page=espresso_permissions">' . __('Settings') . '</a>';
		array_unshift($links, $org_settings_link); // before other links
	}
	return $links;
}

add_filter('plugin_action_links', 'espresso_manager_plugin_actions', 10, 2);

//Create pages
//Core only
function espresso_permissions_roles_mnu() {
	global $wpdb, $espresso_manager, $wp_roles;
	?>
	<div id="configure_espresso_manager_form" class="wrap meta-box-sortables ui-sortable">
		<div id="icon-options-event" class="icon32"> </div>
		<h2>
			<?php _e('Event Espresso - User Roles Manager', 'event_espresso'); ?>
		</h2>
		<div id="event_espresso-col-left" style="width:70%;">
			<?php espresso_edit_roles_page(); ?>
		</div>
	</div>
	<?php
}

function espresso_permissions_newroles_mnu() {
	global $wpdb, $espresso_manager, $wp_roles;
	//Debug
	//echo "-----> ".espresso_user_cap("espresso_group_admin");
	?>
	<div id="configure_espresso_manager_form" class="wrap meta-box-sortables ui-sortable">
		<div id="icon-options-event" class="icon32"> </div>
		<h2>
			<?php _e('Event Espresso - User Roles Manager', 'event_espresso'); ?>
		</h2>
		<div id="event_espresso-col-left" style="width:70%;">
			<?php espresso_new_role_page(); ?>
		</div>
	</div>
	<?php
}

//Permissions
function espresso_permissions_add_to_admin_menu($espresso_manager) {
	global $org_options;
	add_submenu_page('events', __('Event Espresso - Permissions Settings', 'event_espresso'), '<span class="ee_menu_group"  onclick="return false;">' . __('Permissions', 'event_espresso') . '</span>', 'administrator', 'espresso_permissions', 'espresso_permissions_config_mnu');

	//Permissions settings
	add_submenu_page('events', __('Event Espresso - Event Manager Permissions', 'event_espresso'), __('Settings', 'event_espresso'), 'administrator', 'espresso_permissions', 'espresso_permissions_config_mnu');
	add_submenu_page('events', __('Event Espresso - Event Manager Roles', 'event_espresso'), __('User Roles', 'event_espresso'), 'administrator', 'roles', 'espresso_permissions_roles_mnu');
	if ($org_options['use_venue_manager'] == 'Y' && function_exists('espresso_permissions_user_groups')) {
		if (espresso_member_data('role') == "administrator") {
			add_submenu_page('events', __('Event Espresso - Locales/Regions', 'event_espresso'), __('Locales/Regions', 'event_espresso'), apply_filters('filter_hook_espresso_management_capability', 'administrator', $espresso_manager['espresso_manager_venue_manager']), 'event_locales', 'event_espresso_locale_config_mnu');
		}
		add_submenu_page('events', __('Event Espresso - Regional Managers', 'event_espresso'), __('Regional Managers', 'event_espresso'), 'administrator', 'event_groups', 'espresso_permissions_user_groups');
	}
}

add_action('action_hook_espresso_add_new_submenu_to_group_settings', 'espresso_permissions_add_to_admin_menu', 40);

function espresso_permissions_config_mnu() {

	global $wpdb, $espresso_manager, $wp_roles;

	function espresso_manager_updated() {
		return __('Manager details saved.', 'event_espresso');
	}

	if ($_POST['update_permissions'] == 'update') {

		$espresso_manager['espresso_manager_general'] = $_POST['espresso_manager_general'];
		$espresso_manager['espresso_manager_events'] = $_POST['espresso_manager_events'];
		$espresso_manager['espresso_manager_categories'] = $_POST['espresso_manager_categories'];
		$espresso_manager['espresso_manager_discounts'] = $_POST['espresso_manager_discounts'];
		$espresso_manager['espresso_manager_groupons'] = $_POST['espresso_manager_groupons'];
		$espresso_manager['espresso_manager_form_builder'] = $_POST['espresso_manager_form_builder'];
		$espresso_manager['espresso_manager_form_groups'] = $_POST['espresso_manager_form_groups'];
		$espresso_manager['espresso_manager_event_emails'] = $_POST['espresso_manager_event_emails'];
		$espresso_manager['espresso_manager_payment_gateways'] = $_POST['espresso_manager_payment_gateways'];
		$espresso_manager['espresso_manager_members'] = $_POST['espresso_manager_members'];
		$espresso_manager['espresso_manager_calendar'] = $_POST['espresso_manager_calendar'];
		$espresso_manager['espresso_manager_social'] = $_POST['espresso_manager_social'];
		$espresso_manager['espresso_manager_addons'] = $_POST['espresso_manager_addons'];
		$espresso_manager['espresso_manager_support'] = $_POST['espresso_manager_support'];
		$espresso_manager['espresso_manager_venue_manager'] = $_POST['espresso_manager_venue_manager'];
		$espresso_manager['espresso_manager_personnel_manager'] = $_POST['espresso_manager_personnel_manager'];
		$espresso_manager['event_manager_approval'] = $_POST['event_manager_approval'];
		$espresso_manager['event_manager_venue'] = $_POST['event_manager_venue'];
		$espresso_manager['event_manager_staff'] = $_POST['event_manager_staff'];
		$espresso_manager['event_manager_create_post'] = $_POST['event_manager_create_post'];
		$espresso_manager['event_manager_share_cats'] = $_POST['event_manager_share_cats'];
		$espresso_manager['can_accept_payments'] = $_POST['can_accept_payments'];

		update_option('espresso_manager_settings', $espresso_manager);
		add_action('admin_notices', 'espresso_manager_updated');
	}
	if ($_REQUEST['reset_permissions'] == 'true') {
		delete_option("espresso_manager_settings");
		espresso_manager_install();
	}
	$espresso_manager = get_option('espresso_manager_settings');

	$values = array(
			array('id' => 'administrator', 'text' => __('Administrator', 'event_espresso')),
			array('id' => 'espresso_event_admin', 'text' => __('Master Admin', 'event_espresso')),
	);

	//OVerride the values array if the pro version is installed
	if (function_exists('espresso_manager_pro_options')) {
		$values = array(
				array('id' => 'administrator', 'text' => __('Administrator', 'event_espresso')),
				array('id' => 'espresso_event_admin', 'text' => __('Master Admin', 'event_espresso')),
				array('id' => 'espresso_event_manager', 'text' => __('Event Manager', 'event_espresso')),
				array('id' => 'espresso_group_admin', 'text' => __('Regional Manager', 'event_espresso'))
		);
	}
	?>
	<div id="configure_espresso_manager_form" class="wrap meta-box-sortables ui-sortable">
		<div class="wrap">
			<div id="icon-options-event" class="icon32"> </div>
			<h2>
				<?php _e('Event Espresso - Event Manager Permissions', 'event_espresso'); ?>  <?php
			if (function_exists('espresso_manager_pro_options')) {
				echo __('Pro', 'event_espresso');
			}
				?>
			</h2>
			<div id="poststuff" class="metabox-holder has-right-sidebar">
				<div id="side-info-column" class="inner-sidebar">
					<?php do_meta_boxes('event-espresso_page_espresso_permissions', 'side', null); ?>
				</div>
				<div id="post-body">
					<div id="post-body-content">
						<div class="postbox">
							<h3>
								<?php _e('Current Roles/Capabilities', 'event_espresso'); ?>
							</h3>
							<div class="inside">
								<ul>
									<?php
#	print_r(	get_role("administrator")	);
#	echo espresso_member_data('role');
									$users_of_blog = count_users();
									$total_users = $users_of_blog['total_users'];
									$avail_roles = & $users_of_blog['avail_roles'];
									unset($users_of_blog);

									$current_role = false;
									$class = empty($role) ? ' class="current"' : '';
									$role_links = array();
									//$role_links[] = "<li><a href='users.php'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'users' ), number_format_i18n( $total_users ) ) . '</a>';
									foreach ($wp_roles->get_names() as $this_role => $name) {
//							if ( !isset($avail_roles[$this_role]) ) continue;
										$class = '';
										if ($this_role == $role) {
											$current_role = $role;
											$class = ' class="current"';
										}
										$name = translate_user_role($name);
										/* translators: User role name with count */
										$name = sprintf(__('%1$s <span class="count">(%2$s)</span>'), $name, $avail_roles[$this_role]);
										switch ($this_role) {
											case 'administrator':
												$role_links[] = "<li><a href='users.php?role=$this_role'$class>$name</a><br />" . __('Access to all admin pages and all events/attendees.', 'event_espresso');
												break;
											case 'espresso_event_admin':
												$role_links[] = "<li><a href='users.php?role=$this_role'$class>$name</a><br />" . __('Access to selected admin pages below and all events/attendees.', 'event_espresso');
												break;
											case 'espresso_event_manager':
												if (defined('ESPRESSO_MANAGER_PRO_VERSION')) {
													$role_links[] = "<li><a href='users.php?role=$this_role'$class>$name</a><br />" . __('Access to events/attendees created by the user of this role and the selected pages below.', 'event_espresso');
												}
												break;
											case 'espresso_group_admin':
												if (defined('ESPRESSO_MANAGER_PRO_VERSION')) {
													$role_links[] = "<li><a href='users.php?role=$this_role'$class>$name</a><br />" . __('Access to events/attendees created by the user of this role, the selected pages below, and any events/attendees within the locales/regions assigned to this user.', 'event_espresso');
												}
												break;
										}
									}

									echo implode("</li>\n", $role_links) . '</li>';
									unset($role_links);
									?>
								</ul>
							</div>
						</div>
						<form class="espresso_form" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
							<div class="postbox">
								<h3>
									<?php _e('Minimum Page Permissions', 'event_espresso'); ?>
								</h3>
								<table id="table" class="widefat fixed" width="100%">
									<tbody>
										<tr>
											<td><label for="espresso_manager_general">
													<?php _e('General Settings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_general', $values, $espresso_manager['espresso_manager_general']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_events">
													<?php _e('Event/Attendee Listings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_events', $values, $espresso_manager['espresso_manager_events']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_categories">
													<?php _e('Categories Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_categories', $values, $espresso_manager['espresso_manager_categories']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_discounts">
													<?php _e('Discounts Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_discounts', $values, $espresso_manager['espresso_manager_discounts']); ?></td>
										</tr>
										<?php if (function_exists('event_espresso_groupon_config_mnu')) { ?>
											<tr>
												<td><label for="espresso_manager_groupons">
														<?php _e('Groupons Page', 'event_espresso'); ?>
													</label></td>
												<td><?php echo select_input('espresso_manager_groupons', $values, $espresso_manager['espresso_manager_groupons']); ?></td>
											</tr>
										<?php } ?>
										<tr>
											<td><label for="espresso_manager_form_builder">
													<?php _e('Questions Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_form_builder', $values, $espresso_manager['espresso_manager_form_builder']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_form_groups">
													<?php _e('Question Groups Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_form_groups', $values, $espresso_manager['espresso_manager_form_groups']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_venue_manager">
													<?php _e('Venue Manager Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_venue_manager', $values, $espresso_manager['espresso_manager_venue_manager']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_personnel_manager">
													<?php _e('Staff Manager Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_personnel_manager', $values, $espresso_manager['espresso_manager_personnel_manager']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_event_emails">
													<?php _e('Email Manager Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_event_emails', $values, $espresso_manager['espresso_manager_event_emails']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_payment_gateways">
													<?php _e('Payment Settings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_payment_gateways', $values, $espresso_manager['espresso_manager_payment_gateways']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_members">
													<?php _e('Member Settings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_members', $values, $espresso_manager['espresso_manager_members']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_calendar">
													<?php _e('Calendar Settings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_calendar', $values, $espresso_manager['espresso_manager_calendar']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_social">
													<?php _e('Social Media Settings Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_social', $values, $espresso_manager['espresso_manager_social']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_addons">
													<?php _e('Addons Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_addons', $values, $espresso_manager['espresso_manager_addons']); ?></td>
										</tr>
										<tr>
											<td><label for="espresso_manager_support">
													<?php _e('Support Page', 'event_espresso'); ?>
												</label></td>
											<td><?php echo select_input('espresso_manager_support', $values, $espresso_manager['espresso_manager_support']); ?></td>
										</tr>
									</tbody>
								</table>
							</div>
							<?php
							if (function_exists('espresso_manager_pro_options')) {
								echo espresso_manager_pro_options();
							}
							?>

							<input type="hidden" name="update_permissions" value="update" />
							<p>
								<input class="button-primary" type="submit" name="Submit" value="<?php _e('Save Permissions', 'event_espresso'); ?>" id="save_permissions" />
							</p>
							<p>
								<?php _e('Reset Permissions?', 'event_espresso'); ?>
								<input name="reset_permissions" type="checkbox" value="true" />
							</p>
						</form>
						<?php 
						if (function_exists('espresso_manager_pro_options')) {
							echo espresso_select_manager_form();
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function espresso_permissions_filter_wp_user_id($wp_user_id) {
	if (espresso_member_data('role') == 'espresso_event_manager' || espresso_member_data('role') == 'espresso_group_admin') {
		$wp_user_id = espresso_member_data('id');
	}
	return $wp_user_id;
}

add_filter('filter_hook_espresso_get_user_id', 'espresso_permissions_filter_wp_user_id', 10);