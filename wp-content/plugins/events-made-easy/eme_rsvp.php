<?php

function eme_add_booking_form($event_id,$show_message=1,$not_registered_only=0) {
   global $eme_timezone;
   $form_result_message = "";
   $event = eme_get_event($event_id);
   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }

   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      if (!is_user_logged_in()) {
         return;
      }
   }

   #$destination = eme_event_url($event)."#eme-rsvp-message";
   if (isset($_GET['lang'])) {
      $language=eme_strip_tags($_GET['lang']);
      $destination = "?lang=".$language."#eme-rsvp-message";
   } else {
      $destination = "#eme-rsvp-message";
   }

   // after the add or delete booking, we do a POST to the same page using javascript to show just the result
   // this has 2 advantages: you can give arguments in the post, and refreshing the page won't repeat the booking action, just the post showing the result
   // a javascript redir using window.replace + GET would work too, but that leaves an ugly GET url
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'add_booking' && isset($_POST['eme_event_id'])) {
      $event_id = intval($_POST['eme_event_id']);
      $event = eme_get_event($event_id);
      if (has_filter('eme_eval_booking_form_post_filter'))
         $eval_filter_return=apply_filters('eme_eval_booking_form_post_filter',$event);
      else
         $eval_filter_return=array(0=>1,1=>'');
      if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
         // the result of own eval rules failed, so let's use that as a result
         $booking_id_done = 0;
         $form_result_message = $eval_filter_return[1];
      } else {
         $send_mail=1;
         $booking_res = eme_book_seats($event,$send_mail);
         $form_result_message = $booking_res[0];
         $booking_id_done=$booking_res[1];
      }
      $post_string="{";
      if ($booking_id_done && eme_event_can_pay_online($event)) {
         $payment_id = eme_get_booking_payment_id($booking_id_done);
         if (!empty($payment_id)) {
            // you did a successfull registration, so now we decide wether to show the form again, or the payment form
            // but to make sure people don't mess with the booking id in the url, we use wp_nonce
            // by default the nonce is valid for 24 hours
            $eme_payment_nonce=wp_create_nonce('eme_payment_id'.$payment_id);
            // create the JS array that will be used to post
            $post_arr = array (
                  "eme_eventAction" => 'pay_booking',
                  "eme_message" => $form_result_message,
                  "eme_payment_id" => $payment_id,
                  "eme_payment_nonce" => $eme_payment_nonce
                  );
         } else {
            // no payment registered (price=0)
            $post_arr = array (
                  "eme_eventAction" => 'message',
                  "eme_message" => $form_result_message,
                  "booking_done" => 1
                  );
         }
      } elseif ($booking_id_done) {
         $post_arr = array (
               "eme_eventAction" => 'message',
               "eme_message" => $form_result_message,
               "booking_done" => 1
               );
      } else {
         // booking failed: we add $_POST to the json, so we can pre-fill the form so the user can just correct the mistake
         $post_arr = stripslashes_deep($_POST);
         $post_arr['eme_eventAction'] = 'message';
         $post_arr['eme_message'] = $form_result_message;
      }
      $post_string=json_encode($post_arr);
      ?>
      <script type="text/javascript">
      function postwith (to,p) {
         var myForm = document.createElement("form");
         myForm.method="post" ;
         myForm.action = to ;
         for (var k in p) {
            var myInput = document.createElement("input") ;
            myInput.setAttribute("name", k) ;
            myInput.setAttribute("value", p[k]);
            myForm.appendChild(myInput) ;
         }
         document.body.appendChild(myForm) ;
         myForm.submit() ;
         document.body.removeChild(myForm) ;
      }
      <?php echo "postwith('$destination',$post_string);"; ?>
      </script>
      <?php
      return;
   }

   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'pay_booking' && isset($_POST['eme_message']) && isset($_POST['eme_payment_id'])) {
      $payment_id = intval($_POST['eme_payment_id']);
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      // verify the nonce, to make sure people didn't mess with the booking id
      if (!isset($_POST['eme_payment_nonce']) || !wp_verify_nonce($_POST['eme_payment_nonce'], 'eme_payment_id'.$payment_id)) {
         return;
      } else {
         return eme_payment_form($event,$payment_id,$form_result_message);
      }
   }

   $message_is_result_of_booking=0;
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'message' && isset($_POST['eme_message'])) {
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      if (isset($_POST['booking_done']))
         $message_is_result_of_booking=1;
   }

   $ret_string = "<div id='eme-rsvp-message'>";
   if ($show_message && !empty($form_result_message))
      $ret_string .= "<div class='eme-rsvp-message'>$form_result_message</div>";

   $event_rsvp_startdatetime = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
   $event_rsvp_enddatetime = new ExpressiveDate($event['event_end_date']." ".$event['event_end_time'],$eme_timezone);
   if ($event['event_properties']['rsvp_end_target']=='start')
      $event_rsvp_datetime = $event_rsvp_startdatetime->copy();
   else
      $event_rsvp_datetime = $event_rsvp_enddatetime->copy();

   $eme_date_obj_now = new ExpressiveDate(null,$eme_timezone);
   if ($event_rsvp_datetime->lessThan($eme_date_obj_now->copy()->modifyDays($event['rsvp_number_days'])->modifyHours($event['rsvp_number_hours'])) ||
       $event_rsvp_enddatetime->lessOrEqualTo($eme_date_obj_now)) {
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'eme')."</div></div>";
   }

   // you can book the available number of seats, with a max of x per time
   $min_allowed = $event['event_properties']['min_allowed'];
   // no seats anymore? No booking form then ... but only if it is required that the min number of
   // bookings should be >0 (it can be=0 for attendance bookings)
   $seats_available=1;
   if (eme_is_multi($min_allowed)) {
      $min_allowed_arr=eme_convert_multi2array($min_allowed);
      $avail_seats = eme_get_available_multiseats($event_id);
      foreach ($avail_seats as $key=> $value) {
          if ($value==0 && $min_allowed_arr[$key]>0)
             $seats_available=0;
      }
   } else {
      $avail_seats = eme_get_available_seats($event_id);
      if ($avail_seats == 0 && $min_allowed>0)
         $seats_available=0;
   }

   $form_html = "";
   if (!$seats_available) {
      // we show the message concerning 'no more seats' only if it is not after a successful booking
      if (!$message_is_result_of_booking)
         $ret_string.="<div class='eme-rsvp-message'>".__('Bookings no longer possible: no seats available anymore', 'eme')."</div>";
   } else {
      if (!$message_is_result_of_booking || ($message_is_result_of_booking && get_option('eme_rsvp_show_form_after_booking'))) {
         $current_userid=get_current_user_id();
         if (!$not_registered_only || ($not_registered_only && is_user_logged_in() && !eme_get_booking_ids_by_wp_id($current_userid,$event['event_id']))) {
            $form_html = "<form id='eme-rsvp-form' name='booking-form' method='post' action='$destination' onsubmit='eme_submit_button.disabled=true; return true;'>";
            $form_html .= eme_replace_formfields_placeholders ($event);
            // add a nonce for extra security
            $form_html .= wp_nonce_field('add_booking','eme_rsvp_nonce',false,false);
            // also add a honeypot field: if it gets completed with data, 
            // it's a bot, since a humand can't see this (using CSS to render it invisible)
            $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>
               <p id='eme_mark_required_field'>".__('(* marks a required field)', 'eme')."</p>
               <input type='hidden' name='eme_eventAction' value='add_booking' />
               <input type='hidden' name='eme_event_id' value='$event_id' />
               </form>";
            if (has_filter('eme_add_booking_form_filter')) $form_html=apply_filters('eme_add_booking_form_filter',$form_html);
         }
      }
   }
   return $ret_string.$form_html."</div>";
   
}

function eme_add_multibooking_form($event_ids,$template_id_header=0,$template_id_entry,$template_id_footer=0,$eme_register_empty_seats=0,$show_message=1) {
   global $eme_timezone;
   // we need template ids
   $format_header = eme_get_template_format($template_id_header);
   $format_entry = eme_get_template_format($template_id_entry);
   $format_footer = eme_get_template_format($template_id_footer);

   $events=eme_get_event($event_ids);

   // rsvp not active or no rsvp for this event, then return
   foreach ($events as $event) {
      if (!eme_is_event_rsvp($event)) {
         return;
      }

      $registration_wp_users_only=$event['registration_wp_users_only'];
      if ($registration_wp_users_only) {
         // we require a user to be WP registered to be able to book
         if (!is_user_logged_in()) {
            return;
         }
      }
   }

   #$destination = eme_event_url($event)."#eme-rsvp-message";
   if (isset($_GET['lang'])) {
      $language=eme_strip_tags($_GET['lang']);
      $destination = "?lang=".$language."#eme-rsvp-message";
   } else {
      $destination = "#eme-rsvp-message";
   }

   // after the add or delete booking, we do a POST to the same page using javascript to show just the result
   // this has 2 advantages: you can give arguments in the post, and refreshing the page won't repeat the booking action, just the post showing the result
   // a javascript redir using window.replace + GET would work too, but that leaves an ugly GET url
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'add_bookings' && isset($_POST['eme_event_ids'])) {
      $event_ids = $_POST['eme_event_ids'];
      $events = eme_get_event($event_ids);

      if (has_filter('eme_eval_multibooking_form_post_filter'))
         $eval_filter_return=apply_filters('eme_eval_multibooking_form_post_filter',$events);
      else
         $eval_filter_return=array(0=>1,1=>'');
      if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
         // the result of own eval rules failed, so let's use that as a result
         $booking_ids_done = 0;
         $form_result_message = $eval_filter_return[1];
      } else {
         $send_mail=1;
         $booking_res = eme_multibook_seats($events,$send_mail,$format_entry);
         $form_result_message = $booking_res[0];
         $booking_ids_done=$booking_res[1];
      }

      $post_string="{";
      // let's decide for the first event wether or not payment is needed
      if ($booking_ids_done && eme_event_can_pay_online($events[0])) {
         $payment_id = eme_get_bookings_payment_id($booking_ids_done);
         if (!empty($payment_id)) {
            // you did a successfull registration, so now we decide wether to show the form again, or the payment form
            // but to make sure people don't mess with the booking id in the url, we use wp_nonce
            // by default the nonce is valid for 24 hours
            $eme_payment_nonce=wp_create_nonce('eme_payment_id'.$payment_id);
            // create the JS array that will be used to post
            $post_arr = array (
                  "eme_eventAction" => 'pay_bookings',
                  "eme_message" => $form_result_message,
                  "eme_payment_id" => $payment_id,
                  "eme_payment_nonce" => $eme_payment_nonce
                  );
         } else {
            // no payment registered (price=0)
            $post_arr = array (
                  "eme_eventAction" => 'message',
                  "eme_message" => $form_result_message,
                  "booking_done" => 1
                  );
         }
      } elseif ($booking_ids_done) {
         $post_arr = array (
               "eme_eventAction" => 'message',
               "eme_message" => $form_result_message,
               "booking_done" => 1
               );
      } else {
         // booking failed: we add $_POST to the json, so we can pre-fill the form so the user can just correct the mistake
         $post_arr = stripslashes_deep($_POST);
         $post_arr['eme_eventAction'] = 'message';
         $post_arr['eme_message'] = $form_result_message;
      }

      // this should not be reposted (useless list of event ids now)
      unset($post_arr['eme_event_ids']);
      // and some parts should be formatted differently in the name (php makes arrays, but we need it as names for javascript)
      if (isset($post_arr['bookings'])) {
         foreach ($post_arr['bookings'] as $key=>$val) {
            $post_arr['bookings['.$key.'][bookedSeats]']=$val['bookedSeats'];
         }
         unset($post_arr['bookings']);
      }

      $post_string=json_encode($post_arr);
      ?>
      <script type="text/javascript">
      function postwith (to,p) {
         var myForm = document.createElement("form");
         myForm.method="post" ;
         myForm.action = to ;
         for (var k in p) {
            var myInput = document.createElement("input") ;
            myInput.setAttribute("name", k) ;
            myInput.setAttribute("value", p[k]);
            myForm.appendChild(myInput) ;
         }
         document.body.appendChild(myForm) ;
         myForm.submit() ;
         document.body.removeChild(myForm) ;
      }
      <?php echo "postwith('$destination',$post_string);"; ?>
      </script>
      <?php
      return;
   }

   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'pay_bookings' && isset($_POST['eme_message']) && isset($_POST['eme_payment_id'])) {
      $payment_id = $_POST['eme_payment_id'];
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      // verify the nonce, to make sure people didn't mess with the booking id
      if (!isset($_POST['eme_payment_nonce']) || !wp_verify_nonce($_POST['eme_payment_nonce'], 'eme_payment_id'.$payment_id)) {
         return;
      } else {
         return eme_multipayment_form($payment_id,$form_result_message);
      }
   }

   $message_is_result_of_booking=0;
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'message' && isset($_POST['eme_message'])) {
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      if (isset($_POST['booking_done']))
         $message_is_result_of_booking=1;
   }

   $ret_string = "<div id='eme-rsvp-message'>";
   if ($show_message && !empty($form_result_message))
      $ret_string .= "<div class='eme-rsvp-message'>$form_result_message</div>";

   $form_html = "";
   if (!$message_is_result_of_booking || ($message_is_result_of_booking && get_option('eme_rsvp_show_form_after_booking'))) {
	   $form_html = "<form id='eme-rsvp-form' name='booking-form' method='post' action='$destination'>";
	   // add a nonce for extra security
	   $form_html .= wp_nonce_field('add_booking','eme_rsvp_nonce',false,false);
	   // also add a honeypot field: if it gets completed with data, 
	   // it's a bot, since a humand can't see this (using CSS to render it invisible)
	   $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>
		   <input type='hidden' name='eme_eventAction' value='add_bookings' />
		   <input type='hidden' name='eme_register_empty_seats' value='$eme_register_empty_seats' />
		   ";

	   $form_html .= eme_replace_extra_multibooking_formfields_placeholders($format_header);

      $eme_date_obj_now = new ExpressiveDate(null,$eme_timezone);
	   foreach ($events as $event) {
		   $event_id=$event['event_id'];
         $event_rsvp_startdatetime = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
         $event_rsvp_enddatetime = new ExpressiveDate($event['event_end_date']." ".$event['event_end_time'],$eme_timezone);
         if ($event['event_properties']['rsvp_end_target']=='start')
            $event_rsvp_datetime = $event_rsvp_startdatetime->copy();
         else
            $event_rsvp_datetime = $event_rsvp_enddatetime->copy();

         if ($event_rsvp_datetime->lessThan($eme_date_obj_now->copy()->modifyDays($event['rsvp_number_days'])->modifyHours($event['rsvp_number_hours'])) ||
               $event_rsvp_enddatetime->lessOrEqualTo($eme_date_obj_now)) {
			   continue;
		   }

		   // you can book the available number of seats, with a max of x per time
		   $min_allowed = $event['event_properties']['min_allowed'];
		   // no seats anymore? No booking form then ... but only if it is required that the min number of
		   // bookings should be >0 (it can be=0 for attendance bookings)
         $seats_available=1;
         if (eme_is_multi($min_allowed)) {
            $min_allowed_arr=eme_convert_multi2array($min_allowed);
            $avail_seats = eme_get_available_multiseats($event_id);
            foreach ($avail_seats as $key=> $value) {
               if ($value==0 && $min_allowed_arr[$key]>0)
                  $seats_available=0;
            }
         } else {
            $avail_seats = eme_get_available_seats($event_id);
            if ($avail_seats == 0 && $min_allowed>0)
               $seats_available=0;
         }

		   if (!$seats_available) {
			   // we show the message concerning 'no more seats' only if it is not after a successful booking
			   //if (!$message_is_result_of_booking)
			   //   $form_html.="<div class='eme-rsvp-message'>".__('Bookings no longer possible: no seats available anymore', 'eme')."</div>";
		   } else {
			   $form_html .= "<input type='hidden' name='eme_event_ids[]' value='$event_id' />";
			   // regular formfield replacement here, but indicate that it is for multibooking
			   $form_html .= eme_replace_formfields_placeholders ($event,"",$format_entry,1);
		   }
	   }
	   $form_html .= eme_replace_extra_multibooking_formfields_placeholders($format_footer);
	   $form_html .= "</form>";
	   if (has_filter('eme_add_booking_form_filter')) $form_html=apply_filters('eme_add_booking_form_filter',$form_html);
   }

   return $ret_string.$form_html."</div>";
}

function eme_add_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0), $atts));
   if ($id)
      return eme_add_booking_form($id);
}

function eme_add_multibooking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'recurrence_id'=>0,'category_id'=>0,'template_id_header'=>0,'template_id'=>0,'template_id_footer'=>0,'eme_register_empty_seats'=>0), $atts));
   $ids=explode(",", $id);
   if ($recurrence_id) {
      // we only want future events, so set the second arg to 1
      $ids=eme_get_recurrence_eventids($recurrence_id,1);
   }
   if ($category_id) {
      // we only want future events, so set the second arg to 1
      $ids=eme_get_category_eventids($category_id,1);
   }
   if ($ids && $template_id_header && $template_id && $template_id_footer)
      return eme_add_multibooking_form($ids,$template_id_header,$template_id,$template_id_footer,$eme_register_empty_seats);
}

function eme_booking_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0), $atts));
   if ($id>0) {
   	$event = eme_get_event(intval($id));
   	if ($event)
      		return eme_get_bookings_list_for_event($event,$template_id,$template_id_header,$template_id_footer);
   }
}

function eme_mybooking_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0,'future'=>1,'approval_status'=>0), $atts));
   if (is_user_logged_in()) {
	$booker_wp_id=get_current_user_id();
	$person=eme_get_person_by_wp_id($booker_wp_id);
	if ($person)
		return eme_get_bookings_list_for_person($person,$future,'',$template_id,$template_id_header,$template_id_footer,$approval_status);
   }
}

function eme_attendee_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0), $atts));
   $event = eme_get_event(intval($id));
   if ($event)
      return eme_get_attendees_list_for($event,$template_id,$template_id_header,$template_id_footer);
}

function eme_delete_booking_form($event_id,$show_message=1,$registered_only=0) {
   global $current_user, $eme_timezone;
   
   $form_html = "";
   $form_result_message = "";
   $event = eme_get_event($event_id);
   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      if (!is_user_logged_in()) {
         return;
      }
      $readonly="disabled='disabled'";
   } else {
      $readonly="";
   }

   #$destination = eme_event_url($event)."#eme-rsvp-message";
   if (isset($_GET['lang'])) {
      $language=eme_strip_tags($_GET['lang']);
      $destination = "?lang=".$language."#eme-rsvp-message";
   } else {
      $destination = "#eme-rsvp-message";
   }
   
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'delete_booking' && isset($_POST['event_id'])) {
      $form_result_message = eme_cancel_seats($event);
      $eme_message_nonce=wp_create_nonce('eme_message'.$form_result_message);
      // post to a page showing the result of the booking
      // create the JS array that will be used to post
      $post_arr = array (
            "eme_eventAction" => 'message',
            "eme_message" => $form_result_message,
            );
      $post_string=json_encode($post_arr);
      ?>
      <script type="text/javascript">
      function postwith (to,p) {
         var myForm = document.createElement("form");
         myForm.method="post" ;
         myForm.action = to ;
         for (var k in p) {
            var myInput = document.createElement("input") ;
            myInput.setAttribute("name", k) ;
            myInput.setAttribute("value", p[k]);
            myForm.appendChild(myInput) ;
         }
         document.body.appendChild(myForm) ;
         myForm.submit() ;
         document.body.removeChild(myForm) ;
      }
      <?php echo "postwith('$destination',$post_string);"; ?>
      </script>
      <?php
      return;
   }
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'message' && isset($_POST['eme_message'])) {
      $form_result_message = eme_sanitize_html($_POST['eme_message']);
   }

   $event_rsvp_startdatetime = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
   $event_rsvp_enddatetime = new ExpressiveDate($event['event_end_date']." ".$event['event_end_time'],$eme_timezone);
   if ($event['event_properties']['rsvp_end_target']=='start')
      $event_rsvp_datetime = $event_rsvp_startdatetime->copy();
   else
      $event_rsvp_datetime = $event_rsvp_enddatetime->copy();

   $eme_date_obj_now = new ExpressiveDate(null,$eme_timezone);
   if ($event_rsvp_datetime->lessThan($eme_date_obj_now->copy()->modifyDays($event['rsvp_number_days'])->modifyHours($event['rsvp_number_hours'])) ||
       $event_rsvp_enddatetime->lessOrEqualTo($eme_date_obj_now)) {
      if(!empty($form_result_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_result_message</div>";
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'eme')."</div></div>";
   }

   if ($show_message && !empty($form_result_message)) {
      $form_html = "<div id='eme-rsvp-message'>";
      $form_html .= "<div class='eme-rsvp-message'>$form_result_message</div>";
      $form_html .= "</div>";
   }

   $current_userid=get_current_user_id();
   if (!$registered_only || ($registered_only && is_user_logged_in() && eme_get_booking_ids_by_wp_id($current_userid,$event['event_id']))) {
      $form_html .= "<form id='booking-delete-form' name='booking-delete-form' method='post' action='$destination'>
         <input type='hidden' name='eme_eventAction' value='delete_booking' />
         <input type='hidden' name='event_id' value='$event_id' />";
      $form_html .= wp_nonce_field('del_booking','eme_rsvp_nonce',false,false);
      $form_html .= eme_replace_cancelformfields_placeholders($event);
      $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>
         <p id='eme_mark_required_field'>".__('(* marks a required field)', 'eme')."</p>";
      $form_html .= "</form>";
      if (has_filter('eme_delete_booking_form_filter')) $form_html=apply_filters('eme_delete_booking_form_filter',$form_html);
   }
   return $form_html;
}

function eme_delete_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id' => 0), $atts));
   return eme_delete_booking_form($id);
}

function eme_cancel_confirm_form($payment_randomid) {
   global $eme_timezone;

   $destination = eme_get_events_page(true, false);
   $payment=eme_get_payment(0,$payment_randomid);
   $booking_ids=eme_get_payment_booking_ids($payment['id']);
   if ($booking_ids) {
      $format="#_STARTDATE #_STARTTIME: #_EVENTNAME (#_RESPSPACES places) <br />";
      $eme_format_header=get_option('eme_bookings_list_header_format');
      $eme_format_footer=get_option('eme_bookings_list_footer_format');

      $res=__("You're about to cancel the following bookings:","eme").$eme_format_header;
      $eme_date_obj_now=new ExpressiveDate(null,$eme_timezone);
      foreach ($booking_ids as $booking_id) {
         // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
         $booking=eme_get_booking($booking_id);
         $event=eme_get_event($booking['event_id']);
         $cancel_cutofftime=new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
         $eme_cancel_rsvp_days=-1*get_option('eme_cancel_rsvp_days');
         $cancel_cutofftime->modifyDays($eme_cancel_rsvp_days);
         if ($cancel_cutofftime->lessThan($eme_date_obj_now)) {
            $res="<p class='eme_no_booking'>".__("You're no longer allowed to cancel this booking","eme")."</p>";
            return $res;
         }
         $tmp_format = eme_replace_placeholders($format, $event, "html", 0);
         $res.= eme_replace_booking_placeholders($tmp_format,$event,$booking);
      }
      $res.=$eme_format_footer;
      $res .= "<form id='booking-cancel-form' name='booking-cancel-form' method='post' action='$destination'>
         <input type='hidden' name='eme_confirm_cancel_booking' value='1' />
         <input type='hidden' name='eme_pmt_rndid' value='$payment_randomid' />";
      $res .= wp_nonce_field("cancel booking $payment_randomid",'eme_rsvp_nonce',false,false);
      $res .= "<input name='eme_submit_button' type='submit' value='Cancel the booking' />";
      $res .= "</form>";

   } else {
      $res="<p class='eme_no_booking'>".__('No such booking found!','eme')."</p>";
   }
   return $res;
}

 // eme_cancel_seats is NOT called from the admin backend, but to be sure: we check for it
function eme_cancel_seats($event) {
   global $current_user;
   $event_id = $event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];

   if (is_admin()) {
      return __('This function is not allowed from the admin backend.', 'eme');
   }

   // check for spammers as early as possible
   if (isset($_POST['honeypot_check'])) {
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   } elseif (!is_admin() && !isset($_POST['honeypot_check'])) {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $honeypot_check = "bad boy";
   } else {
      $honeypot_check = "";
   }

   if (!is_admin() && get_option('eme_captcha_for_booking')) {
      $captcha_err = response_check_captcha("captcha_check","eme_del_booking");
   } else {
      $captcha_err = "";
   }

   if (!is_admin() && (! isset( $_POST['eme_rsvp_nonce'] ) ||
       ! wp_verify_nonce( $_POST['eme_rsvp_nonce'], 'del_booking' ))) {
      $nonce_err = "bad boy";
   } else {
      $nonce_err = "";
   }

   if(!empty($captcha_err)) {
      return __('You entered an incorrect code','eme');
   } elseif (!empty($honeypot_check) ||  !empty($nonce_err)) {
      return __("You're not allowed to do this. If you believe you've received this message in error please contact the site owner.",'eme');
   } 

   $booker = array();
   if ($registration_wp_users_only && is_user_logged_in()) {
      // we require a user to be WP registered to be able to book
      get_currentuserinfo();
      $booker_wp_id=$current_user->ID;
      // we also need name and email for sending the mail
      $bookerLastName = $current_user->user_lastname;
      if (empty($bookerLastName))
         $bookerLastName = $current_user->display_name;
      $bookerFirstName = $current_user->user_firstname;
      $bookerEmail = $current_user->user_email;
      $booker = eme_get_person_by_wp_id($booker_wp_id);
   } elseif (isset($_POST['lastname']) && isset($_POST['email'])) {
      $bookerLastName = eme_strip_tags($_POST['lastname']);
      if (isset($_POST['firstname']))
         $bookerFirstName = eme_strip_tags($_POST['firstname']);
      else
         $bookerFirstName = "";
      $bookerEmail = eme_strip_tags($_POST['email']);
      $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
   }
   if (!empty($booker)) {
      $person_id = $booker['person_id'];
      $booking_ids=eme_get_booking_ids_by_person_event_id($person_id,$event_id);
      if (!empty($booking_ids)) {
         foreach ($booking_ids as $booking_id) {
            // first get the booking details, then delete it and then send the mail
            // the mail needs to be sent after the deletion, otherwise the count of free spaces is wrong
            $booking = eme_get_booking ($booking_id);
            eme_delete_booking($booking_id);
            eme_email_rsvp_booking($booking,"cancelRegistration");
            // delete the booking answers after the mail is sent, so the answers can still be used in the mail
            eme_delete_answers($booking_id);
         }
         $result = __('Booking deleted', 'eme');
      } else {
         $result = __('There are no bookings associated to this name and e-mail', 'eme');
      }
   } else {
      $result = __('There are no bookings associated to this name and e-mail', 'eme');
   }
   return $result;
}

function eme_multibook_seats($events, $send_mail, $format) {
   global $current_user;
   $booking_ids = array();
   $total_price = 0;
   $result="";

   // check for spammers as early as possible
   if (isset($_POST['honeypot_check'])) {
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   } elseif (!is_admin() && !isset($_POST['honeypot_check'])) {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $honeypot_check = "bad boy";
   } else {
      $honeypot_check = "";
   }

   if (!is_admin() && get_option('eme_captcha_for_booking')) {
      $captcha_err = response_check_captcha("captcha_check","eme_add_booking");
   } else {
      $captcha_err = "";
   }

   if (!is_admin() && (! isset( $_POST['eme_rsvp_nonce'] ) ||
       ! wp_verify_nonce( $_POST['eme_rsvp_nonce'], 'add_booking' ))) {
      $nonce_err = "bad boy";
   } else {
      $nonce_err = "";
   }

   if(!empty($captcha_err)) {
      $result = __('You entered an incorrect code','eme');
      return array(0=>$result,1=>$booking_ids);
   } elseif (!empty($honeypot_check) ||  !empty($nonce_err)) {
      $result = __("You're not allowed to do this. If you believe you've received this message in error please contact the site owner.",'eme');
      return array(0=>$result,1=>$booking_ids);
   } 

   // now do regular checks
   $all_required_fields=eme_find_required_formfields($format);
   foreach ($events as $event) {
      $min_allowed = $event['event_properties']['min_allowed'];
      $max_allowed = $event['event_properties']['max_allowed'];
      if ($event['event_properties']['take_attendance']) {
         $min_allowed = 0;
         $max_allowed = 1;
      }

      $event_id=$event['event_id'];
      if (isset($_POST['bookings'][$event_id]['bookedSeats']))
         $bookedSeats = intval($_POST['bookings'][$event_id]['bookedSeats']);
      else
         $bookedSeats = 0;

      // only register empty seats if wanted
      if ($bookedSeats==0 && (!isset($_POST['eme_register_empty_seats']) || intval($_POST['eme_register_empty_seats'])==0))
         continue;

      // for multiple prices, we have multiple booked Seats as well
      // the next foreach is only valid when called from the frontend
      $bookedSeats_mp = array();
      if (eme_is_multi($event['price'])) {
         // make sure the array contains the correct keys already, since
         // later on in the function eme_record_booking we do a join
         $booking_prices_mp=eme_convert_multi2array($event['price']);
         foreach ($booking_prices_mp as $key=>$value) {
            $bookedSeats_mp[$key] = 0;
         }
         foreach($_POST['bookings'][$event_id] as $key=>$value) {
            if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
               $field_id = intval($matches[1])-1;
               $bookedSeats += $value;
               $bookedSeats_mp[$field_id]=$value;
            }
         }
      }

      if (isset($_POST['bookings'][$event_id]['comment']))
         $bookerComment = eme_strip_tags($_POST['bookings'][$event_id]['comment']);
      elseif (isset($_POST['comment']))
         $bookerComment = eme_strip_tags($_POST['comment']); 
      else
         $bookerComment = "";

      $missing_required_fields=array();
      // check all required fields
      if (!is_admin() && get_option('eme_rsvp_check_required_fields')) {
         foreach ($all_required_fields as $required_field) {
            if (preg_match ("/LASTNAME|EMAIL|SEATS/",$required_field)) {
               // we already check these seperately, and EMAIL regex also catches _HTML5_EMAIL
               // since NAME would also match FIRSTNAME (which is not necessarily required), we check the beginning too
               continue;
            } elseif (preg_match ("/PHONE/",$required_field)) {
               // PHONE regex also catches HTML5_PHONE
               if (!isset($_POST['phone']) || empty($_POST['phone'])) array_push($missing_required_fields, __('Phone number','eme'));
            } elseif (preg_match ("/(ADDRESS1|ADDRESS2|CITY|STATE|ZIP|COUNTRY|FIRSTNAME)/",$required_field, $matches)) {
               $fieldname=strtolower($matches[1]);
               $fieldname_ucfirst=ucfirst($fieldname);
               if (!isset($_POST[$fieldname])) array_push($missing_required_fields, __($fieldname_ucfirst,'eme'));
            } elseif (preg_match ("/COMMENT/",$required_field)) {
               if (empty($bookerComment)) array_push($missing_required_fields, __('Comment','eme'));
            } elseif ((!isset($_POST['bookings'][$event_id][$required_field]) || $_POST['bookings'][$event_id][$required_field]==='') && 
		      (!isset($_POST[$required_field]) || $_POST[$required_field]==='')) {
               if (preg_match('/FIELD(\d+)/', $required_field, $matches)) {
                  $field_id = intval($matches[1]);
                  $formfield = eme_get_formfield_byid($field_id);
                  array_push($missing_required_fields, $formfield['field_name']);
               } else {
                  array_push($missing_required_fields, $required_field);
               }
            }
         }
      }

      $registration_wp_users_only=$event['registration_wp_users_only'];
      $bookerLastName = "";
      $bookerFirstName = "";
      $bookerEmail = "";
      $booker=array();
      if (!is_admin() && $registration_wp_users_only && is_user_logged_in()) {
         // we require a user to be WP registered to be able to book
         get_currentuserinfo();
         $booker_wp_id=$current_user->ID;
         // we also need name and email for sending the mail
         $bookerLastName = $current_user->user_lastname;
         if (empty($bookerLastName))
            $bookerLastName = $current_user->display_name;
         $bookerFirstName = $current_user->user_firstname;
         $bookerEmail = $current_user->user_email;
         $booker = eme_get_person_by_wp_id($booker_wp_id);
      } elseif (!is_admin() && is_user_logged_in() && isset($_POST['lastname']) && isset($_POST['email'])) {
         $booker_wp_id=get_current_user_id();
         $bookerLastName = eme_strip_tags($_POST['lastname']);
         if (isset($_POST['firstname']))
            $bookerFirstName = eme_strip_tags($_POST['firstname']);
         $bookerEmail = eme_strip_tags($_POST['email']);
         $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
      } elseif (isset($_POST['lastname']) && isset($_POST['email'])) {
         // when called from the admin backend, we don't care about registration_wp_users_only
         $booker_wp_id=0;
         $bookerLastName = eme_strip_tags($_POST['lastname']);
         if (isset($_POST['firstname']))
            $bookerFirstName = eme_strip_tags($_POST['firstname']);
         $bookerEmail = eme_strip_tags($_POST['email']);
         $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
      }

      if (has_filter('eme_eval_booking_filter'))
         $eval_filter_return=apply_filters('eme_eval_booking_filter',$event);
      else
         $eval_filter_return=array(0=>1,1=>'');

      if (empty($bookerLastName)) {
         // if any required field is empty: return an error
         $result .= __('Please fill out your last name','eme');
         // to be backwards compatible, don't require bookerFirstName here: it can be empty for forms that just use #_NAME
      } elseif (empty($bookerEmail)) {
         // if any required field is empty: return an error
         $result .= __('Please fill out your e-mail','eme');
      } elseif (count($missing_required_fields)>0) {
         // if any required field is empty: return an error
         $missing_required_fields_string=join(", ",$missing_required_fields);
         $result .= sprintf(__('Please make sure all of the following required fields are filled out correctly: %s','eme'),$missing_required_fields_string);
      } elseif (!is_email($bookerEmail)) {
         $result .= __('Please enter a valid mail address','eme');
      } elseif (!eme_is_multi($min_allowed) && $bookedSeats < $min_allowed) {
         $result .= __('Please enter a correct number of spaces to reserve','eme');
      } elseif (eme_is_multi($min_allowed) && eme_is_multi($event['event_seats']) && $bookedSeats_mp < eme_convert_multi2array($min_allowed)) {
         $result .= __('Please enter a correct number of spaces to reserve','eme');
      } elseif (!eme_is_multi($max_allowed) && $max_allowed>0 && $bookedSeats>$max_allowed) {
         // we check the max, but only is max_allowed>0, max_allowed=0 means no limit
         $result .= __('Please enter a correct number of spaces to reserve','eme');
      } elseif (eme_is_multi($max_allowed) && eme_is_multi($event['event_seats']) && eme_get_multitotal($max_allowed)>0 && $bookedSeats_mp >  eme_convert_multi2array($max_allowed)) {
         // we check the max, but only is the total max_allowed>0, max_allowed=0 means no limit
         // currently we don't support 0 as being no limit per array element
         $result .= __('Please enter a correct number of spaces to reserve','eme');
      } elseif (!is_admin() && $registration_wp_users_only && !$booker_wp_id) {
         // spammers might get here, but we catch them
         $result .= __('WP membership is required for registration','eme');
      } elseif (is_array($eval_filter_return) && !$eval_filter_return[0]) {
         // the result of own eval rules
         $result .= $eval_filter_return[1];
      } else {
         $language=eme_detect_lang();
         if (eme_is_multi($event['event_seats']))
            $seats_available=eme_are_multiseats_available_for($event_id, $bookedSeats_mp);
         else
            $seats_available=eme_are_seats_available_for($event_id, $bookedSeats);
         if ($seats_available) {
            if (empty($booker))
               $booker = eme_add_person($bookerLastName, $bookerFirstName, $bookerEmail, $booker_wp_id,$language);
            else
               $booker = eme_update_person_with_postinfo($booker['person_id']);

            // ok, just to be safe: check the person_id of the booker
            if ($booker['person_id']>0) {
               // we can only use the filter here, since the booker needs to be created first if needed
               if (has_filter('eme_eval_booking_form_filter'))
                  $eval_filter_return=apply_filters('eme_eval_booking_form_filter',$event,$booker);
               else
                  $eval_filter_return=array(0=>1,1=>'');
               if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
                  // the result of own eval rules failed, so let's use that as a result
                  $result .= $eval_filter_return[1];
               } else {
                  $booking_id=eme_record_booking($event, $booker['person_id'], $bookedSeats,$bookedSeats_mp,$bookerComment,$language);
                  $booking_ids[]=$booking_id;
                  // everything ok, so we unset the variables entered, so when the form is shown again, all is defaulted again
                  foreach($_POST['bookings'][$event_id] as $key=>$value) {
                     unset($_POST['bookings'][$event_id][$key]);
                  }
               }
            } else {
               $result .= __('No booker ID found, something is wrong here','eme');
               unset($_POST['bookings'][$event_id]['bookedSeats']);
            }
         } else {
            $result .= __('Booking cannot be made: not enough seats available!', 'eme');
            // here we only unset the number of seats entered, so the user doesn't have to fill in the rest again
            unset($_POST['bookings'][$event_id]['bookedSeats']);
         }
      }
   }

   $booking_ids_done=join(',',$booking_ids);
   if (!empty($booking_ids_done)) {
      // the payment needs to be created before the mail is sent or placeholders replaced, otherwise you can't send a link to the payment ...
      eme_create_payment($booking_ids_done);

      $is_multibooking=1;
      foreach ($booking_ids as $booking_id) {
         $booking = eme_get_booking ($booking_id);
         $total_price += eme_get_total_booking_price($event,$booking);

         if (!empty($event['event_registration_recorded_ok_html']))
            $ok_format = $event['event_registration_recorded_ok_html'];
         elseif ($event['event_properties']['event_registration_recorded_ok_html_tpl']>0)
            $ok_format = eme_get_template_format($event['event_properties']['event_registration_recorded_ok_html_tpl']);
         else
            $ok_format = get_option('eme_registration_recorded_ok_html' );

         // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
         $result = eme_replace_placeholders($ok_format, $event, "html", 0);
         $result = eme_replace_booking_placeholders($result, $event, $booking,$is_multibooking);
         if (is_admin()) {
            $action="approveRegistration";
         } else {
            $action="";
         }
      }
      // send the mail based on the first booking done in the series
      $booking = eme_get_booking ($booking_ids[0]);
      if ($send_mail) eme_email_rsvp_booking($booking,$action,$is_multibooking);
   }

   $res = array(0=>$result,1=>$booking_ids_done);
   return $res;
}

// the eme_book_seats can also be called from the admin backend, that's why for certain things, we check using is_admin where we are
function eme_book_seats($event, $send_mail) {
   global $current_user;
   $booking_id = 0;
   $total_price = 0;
   $result="";

   // check for spammers as early as possible
   if (isset($_POST['honeypot_check'])) {
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   } elseif (!is_admin() && !isset($_POST['honeypot_check'])) {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $honeypot_check = "bad boy";
   } else {
      $honeypot_check = "";
   }

   if (!is_admin() && get_option('eme_captcha_for_booking')) {
      $captcha_err = response_check_captcha("captcha_check","eme_add_booking");
   } else {
      $captcha_err = "";
   }

   if (!is_admin() && (! isset( $_POST['eme_rsvp_nonce'] ) ||
       ! wp_verify_nonce( $_POST['eme_rsvp_nonce'], 'add_booking' ))) {
      $nonce_err = "bad boy";
   } else {
      $nonce_err = "";
   }

   if(!empty($captcha_err)) {
      $result = __('You entered an incorrect code','eme');
      return array(0=>$result,1=>$booking_id);
   } elseif (!empty($honeypot_check) ||  !empty($nonce_err)) {
      $result = __("You're not allowed to do this. If you believe you've received this message in error please contact the site owner.",'eme');
      return array(0=>$result,1=>$booking_id);
   } 


   // now do regular checks
   if (!empty($event['event_registration_form_format']))
      $format = $event['event_registration_form_format'];
   elseif ($event['event_properties']['event_registration_form_format_tpl']>0)
      $format = eme_get_template_format($event['event_properties']['event_registration_form_format_tpl']);
   else
      $format = get_option('eme_registration_form_format' );
   $all_required_fields=eme_find_required_formfields($format);

   $min_allowed = $event['event_properties']['min_allowed'];
   $max_allowed = $event['event_properties']['max_allowed'];
   if ($event['event_properties']['take_attendance']) {
      $min_allowed = 0;
      $max_allowed = 1;
   }

   if (isset($_POST['bookedSeats']))
      $bookedSeats = intval($_POST['bookedSeats']);
   else
      $bookedSeats = 0;

   // for multiple prices, we have multiple booked Seats as well
   // the next foreach is only valid when called from the frontend
   $bookedSeats_mp = array();
   if (eme_is_multi($event['price'])) {
      // make sure the array contains the correct keys already, since
      // later on in the function eme_record_booking we do a join
      $booking_prices_mp=eme_convert_multi2array($event['price']);
      foreach ($booking_prices_mp as $key=>$value) {
         $bookedSeats_mp[$key] = 0;
      }
      foreach($_POST as $key=>$value) {
         if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
            $field_id = intval($matches[1])-1;
            $bookedSeats += $value;
            $bookedSeats_mp[$field_id]=$value;
         }
      }
   }

   if (isset($_POST['comment']))
      $bookerComment = eme_strip_tags($_POST['comment']);
   else
      $bookerComment = "";

   $missing_required_fields=array();
   // check all required fields
   if (!is_admin() && get_option('eme_rsvp_check_required_fields')) {
      foreach ($all_required_fields as $required_field) {
         if (preg_match ("/LASTNAME|EMAIL|SEATS/",$required_field)) {
            // we already check these seperately, and EMAIL regex also catches _HTML5_EMAIL
            continue;
         } elseif (preg_match ("/PHONE/",$required_field)) {
            // PHONE regex also catches _HTML5_PHONE
            if (!isset($_POST['phone']) || empty($_POST['phone'])) array_push($missing_required_fields, __('Phone number','eme'));
         } elseif (preg_match ("/(ADDRESS1|ADDRESS2|CITY|STATE|ZIP|COUNTRY|FIRSTNAME)/",$required_field, $matches)) {
            $fieldname=strtolower($matches[1]);
            $fieldname_ucfirst=ucfirst($fieldname);
            if (!isset($_POST[$fieldname])) array_push($missing_required_fields, __($fieldname_ucfirst,'eme'));
         } elseif (preg_match ("/COMMENT/",$required_field)) {
            if (empty($bookerComment)) array_push($missing_required_fields, __('Comment','eme'));
         } elseif (!isset($_POST[$required_field]) || $_POST[$required_field]==='') {
            if (preg_match('/FIELD(\d+)/', $required_field, $matches)) {
               $field_id = intval($matches[1]);
               $formfield = eme_get_formfield_byid($field_id);
               array_push($missing_required_fields, $formfield['field_name']);
            } else {
               array_push($missing_required_fields, $required_field);
            }
         }
      }
   }

   $event_id = $event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];
   $bookerLastName = "";
   $bookerFirstName = "";
   $bookerEmail = "";
   $booker=array();
   if (!is_admin() && $registration_wp_users_only && is_user_logged_in()) {
      // we require a user to be WP registered to be able to book
      get_currentuserinfo();
      $booker_wp_id=$current_user->ID;
      // we also need name and email for sending the mail
      $bookerLastName = $current_user->user_lastname;
      if (empty($bookerLastName))
         $bookerLastName = $current_user->display_name;
      $bookerFirstName = $current_user->user_firstname;
      $bookerEmail = $current_user->user_email;
      $booker = eme_get_person_by_wp_id($booker_wp_id);
   } elseif (!is_admin() && is_user_logged_in() && isset($_POST['lastname']) && isset($_POST['email'])) {
      $booker_wp_id=get_current_user_id();
      $bookerLastName = eme_strip_tags($_POST['lastname']);
      if (isset($_POST['firstname']))
         $bookerFirstName = eme_strip_tags($_POST['firstname']);
      $bookerEmail = eme_strip_tags($_POST['email']);
      $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
   } elseif (isset($_POST['lastname']) && isset($_POST['email'])) {
      // when called from the admin backend, we don't care about registration_wp_users_only
      $booker_wp_id=0;
      $bookerLastName = eme_strip_tags($_POST['lastname']);
      if (isset($_POST['firstname']))
         $bookerFirstName = eme_strip_tags($_POST['firstname']);
      $bookerEmail = eme_strip_tags($_POST['email']);
      $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
   }
   
   if (has_filter('eme_eval_booking_filter'))
      $eval_filter_return=apply_filters('eme_eval_booking_filter',$event);
   else
      $eval_filter_return=array(0=>1,1=>'');

   if (empty($bookerLastName)) {
      // if any required field is empty: return an error
      $result = __('Please fill out your last name','eme');
      // to be backwards compatible, don't require bookerFirstName here: it can be empty for forms that just use #_NAME
   } elseif (empty($bookerEmail)) {
      // if any required field is empty: return an error
      $result = __('Please fill out your e-mail','eme');
   } elseif (count($missing_required_fields)>0) {
      // if any required field is empty: return an error
      $missing_required_fields_string=join(", ",$missing_required_fields);
      $result = sprintf(__('Please make sure all of the following required fields are filled out correctly: %s','eme'),$missing_required_fields_string);
   } elseif (!is_email($bookerEmail)) {
      $result = __('Please enter a valid mail address','eme');
   } elseif (!eme_is_multi($min_allowed) && $bookedSeats < $min_allowed) {
      $result = __('Please enter a correct number of spaces to reserve','eme');
   } elseif (eme_is_multi($min_allowed) && eme_is_multi($event['event_seats']) && $bookedSeats_mp < eme_convert_multi2array($min_allowed)) {
      $result = __('Please enter a correct number of spaces to reserve','eme');
   } elseif (!eme_is_multi($max_allowed) && $max_allowed>0 && $bookedSeats>$max_allowed) {
      // we check the max, but only is max_allowed>0, max_allowed=0 means no limit
      $result = __('Please enter a correct number of spaces to reserve','eme');
   } elseif (eme_is_multi($max_allowed) && eme_is_multi($event['event_seats']) && eme_get_multitotal($max_allowed)>0 && $bookedSeats_mp >  eme_convert_multi2array($max_allowed)) {
      // we check the max, but only is the total max_allowed>0, max_allowed=0 means no limit
      // currently we don't support 0 as being no limit per array element
      $result = __('Please enter a correct number of spaces to reserve','eme');
   } elseif (!is_admin() && $registration_wp_users_only && !$booker_wp_id) {
      // spammers might get here, but we catch them
      $result = __('WP membership is required for registration','eme');
   } elseif (is_array($eval_filter_return) && !$eval_filter_return[0]) {
      // the result of own eval rules
      $result = $eval_filter_return[1];
   } else {
      $language=eme_detect_lang();
      if (eme_is_multi($event['event_seats']))
         $seats_available=eme_are_multiseats_available_for($event_id, $bookedSeats_mp);
      else
         $seats_available=eme_are_seats_available_for($event_id, $bookedSeats);
      if ($seats_available) {
         if (empty($booker))
            $booker = eme_add_person($bookerLastName, $bookerFirstName, $bookerEmail, $booker_wp_id,$language);
         else
            $booker = eme_update_person_with_postinfo($booker['person_id']);

         // ok, just to be safe: check the person_id of the booker
         if ($booker['person_id']>0) {
            // we can only use the filter here, since the booker needs to be created first if needed
            if (has_filter('eme_eval_booking_form_filter'))
               $eval_filter_return=apply_filters('eme_eval_booking_form_filter',$event,$booker);
            else
               $eval_filter_return=array(0=>1,1=>'');
            if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
               // the result of own eval rules failed, so let's use that as a result
               $result = $eval_filter_return[1];
            } else {
               $booking_id=eme_record_booking($event, $booker['person_id'], $bookedSeats,$bookedSeats_mp,$bookerComment,$language);
               // everything ok, so we unset the variables entered, so when the form is shown again, all is defaulted again
               foreach($_POST as $key=>$value) {
                  unset($_POST[$key]);
               }
            }
         } else {
            $result = __('No booker ID found, something is wrong here','eme');
            unset($_POST['bookedSeats']);
         }
      } else {
         $result = __('Booking cannot be made: not enough seats available!', 'eme');
         // here we only unset the number of seats entered, so the user doesn't have to fill in the rest again
         unset($_POST['bookedSeats']);
      }
   }

   if ($booking_id) {
      // the payment needs to be created before the mail is sent or placeholders replaced, otherwise you can't send a link to the payment ...
      eme_create_payment($booking_id);

      $booking = eme_get_booking ($booking_id);
      $total_price = eme_get_total_booking_price($event,$booking);
      if (!empty($event['event_registration_recorded_ok_html']))
         $ok_format = $event['event_registration_recorded_ok_html'];
      elseif ($event['event_properties']['event_registration_recorded_ok_html_tpl']>0)
         $ok_format = eme_get_template_format($event['event_properties']['event_registration_recorded_ok_html_tpl']);
      else
         $ok_format = get_option('eme_registration_recorded_ok_html' );

      // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
      $result = eme_replace_placeholders($ok_format, $event, "html", 0);
      $result = eme_replace_booking_placeholders($result, $event, $booking);
      if (is_admin()) {
         $action="approveRegistration";
      } else {
         $action="";
      }
      if ($send_mail) eme_email_rsvp_booking($booking,$action);
   }

   $res = array(0=>$result,1=>$booking_id);
   return $res;
}

function eme_get_booking($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT * FROM $bookings_table WHERE booking_id = '$booking_id';" ;
   $result = $wpdb->get_row($sql, ARRAY_A);
   // for older bookings, the booking_price field might be empty
   if ($result['booking_price']==="")
      $result['booking_price'] = eme_get_event_price($result['event_id']);
   return $result;
}

function eme_get_event_price($event_id) {
   global $wpdb; 
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price FROM $events_table WHERE event_id =%d",$event_id);
   $result = $wpdb->get_var($sql);
   return $result;
   }

function eme_get_bookings_by_person_id($person_id,$future,$approval_status=0) {
   global $wpdb; 
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   if ($future) {
      $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
      $this_time = $eme_date_obj->format('Y-m-d H:i:00');
      if ($approval_status==1) $extra_condition="bookings.approved=1 AND ";
      elseif ($approval_status==2) $extra_condition="bookings.approved=0 AND ";
      else $extra_condition="";
	   $sql= $wpdb->prepare("select bookings.* from $bookings_table as bookings,$events_table as events where $extra_condition person_id = %d AND bookings.event_id=events.event_id AND CONCAT(events.event_start_date,' ',events.event_start_time)>'$this_time'",$person_id);
   } else {
	   $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE person_id = %d",$person_id);
   }
   $result = $wpdb->get_results($sql, ARRAY_A);
   return $result;
}

function eme_get_booking_by_person_event_id($person_id,$event_id) {
   return eme_get_booking_ids_by_person_event_id($person_id,$event_id);
}
function eme_get_booking_ids_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_booking_ids_by_wp_id($wp_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_booked_seats_by_wp_event_id($wp_id,$event_id) {
   global $wpdb;
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats_by_wp_event_id($wp_id,$event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats_by_wp_event_id($wp_id,$event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT booking_seats_mp FROM $bookings_table WHERE event_id = $event_id"; 
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_booked_seats_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats_by_person_event_id($person_id,$event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats_by_person_event_id($person_id,$event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT booking_seats_mp FROM $bookings_table WHERE event_id = $event_id"; 
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_event_id_by_booking_id($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE booking_id = %d",$booking_id);
   $event_id = $wpdb->get_var($sql);
   return $event_id;
}

function eme_get_event_by_booking_id($booking_id) {
   $event_id = eme_get_event_id_by_booking_id($booking_id);
   if ($event_id)
      $event = eme_get_event($event_id);
   else
      $event = eme_new_event();
   return $event;
}

function eme_get_event_ids_by_booker_id($person_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE person_id = %d",$person_id);
   return $wpdb->get_col($sql);
}

function eme_record_booking($event, $person_id, $seats, $seats_mp, $comment, $lang) {
   global $wpdb, $plugin_page;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $person_id = intval($person_id);
   $seats = intval($seats);
   // sanitize not needed: wpdb->insert does it already
   //$comment = eme_sanitize_request($comment);
   $booking['event_id']=$event['event_id'];
   $booking['person_id']=$person_id;
   $booking['wp_id']=get_current_user_id();
   $booking['booking_seats']=$seats;
   $booking['booking_seats_mp']=eme_convert_array2multi($seats_mp);
   $booking['booking_price']=$event['price'];
   $booking['booking_comment']=$comment;
   $booking['lang']=$lang;
   $booking['creation_date']=current_time('mysql', false);
   $booking['modif_date']=current_time('mysql', false);
   $booking['creation_date_gmt']=current_time('mysql', true);
   $booking['modif_date_gmt']=current_time('mysql', true);
   if (!is_admin() && $event['registration_requires_approval']) {
      // if we're adding a booking via the frontend, check for approval needed
      $booking['booking_approved']=0;
   } elseif (is_admin() && $event['registration_requires_approval'] && $plugin_page=='eme-registration-approval') {
      // if we're adding a booking via the backend, check the page we came from to check for approval too
      $booking['booking_approved']=0;
   } else {
      $booking['booking_approved']=1;
   }

   if ($wpdb->insert($bookings_table,$booking)) {
	   $booking_id = $wpdb->insert_id;
	   $booking['booking_id'] = $booking_id;
	   eme_record_answers($booking_id);
	   // now that everything is (or should be) correctly entered in the db, execute possible actions for the new booking
	   if (has_action('eme_insert_rsvp_action')) do_action('eme_insert_rsvp_action',$booking);
	   return $booking_id;
   } else {
	   return false;
   }
}

function eme_record_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $fields_seen=array();

   $event_id=eme_get_event_id_by_booking_id($booking_id);
   // first do the multibooking answers if any
   if (isset($_POST['bookings'][$event_id])) {
	   foreach($_POST['bookings'][$event_id] as $key =>$value) {
		   if (preg_match('/^FIELD(\d+)$/', $key, $matches)) { 
			   $field_id = intval($matches[1]);
			   $fields_seen[]=$field_id;
			   $formfield = eme_get_formfield_byid($field_id);
			   if ($formfield) {
				   // for multivalue fields like checkbox, the value is in fact an array
				   // to store it, we make it a simple "multi" string using eme_convert_array2multi, so later on when we need to parse the values 
				   // (when editing a booking), we can re-convert it to an array with eme_convert_multi2array (see eme_formfields.php)
				   if (is_array($value)) $value=eme_convert_array2multi($value);
				   $sql = $wpdb->prepare("INSERT INTO $answers_table (booking_id,field_name,answer) VALUES (%d,%s,%s)",$booking_id,$formfield['field_name'],stripslashes($value));
				   $wpdb->query($sql);
			   }
		   }
	   }
   }

   foreach($_POST as $key =>$value) {
      if (preg_match('/^FIELD(\d+)$/', $key, $matches)) { 
         $field_id = intval($matches[1]);
	 // the value was already stored for a multibooking, so don't do it again
	 if (in_array($field_id,$fields_seen))
		continue;
         $formfield = eme_get_formfield_byid($field_id);
	 if ($formfield) {
		 // for multivalue fields like checkbox, the value is in fact an array
		 // to store it, we make it a simple "multi" string using eme_convert_array2multi, so later on when we need to parse the values 
		 // (when editing a booking), we can re-convert it to an array with eme_convert_multi2array (see eme_formfields.php)
		 if (is_array($value)) $value=eme_convert_array2multi($value);
		 $sql = $wpdb->prepare("INSERT INTO $answers_table (booking_id,field_name,answer) VALUES (%d,%s,%s)",$booking_id,$formfield['field_name'],stripslashes($value));
		 $wpdb->query($sql);
	 }
      }
   }
}

function eme_get_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = $wpdb->prepare("SELECT * FROM $answers_table WHERE booking_id=%d",$booking_id);
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id=%d",$booking_id);
   $wpdb->query($sql);
}

function eme_convert_answer2tag($answer) {
   $formfield=eme_get_formfield_byname($answer['field_name']);
   $field_info=$formfield['field_info'];
   $field_tags=$formfield['field_tags'];

   if (!empty($field_tags) && eme_is_multifield($formfield['field_type'])) {
      $answers = eme_convert_multi2array($answer['answer']);
      $values = eme_convert_multi2array($field_info);
      $tags = eme_convert_multi2array($field_tags);
      $my_arr = array();
      foreach ($answers as $ans) {
         foreach ($values as $key=>$val) {
            if ($val==$ans) {
               $my_arr[]=$tags[$key];
            }
         }
      }
      return eme_convert_array2multi($my_arr);
   } else {
      return $answer['answer'];
   }
} 

function eme_get_answercolumns($booking_ids) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = "SELECT DISTINCT field_name FROM $answers_table WHERE booking_id IN (".join(",",$booking_ids).")";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_all_bookings_for_event_id($event_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id IN (SELECT booking_id from $bookings_table WHERE event_id = %d)",$event_id);
   $wpdb->query($sql);
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE event_id = %d",$event_id);
   $wpdb->query($sql);
   return 1;
}

function eme_delete_all_bookings_for_person_id($person_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id IN (SELECT booking_id from $bookings_table WHERE person_id = %d)",$person_id);
   $wpdb->query($sql);
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE person_id = %d",$person_id);
   $wpdb->query($sql);
   return 1;
}

function eme_transfer_all_bookings($person_id,$to_person_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $where = array();
   $fields = array();
   $where['person_id'] = $person_id;
   $fields['person_id'] = $to_person_id;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else return true;
}

function eme_move_booking_event($booking_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("UPDATE $bookings_table SET event_id = %d WHERE booking_id = %d",$event_id,$booking_id);
   return $wpdb->query($sql);
}

function eme_delete_booking($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE booking_id = %d",$booking_id);
   return $wpdb->query($sql);
}

function eme_update_booking_payed($booking_id,$booking_payed,$approve_pending=0) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   
   $where = array();
   $fields = array();
   $where['booking_id'] = intval($booking_id);
   $fields['booking_payed'] = intval($booking_payed) ;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($booking_payed==1 && $approve_pending == 1)
      $fields['booking_approved'] = 1;
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;
   if ($res && $approve_pending == 1 && $booking_payed==1) {
      $booking = eme_get_booking ($booking_id);
      eme_email_rsvp_booking($booking,"approveRegistration");
   }
   return $res;
   
}

function eme_approve_booking($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 

   $where = array();
   $fields = array();
   $where['booking_id'] = $booking_id;
   $fields['booking_approved'] = 1;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else
      return true;
   //$sql = "UPDATE $bookings_table SET booking_approved='1' WHERE booking_id = $booking_id";
   //$wpdb->query($sql);
   //return __('Booking approved', 'eme');
}

function eme_update_booking($booking_id,$event_id,$seats,$booking_price,$comment="") {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $where = array();
   $fields = array();
   $where['booking_id'] =$booking_id;

   # if it is a multi-price event, the total number of seats is the sum of the other ones
   if (eme_is_multi($booking_price)) {
      $fields['booking_seats']=0;
      # make sure the correct amount of seats is defined for multiprice
      $booking_prices_mp=eme_convert_multi2array($booking_price);
      $booking_seats_mp=eme_convert_multi2array($seats);
      foreach ($booking_prices_mp as $key=>$value) {
         if (!isset($booking_seats_mp[$key]))
            $booking_seats_mp[$key] = 0;
         $fields['booking_seats'] += intval($booking_seats_mp[$key]);
      }
      $fields['booking_seats_mp'] = eme_convert_array2multi($booking_seats_mp);
   } else {
      $fields['booking_seats'] = intval($seats);
   }
   $fields['booking_comment']=$comment;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;
   if ($res) {
      eme_delete_answers($booking_id);
      eme_record_answers($booking_id);
   }
   // now that everything is (or should be) correctly entered in the db, execute possible actions for the booking
   if (has_action('eme_update_rsvp_action')) {
      $booking=eme_get_booking($booking_id);
      do_action('eme_update_rsvp_action',$booking);
   }
   return $res;
}

function eme_get_available_seats($event_id) {
   $event = eme_get_event($event_id);
   if (eme_is_multi($event['event_seats']))
      return array_sum(eme_get_available_multiseats($event_id));

   if ($event['event_properties']['ignore_pending'] == 1)
      $available_seats = $event['event_seats'] - eme_get_approved_seats($event_id);
   else
      $available_seats = $event['event_seats'] - eme_get_booked_seats($event_id);
   // the number of seats left can be <0 if more than one booking happened at the same time and people fill in things slowly
   if ($available_seats<0) $available_seats=0;
   return $available_seats;
}

function eme_get_available_multiseats($event_id) {
   $event = eme_get_event($event_id);
   $multiseats = eme_convert_multi2array($event['event_seats']);
   $available_seats=array();
   if ($event['event_properties']['ignore_pending'] == 1) {
      $used_multiseats=eme_get_approved_multiseats($event_id);
   } else {
      $used_multiseats=eme_get_booked_multiseats($event_id);
   }
   foreach ($multiseats as $key=>$value) {
      if (isset($used_multiseats[$key]))
         $available_seats[$key] = $value - $used_multiseats[$key];
      else
         $available_seats[$key] = $value;
      // the number of seats left can be <0 if more than one booking happened at the same time and people fill in things slowly
      if ($available_seats[$key]<0) $available_seats[$key]=0;
   }
   return $available_seats;
}

function eme_get_booked_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_approved_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_approved_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d and booking_approved=1",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_approved_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d and booking_approved=1",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_pending_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_pending_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_pending_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_pending_bookings($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT COUNT(*) AS pending_bookings FROM $bookings_table WHERE event_id = $event_id and booking_approved=0"; 
   $sql = $wpdb->prepare("SELECT COUNT(*) AS pending_bookings FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   return $wpdb->get_var($sql);
}

function eme_are_seats_available_for($event_id, $seats) {
   $available_seats = eme_get_available_seats($event_id);
   $remaining_seats = $available_seats - $seats;
   return ($remaining_seats >= 0);
} 

function eme_are_multiseats_available_for($event_id, $multiseats) {
   $available_seats = eme_get_available_multiseats($event_id);
   foreach ($available_seats as $key=> $value) {
   	$remaining_seats = $value - $multiseats[$key];
      if ($remaining_seats<0)
         return 0;
   }
   return 1;
} 
 
function eme_bookings_compact_table($event_id) {
   $bookings =  eme_get_bookings_for($event_id);
   $destination = admin_url("edit.php"); 
   $available_seats = eme_get_available_seats($event_id);
   $approved_seats = eme_get_approved_seats($event_id);
   $pending_seats = eme_get_pending_seats($event_id);
   $booked_seats = eme_get_booked_seats($event_id);
   if (eme_is_event_multiseats($event_id)) {
	   $available_seats_ms=eme_convert_array2multi(eme_get_available_multiseats($event_id));
	   $approved_seats_ms=eme_convert_array2multi(eme_get_approved_multiseats($event_id));
	   $booked_seats_ms=eme_convert_array2multi(eme_get_booked_multiseats($event_id));
	   $pending_seats_ms=eme_convert_array2multi(eme_get_pending_multiseats($event_id));
	   if ($pending_seats>0) {
		   $booked_seats_info="$booked_seats: $booked_seats_ms ($approved_seats_ms ".__('approved','eme').", $pending_seats_ms ".__('pending','eme');
	   } else {
	      $booked_seats_info="$booked_seats: $booked_seats_ms";
	   }
	   $available_seats_info="$available_seats: $available_seats_ms";
   } else {
	   if ($pending_seats>0) {
		   $booked_seats_info="$booked_seats ($approved_seats ".__('approved','eme').", $pending_seats ".__('pending','eme');
	   } else {
		   $booked_seats_info=$booked_seats;
	   }
	   $available_seats_info=$available_seats;
   }
   $count_bookings=count($bookings);
   if ($count_bookings>0) { 
      $printable_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_printable&amp;event_id=$event_id");
      $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=$event_id");
      $table = 
      "<div class='wrap'>
            <h4>$count_bookings ".__('bookings so far','eme').":</h4>
            <table id='eme-bookings-table-$event_id' class='widefat post fixed'>
               <thead>
                  <tr>
                     <th class='manage-column column-cb check-column' scope='col'>&nbsp;</th>
                     <th class='manage-column ' scope='col'>".__('Respondent', 'eme')."</th>
                     <th scope='col'>".__('Spaces', 'eme')."</th>
                  </tr>
               </thead>
               <tfoot>
                  <tr>
                     <th scope='row' colspan='2'>".__('Booked spaces','eme').":</th><td class='booking-result' id='booked-seats'>$booked_seats_info</td></tr>
                  <tr><th scope='row' colspan='2'>".__('Available spaces','eme').":</th><td class='booking-result' id='available-seats'>$available_seats_info</td>
                  </tr>
               </tfoot>
               <tbody>" ;
      foreach ($bookings as $booking) {
         $person  = eme_get_person ($booking['person_id']);
         ($booking['booking_comment']) ? $baloon = " <img src='".EME_PLUGIN_URL."images/baloon.png' title='".__('Comment:','eme')." ".$booking['booking_comment']."' alt='comment'/>" : $baloon = "";
         if (eme_is_event_multiprice($event_id))
            $booking_info = $booking['booking_seats'].': '.$booking['booking_seats_mp'];
         else
            $booking_info = $booking['booking_seats'];
         if (eme_event_needs_approval($event_id) && !$booking['booking_approved']) {
            $booking_info.=" ".__('(pending)','eme');
         }
         $table .= 
         "<tr id='booking-".$booking['booking_id']."'> 
            <td><a id='booking-check-".$booking['booking_id']."' class='bookingdelbutton'>X</a></td>
            <td><a title=\"".eme_sanitize_html($person['email'])." - ".eme_sanitize_html($person['phone'])."\">".eme_sanitize_html($person['lastname'])."</a>$baloon</td>
            <td>$booking_info</td>
          </tr>";
      }
    
      $table .=  "</tbody>
         </table>
         </div>
         <br class='clear'/>
         <div id='major-publishing-actions'>
         <div id='publishing-action'> 
            <a id='printable'  target='' href='$printable_address'>".__('Printable view','eme')."</a>
            <br class='clear'/>
         </div>
         <div id='publishing-action-csv'> 
            <a id='printable'  target='' href='$csv_address'>".__('CSV export','eme')."</a>
            <br class='clear'/>
         </div>
         <br class='clear'/>
         </div> ";
   } else {
      $table = "<p><em>".__('No responses yet!','eme')."</em></p>";
   } 
   echo $table;
}

function eme_get_bookingids_for($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE event_id=%d",$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_bookings_for($event_ids,$pending_approved=0,$only_unpayed=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   
   $bookings = array();
   if (!$event_ids)
      return $bookings;
   
   if (is_array($event_ids)) {
      $where="event_id IN (".join(",",$event_ids).")";
   } else {
      $where="event_id = $event_ids";
   }
   $sql = "SELECT * FROM $bookings_table WHERE $where";
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpayed) {
      $sql .= " AND booking_payed=0";
   }
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_bookings($booking_ids) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   
   $bookings = array();
   if (!$booking_ids)
      return $bookings;
   
   if (is_array($booking_ids)) {
      $where="booking_id IN (".join(",",$booking_ids).")";
   } else {
      $where="booking_id = $booking_ids";
   }
   $sql = "SELECT * FROM $bookings_table WHERE $where";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_wp_ids_for($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT wp_id FROM $bookings_table WHERE event_id = %s AND wp_id != 0",$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_attendees_for($event_id,$pending_approved=0,$only_unpayed=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT person_id FROM $bookings_table WHERE event_id = %s",$event_id);
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpayed) {
      $sql .= " AND booking_payed=0";
   }

   $person_ids = $wpdb->get_col($sql);
   if ($person_ids) {
      $attendees = eme_get_persons($person_ids);
   } else {
      $attendees= array();
   }
   return $attendees;
}

function eme_get_attendees_list_for($event,$template_id=0,$template_id_header=0,$template_id_footer=0) {
   $ignore_pending=get_option('eme_attendees_list_ignore_pending');
   $attendees = eme_get_attendees_for($event['event_id'],$ignore_pending);
   $format=get_option('eme_attendees_list_format');
   $eme_format_header=DEFAULT_BOOKINGS_LIST_HEADER_FORMAT;
   $eme_format_footer=DEFAULT_BOOKINGS_LIST_FOOTER_FORMAT;

   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   
   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   if ($template_id_header) {
      $format_header = eme_get_template_format($template_id_header);
      $eme_format_header=eme_replace_placeholders($format_header, $event);
   }
   if ($template_id_footer) {
      $format_footer = eme_get_template_format($template_id_footer);
      $eme_format_footer=eme_replace_placeholders($format_footer, $event);
   }

   if ($attendees) {
      $res=$eme_format_header;
      // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_attendees_placeholders finish and that will do it
      $format = eme_replace_placeholders($format, $event, "html", 0);
      foreach ($attendees as $attendee) {
         $res.=eme_replace_attendees_placeholders($format,$event,$attendee);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','eme')."</p>";
   }
   return $res;
}

function eme_get_bookings_list_for_event($event,$template_id=0,$template_id_header=0,$template_id_footer=0) {
   $ignore_pending=get_option('eme_bookings_list_ignore_pending');
   $bookings=eme_get_bookings_for($event['event_id'],$ignore_pending);
   $format=get_option('eme_bookings_list_format');
   $eme_format_header=get_option('eme_bookings_list_header_format');
   $eme_format_footer=get_option('eme_bookings_list_footer_format');

   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   
   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   if ($template_id_header) {
      $format_header = eme_get_template_format($template_id_header);
      $eme_format_header=eme_replace_placeholders($format_header, $event);
   }
   if ($template_id_footer) {
      $format_footer = eme_get_template_format($template_id_footer);
      $eme_format_footer=eme_replace_placeholders($format_footer, $event);
   }

   if ($bookings) {
      $res=$eme_format_header;
      // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
      $format = eme_replace_placeholders($format, $event, "html", 0);
      foreach ($bookings as $booking) {
         $res.= eme_replace_booking_placeholders($format,$event,$booking);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','eme')."</p>";
   }
   return $res;
}

function eme_get_bookings_list_for_person($person,$future=0,$template="",$template_id=0,$template_id_header=0,$template_id_footer=0,$approval_status=0) {
   $bookings=eme_get_bookings_by_person_id($person['person_id'], $future,$approval_status);

   if ($template) {
      $format=$template;
      $eme_format_header="";
      $eme_format_footer="";
   } else {
      $format=get_option('eme_bookings_list_format');
      $eme_format_header=get_option('eme_bookings_list_header_format');
      $eme_format_footer=get_option('eme_bookings_list_footer_format');
   }

   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   // but for a person, no event info in header/footer either, so no replacement at all
   if ($template_id_header) {
      $eme_format_header = eme_get_template_format($template_id_header);
   }
   if ($template_id_footer) {
      $eme_format_footer = eme_get_template_format($template_id_footer);
   }

   if ($bookings) {
      $res=$eme_format_header;
      foreach ($bookings as $booking) {
         $event = eme_get_event($booking['event_id']);
      	// don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
      	$tmp_format = eme_replace_placeholders($format, $event, "html", 0);
         $res.= eme_replace_booking_placeholders($tmp_format,$event,$booking);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__("No bookings found.",'eme')."</p>";
   }
   return $res;
}

function eme_replace_booking_placeholders($format, $event, $booking, $is_multibooking=0, $target="html",$lang='') {
   global $eme_timezone;
   $deprecated=get_option('eme_deprecated');

   preg_match_all("/#(ESC)?_?[A-Za-z0-9_]+(\{[A-Za-z0-9_]+\})?/", $format, $placeholders);
   $person  = eme_get_person ($booking['person_id']);
   $current_userid=get_current_user_id();
   $answers = eme_get_answers($booking['booking_id']);
   $payment_id = eme_get_booking_payment_id($booking['booking_id']);
   $payment = eme_get_payment($payment_id);
   $booking_ids=array();
   $bookings=array();
   if ($payment_id) {
      $booking_ids = eme_get_payment_booking_ids($payment_id);
      $bookings = eme_get_bookings($booking_ids);
   }

   usort($placeholders[0],'sort_stringlenth');
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      $need_escape=0;
      $orig_result = $result;
      if (strstr($result,'#ESC')) {
         $result = str_replace("#ESC","#",$result);
         $need_escape=1;
      }
      if (preg_match('/#_RESPID/', $result)) {
         $replacement = $person['person_id'];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_RESP(NAME|LASTNAME|FIRSTNAME|ZIP|CITY|STATE|COUNTRY|ADDRESS1|ADDRESS2|PHONE|EMAIL)/', $result)) {
         $field = preg_replace("/#_RESP/","",$result);
         $field = strtolower($field);
         if ($field=="name") $field="lastname";
         $replacement = $person[$field];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_RESPNICKNAME$/', $result)) {
         if ($person['wp_id']>0) {
            $user = get_userdata( $person['wp_id']);
            if ($user)
               $replacement=eme_sanitize_html($user->user_nicename);
         }
      } elseif (preg_match('/#_RESPDISPNAME$/', $result)) {
         if ($person['wp_id']>0) {
            $user = get_userdata( $person['wp_id']);
            if ($user)
               $replacement=eme_sanitize_html($user->display_name);
         }
      } elseif (preg_match('/#_(RESPCOMMENT|COMMENT)/', $result)) {
         $replacement = $booking['booking_comment'];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (($deprecated && preg_match('/#_RESPSPACES(\d+)/', $result, $matches)) ||
                preg_match('/#_RESPSPACES\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1])-1;
         if (eme_is_multi($booking['booking_price'])) {
             $seats=eme_convert_multi2array($booking['booking_seats_mp']);
             if (array_key_exists($field_id,$seats))
                $replacement = $seats[$field_id];
         }
      } elseif (preg_match('/#_TOTALPRICE$/', $result)) {
         $price = eme_get_total_booking_price($event,$booking);
         $replacement = sprintf("%01.2f",$price);
      } elseif (preg_match('/#_BOOKINGPRICEPERSEAT$/', $result)) {
         $price = eme_get_seat_booking_price($event,$booking);
         $replacement = sprintf("%01.2f",$price);
      } elseif (preg_match('/#_BOOKINGPRICEPERSEAT\{(\d+)\}/', $result, $matches)) {
         // total price to pay per price if multiprice
         $total_prices=eme_get_seat_booking_multiprice($event,$booking);
         $field_id = intval($matches[1])-1;
         if (array_key_exists($field_id,$total_prices)) {
            $price = $total_prices[$field_id];
            $replacement = sprintf("%01.2f",$price);
         }
       } elseif (preg_match('/#_TOTALPRICE\{(\d+)\}/', $result, $matches)) {
         // total price to pay per price if multiprice
         $total_prices=eme_get_total_booking_multiprice($event,$booking);
         $field_id = intval($matches[1])-1;
         if (array_key_exists($field_id,$total_prices)) {
            $price = $total_prices[$field_id];
            $replacement = sprintf("%01.2f",$price);
         }
       } elseif ($deprecated && preg_match('/#_TOTALPRICE(\d+)/', $result, $matches)) {
         // total price to pay per price if multiprice
         $total_prices=eme_get_total_booking_multiprice($event,$booking);
         $field_id = intval($matches[1])-1;
         if (array_key_exists($field_id,$total_prices)) {
            $price = $total_prices[$field_id];
            $replacement = sprintf("%01.2f",$price);
         }
      } elseif (preg_match('/#_CHARGE\{(.+)\}$/', $result, $matches)) {
         $price = eme_get_total_booking_price($event,$booking);
         $replacement = eme_payment_provider_extra_charge($price,$matches[1]);
      } elseif (preg_match('/#_RESPSPACES$/', $result)) {
         $replacement = eme_get_multitotal($booking['booking_seats']);
      } elseif (preg_match('/#_BOOKINGCREATIONDATE/', $result)) {
         $replacement = eme_localised_date($booking['creation_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGMODIFDATE/', $result)) {
         $replacement = eme_localised_date($booking['modif_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGCREATIONTIME/', $result)) {
         $replacement = eme_localised_time($booking['creation_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGMODIFTIME/', $result)) {
         $replacement = eme_localised_time($booking['modif_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGID/', $result)) {
         $replacement = $booking['booking_id'];
      } elseif (preg_match('/#_TRANSFER_NBR_BE97/', $result)) {
         $replacement = $booking['transfer_nbr_be97'];
      } elseif (preg_match('/#_PAYMENT_URL/', $result)) {
         if ($payment_id && eme_event_can_pay_online($event))
            $replacement = eme_payment_url($payment_id);
      } elseif (preg_match('/#_CANCEL_LINK$/', $result)) {
         $url = eme_cancel_url($payment['random_id']);
         $replacement="<a href='$url'>".__('Cancel booking','eme')."</a>";
      } elseif (preg_match('/#_CANCEL_URL$/', $result)) {
         $replacement = eme_cancel_url($payment['random_id']);
      } elseif (preg_match('/#_CANCEL_CODE$/', $result)) {
         $replacement = $payment['random_id'];
      } elseif (preg_match('/#_FIELDS/', $result)) {
         $field_replace = "";
         foreach ($answers as $answer) {
            $tmp_answer=eme_convert_answer2tag($answer);
            $field_replace.=$answer['field_name'].": $tmp_answer\n";
         }
         $replacement = eme_trans_sanitize_html($field_replace,$lang);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_PAYED/', $result)) {
         $replacement = ($booking['booking_payed'])? __('Yes') : __('No');
      } elseif (($deprecated && preg_match('/#_FIELDNAME(\d+)/', $result, $matches)) ||
                preg_match('/#_FIELDNAME\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $replacement = eme_trans_sanitize_html($formfield['field_name'],$lang);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (($deprecated && preg_match('/#_FIELD(\d+)/', $result, $matches)) ||
                preg_match('/#_FIELD\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $field_replace = "";
         foreach ($answers as $answer) {
            if ($answer['field_name'] == $formfield['field_name']) {
               $tmp_answer=eme_convert_answer2tag($answer);
               $field_replace=$tmp_answer;
            }
         }
         $replacement = eme_trans_sanitize_html($field_replace,$lang);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_FIELDVALUE\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         foreach ($answers as $answer) {
            if ($answer['field_name'] == $formfield['field_name']) {
               if (is_array($answer['answer']))
                  $tmp_answer=eme_convert_array2multi($answer['answer']);
               else
                  $tmp_answer=$answer['answer'];
               $field_replace=$tmp_answer;
            }
         }
         $replacement = eme_trans_sanitize_html($field_replace,$lang);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_MULTIBOOKING_SEATS$/', $result)) {
         if ($is_multibooking) {
            // returns the total of all seats for all bookings in the payment id related to this booking
            $replacement = eme_bookings_total_booking_seats($bookings);
         }
      } elseif (preg_match('/#_MULTIBOOKING_TOTALPRICE$/', $result)) {
         if ($is_multibooking) {
            // returns the price for all bookings in the payment id related to this booking
            $price = eme_bookings_total_booking_price($bookings);
            $replacement = sprintf("%01.2f",$price);
         }
      } elseif (preg_match('/#_MULTIBOOKING_DETAILS_TEMPLATE\{(\d+)\}$/', $result, $matches)) {
         $template_id = intval($matches[1]);
         $template=eme_get_template_format($template_id);
         $res="";
         if ($template && $is_multibooking) {
            // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
            foreach ($bookings as $tmp_booking) {
               $tmp_event = eme_get_event_by_booking_id($tmp_booking['booking_id']);
               $tmp_res = eme_replace_placeholders($template, $tmp_event, "text", 0);
               $res .= eme_replace_booking_placeholders($tmp_res,$tmp_event,$tmp_booking,$is_multibooking,"text")."\n";
            }
         }
         $replacement = $res;
      } elseif (preg_match('/#_IS_MULTIBOOKING/', $result)) {
         $replacement=$is_multibooking;
       } else {
         $found = 0;
      }

      if ($found) {
         if ($need_escape)
            $replacement = eme_sanitize_request(eme_sanitize_html(preg_replace('/\n|\r/','',$replacement)));
         $format = str_replace($orig_result, $replacement ,$format );
      }
   }

   // now, replace any language tags found in the format itself
   $format = eme_translate($format,$lang);

   return do_shortcode($format);   
}

function eme_replace_attendees_placeholders($format, $event, $attendee, $target="html", $lang='') {
   preg_match_all("/#_?[A-Za-z0-9_]+(\{.*?\})?(\{.*?\})?/", $format, $placeholders);

   usort($placeholders[0],'sort_stringlenth');
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      $orig_result = $result;
      if (preg_match('/#_(ATTEND)?ID/', $result)) {
         $replacement = $attendee['person_id'];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_(ATTEND)?(NAME|LASTNAME|FIRSTNAME|ZIP|CITY|STATE|COUNTRY|ADDRESS1|ADDRESS2|PHONE|EMAIL)/', $result)) {
         $field = preg_replace("/#_ATTEND|#_/","",$result);
         $field = strtolower($field);
         if ($field=="name") $field="lastname";
         $replacement = $attendee[$field];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 

      } elseif (preg_match('/#_ATTENDSPACES$/', $result)) {
         $replacement = eme_get_booked_seats_by_person_event_id($attendee['person_id'],$event['event_id']);
      } elseif (preg_match('/#_ATTENDSPACES\{(\d+)\}$/', $result, $matches)) {
         $field_id = intval($matches[1])-1;
         $replacement = 0;
         if (eme_is_multi($event['event_seats'])) {
	    $seats = eme_get_booked_multiseats_by_person_event_id($attendee['person_id'],$event['event_id']);
            if (array_key_exists($field_id,$seats))
               $replacement = $seats[$field_id];
         }
      } elseif (preg_match('/#_ATTENDNICKNAME$/', $result)) {
         if ($attendee['wp_id']>0) {
            $user = get_userdata( $attendee['wp_id']);
            if ($user)
               $replacement=eme_sanitize_html($user->user_nicename);
         }
      } elseif (preg_match('/#_ATTENDDISPNAME$/', $result)) {
         if ($attendee['wp_id']>0) {
            $user = get_userdata( $attendee['wp_id']);
            if ($user)
               $replacement=eme_sanitize_html($user->display_name);
         }
      } else {
         $found = 0;
      }
      if ($found)
         $format = str_replace($orig_result, $replacement ,$format );
   }

   // now, replace any language tags found in the format itself
   $format = eme_translate($format,$lang);

   return do_shortcode($format);   
}

function eme_email_rsvp_booking($booking,$action, $is_multibooking=0) {
   // first check if a mail should be send at all
   $mailing_is_active = get_option('eme_rsvp_mail_notify_is_active');
   if (!$mailing_is_active) {
      return;
   }

   $person = eme_get_person ($booking['person_id']);
   $event = eme_get_event($booking['event_id']);
   $contact = eme_get_contact ($event);
   $contact_email = $contact->user_email;
   $contact_name = $contact->display_name;
   $mail_text_html=get_option('eme_rsvp_send_html')?"html":"text";
   
   $booker_body_vars=array('confirmed_body','updated_body','pending_body','denied_body','cancelled_body');
   $booker_subject_vars=array('confirmed_subject','updated_subject','pending_subject','denied_subject','cancelled_subject');
   $booker_vars=array_merge($booker_body_vars,$booker_subject_vars);
   $contact_body_vars=array('contact_body','contact_cancelled_body','contact_pending_body');
   $contact_subject_vars=array('contact_subject','contact_cancelled_subject','contact_pending_subject');
   $contact_vars=array_merge($contact_body_vars,$contact_subject_vars);

   // first get the initial values
   $confirmed_subject = get_option('eme_respondent_email_subject' );
   if (!empty($event['event_respondent_email_body']))
      $confirmed_body = $event['event_respondent_email_body'];
   elseif ($event['event_properties']['event_respondent_email_body_tpl']>0)
      $confirmed_body = eme_get_template_format($event['event_properties']['event_respondent_email_body_tpl']);
   else
      $confirmed_body = get_option('eme_respondent_email_body' );
   $pending_subject = get_option('eme_registration_pending_email_subject' );
   $pending_body = ( $event['event_registration_pending_email_body'] != '' ) ? $event['event_registration_pending_email_body'] : get_option('eme_registration_pending_email_body' );
   if (!empty($event['event_registration_pending_email_body']))
      $pending_body = $event['event_registration_pending_email_body'];
   elseif ($event['event_properties']['event_registration_pending_email_body_tpl']>0)
      $pending_body = eme_get_template_format($event['event_properties']['event_registration_pending_email_body_tpl']);
   else
      $pending_body = get_option('eme_registration_pending_email_body' );
   $denied_subject = get_option('eme_registration_denied_email_subject' );
   if (!empty($event['event_registration_denied_email_body']))
      $denied_body = $event['event_registration_denied_email_body'];
   elseif ($event['event_properties']['event_registration_denied_email_body_tpl']>0)
      $denied_body = eme_get_template_format($event['event_properties']['event_registration_denied_email_body_tpl']);
   else
      $denied_body = get_option('eme_registration_denied_email_body' );
   $updated_subject = get_option('eme_registration_updated_email_subject' );
   if (!empty($event['event_registration_updated_email_body']))
      $updated_body = $event['event_registration_updated_email_body'];
   elseif ($event['event_properties']['event_registration_updated_email_body_tpl']>0)
      $updated_body = eme_get_template_format($event['event_properties']['event_registration_updated_email_body_tpl']);
   else
      $updated_body = get_option('eme_registration_updated_email_body' );
   $cancelled_subject = get_option('eme_registration_cancelled_email_subject' );
   if (!empty($event['event_registration_cancelled_email_body']))
      $cancelled_body = $event['event_registration_cancelled_email_body'];
   elseif ($event['event_properties']['event_registration_cancelled_email_body_tpl']>0)
      $cancelled_body = eme_get_template_format($event['event_properties']['event_registration_cancelled_email_body_tpl']);
   else
      $cancelled_body = get_option('eme_registration_cancelled_email_body' );

   $contact_subject = get_option('eme_contactperson_email_subject' );
   if (!empty($event['event_contactperson_email_body']))
      $contact_body = $event['event_contactperson_email_body'];
   elseif ($event['event_properties']['event_contactperson_email_body_tpl']>0)
      $contact_body = eme_get_template_format($event['event_properties']['event_contactperson_email_body_tpl']);
   else
      $contact_body = get_option('eme_contactperson_email_body' );
   $contact_cancelled_subject = get_option('eme_contactperson_cancelled_email_subject' );
   $contact_cancelled_body = get_option('eme_contactperson_cancelled_email_body' );
   $contact_pending_subject = get_option('eme_contactperson_pending_email_subject' );
   $contact_pending_body = get_option('eme_contactperson_pending_email_body' );

   // replace needed placeholders
   foreach ($contact_subject_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, "text",0);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, "text");
   }
   foreach ($contact_body_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, $mail_text_html,0);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, $mail_text_html);
   }
   foreach ($booker_subject_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, "text",0,$booking['lang']);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, "text",$booking['lang']);
   }
   foreach ($booker_body_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, $mail_text_html,0,$booking['lang']);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, $mail_text_html,$booking['lang']);
   }

   // possible translations are handled last 
   foreach ($contact_vars as $var) {
      $$var=eme_translate($$var);
   }
   foreach ($booker_vars as $var) {
      $$var=eme_translate($$var,$booking['lang']);
   }

   // possible mail body filter: eme_rsvp_email_body_text_filter or eme_rsvp_email_body_html_filter
   $filtername='eme_rsvp_email_body_'.$mail_text_html.'_filter';
   if (has_filter($filtername)) {
      foreach ($contact_body_vars as $var) {
         $$var=apply_filters($filtername,$$var);
      }
      foreach ($booker_body_vars as $var) {
         $$var=apply_filters($filtername,$$var);
      }
   }

   // and now send the wanted mails
   $person_name=$person['lastname'].' '.$person['firstname'];
   if ($action == 'approveRegistration') {
      eme_send_mail($confirmed_subject,$confirmed_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'denyRegistration') {
      eme_send_mail($denied_subject,$denied_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'updateRegistration') {
      eme_send_mail($updated_subject,$updated_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'cancelRegistration') {
      eme_send_mail($cancelled_subject,$cancelled_body, $person['email'], $person_name, $contact_email, $contact_name);
      eme_send_mail($contact_cancelled_subject, $contact_cancelled_body, $contact_email, $contact_name, $contact_email, $contact_name);
   } elseif (empty($action)) {
      // send different mails depending on approval or not
      if ($event['registration_requires_approval']) {
         eme_send_mail($pending_subject,$pending_body, $person['email'], $person_name, $contact_email, $contact_name);
         eme_send_mail($contact_pending_subject, $contact_pending_body, $contact_email, $contact_name, $contact_email, $contact_name);
      } else {
         eme_send_mail($confirmed_subject,$confirmed_body, $person['email'], $person_name, $contact_email, $contact_name);
         eme_send_mail($contact_subject, $contact_body, $contact_email,$contact_name, $contact_email, $contact_name);
      }
   }
} 

function eme_registration_approval_page() {
   eme_registration_seats_page(1);
}

function eme_registration_seats_page($pending=0) {
   global $wpdb,$plugin_page,$eme_timezone;

   // do the actions if required
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "editRegistration" && isset($_GET['booking_id'])) {
      $booking_id = intval($_GET['booking_id']);
      $booking = eme_get_booking($booking_id);
      $event_id = $booking['event_id'];
      $event = eme_get_event($event_id);
      // we need to set the action url, otherwise the GET parameters stay and we will fall in this if-statement all over again
      $action_url = admin_url("admin.php?page=$plugin_page");
      $ret_string = "<form id='eme-rsvp-form' name='booking-form' method='post' action='$action_url'>";
      $ret_string.= __('Send mails for changed registration?','eme') . eme_ui_select_binary(1,"send_mail");
      $all_events = eme_get_events("extra_conditions=".urlencode("event_rsvp=1 AND event_id!=$event_id"));
      if (count($all_events)>0) {
         $ret_string.= "<br />".__('Move booking to event','eme');
         $ret_string.= " <select name='event_id'>";
         $ret_string.=  "<option value='0' ></option>";
         foreach ( $all_events as $this_event ) {
            if ($this_event ['event_rsvp']) {
               $option_text=$this_event['event_name']." (".eme_localised_date($this_event['event_start_date']." ".$this_event['event_start_time']." ".$eme_timezone).")";
               $ret_string.=  "<option value='".$this_event['event_id']."' >".$option_text."</option>";
            }
         }
         $ret_string .= "</select>";
      }
      $ret_string.= eme_replace_formfields_placeholders ($event,$booking);
      $ret_string .= "
         <input type='hidden' name='eme_admin_action' value='updateRegistration' />
         <input type='hidden' name='booking_id' value='$booking_id' />
         </form>";
      print $ret_string;
      return;
   } else {
      $action = isset($_POST ['eme_admin_action']) ? $_POST ['eme_admin_action'] : '';
      $send_mail = isset($_POST ['send_mail']) ? intval($_POST ['send_mail']) : 1;

      if ($action == 'newRegistration') {
         $event_id = intval($_POST['event_id']);
         $event = eme_get_event($event_id);
         $ret_string = "<form id='eme-rsvp-form' name='booking-form' method='post' action=''>";
         $ret_string.= __('Send mails for new registration?','eme') . eme_ui_select_binary(1,"send_mail");
         $ret_string.= eme_replace_formfields_placeholders ($event);
         $ret_string .= "
            <input type='hidden' name='eme_admin_action' value='addRegistration' />
            <input type='hidden' name='event_id' value='$event_id' />
            </form>";
         print $ret_string;
         return;
      } elseif ($action == 'addRegistration') {
         $event_id = intval($_POST['event_id']);
         $booking_payed = isset($_POST ['booking_payed']) ? intval($_POST ['booking_payed']) : 0;
         $event = eme_get_event($event_id);
         $booking_res = eme_book_seats($event, $send_mail);
         $result=$booking_res[0];
         $booking_id_done=$booking_res[1];
         if (!$booking_id_done) {
            print "<div id='message' class='error'><p>$result</p></div>";
         } else {
            print "<div id='message' class='updated'><p>$result</p></div>";
            eme_update_booking_payed($booking_id_done,$booking_payed);
         }
      } elseif ($action == 'updateRegistration') {
         $booking_id = intval($_POST['booking_id']);
         $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
         if ($event_id)
            eme_move_booking_event($booking_id,$event_id);
         $booking = eme_get_booking ($booking_id);

         if (isset($_POST['comment']))
            $bookerComment = eme_strip_tags($_POST['comment']);
         else
            $bookerComment = "";

         if (isset($_POST['bookedSeats']))
            $bookedSeats = intval($_POST['bookedSeats']);
         else
            $bookedSeats = 0;

         // for multiple prices, we have multiple booked Seats as well
         // the next foreach is only valid when called from the frontend
         $bookedSeats_mp = array();
         //if (eme_is_multi($event['price'])) {
         if (eme_is_multi($booking['booking_price'])) {
            // make sure the array contains the correct keys already, since
            // later on in the function eme_record_booking we do a join
            //$booking_prices_mp=eme_convert_multi2array($event['price']);
            $booking_prices_mp=eme_convert_multi2array($booking['booking_price']);
            foreach ($booking_prices_mp as $key=>$value) {
               $bookedSeats_mp[$key] = 0;
            }
            foreach($_POST as $key=>$value) {
               if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
                  $field_id = intval($matches[1])-1;
                  $bookedSeats += $value;
                  $bookedSeats_mp[$field_id]=$value;
               }
            }
            eme_update_booking($booking_id,$booking['event_id'],eme_convert_array2multi($bookedSeats_mp),$booking['booking_price'],$bookerComment);
         } else {
            eme_update_booking($booking_id,$booking['event_id'],$bookedSeats,$booking['booking_price'],$bookerComment);
         }
         eme_update_person_with_postinfo($booking['person_id']);

         // now get the changed booking and send mail if wanted
         $booking = eme_get_booking ($booking_id);
         if ($send_mail) eme_email_rsvp_booking($booking,$action);
         print "<div id='message' class='updated'><p>".__("Booking updated","eme")."</p></div>";

      } elseif ($action == 'approveRegistration' || $action == 'denyRegistration' || $action == 'updatePayedStatus') {
         $bookings = isset($_POST ['bookings']) ? $_POST ['bookings'] : array();
         $selected_bookings = isset($_POST ['selected_bookings']) ? $_POST ['selected_bookings'] : array();
         $bookings_seats = isset($_POST ['bookings_seats']) ? $_POST ['bookings_seats'] : array();
         $bookings_payed = isset($_POST ['bookings_payed']) ? $_POST ['bookings_payed'] : array();

         foreach ( $bookings as $key=>$booking_id ) {
            if (!in_array($booking_id,$selected_bookings)) {
               continue;
            }
            // make sure the seats are integers
            $booking = eme_get_booking ($booking_id);
            if ($action == 'updatePayedStatus') {
               if ($booking['booking_payed']!= intval($bookings_payed[$key]))
                  eme_update_booking_payed($booking_id,intval($bookings_payed[$key]));
            } elseif ($action == 'approveRegistration') {
               eme_approve_booking($booking_id);
               if ($booking['booking_payed']!= intval($bookings_payed[$key]))
                  eme_update_booking_payed($booking_id,intval($bookings_payed[$key]));
               if ($send_mail) eme_email_rsvp_booking($booking,$action);
            } elseif ($action == 'denyRegistration') {
               // the mail needs to be sent after the deletion, otherwise the count of free spaces is wrong
               eme_delete_booking($booking_id);
               if ($send_mail) eme_email_rsvp_booking($booking,$action);
               // delete the booking answers after the mail is sent, so the answers can still be used in the mail
               eme_delete_answers($booking_id);
            }
         }
      }
   }

   // now show the menu
   eme_registration_seats_form_table($pending);
}

function eme_registration_seats_form_table($pending=0) {
   global $plugin_page, $eme_timezone;

   $scope_names = array ();
   $scope_names['past'] = __ ( 'Past events', 'eme' );
   $scope_names['all'] = __ ( 'All events', 'eme' );
   $scope_names['future'] = __ ( 'Future events', 'eme' );

   $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
   $scope = isset($_POST['scope']) ? $_POST['scope'] : 'future';
   if (isset($_GET['search'])) {
      $scope="all";
      $search = "[person_id=".intval($_GET['search'])."]";
   }
   $all_events=eme_get_events(0,$scope);

?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h1><?php _e ('Add a registration for an event','eme'); ?></h1>
<div class="wrap">
<br />
<?php admin_show_warnings();?>
   <form id='add-booking' name='add-booking' action="" method="post">
   <input type='hidden' name='eme_admin_action' value='newRegistration' />
   <table class="widefat">
   <tbody>
            <tr><th scope='row'><?php _e('Event', 'eme'); ?>:</th><td>
   <select name="event_id">
   <?php
   foreach ( $all_events as $event ) {
      if ($event ['event_rsvp']) {
         $option_text=$event['event_name']." (".eme_localised_date($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone).")"; 
         echo "<option value='".$event['event_id']."' >".$option_text."</option>  ";
      }
   }
   ?>
   </select>
                </td>
            </tr>
   </tbody>
   </table>
   <input type="submit" class="button-primary action" value="<?php _e ( 'Register new booking','eme' )?>" />
   </form>
<br />
</div>
<div class="clear"></div>
<h1><?php 
   if ($pending) 
      _e ('Pending Approvals','eme');
   else
      _e ('Change reserved spaces or cancel registrations','eme');
   ?>
</h1>
<div class="wrap">
<br />

   <div class="tablenav">
   <div class="alignleft">
   <form id="eme-admin-regsearchform" name="eme-admin-regsearchform" action="<?php echo admin_url("admin.php?page=$plugin_page"); ?>" method="post">

   <select name="scope">
   <?php
   foreach ( $scope_names as $key => $value ) {
      $selected = "";
      if ($key == $scope)
         $selected = "selected='selected'";
      echo "<option value='$key' $selected>$value</option>  ";
   }
   ?>
   </select>

   <select name="event_id">
   <option value='0'><?php _e ( 'All events' ); ?></option>
   <?php
   $events_with_bookings=array();
   foreach ( $all_events as $event ) {
      $selected = "";
      if ($event_id && ($event['event_id'] == $event_id))
         $selected = "selected='selected'";

      if ($pending && eme_get_pending_bookings($event['event_id'])>0) {
         $events_with_bookings[]=$event['event_id'];
         echo "<option value='".$event['event_id']."' $selected>".$event['event_name']."</option>  ";
      } elseif (eme_get_approved_seats($event['event_id'])>0) {
         $events_with_bookings[]=$event['event_id'];
         echo "<option value='".$event['event_id']."' $selected>".$event['event_name']."</option>  ";
      }
   }
   ?>
   </select>

   <input class="button-secondary" type="submit" value="<?php _e ( 'Filter' )?>" />
   </form>
   </div>
   <br />
   <br />
   <form id="eme-admin-regform" name="eme-admin-regform" action="" method="post">
   <select name="eme_admin_action">
   <option value="-1" selected="selected"><?php _e ( 'Bulk Actions' ); ?></option>
<?php if ($pending) { ?>
   <option value="approveRegistration"><?php _e ( 'Approve registration','eme' ); ?></option>
<?php } ?>
   <option value="updatePayedStatus"><?php _e ( 'Update payed status','eme' ); ?></option>
   <option value="denyRegistration"><?php _e ( 'Deny registration','eme' ); ?></option>
   </select>
   <input type="submit" class="button-secondary" value="<?php _e ( 'Apply' )?>" />

   <div class="clear"><p>
   <?php _e('Send mails to attendees upon changes being made?','eme'); echo eme_ui_select_binary(1,"send_mail"); ?>
   </p></div>
<?php 
      if ($pending) {
         $booking_status=1;
         // different table id for pending bookings, so the save-state from datatables doesn't interfere with the one from non-pending
         $table_id="eme_pending_admin_bookings";
      } else {
         $booking_status=2;
         $table_id="eme_admin_bookings";
      }

      if ($event_id)
         $bookings = eme_get_bookings_for($event_id,$booking_status);
      else
         $bookings = eme_get_bookings_for($events_with_bookings,$booking_status);
      if (!empty($bookings)) {
?>
   <table class="widefat hover stripe" id="<?php print "$table_id";?>">
   <thead>
      <tr>
         <th class='manage-column column-cb check-column' scope='col'><input
            class='select-all' type="checkbox" value='1' /></th>
         <th>hidden for person id search</th>
         <th><?php _e ('ID','eme'); ?></th>
         <th><?php _e ('Name','eme'); ?></th>
         <th><?php _e ('Date and time','eme'); ?></th>
         <th><?php _e ('Booker','eme'); ?></th>
         <th><?php _e ('Booking date','eme'); ?></th>
         <th><?php _e ('Seats','eme'); ?></th>
         <th><?php _e ('Event price','eme'); ?></th>
         <th><?php _e ('Total price','eme'); ?></th>
         <th><?php _e ('Unique nbr','eme'); ?></th>
         <th><?php _e ('Paid','eme'); ?></th>
      </tr>
   </thead>
   <tbody>
     <?php

      $search_dest=admin_url("admin.php?page=eme-people");
      foreach ( $bookings as $event_booking ) {
         $person = eme_get_person ($event_booking['person_id']);
         $person_info_shown = eme_sanitize_html($person['lastname']);
         if ($person['firstname'])
            $person_info_shown .= " ".eme_sanitize_html($person['firstname']);
         $person_info_shown .= " (". eme_sanitize_html($person['email']).")";
         $search_url=add_query_arg(array('search'=>$person['person_id']),$search_dest);
         $event = eme_get_event($event_booking['event_id']);
         $payment_id = eme_get_booking_payment_id($event_booking ['booking_id']);
         $localised_start_date = eme_localised_date($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone);
         $localised_start_time = eme_localised_time($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone);
         $localised_end_date = eme_localised_date($event['event_end_date']." ".$event['event_end_time']." ".$eme_timezone);
         $localised_end_time = eme_localised_time($event['event_end_date']." ".$event['event_end_time']." ".$eme_timezone);
         $localised_booking_date = eme_localised_date($event_booking['creation_date']." ".$eme_timezone);
         $localised_booking_time = eme_localised_time($event_booking['creation_date']." ".$eme_timezone);
         $style = "";
         $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
         $today=$eme_date_obj->getDate();
         $datasort_startstring=strtotime($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone);
         $bookingtimestamp=strtotime($event_booking['creation_date']." ".$eme_timezone);
         
         if ($event['event_start_date'] < $today)
            $style = "style ='background-color: #FADDB7;'";
         ?>
      <tr <?php echo "$style"; ?>>
         <td><input type='checkbox' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='selected_bookings[]' />
             <input type='hidden' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='bookings[]' /></td>
          <td>[person_id=<?php echo $person['person_id']; ?>]</td>
         <td><a class="row-title" href="<?php echo admin_url("admin.php?page=$plugin_page&amp;eme_admin_action=editRegistration&amp;booking_id=".$event_booking ['booking_id']); ?>" title="<?php _e('Click the booking ID in order to see and/or edit the details of the booking.','eme')?>"><?php echo $event_booking ['booking_id']; ?></a>
         <td><strong>
         <a class="row-title" href="<?php echo admin_url("admin.php?page=events-manager&amp;eme_admin_action=edit_event&amp;event_id=".$event_booking ['event_id']); ?>" title="<?php _e('Click the event name in order to see and/or edit the details of the event.','eme')?>"><?php echo eme_trans_sanitize_html($event ['event_name']); ?></a>
         </strong>
         <?php
             $approved_seats = eme_get_approved_seats($event['event_id']);
             $pending_seats = eme_get_pending_seats($event['event_id']);
             $total_seats = $event ['event_seats'];
             echo "<br />".__('Approved: ','eme' ).$approved_seats.", ".__('Pending: ','eme').$pending_seats.", ".__('Max: ','eme').$total_seats;
             if ($approved_seats>0 || $pending_seats>0) {
                $printable_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_printable&amp;event_id=".$event['event_id']);
                $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=".$event['event_id']);
                echo " (<a id='booking_printable_".$event['event_id']."'  target='' href='$printable_address'>".__('Printable view','eme')."</a>)";
                echo " (<a id='booking_csv_".$event['event_id']."'  target='' href='$csv_address'>".__('CSV export','eme')."</a>)";
             }
         ?>
         </td>
         <td data-sort="<?php echo $datasort_startstring; ?>">
            <?php echo $localised_start_date; if ($localised_end_date !='' && $localised_end_date != $localised_start_date) echo " - " . $localised_end_date; ?><br />
            <?php echo "$localised_start_time - $localised_end_time"; ?>
         </td>
         <td><a href="<?php echo $search_url; ?>" title="<?php _e('Click the name of the booker in order to see and/or edit the details of the booker.','eme')?>"><?php print $person_info_shown;?></a>
         </td>
         <td data-sort="<?php echo $bookingtimestamp; ?>">
            <?php echo $localised_booking_date ." ". $localised_booking_time;?>
         </td>
         <?php if (eme_is_multi(eme_get_booking_price($event,$event_booking))) { ?>
         <td>
            <?php echo $event_booking['booking_seats_mp'] .'<br />'. __('(Multiprice)','eme');?>
         </td>
         <?php } else { ?>
         <td>
            <?php echo $event_booking['booking_seats'];?>
         </td>
         <?php } ?>
         <td>
            <?php echo eme_get_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <?php echo eme_get_total_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <span title="<?php print sprintf(__('This is based on the payment ID of the booking: %d','eme'),$payment_id);?>"><?php echo eme_sanitize_html($event_booking['transfer_nbr_be97']); ?></span>
         </td>
         <td>
            <?php echo eme_ui_select_binary($event_booking['booking_payed'],"bookings_payed[]"); ?>
         </td>
      </tr>
      <?php
      }
      ?>
   </tbody>
   </table>

<script type="text/javascript">
   jQuery(document).ready( function() {
         jQuery('#<?php print "$table_id";?>').dataTable( {
            "dom": 'Blfrtip',
            "colReorder": true,
            <?php
            // jquery datatables locale loading
            $locale_code = get_locale();
            $locale_file = EME_PLUGIN_DIR. "js/jquery-datatables/i18n/$locale_code.json";
            $locale_file_url = EME_PLUGIN_URL. "js/jquery-datatables/i18n/$locale_code.json";
            if ($locale_code != "en_US" && file_exists($locale_file)) {
            ?>
            "language": {
               "url": "<?php echo $locale_file_url; ?>"
               },
            <?php
            }
            ?> 
            "stateSave": true,
            <?php
            if (!empty($search)) {
               // If datatables state is saved, the initial search
               // is ignored and we need to use stateloadparams
               // So we give the 2 options
            ?> 
            "stateLoadParams": function (settings, data) {
               data.search.search = "<?php echo $search; ?>";
            },
            "search": {
               "search":  "<?php echo $search; ?>"
            },
            <?php
            }
            ?> 
            "pagingType": "full",
            "columnDefs": [
               { "sortable": false, "targets": 0 },
               { "visible": false, "targets": 1 }
            ],
            "buttons": [
               'csv',
               'print',
               {
                  extend: 'colvis',
                  columns: [2,3,4,5,6,7,8,9,10,11]
               }
            ]

         } );
   } );
</script>

<?php } ?>

   <div class='tablenav'>
   <div class="alignleft actions"><br class='clear' />
   </div>
   <br class='clear' />
   </div>

   </div>
   </form>
</div>
</div>
<?php
}

function eme_send_mails_page() {
   global $wpdb;

   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   $action = isset($_POST ['eme_admin_action']) ? $_POST ['eme_admin_action'] : '';
   $onchange = isset($_POST ['onchange']) ? intval($_POST ['onchange']) : 0;

   if (isset($_POST ['mail_subject']) && !empty($_POST ['mail_subject']))
      $mail_subject = stripslashes_deep($_POST ['mail_subject']);
   elseif (isset($_POST ['subject_template']) && intval($_POST ['subject_template'])>0)
      $mail_subject = eme_get_template_format(intval($_POST ['subject_template']));
   else
      $mail_subject = "";

   if (isset($_POST ['mail_message']) && !empty($_POST ['mail_message']))
      $mail_message = stripslashes_deep($_POST ['mail_message']);
   elseif (isset($_POST ['message_template']) && intval($_POST ['message_template'])>0)
      $mail_message = eme_get_template_format(intval($_POST ['message_template']));
   else
      $mail_message = "";

   if (!$onchange && $event_id>0 && $action == 'send_mail') {
      $pending_approved = isset($_POST ['pending_approved']) ? $_POST ['pending_approved'] : 0;
      $only_unpayed = isset($_POST ['only_unpayed']) ? $_POST ['only_unpayed'] : 0;
      $eme_mail_type = isset($_POST ['eme_mail_type']) ? $_POST ['eme_mail_type'] : 'attendees';
	   if (empty($mail_subject) || empty($mail_message)) {
		   print "<div id='message' class='error'><p>".__('Please enter both subject and message for the mail to be sent.','eme')."</p></div>";
	   } else {
		   $event = eme_get_event($event_id);
		   $current_userid=get_current_user_id();
		   if (current_user_can( get_option('eme_cap_send_other_mails')) ||
				   (current_user_can( get_option('eme_cap_send_mails')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid))) {  

			   $event_name = $event['event_name'];
			   $contact = eme_get_contact ($event);
			   $contact_email = $contact->user_email;
			   $contact_name = $contact->display_name;
			   $mail_text_html=get_option('eme_rsvp_send_html')?"html":"text";

			   if ($eme_mail_type == 'attendees') {
				   $attendees = eme_get_attendees_for($event_id,$pending_approved,$only_unpayed);
				   foreach ( $attendees as $attendee ) {
					   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text",0,$attendee['lang']);
					   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html,0,$attendee['lang']);
					   $tmp_subject = eme_replace_attendees_placeholders($tmp_subject, $event, $attendee, "text",0,$attendee['lang']);
					   $tmp_message = eme_replace_attendees_placeholders($tmp_message, $event, $attendee, $mail_text_html,0,$attendee['lang']);
					   $tmp_subject = eme_translate($tmp_subject,$attendee['lang']);
					   $tmp_message = eme_translate($tmp_message,$attendee['lang']);
					   $person_name=$attendee['lastname'].' '.$attendee['firstname'];
					   eme_send_mail($tmp_subject,$tmp_message, $attendee['email'], $person_name, $contact_email, $contact_name);
				   }
			   } elseif ($eme_mail_type == 'bookings') {
				   $bookings = eme_get_bookings_for($event_id,$pending_approved,$only_unpayed);
				   foreach ( $bookings as $booking ) {
					   // we use the language done in the booking for the mails, not the attendee lang in this case
					   $attendee = eme_get_person($booking['person_id']);
					   if ($attendee && is_array($attendee)) {
						   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text",0,$booking['lang']);
						   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html,0,$booking['lang']);
						   $tmp_subject = eme_replace_booking_placeholders($tmp_subject, $event, $booking, "text",0,$booking['lang']);
						   $tmp_message = eme_replace_booking_placeholders($tmp_message, $event, $booking, $mail_text_html,0,$booking['lang']);
						   $tmp_subject = eme_translate($tmp_subject,$booking['lang']);
						   $tmp_message = eme_translate($tmp_message,$booking['lang']);
						   $person_name=$attendee['lastname'].' '.$attendee['firstname'];
						   eme_send_mail($tmp_subject,$tmp_message, $attendee['email'], $person_name, $contact_email, $contact_name);
					   }
				   }
			   } elseif ($eme_mail_type == 'all_wp') {
				   $wp_users = get_users();
				   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text");
				   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html);
				   foreach ( $wp_users as $wp_user ) {
					   eme_send_mail($tmp_subject,$tmp_message, $wp_user->user_email, $wp_user->display_name, $contact_email, $contact_name);
				   }
			   } elseif ($eme_mail_type == 'all_wp_not_registered') {
				   $wp_users = get_users();
				   $attendee_wp_ids = eme_get_wp_ids_for($event_id);
				   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text");
				   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html);
				   foreach ( $wp_users as $wp_user ) {
					   if (!in_array($wp_user->ID,$attendee_wp_ids))
						   eme_send_mail($tmp_subject,$tmp_message, $wp_user->user_email, $wp_user->display_name, $contact_email, $contact_name);
				   }
			   }
			   print "<div id='message' class='updated'><p>".__('The mail has been sent.','eme')."</p></div>";
		   } else {
			   print "<div id='message' class='error'><p>".__('You do not have the permission to send mails for this event.','eme')."</p></div>";
		   }
	   }
   }

   // now show the form
   eme_send_mail_form($event_id);
}

function eme_send_mail_form($event_id=0) {
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h1><?php _e ('Send Mails to attendees or bookings for a event','eme'); ?></h1>
<?php admin_show_warnings();?>
   <div id='message' class='updated'><p>
<?php
   _e('Warning: using this functionality to send mails to attendees can result in a php timeout, so not everybody will receive the mail then. This depends on the number of attendees, the load on the server, ... . If this happens, use the CSV export link to get the list of all attendees and use mass mailing tools (like OpenOffice) for your mailing.','eme');
   $all_events=eme_get_events(0,"future");
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   $current_userid=get_current_user_id();
   $templates_array=eme_get_templates_array_by_id();
   if (is_array($templates_array) && count($templates_array)>0)
      $templates_array[0]='';
   else
      $templates_array[0]=__('No templates defined yet!','eme');
   ksort($templates_array);
?>
   </p></div>
   <form id='send_mail' name='send_mail' action="" method="post">
   <input type='hidden' name='page' value='eme-send-mails' />
   <input type='hidden' name='eme_admin_action' value='send_mail' />
   <input type='hidden' id='onchange' name='onchange' value='0' />
   <select name="event_id" onchange="document.getElementById('onchange').value='1';this.form.submit();">
   <option value='0' ><?php _e('Select the event','eme') ?></option>
   <?php
   foreach ( $all_events as $event ) {
      $option_text=$event['event_name']." (".eme_localised_date($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone).")";
      if ($event['event_rsvp'] && current_user_can( get_option('eme_cap_send_other_mails')) ||
            (current_user_can( get_option('eme_cap_send_mails')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid))) {  
         if ($event['event_id'] == $event_id) {
            echo "<option selected='selected' value='".$event['event_id']."' >".$option_text."</option>  ";
         } else {
            echo "<option value='".$event['event_id']."' >".$option_text."</option>  ";
         }
      }
   }
   ?>
   </select>
   <p>
   <?php if ($event_id>0) {?>
      <table>
      <tr>
	   <td><label><?php _e('Select the type of mail','eme'); ?></td>
      <td>
           <select name="eme_mail_type" required='required'>
           <option value=''></option>
           <option value='attendees'><?php _e('Attendee mails','eme'); ?></option>
           <option value='bookings'><?php _e('Booking mails','eme'); ?></option>
           <option value='all_wp'><?php _e('Mail to all WP users','eme'); ?></option>
           <option value='all_wp_not_registered'><?php _e('All WP users except those registered for the event','eme'); ?></option>
           </select>
      </td>
      </tr>
      <tr id="eme_pending_approved_row">
	   <td><label><?php _e('Select your target audience','eme'); ?></td>
      <td>
           <select name="pending_approved">
           <option value=0><?php _e('All registered persons','eme'); ?></option>
           <option value=2><?php _e('Exclude pending registrations','eme'); ?></option>
           <option value=1><?php _e('Only pending registrations','eme'); ?></option>
           </select></p><p>
      </td>
      </tr>
      <tr id="eme_only_unpayed_row">
      <td><?php _e('Only send mails to attendees who did not pay yet','eme'); ?>&nbsp;</td>
      <td>
           <input type="checkbox" name="only_unpayed" value="1" />
      </td>
      </tr>
      </table>
	   <div id="titlediv" class="form-field form-required"><p>
      <b><?php _e('Subject','eme'); ?></b><br />
      <?php _e('Either choose from a template: ','eme'); echo eme_ui_select(0,'subject_template',$templates_array); ?><br />
      <?php _e('Or enter your own (if anything is entered here, it takes precedence over the selected template): ','eme');?>
      <input type="text" name="mail_subject" id="mail_subject" value="" /></p>
	   </div>
	   <div class="form-field form-required"><p>
	   <b><?php _e('Message','eme'); ?></b><br />
      <?php _e('Either choose from a template: ','eme'); echo eme_ui_select(0,'message_template',$templates_array); ?><br />
      <?php _e('Or enter your own (if anything is entered here, it takes precedence over the selected template): ','eme');?>
	   <textarea name="mail_message" id="mail_message" value="" rows=10></textarea> </p>
	   </div>
	   <div>
	   <?php _e('You can use any placeholders mentioned here:','eme');
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=25'>".__('Event placeholders','eme')."</a>";
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=48'>".__('Attendees placeholders','eme')."</a> (".__('for ','eme').__('Attendee mails','eme').")";
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=45'>".__('Booking placeholders','eme')."</a> (".__('for ','eme').__('Booking mails','eme').")";
	   ?>
	   </div>
      <br />
	   <input type="submit" value="<?php _e ( 'Send Mail', 'eme' ); ?>" class="button-primary action" />
	   </form>

   <?php
	   $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=".$event['event_id']);
	   $available_seats = eme_get_available_seats($event['event_id']);
	   $total_seats = $event ['event_seats'];
	   if ($total_seats!=$available_seats)
		   echo "<br /><br /> <a id='booking_csv_".$event['event_id']."'  target='' href='$csv_address'>".__('CSV export','eme')."</a>";
   }
}

// template function
function eme_is_event_rsvpable() {
   if (eme_is_single_event_page() && isset($_REQUEST['event_id'])) {
      $event = eme_get_event(intval($_REQUEST['event_id']));
      if($event)
         return $event['event_rsvp'];
   }
   return 0;
}

function eme_event_needs_approval($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT registration_requires_approval from $events_table where event_id=%d",$event_id);
   return $wpdb->get_var( $sql );
}

// the next function returns the price for 1 booking, not taking into account the number of seats or anything
function eme_get_booking_price($event,$booking) {
   if ($booking['booking_price']!=="")
      $basic_price=$booking['booking_price'];
   else
      $basic_price=$event['price'];
   // don't convert to int or float or whatever; it can be multiprice
   return $basic_price;
}

// the next function returns the price for a specific booking, multiplied by the number of seats booked and multiprice taken into account
function eme_get_total_booking_price($event,$booking) {
   $price=0;
   $basic_price= eme_get_booking_price($event,$booking);

   if (eme_is_multi($basic_price)) {
      $prices=eme_convert_multi2array($basic_price);
      $seats=eme_convert_multi2array($booking['booking_seats_mp']);
      foreach ($prices as $key=>$val) {
         $price += $val*$seats[$key];
      }
   } else {
      $price = $basic_price*$booking['booking_seats'];
   }
   return $price;
}

function eme_bookings_total_booking_price($bookings) {
   $price=0;
   foreach ($bookings as $booking) {
      $event=eme_get_event($booking['event_id']);
      if (!$booking['booking_payed'] && is_array($event))
         $price += eme_get_total_booking_price($event,$booking);
   }
   return $price;
}

function eme_bookings_total_booking_seats($bookings) {
   $seats=0;
   foreach ($bookings as $booking) {
      $seats += $booking['booking_seats'];
   }
   return $seats;
}

function eme_get_seat_booking_price($event,$booking) {
   $price=0;
   $basic_price= eme_get_booking_price($event,$booking);

   if (eme_is_multi($basic_price)) {
      $prices=eme_convert_multi2array($basic_price);
      $seats=eme_convert_multi2array($booking['booking_seats_mp']);
      foreach ($prices as $key=>$val) {
         $price += $val*$seats[$key];
      }
      $price /= $booking['booking_seats'];
   } else {
      $price = $basic_price;
   }
   return $price;
}


function eme_get_total_booking_multiprice($event,$booking) {
   $price=array();
   $basic_price= eme_get_booking_price($event,$booking);

   if (eme_is_multi($basic_price)) {
      $prices=eme_convert_multi2array($basic_price);
      $seats=eme_convert_multi2array($booking['booking_seats_mp']);
      foreach ($prices as $key=>$val) {
         $price[] = $val*$seats[$key];
      }
   }
   return $price;
}

function eme_get_seat_booking_multiprice($event,$booking) {
   $price=array();
   $basic_price= eme_get_booking_price($event,$booking);

   if (eme_is_multi($basic_price)) {
      $price=eme_convert_multi2array($basic_price);
   }
   return $price;
}

function eme_is_event_rsvp ($event) {
   $rsvp_is_active = get_option('eme_rsvp_enabled');
   if ($rsvp_is_active && $event['event_rsvp'])
      return 1;
   else
      return 0;
}

function eme_is_event_multiprice($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price from $events_table where event_id=%d",$event_id);
   $price = $wpdb->get_var( $sql );
   return eme_is_multi($price);
}

function eme_is_multi($price) {
   if (preg_match("/\|\|/",$price))
      return 1;
   else
      return 0;
}

function eme_convert_multi2array($multistring) {
   return preg_split("/\|\|/",$multistring);
}

function eme_convert_array2multi($multiarr) {
   return join("||",$multiarr);
}

function eme_is_event_multiseats($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT event_seats from $events_table where event_id=%d",$event_id);
   $seats = $wpdb->get_var( $sql );
   return eme_is_multi($seats);
}

function eme_get_multitotal($multistring) {
   return array_sum(eme_convert_multi2array($multistring));
}

?>
