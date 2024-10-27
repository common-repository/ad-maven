<?php
/*
Plugin Name: The Official Ad-Maven Plugin for Wordpress
Description: This is the official Ad-Maven plugin.
Version: 1.4.2
*/
const ADMVN_SCRIPT_URL = 'http://aringours.com/?tid=';
const ADMVN_SCRIPT_FILE_NAME = 'admvn_script';
const ADMVN_TAG_ID_FILE_NAME = 'admvn_tag_id';
const ADMVN_SAVED_FILES_EXTENSION = '.txt';
const ADMVN_FETCH_SCRIPT_EVENT_NAME = 'admvn_s2s_fetch_event';
const ADMVN_S2S_SCRIPT_FETCH_CRONTAB_INTERVAL_TIME = 'admvn_5min';
const TAG_ID_KEY_NAME = 'tid';

/**
 * add a 5 minute schedule
 * @return Schedules
 */
function admvn_my_cron_schedules($schedules)
{
    if (!isset($schedules[ADMVN_S2S_SCRIPT_FETCH_CRONTAB_INTERVAL_TIME])) {
        $schedules[ADMVN_S2S_SCRIPT_FETCH_CRONTAB_INTERVAL_TIME] = array(
            'interval' => 5 * 60,
            'display' => __('Once every 5 minutes')
        );
    }
    return $schedules;
}

/**
 * Tag filename generator
 * @return string
 */
function admvn_get_tag_id_file_name(){
    return ADMVN_TAG_ID_FILE_NAME . ADMVN_SAVED_FILES_EXTENSION;
}

/**
 * Reading Tag id from file source
 * @return string
 */
function admvn_get_tag_id(){
    $tag_id = '';
    $tag_id_file_name = admvn_get_tag_id_file_name();
    if(file_exists($tag_id_file_name)){
        $tag_id_from_file = file_get_contents($tag_id_file_name);
        if($tag_id_from_file){
            $tag_id = (int) sanitize_text_field($tag_id_from_file);
        }
    }
    return $tag_id;
}

/**
 * @param {string} tag_id
 * @return string
 */
function admvn_get_fetch_script_url($tag_id)
{
    return ADMVN_SCRIPT_URL . $tag_id;
}

/**
 *
 * Tag filename generator
 * @return string
 */
function admvn_get_script_file_name()
{
    return ADMVN_SCRIPT_FILE_NAME . ADMVN_SAVED_FILES_EXTENSION;
}

/**
 * Unschedules all events attached to the hook with the specified arguments
 */
function admvn_my_deactivation()
{
    wp_clear_scheduled_hook(ADMVN_FETCH_SCRIPT_EVENT_NAME);
}

/**
 * Scheduling event attached to the hook with the specified arguments
 */
function admvn_my_activation()
{
    if (!wp_next_scheduled(ADMVN_FETCH_SCRIPT_EVENT_NAME)) {
        wp_schedule_event(time(), ADMVN_S2S_SCRIPT_FETCH_CRONTAB_INTERVAL_TIME, ADMVN_FETCH_SCRIPT_EVENT_NAME);
    }
}

/**
 * Adding s2s script to user html
 */
function admvn_add_s2s_script()
{
    $file_name = admvn_get_script_file_name();
    if(file_exists($file_name)){
        $script = file_get_contents($file_name);
        if ($script) {
            echo "<script data-cfasync='false'>$script</script>";
        }
    }
}

/**
 * Get script content
 */
function admvn_fetch_script_callback()
{
    $tag_id = admvn_get_tag_id();
    if(!empty($tag_id)){
        $script_fetch_response = wp_remote_get(admvn_get_fetch_script_url($tag_id));
        $response_body = wp_remote_retrieve_body($script_fetch_response);
        $response_http_code = wp_remote_retrieve_response_code($script_fetch_response);
        if($response_http_code == 200){
            file_put_contents(admvn_get_script_file_name(), $response_body);
        } else {
            file_put_contents(admvn_get_script_file_name(), "");
        }
    }
}

add_filter('cron_schedules', 'admvn_my_cron_schedules');
register_activation_hook(__FILE__, 'admvn_my_activation');

add_action(ADMVN_FETCH_SCRIPT_EVENT_NAME, 'admvn_fetch_script_callback');
register_deactivation_hook(__FILE__, 'admvn_my_deactivation');

add_action('wp_head', 'admvn_add_s2s_script');

/**
 * Admin Menu
 */
function admvn_register_menu()
{
    add_menu_page('Ad-Maven', 'Ad-Maven', 'manage_options', 'Ad_Maven_Plugin', 'admvn_admin_menu_page_display');
}

function admvn_admin_menu_page_display()
{
    if (isset($_POST[TAG_ID_KEY_NAME])) {
        $message = '<h1>Ad-Maven plugin</h1>';
        $tag_id = (int) sanitize_text_field($_POST[TAG_ID_KEY_NAME]);
        if (is_numeric($tag_id) && $tag_id != 0) {
            file_put_contents(get_home_path().admvn_get_tag_id_file_name(), $tag_id);
            $message = $message . "<b>Tag ID was updated to $tag_id</b>";
        } else {
            $message = $message . '<b style="color:red">tag id is incorrect</b>';
        }
        echo $message;
    } else {
        echo '
    <!DOCTYPE html>
    <html>
    <body>
    </body>
    <h1>Ad-Maven plugin</h1>
        <form method="post">
            Tag Id:<br>
            <input type="number" name="tid" placeholder="Tag Id">
            <input type="submit" value="Send">
        </form>
    </html>
    ';
    }
}

add_action('admin_menu', 'admvn_register_menu');
