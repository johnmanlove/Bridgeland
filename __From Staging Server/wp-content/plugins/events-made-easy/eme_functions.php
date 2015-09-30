<?php

function eme_client_clock_callback() {
   // Set php clock values in an array
   $phptime_obj = new ExpressiveDate(null,$eme_timezone);
   // if clock data not set
   if (!isset($_SESSION['eme_client_unixtime'])) {
      // Preset php clock values in client session variables for fall-back if valid client clock data isn't received.
      $_SESSION['eme_client_clock_valid'] = false; // Will be set true if all client clock data passes sanity tests
      $_SESSION['eme_client_php_difference'] = 0; // Client-php clock difference integer seconds
      $_SESSION['eme_client_unixtime'] = (int) $phptime_obj->format('U'); // Integer seconds since 1/1/1970 @ 12:00 AM
      $_SESSION['eme_client_seconds'] = (int) $phptime_obj->format('s'); // Integer second this minute (0-59)
      $_SESSION['eme_client_minutes'] = (int) $phptime_obj->format('i'); // Integer minute this hour (0-59)
      $_SESSION['eme_client_hours'] = (int) $phptime_obj->format('h'); // Integer hour this day (0-23)
      $_SESSION['eme_client_wday'] = (int) $phptime_obj->format('w'); // Integer day this week (0-6), 0 = Sunday, ... , 6 = Saturday
      $_SESSION['eme_client_mday'] = (int) $phptime_obj->format('j'); // Integer day this month 1-31)
      $_SESSION['eme_client_month'] = (int) $phptime_obj->format('n'); // Integer month this year (1-12)
      $_SESSION['eme_client_fullyear'] = (int) $phptime_obj->format('Y'); // Integer year (1970-9999)
      $ret = '1'; // reload from server
   } else {
      $ret = '0';
   }
   
   // Cast client clock values as integers to avoid mathematical errors and set in temporary local variables.
   $client_unixtime = (int) $_POST['client_unixtime'];
   $client_seconds = (int) $_POST['client_seconds'];
   $client_minutes = (int) $_POST['client_minutes'];
   $client_hours = (int) $_POST['client_hours'];
   $client_wday = (int) $_POST['client_wday'];
   $client_mday = (int) $_POST['client_mday'];
   $client_month = (int) $_POST['client_month'];
   $client_fullyear = (int) $_POST['client_fullyear'];
   
   // Client clock sanity tests
   $valid = true;
   if (abs($client_unixtime - $_SESSION['eme_client_unixtime']) > 300) $valid = false; // allow +/-5 min difference
   if (abs($client_seconds - 30) > 30) $valid = false; // Seconds <0 or >60
   if (abs($client_minutes - 30) > 30) $valid = false; // Minutes <0 or >60
   if (abs($client_hours - 12) > 12) $valid = false; // Hours <0 or >24
   if (abs($client_wday - 3) > 3) $valid = false; // Weekday <0 or >6
   if (abs($client_mday - $_SESSION['eme_client_mday']) > 30) $valid = false; // >30 day difference
   if (abs($client_month - $_SESSION['eme_client_month']) > 11) $valid = false; // >11 month difference
   if (abs($client_fullyear - $_SESSION['eme_client_fullyear']) > 1) $valid = false; // >1 year difference

   // To insure mutual consistency, don't use any client values unless they all passed the tests.
   if ($valid) {
      $_SESSION['eme_client_unixtime'] = $client_unixtime;
      $_SESSION['eme_client_seconds'] = $client_seconds;
      $_SESSION['eme_client_minutes'] = $client_minutes;
      $_SESSION['eme_client_hours'] = $client_hours;
      $_SESSION['eme_client_wday'] = $client_wday;
      $_SESSION['eme_client_mday'] = $client_mday;
      $_SESSION['eme_client_month'] = $client_month;
      $_SESSION['eme_client_fullyear'] = $client_fullyear;
      $_SESSION['eme_client_clock_valid'] = true;
      // Set  date & time clock strings
      $client_clock_str = "$client_fullyear-$client_month-$client_mday $client_hours:$client_minutes:$client_seconds";
      $client_clock_obj = new ExpressiveDate($client_clock_str,$eme_timezone);
      $_SESSION['eme_client_php_difference'] = (int) $client_clock_obj->getDifferenceInSeconds($phptime_obj);
   }
   
   // it is an ajax instance: echo the result
   echo $ret;
}
function eme_if_shortcode($atts,$content) {
   extract ( shortcode_atts ( array ('tag' => '', 'value' => '', 'notvalue' => '', 'lt' => '', 'le' => '',  'gt' => '', 'ge' => '', 'contains'=>'', 'notcontains'=>'', 'is_empty'=>0 ), $atts ) );
   if ($is_empty) {
      if (empty($tag)) return do_shortcode($content);
   } elseif (is_numeric($value) || !empty($value)) {
      if ($tag==$value) return do_shortcode($content);
   } elseif (is_numeric($notvalue) || !empty($notvalue)) {
      if ($tag!=$notvalue) return do_shortcode($content);
   } elseif (is_numeric($lt) || !empty($lt)) {
      if ($tag<$lt) return do_shortcode($content);
   } elseif (is_numeric($le) || !empty($le)) {
      if ($tag<=$le) return do_shortcode($content);
   } elseif (is_numeric($gt) || !empty($gt)) {
      if ($tag>$gt) return do_shortcode($content);
   } elseif (is_numeric($ge) || !empty($ge)) {
      if ($tag>=$ge) return do_shortcode($content);
   } elseif (is_numeric($contains) || !empty($contains)) {
      if (strpos($tag,"$contains")!== false) return do_shortcode($content);
   } elseif (is_numeric($notcontains) || !empty($notcontains)) {
      if (strpos($tag,"$notcontains")===false) return do_shortcode($content);
   } else {
      if (!empty($tag)) return do_shortcode($content);
   }
}

// Returns true if the page in question is the events page
function eme_is_events_page() {
   $events_page_id = eme_get_events_page_id();
   if ($events_page_id) {
      return is_page ( $events_page_id );
   } else {
      return false;
   }
}

function eme_get_events_page_id() {
   return get_option('eme_events_page');
}

function eme_is_single_day_page() {
   return (eme_is_events_page () && get_query_var('calendar_day'));
}

function eme_is_single_event_page() {
   return (eme_is_events_page () && get_query_var('event_id'));
}

function eme_is_multiple_events_page() {
   return (eme_is_events_page () && get_query_var('event_id'));
}

function eme_is_single_location_page() {
   return (eme_is_events_page () && get_query_var('location_id'));
}

function eme_is_multiple_locations_page() {
   return (eme_is_events_page () && get_query_var('location_id'));
}

function eme_get_contact($event) {
   if ($event['event_contactperson_id'] >0 )
      $contact_id = $event['event_contactperson_id'];
   else
      $contact_id = get_option('eme_default_contact_person');
   // suppose the user has been deleted ...
   if (!get_userdata($contact_id)) $contact_id = get_option('eme_default_contact_person');
   if ($contact_id < 1)
      $contact_id = $event['event_author'];
   if ($contact_id < 1) {
      if (function_exists('is_multisite') && is_multisite()) {
         $thisblog = get_current_blog_id();
         $userinfo = get_user_by('email', get_blog_option($thisblog, 'admin_email'));
      } else {
         $userinfo = get_user_by('email', get_option('admin_email'));
      }
      #$contact_id = get_current_user_id();
   } else {
      $userinfo=get_userdata($contact_id);
   }
   return $userinfo;
}

function eme_get_author($event) {
   $author_id = $event['event_author'];
   if ($author_id < 1) {
      if (function_exists('is_multisite') && is_multisite()) {
         $thisblog = get_current_blog_id();
         $userinfo = get_user_by('email', get_blog_option($thisblog, 'admin_email'));
      } else {
         $userinfo = get_user_by('email', get_option('admin_email'));
      }
      #$contact_id = get_current_user_id();
   } else {
      $userinfo=get_userdata($author_id);
   }
   return $userinfo;
}

function eme_get_user_phone($user_id) {
   return get_user_meta($user_id, 'eme_phone',true);
}

// got from http://davidwalsh.name/php-email-encode-prevent-spam
function eme_ascii_encode($e) {
    $output = "";
    if (has_filter('eme_email_obfuscate_filter')) {
       $output=apply_filters('eme_email_obfuscate_filter',$e);
    } else {
       for ($i = 0; $i < strlen($e); $i++) { $output .= '&#'.ord($e[$i]).';'; }
    }
    return $output;
}

function eme_permalink_convert ($val) {
   // WP provides a function to convert accents to their ascii counterparts
   // called remove_accents, but we also want to replace spaces with "-"
   // and trim the last space. sanitize_title_with_dashes does all that
   // and then, add a trailing slash
   $val = sanitize_title_with_dashes(remove_accents($val));
   return trailingslashit($val);
}

function eme_event_url($event,$language="") {
   global $wp_rewrite;

   $def_language = eme_detect_lang();
   if (empty($language))
         $language = $def_language;
   if ($event['event_url'] != '') {
      $the_link = $event['event_url'];
   } else {
      if (isset($wp_rewrite) && $wp_rewrite->using_permalinks() && get_option('eme_seo_permalink')) {
         $events_prefix=eme_permalink_convert(get_option ( 'eme_permalink_events_prefix'));
         $slug = $event['event_slug'] ? $event['event_slug'] : $event['event_name'];
         $name=$events_prefix.$event['event_id']."/".eme_permalink_convert($slug);
         $the_link = home_url();
         // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
         $the_link = preg_replace("/\/$def_language$/","",$the_link);
         $the_link = trailingslashit(remove_query_arg('lang',$the_link));
         if (!empty($language)) {
            $url_mode=eme_lang_url_mode();
            if ($url_mode==2) {
               $the_link = $the_link."$language/".user_trailingslashit($name);
            } else {
               $the_link = $the_link.user_trailingslashit($name);
               $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
            }
         } else {
            $the_link = $the_link.user_trailingslashit($name);
         }

      } else {
         $the_link = eme_get_events_page(true, false);
         // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
         $the_link = remove_query_arg('lang',$the_link);
         $the_link = add_query_arg( array( 'event_id' => $event['event_id'] ), $the_link );
         if (!empty($language))
            $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
      }
   }
   return $the_link;
}

function eme_location_url($location,$language="") {
   global $wp_rewrite;

   $def_language = eme_detect_lang();
   if (empty($language))
         $language = $def_language;
   $the_link = "";
   if ($location['location_url'] != '') {
      $the_link = $location['location_url'];
   } else {
      $url_mode=eme_lang_url_mode();
      if (isset($location['location_id']) && isset($location['location_name'])) {
         if (isset($wp_rewrite) && $wp_rewrite->using_permalinks() && get_option('eme_seo_permalink')) {
            $locations_prefix=eme_permalink_convert(get_option ( 'eme_permalink_locations_prefix'));
            $slug = $location['location_slug'] ? $location['location_slug'] : $location['location_name'];
            $name=$locations_prefix.$location['location_id']."/".eme_permalink_convert($slug);
            $the_link = home_url();
            // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
            $the_link = preg_replace("/\/$def_language$/","",$the_link);
            $the_link = trailingslashit(remove_query_arg('lang',$the_link));
            if (!empty($language)) {
               $url_mode=eme_lang_url_mode();
               if ($url_mode==2) {
                  $the_link = $the_link."$language/".user_trailingslashit($name);
               } else {
                  $the_link = $the_link.user_trailingslashit($name);
                  $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
               }
            } else {
               $the_link = $the_link.user_trailingslashit($name);
            }
         } else {
            $the_link = eme_get_events_page(true, false);
            // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
            $the_link = remove_query_arg('lang',$the_link);
            $the_link = add_query_arg( array( 'location_id' => $location['location_id'] ), $the_link );
            if (!empty($language))
               $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
         }
      }
   }
   return $the_link;
}

function eme_calendar_day_url($day) {
   global $wp_rewrite;

   $def_language = eme_detect_lang();
   $language = $def_language;

   if (isset($wp_rewrite) && $wp_rewrite->using_permalinks() && get_option('eme_seo_permalink')) {
      $events_prefix=eme_permalink_convert(get_option ( 'eme_permalink_events_prefix'));
      $name=$events_prefix.eme_permalink_convert($day);
      $the_link = home_url();
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = preg_replace("/\/$def_language$/","",$the_link);
      $the_link = trailingslashit(remove_query_arg('lang',$the_link));
      if (!empty($language)) {
         $url_mode=eme_lang_url_mode();
         if ($url_mode==2) {
            $the_link = $the_link."$language/".user_trailingslashit($name);
         } else {
            $the_link = $the_link.user_trailingslashit($name);
            $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
         }
      } else {
         $the_link = $the_link.user_trailingslashit($name);
      }
   } else {
      $the_link = eme_get_events_page(true, false);
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = remove_query_arg('lang',$the_link);
      $the_link = add_query_arg( array( 'calendar_day' => $day ), $the_link );
      if (!empty($language))
         $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
   }
   return $the_link;
}

function eme_payment_url($payment_id) {
   global $wp_rewrite;

   $def_language = eme_detect_lang();
   $language = $def_language;
   if (isset($wp_rewrite) && $wp_rewrite->using_permalinks() && get_option('eme_seo_permalink')) {
      $events_prefix=eme_permalink_convert(get_option ( 'eme_permalink_events_prefix'));
      $name=$events_prefix."p$payment_id";
      $the_link = home_url();
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = preg_replace("/\/$def_language$/","",$the_link);
      $the_link = trailingslashit(remove_query_arg('lang',$the_link));
      if (!empty($language)) {
         $url_mode=eme_lang_url_mode();
         if ($url_mode==2) {
            $the_link = $the_link."$language/".user_trailingslashit($name);
         } else {
            $the_link = $the_link.user_trailingslashit($name);
            $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
         }
      } else {
         $the_link = $the_link.user_trailingslashit($name);
      }
   } else {
      $the_link = eme_get_events_page(true, false);
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = remove_query_arg('lang',$the_link);
      $the_link = add_query_arg( array( 'eme_pmt_id' => $payment_id ), $the_link );
      if (!empty($language))
         $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
   }
   return $the_link;
}

function eme_category_url($category) {
   global $wp_rewrite;

   $def_language = eme_detect_lang();
   $language = $def_language;
   if (isset($wp_rewrite) && $wp_rewrite->using_permalinks() && get_option('eme_seo_permalink')) {
      $events_prefix=eme_permalink_convert(get_option ( 'eme_permalink_events_prefix'));
      $slug = $category['category_slug'] ? $category['category_slug'] : $category['category_name'];
      $name=$events_prefix."cat/".eme_permalink_convert($slug);
      $the_link = home_url();
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = preg_replace("/\/$def_language$/","",$the_link);
      $the_link = trailingslashit(remove_query_arg('lang',$the_link));
      if (!empty($language)) {
         $url_mode=eme_lang_url_mode();
         if ($url_mode==2) {
            $the_link = $the_link."$language/".user_trailingslashit($name);
         } else {
            $the_link = $the_link.user_trailingslashit($name);
            $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
         }
      } else {
         $the_link = $the_link.user_trailingslashit($name);
      }
   } else {
      $the_link = eme_get_events_page(true, false);
      // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
      $the_link = remove_query_arg('lang',$the_link);
      $slug = $category['category_slug'] ? $category['category_slug'] : $category['category_name'];
      $the_link = add_query_arg( array( 'eme_event_cat' => $slug ), $the_link );
      if (!empty($language))
         $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
   }
   return $the_link;
}

function eme_payment_return_url($event,$payment,$resultcode) {
   $the_link=eme_event_url($event);
   if (get_option('eme_payment_show_custom_return_page')) {
      if ($resultcode==1) {
         $res="succes";
      } else {
         $res="fail";
      }
      $the_link = add_query_arg( array( 'eme_pmt_result' => $res ), $the_link );
      $the_link = add_query_arg( array( 'event_id' => $event['event_id'] ), $the_link );
      if (get_option('eme_payment_add_bookingid_to_return'))
         $the_link = add_query_arg( array( 'eme_pmt_id' => $payment['id'] ), $the_link );
   }
   return $the_link;
}

function eme_cancel_booking_url($booking_id) {
   if (!is_user_logged_in()) return;

   $booking=eme_get_booking($booking_id);
   $current_userid=get_current_user_id();
   if ($booking['wp_id']!=$current_userid) return;

   $def_language = eme_detect_lang();
   $language = $def_language;

   $the_link = "";
   // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
   $the_link = remove_query_arg('lang',$the_link);
   $the_link = add_query_arg( array( 'eme_cancel_booking' => $booking_id ), $the_link );
   if (!empty($language))
	   $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
   return $the_link;
}

function eme_cancel_url($payment_randomid) {
   $def_language = eme_detect_lang();
   $language = $def_language;

   $the_link = eme_get_events_page(true, false);
   // some plugins add the lang info to the home_url, remove it so we don't get into trouble or add it twice
   $the_link = remove_query_arg('lang',$the_link);
   $the_link = add_query_arg( array( 'eme_cancel_booking' => $payment_randomid ), $the_link );
   if (!empty($language))
	   $the_link = add_query_arg( array( 'lang' => $language ), $the_link );
   return $the_link;
}

function eme_check_event_exists($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   $sql = "SELECT COUNT(*) from $events_table WHERE event_id='".$event_id."'";
   return $wpdb->get_var($sql);
}

function eme_check_location_exists($location_id) {
   global $wpdb;
   $locations_table = $wpdb->prefix.LOCATIONS_TBNAME;
   $sql = "SELECT COUNT(*) from $locations_table WHERE location_id='".$location_id."'";
   return $wpdb->get_var($sql);
}

function _eme_are_dates_valid($date) {
   // if it is a series of dates
   if (strstr($date, ',')) {
	$dates=explode(',',$date);
   	foreach ( $dates as $date ) {
		if (!_eme_is_date_valid($date)) return false;
	}
   }
   return true;
}
	
function _eme_is_date_valid($date) {
   if (strlen($date) != 10)
      return false;
   $year = intval(substr ( $date, 0, 4 ));
   $month = intval(substr ( $date, 5, 2 ));
   $day = intval(substr ( $date, 8 ));
   return (checkdate ( $month, $day, $year ));
}

function eme_is_time_valid($time) {
   $result = preg_match ( "/([01]\d|2[0-3])(:[0-5]\d)/", $time );
   return ($result);
}

function eme_capNamesCB ( $cap ) {
   $cap = str_replace('_', ' ', $cap);
   $cap = ucfirst($cap);
   return $cap;
}
function eme_get_all_caps() {
   global $wp_roles;
   $caps = array();
   $capabilities = array();

   foreach ( $wp_roles->roles as $role ) {
      if ($role['capabilities']) {
         foreach ( $role['capabilities'] as $cap=>$val ) {
           if (!preg_match("/^level/",$cap))
	      $capabilities[$cap]=eme_capNamesCB($cap);
         }
      }
   }

#   $sys_caps = get_option('syscaps');
#   if ( is_array($sys_caps) ) {
#      $capabilities = array_merge($sys_caps, $capabilities);
#   }

   asort($capabilities);
   return $capabilities;
}

function eme_delete_image_files($image_basename) {
   $mime_types = array(1 => 'gif', 2 => 'jpg', 3 => 'png');
   foreach($mime_types as $type) {
      if (file_exists($image_basename.".".$type))
         unlink($image_basename.".".$type);
   }
}

function eme_status_array() {
   $event_status_array = array();
   $event_status_array[STATUS_PUBLIC] = __ ( 'Public', 'eme' );
   $event_status_array[STATUS_PRIVATE] = __ ( 'Private', 'eme' );
   $event_status_array[STATUS_DRAFT] = __ ( 'Draft', 'eme' );
   return $event_status_array;
}

function eme_localised_date($mydate,$date_format='') {
   global $eme_date_format, $eme_timezone;
   if (empty($date_format))
      $date_format = $eme_date_format;
   // $mydate contains the timezone, but in case it doesn't we provide it
   $eme_date_obj = new ExpressiveDate($mydate,$eme_timezone);
   // Currently in the backend, the timezone is UTC, but maybe that changes in future wp versions
   //   so we search for the current timezone using date_default_timezone_get
   // Since DateTime::format doesn't respect the locale, we use date_i18n here
   //   but date_i18n uses the WP backend timezone, so we need to account for the timezone difference
   // All this because we don't want to use date_default_timezone_set() and wp doesn't set the backend
   //   timezone correctly ...
   $wp_date = new ExpressiveDate($eme_date_obj->getDateTime(),date_default_timezone_get());
   $tz_diff=$eme_date_obj->getOffset()-$wp_date->getOffset();
   $result = date_i18n($date_format, $eme_date_obj->getTimestamp()+$tz_diff);
   return $result;
}

function eme_localised_time($mydate) {
   global $eme_time_format;
   $result = eme_localised_date($mydate,$eme_time_format);
   if (get_option('eme_time_remove_leading_zeros')) {
      $result = str_replace(":00","",$result);
      $result = str_replace(":0",":",$result);
   }
   return $result;
}

function eme_currency_array() {
   $currency_array = array ();
   $currency_array ['AUD'] = __ ( 'Australian Dollar', 'eme' );
   $currency_array ['CAD'] = __ ( 'Canadian Dollar', 'eme' );
   $currency_array ['CZK'] = __ ( 'Czech Koruna', 'eme' );
   $currency_array ['DKK'] = __ ( 'Danish Krone', 'eme' );
   $currency_array ['EUR'] = __ ( 'Euro', 'eme' );
   $currency_array ['HKD'] = __ ( 'Hong Kong Dollar', 'eme' );
   $currency_array ['HUF'] = __ ( 'Hungarian Forint', 'eme' );
   $currency_array ['ILS'] = __ ( 'Israeli New Sheqel', 'eme' );
   $currency_array ['JPY'] = __ ( 'Japanese Yen', 'eme' );
   $currency_array ['MXN'] = __ ( 'Mexican Peso', 'eme' );
   $currency_array ['NOK'] = __ ( 'Norwegian Krone', 'eme' );
   $currency_array ['NZD'] = __ ( 'New Zealand Dollar', 'eme' );
   $currency_array ['PHP'] = __ ( 'Philippine Peso', 'eme' );
   $currency_array ['PLN'] = __ ( 'Polish Zloty', 'eme' );
   $currency_array ['GBP'] = __ ( 'Pound Sterling', 'eme' );
   $currency_array ['SGD'] = __ ( 'Singapore Dollar', 'eme' );
   $currency_array ['SEK'] = __ ( 'Swedish Krona', 'eme' );
   $currency_array ['CHF'] = __ ( 'Swiss Franc', 'eme' );
   $currency_array ['THB'] = __ ( 'Thai Baht', 'eme' );
   $currency_array ['USD'] = __ ( 'U.S. Dollar', 'eme' );
   $currency_array ['CNY'] = __ ( 'Chinese Yuan Renminbi', 'eme' );

   # the next filter allows people to add extra currencies:
   if (has_filter('eme_add_currencies')) $currency_array=apply_filters('eme_add_currencies',$currency_array);
   return $currency_array;
}

function eme_thumbnail_sizes() {
   global $_wp_additional_image_sizes;
   $sizes = array();
   foreach ( get_intermediate_image_sizes() as $s ) {
      $sizes[ $s ] = $s;
   }
   return $sizes;
}

function eme_transfer_nbr_be97($my_nbr) {
   $transfer_nbr_be97_main=sprintf("%010d",$my_nbr);
   // the control number is the %97 result, or 97 in case %97=0
   $transfer_nbr_be97_check=$transfer_nbr_be97_main % 97;
   if ($transfer_nbr_be97_check==0)
      $transfer_nbr_be97_check = 97 ;
   $transfer_nbr_be97_check=sprintf("%02d",$transfer_nbr_be97_check);
   $transfer_nbr_be97 = $transfer_nbr_be97_main.$transfer_nbr_be97_check;
   $transfer_nbr_be97 = substr($transfer_nbr_be97,0,3)."/".substr($transfer_nbr_be97,3,4)."/".substr($transfer_nbr_be97,7,5);
   return $transfer_nbr_be97_main.$transfer_nbr_be97_check;
}

function eme_redefine_locale($locale) {
   if (function_exists('pll_current_language') && function_exists('pll_languages_list')) {
      $languages=pll_languages_list();
      if (!$languages) return $locale;
      $locale="";
      foreach ($languages as $tmp_lang) {
         if (preg_match("/^$tmp_lang\/|\/$tmp_lang\//",$_SERVER['REQUEST_URI']))
               $locale=$tmp_lang.'_'.strtoupper($tmp_lang);
      }
      if (empty($locale))
         $locale=pll_current_language('locale');
   }
   return $locale;
}

function eme_detect_lang_js_trans_function() {
   if (function_exists('ppqtrans_use')) {
      $function_name="pqtrans_use";
   } elseif (function_exists('qtrans_use')) {
      $function_name="qtrans_use";
   } else {
      $function_name="";
   }
   return $function_name;
}

function eme_detect_lang() {
   $language="";
   if (function_exists('qtrans_getLanguage')) {
      // if permalinks are on, $_GET doesn't contain lang as a parameter
      // so we get it like this to be sure
      $language=qtrans_getLanguage();
   } elseif (function_exists('ppqtrans_getLanguage')) {
      $language=ppqtrans_getLanguage();
   } elseif (function_exists('qtranxf_getLanguage')) {
      $language=qtranxf_getLanguage();
   } elseif (function_exists('pll_current_language') && function_exists('pll_languages_list')) {
      $languages=pll_languages_list();
      if (is_array($languages)) {
          foreach ($languages as $tmp_lang) {
             if (preg_match("/^$tmp_lang\/|\/$tmp_lang\//",$_SERVER['REQUEST_URI']))
                   $language=$tmp_lang;
          }
      }
      if (empty($language))
         $language=pll_current_language('slug');
   } elseif (defined('ICL_LANGUAGE_CODE')) {
      // Both polylang and wpml define this constant, so check polylang first (above)
      // if permalinks are on, $_GET doesn't contain lang as a parameter
      // so we get it like this to be sure
      $language=ICL_LANGUAGE_CODE;
   } elseif (isset($_GET['lang'])) {
      $language=eme_strip_tags($_GET['lang']);
   } else {
      $language="";
   }
   return $language;
}

function eme_lang_url_mode() {
   $url_mode=1;
   if (function_exists('mqtranslate_conf')) {
      // only some functions in mqtrans are different, but the options are named the same as for qtranslate
      $url_mode=get_option('mqtranslate_url_mode');
   } elseif (function_exists('qtrans_getLanguage')) {
      $url_mode=get_option('qtranslate_url_mode');
   } elseif (function_exists('ppqtrans_getLanguage')) {
      $url_mode=get_option('pqtranslate_url_mode');
   } elseif (function_exists('qtranxf_getLanguage')) {
      $url_mode=get_option('qtranslate_url_mode');
   } elseif (function_exists('pll_current_language')) {
      $url_mode=2;
   }
   return $url_mode;
}

# support older php version for array_replace_recursive
if (!function_exists('array_replace_recursive')) {
   function array_replace_recursive($array, $array1) {
      function recurse($array, $array1) {
         foreach ($array1 as $key => $value) {
            // create new key in $array, if it is empty or not an array
            if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
               $array[$key] = array();
            }

            // overwrite the value in the base array
            if (is_array($value)) {
               $value = recurse($array[$key], $value);
            }
            $array[$key] = $value;
         }
         return $array;
      }

      // handle the arguments, merge one by one
      $args = func_get_args();
      $array = $args[0];
      if (!is_array($array)) {
         return $array;
      }
      for ($i = 1; $i < count($args); $i++) {
         if (is_array($args[$i])) {
            $array = recurse($array, $args[$i]);
         }
      }
      return $array;
   }
}

function eme_get_query_arg($arg) {
   if (isset($_GET[$arg]))
      return eme_strip_tags($_GET[$arg]);
   else
      return false;
}

?>
