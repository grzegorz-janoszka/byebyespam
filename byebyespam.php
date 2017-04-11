<?php

/*
 * @wordpress-plugin
 * Plugin Name: Bye bye spam!
 * Plugin URI:  http://jeszcze-nie.ma
 * Description: Zero spamu w Wordpressie
 * Version:     0.1
 * Author:      Grzegorz Janoszka
 * Author URI:  https://paleosmak.pl
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: byebyespam
 * GitHub Plugin URI: https://github.com/jeszcze-nie
 */

// remove/change some default text that bots may look for
// fields can be also accessed by filter comment_form_default_fields
function byebyespam_cfdefaults($defaults) {
  $defaults['comment_notes_before'] = '<p class="comment-notes">Twój adres nie zostanie opublikowany. Pola wymagane są oznaczone symbolem *</p>';
  $defaults['comment_notes_after'] = '';
  // dodajemy nowe pola strona i poczta OBOK url i email
  $commenter = wp_get_current_commenter();
  $defaults['fields']['poczta'] = '<p class="comment-form-poczta"><label for="poczta">Mejl <span class="required">*</span></label> <input id="poczta" name="poczta" type="text" value="'.esc_attr($commenter['comment_author_email']).'" size="30" maxlength="100" required=\'required\' /></p>';
  $defaults['fields']['strona'] = '<p class="comment-form-strona"><label for="strona">Strona internetowa</label><input id="strona" name="strona" type="text" size="30" maxlength="100" value="'.esc_attr($commenter['comment_author_url']).'" /></p>';
  // we set display:none to classes comment-form-url and comment-form-email
  $defaults['fields']['url'] = '<p class="comment-form-url"><label for="url">Zostaw to pole puste</label><input id="url" name="url" type="text" size="30" maxlength="100" /></p>';
  $defaults['fields']['email'] = '<p class="comment-form-email"><label for="email">Zostaw to pole puste</label> <input id="email" name="email" type="email" size="30" maxlength="100" /></p>';
#  $defaults['id_form']='formularz';
#  $defaults['id_submit']='wyslij';
#  $defaults['name_submit']='wyslij';
  return $defaults;
}
add_filter('comment_form_defaults','byebyespam_cfdefaults');


function byebyespam_sfilter($approved,$commentdata) {
  # jeśli istnieje argument 'url', to jest to spam
  if(isset($_POST['url']) && ($_POST['url'] !== '')) return 'spam';
  if(isset($_POST['email']) && ($_POST['email'] !== '')) return 'spam';
  # spam z reguły nie ma referera
  if(!wp_get_referer()) return 'spam';
  # Check for Chinese/Korean/cyryllic characters:
  # from http://wordpress.stackexchange.com/questions/116973/how-can-i-automatically-delete-comments-that-contain-chinese-russian-signs
  #if (preg_match('~\p{Hangul}|\p{Han}|\p{Cyrillic}~u',$commentdata['comment_content'])) return 'spam';
  return $approved;
}
add_filter('pre_comment_approved','byebyespam_sfilter',1,2);


function byebyespam_cfilter($commentdata) {
  $user = wp_get_current_user();
  if(! $user->exists()) {
    if(!isset($_POST['poczta']) || (6 > strlen($_POST['poczta'])) ||
      !is_email($_POST['poczta']) || ($commentdata['comment_author']==''))
	wp_die('<p><strong>BŁĄD</strong>: Proszę prawidłowo wypełnić wymagane pola (podpis, email).</p>','Wysyłanie komentarza nie powiodło się',array('back_link' => true ));
    $commentdata['comment_author_email'] = $_POST['poczta'];
    if(isset($_POST['strona']) && ($_POST['strona'] !== ''))
      $commentdata['comment_author_url'] = $_POST['strona'];
  }
  return $commentdata;
}
add_filter('preprocess_comment','byebyespam_cfilter');


function byebyespam_head() {
  echo '<style type="text/css">.comment-form-url { display: none; } .comment-form-email { display: none; }</style>';
}
add_action('wp_head','byebyespam_head');


function byebyespam_log($comment_id, $comment_status)
{
  if ('spam' === $comment_status) {
    // supposedely sometimes get_comment here returns null, so we check:
    if ($comment = get_comment($comment_id, ARRAY_A)) {
      $remote_addr = (empty($comment['comment_author_IP']))
         ? 'unknown' : $comment['comment_author_IP'];
      openlog("wordpress", LOG_PID, LOG_USER);
      syslog(LOG_INFO, "Spam comment {$comment_id} from ".$remote_addr);
      closelog();
    }
  }
};
add_action('comment_post','byebyespam_log',10,2);
add_action('wp_set_comment_status','byebyespam_log',10,2);


# czas życia ciasteczek do komentarzy w sekundach
# nieco ponad 3 miesiące, domyślnie był rok
add_filter('comment_cookie_lifetime',function () { return 8000000; });

