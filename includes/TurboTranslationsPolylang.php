<?php

// TODO: check if admin exists. Function that assigns post to language
require_once "TurboTranslations.php";
/**
 * Turbotranslations handler for polylang
 */
class TurboTranslationsPolylang
{
    public static function createTranslatedContent($post_id, $content, $lang)
    {
        global $polylang;
        
        $langs = $polylang->model->post->get_translations($post_id);

        if (!pll_get_post($post_id, $lang)) {
            $new_post_id = TurboTranslations::createDuplicatedPost($post_id);
        } else {
            $new_post_id = pll_get_post($post_id, $lang);
        }

        $body = TurboTranslations::parseTranslatedPost($content);
        wp_update_post([
            "ID" => $new_post_id,
            'post_title' => $body['title'],
            "post_content" => $body["content"],
        ]);   
        foreach ($body["meta"] as $key => $value) {
            foreach ($value as $val) {
                update_post_meta($new_post_id, $key, $val);
            }
        }
        $langs = array_merge($langs, [
            $lang => $new_post_id,
        ]);
        pll_set_post_language($new_post_id, $lang);
        pll_save_post_translations($langs);

        return $new_post_id;
    }
}

?>
