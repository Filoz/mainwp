<?php
class MainWPMain
{
    public static function getClassName()
    {
        return __CLASS__;
    }

    protected static $singleton = null;

    public static function get()
    {
        if (self::$singleton == null) {
            self::$singleton = new MainWPMain();
        }
        return self::$singleton;
    }

    public function __construct()
    {
        //Prevent conflicts
        add_filter('screen_layout_columns', array(&$this, 'on_screen_layout_columns'), 10, 2);
        add_action('admin_menu', array(&$this, 'on_admin_menu'));
        add_action('admin_post_save_howto_testPages_general', array(&$this, 'on_save_changes'));
    }

    function on_screen_layout_columns($columns, $screen)
    {
        if ($screen == $this->dashBoard) {
            $columns[$this->dashBoard] = 3; //Number of supported columns
        }
        return $columns;
    }

    function on_admin_menu()
    {
        if (true || MainWPSystem::Instance()->isAPIValid()) {
            global $current_user;
            delete_user_option($current_user->ID, 'screen_layout_toplevel_page_mainwp_tab');
            $this->dashBoard = add_menu_page('MainWP', 'MainWP', 'read', 'mainwp_tab', array($this, 'on_show_page'), plugins_url('images/mainwpicon.png', dirname(__FILE__)), '2.00001');
            add_submenu_page('mainwp_tab', 'MainWP', __('Dashboard','mainwp'), 'read', 'mainwp_tab', array($this, 'on_show_page'));
            $val = get_user_option('screen_layout_' . $this->dashBoard);
            if (!MainWPUtility::ctype_digit($val))
            {
                update_user_option($current_user->ID, 'screen_layout_' . $this->dashBoard, 2, true);
            }
            add_action('load-' . $this->dashBoard, array(&$this, 'on_load_page'));
        }
        else
        {
            $this->dashBoard = add_menu_page('MainWP', 'MainWP', 'read', 'mainwp_tab', array($this, 'require_registration'), plugins_url('images/mainwpicon.png', dirname(__FILE__)), '2.0001');
        }
    }

    function on_load_page()
    {
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');
        wp_enqueue_script('dashboard');
        wp_enqueue_script('widgets');

        self::add_meta_boxes($this->dashBoard);
    }
    
    static function add_meta_boxes($page)
    {
        $i = 1;
        add_meta_box($page.'-contentbox-' . $i++, MainWPRightNow::getName(), array(MainWPRightNow::getClassName(), 'render'), $page, 'normal', 'core');

        add_meta_box($page.'-contentbox-' . $i++, MainWPRecentPosts::getName(), array(MainWPRecentPosts::getClassName(), 'render'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPRecentPages::getName(), array(MainWPRecentPages::getClassName(), 'render'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPSecurityIssues::getMetaboxName(), array(MainWPSecurityIssues::getClassName(), 'renderMetabox'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPBackupTasks::getName(), array(MainWPBackupTasks::getClassName(), 'render'), $page, 'normal', 'core');
        if (get_option('mainwp_seo') == 1) add_meta_box($page.'-contentbox-' . $i++, MainWPSEO::getName(), array(MainWPSEO::getClassName(), 'render'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPExtensionsWidget::getName(), array(MainWPExtensionsWidget::getClassName(), 'render'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPHelp::getName(), array(MainWPHelp::getClassName(), 'render'), $page, 'normal', 'core');
        add_meta_box($page.'-contentbox-' . $i++, MainWPNews::getName(), array(MainWPNews::getClassName(), 'render'), $page, 'normal', 'core');

        $extMetaBoxs = MainWPSystem::Instance()->apply_filter('mainwp-getmetaboxes', array());
        $extMetaBoxs = apply_filters('mainwp-getmetaboxs', $extMetaBoxs);
        foreach ($extMetaBoxs as $metaBox)
        {
            add_meta_box($page.'-contentbox-' . $i++, $metaBox['metabox_title'], $metaBox['callback'], $page, 'normal', 'core');
        }
    }

    function require_registration()
    {
        ?>
    <h2><?php _e('MainWP Dashboard','mainwp'); ?></h2>
        <?php _e('MainWP needs to be activated before using','mainwp'); ?> - <a href="<?php echo admin_url(); ?>admin.php?page=Settings"><?php _e('Activate here','mainwp'); ?></a>.
    <?php
    }

    function on_show_page()
    {
        global $screen_layout_columns;
        ?>
    <div id="mainwp_tab-general" class="wrap"><a href="http://mainwp.com" id="mainwplogo" title="MainWP" target="_blank"><img src="<?php echo plugins_url('images/logo.png', dirname(__FILE__)); ?>" height="50" alt="MainWP" /></a>
        <img src="<?php echo plugins_url('images/icons/mainwp-dashboard.png', dirname(__FILE__)); ?>" style="float: left; margin-right: 8px; margin-top: 7px ;" alt="MainWP Dashboard" height="32"/>
        <h2><?php _e('MainWP Dashboard','mainwp'); ?></h2><div style="clear: both;"></div><br/><br/>

        <?php
        $websites = MainWPDB::Instance()->query(MainWPDB::Instance()->getSQLWebsitesForCurrentUser(false, null, 'wp.dtsSync DESC, wp.url ASC'));
        self::renderDashboardBody($websites, $this->dashBoard, $screen_layout_columns);
        @MainWPDB::free_result($websites);
        ?>
    </div>
    <?php
    }

    public static function renderDashboardBody($websites, $pDashboard, $pScreenLayout)
    {
        ?>
    <form action="admin-post.php" method="post">
        <?php wp_nonce_field('mainwp_tab-general'); ?>
        <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
        <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
        <input type="hidden" name="action" value="save_howto_testPages_general"/>
        <div class="postbox" style="padding-top: 1em;">
            <table id="mainwp-refresh-bar" width="100%">
                <tbody><tr>
                    <?php
                    $current_wp_id = MainWPUtility::get_current_wpid();
                    $website = null;
                    if (!empty($current_wp_id))
                    {
                        $website = $websites[0];
                    }

                    if ((time() - ($website == null ? MainWPDB::Instance()->getFirstSyncedSite() : $website->dtsSync)) > (60 * 60 * 24))
                    {
                    ?>
                <td id="mainwp-welcome-bar" width="50%" style="padding-left: 1em;">
                    <span class="mainwp-reminder"><?php _e('Your MainWP Dashboard has not been synced for 24 hours! Click the Sync Data button to get the latest data from child sites.','mainwp'); ?></span><br/>
                </td>
                    <?php
                    }
                    else
                    {
                    ?>
                <td id="mainwp-welcome-bar" width="50%" style="padding-left: 1em;">
                    <span style="font-size: 24px"><?php echo (($website == null) ? __('Welcome to Your MainWP Dashboard!','mainwp') : sprintf(__('Welcome to %s Dashboard!','mainwp'), $website->name)); ?></span><br/>
                    <span style="font-style: italic; font-size: 14px;"><?php echo (($website == null) ? __('Manage your WordPress sites with ease.','mainwp') : sprintf(__('This information is only for %s','mainwp'), MainWPUtility::getNiceURL($website->url, true))); ?></span>
                </td>
                    <?php
                    }
                    ?>
                <td id="mainwp-refresh-bar-buttons">
                <input type="button" id="dashboard_refresh" value="<?php _e('Sync Data','mainwp'); ?>" class="button-hero button mainwp-upgrade-button" title="<?php echo MainWPRightNow::renderLastUpdate(); ?>" />
                <a class="button-hero button-primary button mainwp-addsite-button" href="admin.php?page=managesites&do=new"><?php _e('Add New Site','mainwp'); ?></a>
                <a class="button-hero button-primary button mainwp-button-red" target="_blank" href="http://extensions.mainwp.com"><?php _e('Get New Extensions','mainwp'); ?></a>
                </td>
            <div id="dashboard_refresh_statusextra" style="display: none">&nbsp;&nbsp;<img src="<?php echo plugins_url('images/loader.gif', dirname(__FILE__)); ?>"/></div>
                </tr></tbody>
            </table>
            <div id="mainwp_dashboard_refresh_status"></div>
        </div>
        <div id="mainwp_main_errors" class="mainwp_error"></div>
    </form>

    <div id="dashboard-widgets-wrap">

    <?php require_once(ABSPATH . 'wp-admin/includes/dashboard.php');

    wp_dashboard(); ?>

    <div class="clear"></div>
    </div><!-- dashboard-widgets-wrap -->
    <?php
        if (isset($_REQUEST['refresh']) && ($_REQUEST['refresh'] == 'yes'))
        {
            echo '<script>jQuery(document).ready(function() {mainwp_refresh_dashboard();});</script>';
        }
    }

    //executed if the post arrives initiated by pressing the submit button of form
    function on_save_changes()
    {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );
		//cross check the given referer
		check_admin_referer('mainwp_tab-general');

		//process here your on $_POST validation and / or option saving

		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		wp_redirect($_POST['_wp_http_referer']);
    }
}