<?php
/*
  Plugin Name: WP Genderize
  Plugin URI:
  Description: The plugin which genderizes strings for WordPr and WordPress
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
        load_plugin_textdomain( 'wp-genderize', false, 'wp-genderize/languages' );

        $this->meta = apply_filters('user_gender_meta', 'wgu_gender');

        $this->strings_to_gender = array(
            'Administrator',
            'Editor',
            'Author',
            'Contributor',
            'Subscriber',
            // Misc.
            'Welcome to WordPress&nbsp;%s',
            // Posts and pages
            'Published',
            'Draft',
            'Private'
        );
        $this->plural_to_gender = array(
            // Posts and pages
            'Mine <span class="count">(%s)</span>',
            'All <span class="count">(%s)</span>',
            'Published <span class="count">(%s)</span>',
            'Draft <span class="count">(%s)</span>',
            'Private <span class="count">(%s)</span>'
        );

        $this->string_to_replace = array(
            'User',
            'Users',
            'All Users',
        );
        $this->plural_to_replace = array(
            'User',
        );

        $this->objects = array(
            'post'=>_x('neutral', 'gender of a post', 'wp-genderize'),
            'page'=>_x('neutral', 'gender of a page', 'wp-genderize'),
            'category'=>_x('neutral', 'gender of a category', 'wp-genderize'),
            'tag'=>_x('neutral', 'gender of a tag', 'wp-genderize'),
            'comment'=>_x('neutral', 'gender of a comment', 'wp-genderize'),
            'widget'=>_x('neutral', 'gender of a widget', 'wp-genderize'),
        );


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

    function determine_gender($_value){
        $value = substr(trim(strtolower($_value)), 0, 1);
        $gender = '';
        if($value=='m'){
            $gender = 'male';
        }
        if($value=='f'){
            $gender = 'female';
        }
        return apply_filters('user_gender', $gender);
    }


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

    function options_update($user_id) {

        if (!current_user_can('edit_user', $user_id))
            return false;

        update_user_meta($user_id, $this->meta, esc_attr(filter_input(INPUT_POST, $this->meta, FILTER_SANITIZE_STRING)));
    }


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

    function get_role_list($role_list, $user_object){
        global $wp_roles;
        $user_gender = $this->determine_gender(get_user_meta($user_object->ID, $this->meta, true));
        foreach($role_list as $role=>$role_name){
            $role_list[$role] = in_array($wp_roles->roles[$role]['name'], $this->strings_to_gender) ? _g($wp_roles->roles[$role]['name'], $user_gender, 'wp-genderize') : $role_name;
        }
        return $role_list;
    }

    function alter_role_names(){
        global $wp_roles;
        foreach($wp_roles->role_names as $role=>$name){
            $wp_roles->role_names[$role] = __($wp_roles->roles[$role]['name'], 'wp-genderize');
        }
    }

}


if(!function_exists('_g')){
    function _g($string, $gender='neutral', $domain='default'){
        return _x($string, $gender, $domain);
    }
}
if(!function_exists('_gn')){
    function _gn($single, $plural, $number, $gender='neutral', $domain='default'){
        return _nx($single, $plural, $number, $gender, $domain);
    }
}
