<?php
use Symfony\Component\Intl\Languages;

require_once "TurboTranslations.php";

class TurboTranslationsAjax {

  public static function init() {
    $events = [
      'getTranslationsCategories' => true,
      'getAvailablePairs' => true,
      'createTurbotranslateOrder' => true,
      'completeTurboTranslateOrder' => true,
      'getPendingPost' => false,
      'getLanguageName' => true
    ];

    foreach ($events as $action => $private) {
      add_action('wp_ajax_'.$action, [__CLASS__, $action]);
      if (!$private) {
        add_action('wp_ajax_nopriv_'.$action, [__CLASS__, $action]);
      }
    }
  }

  public static function getAvailablePairs() {

    $pairs = [];

    if( isset( $_GET['source']) && isset($_GET['post_id'] ) ){
      $id = sanitize_text_field($_GET['post_id']);
      $all_pairs = TurboTranslations::getLanguagePairs($id);
      $pairs = array_filter( $all_pairs, function( $value ) {
        $from = sanitize_text_field($_GET['source']);
        return $value->lang_from->name == $from;
      });
    }
    wp_send_json( my_array_unique($pairs), 200 );

    die();
  }

  public static function createTurbotranslateOrder() {

    // Retrieve HTTP method
    $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING);
    // Retrieve JSON payload
    $data = json_decode(file_get_contents('php://input'));
    $body = $data;
    $post_id = $data->postId;
    unset( $body->postId );
    $lang = function_exists('pll_get_post_language')
    ? pll_get_post_language( absint( $post_id ) )
    : apply_filters( 'wpml_post_language_details', NULL, $post_id );
    $lang = is_array($lang) ? $lang['language_code'] : $lang;
    $langName = Languages::getName($lang, 'en');

    $body->source_lang = new StdClass();
    $body->source_lang->id = TurboTranslations::getLanguageId( $langName );
    try{
      $response = TurboTranslations::createNewOrder( $post_id, $body );
      wp_send_json( $response );
        }
    catch( Exception $e ){
      wp_send_json( ['status' => 500, 'message' => $e->getMessage()] );
    }
    die();
  }

  public static function completeTurboTranslateOrder() {

    // Retrieve HTTP method
    $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING);
    // Retrieve JSON payload
    $data = json_decode(file_get_contents('php://input'));
    $response = Turbotranslations::completeOrder( $data->orderId );

    wp_send_json( $response );
    die();
  }

  public static function getPendingPost() {

    if( !isset( $_GET['id'] ) ) wp_send_json(['status' => false]);
    $id = sanitize_text_field($_GET['id']);
    global $wpdb;
    $sql = "SELECT post_id,translated_post_id,target_language_id,target_language_slug,price_net,tax,price_gross,currency_code,word_count,turbotranslations_order_id
    FROM {$wpdb->prefix}turbotranslations
    WHERE post_id = %d";
    $pending = $wpdb->get_results( $wpdb->prepare( $sql, $id ) );

    // if( !$pending ) wp_send_json(['status' => false]);

    if(function_exists('pll_get_post_translations')) {
      $translations = pll_get_post_translations($id);
    }
    if(class_exists( 'SitePress' )) {
        //TODO: think about replacing below with `wpml_post_duplicates` hook: https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/#hook-606357
        global $sitepress;
            $trid = $sitepress->get_element_trid($id);
            $translations_array = $sitepress->get_element_translations($trid);
            $translations = array_map(function($trans){
                return $trans->element_id;
            }, $translations_array);
    }
    $translated_ids = array_map(function($lang) {
      return $lang->translated_post_id;
     },$pending);

     $all_pairs = TurboTranslations::getLanguagePairs($id);

    foreach($translations as $slug => $translation) {
      if(!in_array(intval($translation), $translated_ids)) {
        $lang_slug = function_exists('pll_get_post_language') ? pll_get_post_language($translation) : $slug;
        $pairs = array_filter( $all_pairs, function( $value ) use ($lang_slug){
          $from = Languages::getName($lang_slug, 'en');
          return $value->lang_from->name == $from;
        });
        $lang = array_shift($pairs);
        $data[] = [
          'status'=>'finished',
          'lang' => $lang->lang_from->id];
      }
    }
    foreach ($pending as $p) {
      try {
        $status = TurboTranslations::getOrderDetails($p->turbotranslations_order_id);
    }
    catch (Exception $e) {
        wp_send_json(['status' => false, 'message' => $e->getMessage()]);
    }
    $translated = in_array($p->translated_post_id, $translations);
        $data[] = [
          'price' => [
            'gross' => [
                'currency_amount_decimal' => $p->price_gross,
                'currency_code' => $p->currency_code,
            ],
            'net' => [
                'currency_amount_decimal' => $p->price_net,
                'currency_code' => $p->currency_code,
            ],
            'tax' => $p->tax,
        ],
        'status' => $translated ? 'finished' : $status[0]->status->slug,
        'words' => $p->word_count,
        'id' => $p->turbotranslations_order_id,
        'lang' => $p->target_language_id,
        'target_lang' => Languages::getName($p->target_language_slug, 'en'),
        'translated_post_id' => $p->translated_post_id
        ];
    }
    wp_send_json($data);
    die();
  }
}

function my_array_unique($array, $keep_key_assoc = false){
    $duplicate_keys = array();
    $tmp = array();

    foreach ($array as $key => $val){
        // convert objects to arrays, in_array() does not support objects
        if (is_object($val))
            $val = (array)$val;

        if (!in_array($val, $tmp))
            $tmp[] = $val;
        else
            $duplicate_keys[] = $key;
    }

    foreach ($duplicate_keys as $key)
        unset($array[$key]);

    return $keep_key_assoc ? $array : array_values($array);
}
