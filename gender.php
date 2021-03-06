<?php
/*
  Plugin Name: WP Genderize
  Plugin URI: https://github.com/bastienho/wp-genderize
  Description: The plugin which genderizes strings for WordPer and WordPress
  Version: 1.0.0
  Author: Bastien Ho
  Author URI: http://ba.stienho.fr
  License: GPLv2
  Text Domain: wp-genderize
 */

/*
  Copyright (C) 2016 bastienho

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */
$WPGenderedUsers = new WPGenderedUsers();

class WPGenderedUsers {
    var $meta;

    var $strings_to_gender;
    var $plural_to_gender;

    var $string_to_replace;
    var $plural_to_replace;

    var $objects;

    var $current_screen;

    function __construct() {
        $basepath = plugin_dir_path(__FILE__);
        load_plugin_textdomain( 'wp-genderize', false, basename($basepath).'/languages' );

        $this->meta = apply_filters('genderize_meta', 'wgu_gender');

        $this->strings_to_gender = apply_filters('genderize_strings_to_gender', include($basepath.'/i18n/strings-to-gender.php'));
        $this->plural_to_gender = apply_filters('genderize_plural_to_gender', include($basepath.'/i18n/plural-to-gender.php'));
        $this->string_to_replace = apply_filters('genderize_strings_to_replace', include($basepath.'/i18n/strings-to-replace.php'));
        $this->plural_to_replace = apply_filters('genderize_plural_to_replace', include($basepath.'/i18n/plural-to-replace.php'));
        $this->objects = apply_filters('genderize_objects', include($basepath.'/i18n/objects.php'));


        add_action('personal_options', array(&$this, 'personal_options'), 100);
        add_action('personal_options_update', array(&$this, 'options_update'));
        add_action('edit_user_profile_update', array(&$this, 'options_update'));
        add_action('wp_loaded', array(&$this, 'alter_role_names'));

        add_filter('ngettext_with_context', array(&$this, 'ngettext_with_context'), 999, 6);
        add_filter('ngettext', array(&$this, 'ngettext'), 999, 5);
        add_filter('gettext_with_context', array(&$this, 'gettext_with_context'), 999,4);
        add_filter('gettext', array(&$this, 'gettext'), 999, 3);
        add_filter('get_role_list', array(&$this, 'get_role_list'), 999, 2);
    }

    // PHP 4 constructor
    function WPGenderedUsers() {
        $this->__construct();
    }

    /**
     * Try to fetch a gender against a string
     *
     * @param string $_value
     * @filter genderize($gender)
     * @return string
     */
    function determine_gender($_value){
        $value = substr(trim(strtolower($_value)), 0, 1);
        $gender = '';
        if($value=='m'){
            $gender = 'male';
        }
        if($value=='f'){
            $gender = 'female';
        }
        return apply_filters('genderize', $gender);
    }

    /**
     * Add a field in user's page
     *
     * @param \WP_user $profileuser
     */
    function personal_options($profileuser) {
        $gender = get_user_meta($profileuser->ID, $this->meta, true);
        ?>
        <tr class="">
            <th scope="row"><label for="<?php echo $this->meta; ?>"><?php _e('Gender', 'wp-genderize'); ?></label></th>
            <td><fieldset><legend class="screen-reader-text"><span><?php _e('Gender') ?></span></legend>
                    <select name="<?php echo $this->meta; ?>" id="<?php echo $this->meta; ?>">
                        <option value=""></option>
                        <option value="male" <?php selected($gender, 'male'); ?>><?php _e('Male', 'wp-genderize'); ?></option>
                        <option value="female" <?php selected($gender, 'female'); ?>><?php _e('Female', 'wp-genderize'); ?></option>
                    </select>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    /**
     * Save user's gender
     *
     * @param int $user_id
     * @return void
     */
    function options_update($user_id) {

        if (!current_user_can('edit_user', $user_id))
            return false;

        update_user_meta($user_id, $this->meta, esc_attr(filter_input(INPUT_POST, $this->meta, FILTER_SANITIZE_STRING)));
    }

    /**
     *
     * @return string
     */
    function get_current_object(){
        $this->current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if(!$this->current_screen){
            return;
        }
        $base = str_replace(array('edit-'), '', $this->current_screen->id);

        if(isset($this->objects[$base])){
            return $base;
        }
        return;
    }

    /**
     *
     * @param string $translated_text
     * @param string $single
     * @param string $plural
     * @param string $number
     * @param string $context
     * @param string $domain
     * @return string
     */
    function ngettext_with_context($translated_text, $single, $plural, $number, $context, $domain){
        if($domain=='wp-genderize'){
            return $translated_text;
        }
        if(in_array($single, $this->plural_to_replace)){
            return _n($single, $plural, $number, 'wp-genderize');
        }
        $gender = $this->determine_gender(get_user_meta(get_current_user_id(), $this->meta, true));
        $object = $this->get_current_object();
        if($object && isset($this->objects[$object])){
            $gender = $this->objects[$object];
        }
        //echo $plural.$object.$gender.$number.$context.' ';
        return (in_array($single, $this->plural_to_gender)) ? _gn($single, $plural, $number, $gender, 'wp-genderize') : $translated_text;
    }

    /**
     *
     * @param string $translated_text
     * @param string $single
     * @param string $plural
     * @param string $number
     * @param string $domain
     * @return string
     */
    function ngettext($translated_text, $single, $plural, $number, $domain){
        if($domain=='wp-genderize'){
            return $translated_text;
        }
        if(in_array($single, $this->plural_to_replace)){
            return _n($single, $plural, $number, 'wp-genderize');
        }
        $gender = $this->determine_gender(get_user_meta(get_current_user_id(), $this->meta, true));
        $object = $this->get_current_object();
        if($object && isset($this->objects[$object])){
            $gender = $this->objects[$object];
        }
        //echo htmlentities($single).$object.$gender.$number.' ';
        return (in_array($single, $this->plural_to_gender)) ? _gn($single, $plural, $number, $gender, 'wp-genderize') : $translated_text;
    }

    /**
     *
     * @param string $translated_text
     * @param string $text
     * @param string $context
     * @param string $domain
     * @return string
     */
    function gettext_with_context($translated_text, $text, $context, $domain){
        if($domain=='wp-genderize'){
            return $translated_text;
        }
        if(in_array($text, $this->string_to_replace)){
            return __($text, 'wp-genderize');
        }
        $gender = $this->determine_gender(get_user_meta(get_current_user_id(), $this->meta, true));
        $object = $this->get_current_object();
        if($object && isset($this->objects[$object])){
            $gender = $this->objects[$object];
        }
        //echo $object.$gender.$context.' ';
        return (in_array($text, $this->strings_to_gender)) ? _g($text, $gender, 'wp-genderize') : $translated_text;
    }

    /**
     *
     * @param string $translated_text
     * @param string $text
     * @param string $domain
     * @return string
     */
    function gettext($translated_text, $text, $domain){
        if($domain=='wp-genderize'){
            return $translated_text;
        }
        if(in_array($text, $this->string_to_replace)){
            return __($text, 'wp-genderize');
        }
        $gender = $this->determine_gender(get_user_meta(get_current_user_id(), $this->meta, true));
        $object = $this->get_current_object();
        if($object && isset($this->objects[$object])){
            $gender = $this->objects[$object];
        }
        //echo $object.$gender.' ';
        return (in_array($text, $this->strings_to_gender)) ? _g($text, $gender, 'wp-genderize') : $translated_text;
    }

    /**
     *
     * @global array $wp_roles
     * @param array $role_list
     * @param \WP_User $user_object
     * @return array
     */
    function get_role_list($role_list, $user_object){
        global $wp_roles;
        $user_gender = $this->determine_gender(get_user_meta($user_object->ID, $this->meta, true));
        foreach($role_list as $role=>$role_name){
            $role_list[$role] = in_array($wp_roles->roles[$role]['name'], $this->strings_to_gender) ? _g($wp_roles->roles[$role]['name'], $user_gender, 'wp-genderize') : $role_name;
        }
        return $role_list;
    }

    /**
     *
     * @global array $wp_roles
     */
    function alter_role_names(){
        global $wp_roles;
        foreach($wp_roles->role_names as $role=>$name){
            $wp_roles->role_names[$role] = __($wp_roles->roles[$role]['name'], 'wp-genderize');
        }
    }

}


if(!function_exists('_g')){
    /**
     *
     * @param string $string
     * @param string $gender
     * @param string $domain
     * @return string
     */
    function _g($string, $gender='neutral', $domain='default'){
        return _x($string, $gender, $domain);
    }
}
if(!function_exists('_gn')){
    /**
     *
     * @param string $single
     * @param string $plural
     * @param string $number
     * @param string $gender
     * @param string $domain
     * @return string
     */
    function _gn($single, $plural, $number, $gender='neutral', $domain='default'){
        return _nx($single, $plural, $number, $gender, $domain);
    }
}
