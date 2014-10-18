<?php
/*
Plugin Name: Fixed Future Posts
Description: If you are not logged in, it will not hide the current public posts. ( Only one generation, but allows the future of the article published. )
Version: 1.0.0
Plugin URI: http://www.onestead.com/fixed-future-posts/
Author: Takashi Murakami
Author URI: http://www.onestead.com/fixed-future-posts/
License: GPLv2
Text Domain: fixed-future-posts
Domain Path: /languages
*/

/**
 * Plug-in ( Fixed Future Posts ) for the WordPress.
 *
 * @package Fixed_Future_Posts
 * @version 1.0.0
 * @author Takashi Murakami <onestead@gmail.com>
 * @copyright Copyright (c) 2014 Takashi Murakami, Onestead Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GPLv2
 * @link http://www.onestead.com/fixed-future-posts/
 */
new FixedFuturePosts();
class FixedFuturePosts {
    const PLUGIN = "FixedFuturePosts";
    const META_KEY = "_fixed_future_posts";
    const PRIORITY = 10;
    const TYPEOF_POST = "WP_Post";
    const IN_POSTS = "posts";
    const IN_META = "meta";
    private function _cache_post($request) {
        if (!is_array($request)
            || !array_key_exists("comment_post_ID", $request))
            return;
        $post_id = (int)$request["comment_post_ID"];
        if (is_int($post_id)
            && $post_id > 0) {
            $post = wp_cache_get($post_id, "posts");
            if (!$post) {
                $publish = get_post_meta($post_id, FixedFuturePosts::META_KEY, true);
                if ($publish) {
                    $data = maybe_unserialize($publish);
                    if (is_array($data)
                        && array_key_exists(FixedFuturePosts::IN_POSTS, $data)) {
                        $post = $data[FixedFuturePosts::IN_POSTS];
                        wp_cache_add($post_id, $post, "posts");
                    }
                }
            }
        }
    }
    private function _cache_meta($post_id) {
        $output = wp_cache_get($post_id, "post_meta");
        if (!$output) {
            $cache = update_meta_cache("post", array($post_id));
            if (is_array($cache)
                && array_key_exists($post_id, $cache))
                $output = $cache[$post_id];
        }
        return $output || array( /**EMPTY**/ );
    }
    private function _auth() {
        return
            is_user_logged_in()
            && (current_user_can("publish_posts")
                || current_user_can("edit_post")
                || current_user_can("edit_published_posts")
                || current_user_can("edit_others_posts")
            );
    }
    function __construct() {
        add_action('init', array(&$this, "actionInit"), FixedFuturePosts::PRIORITY);
        add_action("pre_post_update", array(&$this, "actionPrePostUpdate"), FixedFuturePosts::PRIORITY, 1);
        add_action("publish_post", array(&$this, "actionPublishPost"), FixedFuturePosts::PRIORITY, 1);
        add_filter("posts_results", array(&$this, "filterPostResults"), FixedFuturePosts::PRIORITY);
        add_filter("get_post_metadata", array(&$this, "filterGetPostMetadata"), FixedFuturePosts::PRIORITY, 4);
        add_filter("posts_where", array(&$this, "filterPostsWhere"), FixedFuturePosts::PRIORITY, 2);
        add_filter("post_date_column_time", array(&$this, "filterPostDateColumnTime"), FixedFuturePosts::PRIORITY, 2);
    }
    function actionInit() {
        if ($this->_auth())
            return;
        $this->_cache_post($_REQUEST);
    }
    function actionPrePostUpdate($post_id) {
        if (!$this->_auth()
            || !is_array($_POST)
            || !array_key_exists("post_date", $_POST))
            return;
        $post = get_post($post_id);
        if (is_a($post, FixedFuturePosts::TYPEOF_POST)
            && $post->post_status === "publish") {
            $post_at = strtotime($_POST["post_date"]);
            $current = strtotime(current_time("mysql"));
            if ($post_at && $current && $post_at > 0 && $current > 0 && $post_at > $current) {
                $meta = get_post_meta($post_id);
                $data = array(
                    FixedFuturePosts::IN_POSTS => $post,
                    FixedFuturePosts::IN_META => $meta
                );
                update_post_meta($post_id, FixedFuturePosts::META_KEY, maybe_serialize($data));
            }
        }
    }
    function actionPublishPost($post_id) {
        if (!$this->_auth())
            return;
        $publish = get_post_meta($post_id, FixedFuturePosts::META_KEY, true);
        if ($publish)
            delete_post_meta($post_id, FixedFuturePosts::META_KEY, $publish);
    }
    function filterPostResults($posts) {
        if ($this->_auth()
            || !is_array($posts))
            return $posts;
        for ($i = 0; $i < count($posts); $i++) {
            if (!is_a($posts[$i], FixedFuturePosts::TYPEOF_POST)
                || $posts[$i]->post_status !== "future")
                continue;
            $publish = get_post_meta($posts[$i]->ID, FixedFuturePosts::META_KEY, true);
            if ($publish) {
                $data = maybe_unserialize($publish);
                if (is_array($data)
                    && array_key_exists(FixedFuturePosts::IN_POSTS, $data))
                    $posts[$i] = $data[FixedFuturePosts::IN_POSTS];
            }
        }
        return $posts;
    }
    function filterGetPostMetadata($content, $post_id, $meta_key, $single) {
        if ($this->_auth()
            || $meta_key === FixedFuturePosts::META_KEY)
            return null;
        $cache = $this->_cache_meta($post_id);
        $publish = null;
        if (is_array($cache)
            && array_key_exists(FixedFuturePosts::META_KEY, $cache)) {
            $cache = $cache[FixedFuturePosts::META_KEY];
            if (is_array($cache)
                && count($cache) > 0)
                $publish = maybe_unserialize(maybe_unserialize($cache[0]));
        }
        if (!$publish
            || !is_array($publish)
            || !array_key_exists(FixedFuturePosts::IN_META, $publish))
            return null;
        $output = "";
        $meta = $publish[FixedFuturePosts::IN_META];
        if (is_array($meta)
            && array_key_exists($meta_key, $meta)) {
            $array = $meta[$meta_key];
            if (is_array($array)
                && count($array) > 0
                && $array[0] != null) {
                $output = $array[0];
            }
        }
        return $single ? $output : array($output);
    }
    function filterPostsWhere($where, $query) {
        if ($this->_auth())
            return $where;
        global $wpdb;
        $output = array();
        if (preg_match(sprintf('/^(.*)?%1$s.post_status\s*?=\s*?\'publish\'(.*)$/', $wpdb->posts), $where, $output))
            return sprintf(
                '%1$s ((%2$s.post_status=\'publish\') OR (%2$s.post_status=\'future\''.
                ' AND EXISTS(SELECT * FROM %3$s WHERE %3$s.post_id=%2$s.ID AND meta_key=\'%4$s\'))) %5$s',
                $output[1],
                $wpdb->posts,
                $wpdb->postmeta,
                FixedFuturePosts::META_KEY,
                $output[2]
            );
        return $where;
    }
    function filterPostDateColumnTime($h_time, $post) {
        if ($this->_auth()
            && is_a($post, FixedFuturePosts::TYPEOF_POST)
            && $post->post_status === "future")
            $h_time.= sprintf("<br />%s", get_post_time("g:i a", false, $post));
        return $h_time;
    }
}
