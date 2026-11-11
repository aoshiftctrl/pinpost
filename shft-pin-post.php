<?php

/*
 * Plugin Name:       PinPost
 * Description:       Pinnt einen veröffentlichten Beitrag an die erste Stelle des Kategorie-Archives. Es kann immer nur ein Beitrag gepinnt sein.
 * Version:           1.1.2
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            aoshiftctrl
 * Author URI:        https://github.com/aoshiftctrl
 * Text Domain:       shft-pin-post
 * License:           CC BY-ND 4.0
 * License URI:       https://creativecommons.org/licenses/by-nd/4.0/
 */

namespace Shft\PinPost;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Funktionsweise
 *
 * - Option shft_pp_pinned_id: ID des gepinnten Beitrags (0 = keiner). Eine
 *                       einzige Option statt Post-Meta - es kann per
 *                       Konstruktion nur einen Pin geben, der Wert ist
 *                       autoloaded und kostet keine eigene Query.
 * - REST-Feld shft_pinned: Boolean am Beitrag, nur im Kontext "edit" (nicht
 *                       oeffentlich lesbar). Schreiben setzt bzw. loest die
 *                       Option. Konflikte werden schon vor dem Speichern des
 *                       Beitrags per rest_pre_insert_post abgelehnt.
 * - Editor:             Checkbox im Status-Bereich des Beitragspanels. Aktiv nur
 *                       bei veroeffentlichten Beitraegen und nur, solange kein
 *                       anderer Beitrag gepinnt ist.
 * - Serverseitig:       Verlaesst der gepinnte Beitrag den Status "publish"
 *                       (Entwurf, privat, Papierkorb) oder wird er geloescht,
 *                       wird der Pin entfernt.
 * - Frontend:           posts_orderby setzt den gepinnten Beitrag in der
 *                       Haupt-Query von Kategorie-Archiven nach vorn. Ueber
 *                       ORDER BY statt Einfuegen - Pagination bleibt korrekt.
 *                       Wie bei Kategorie-Archiven ueblich zaehlen
 *                       Unterkategorien mit, der Beitrag steht also auch im
 *                       Archiv der Elternkategorie vorn.
 */

const OPTION      = 'shft_pp_pinned_id';
const FIELD       = 'shft_pinned';
const LEGACY_META = '_shft_pinned';

/**
 * ID des gepinnten Beitrags, 0 wenn keiner gepinnt ist.
 */
function pinned_id(): int
{
    $id = (int) get_option(OPTION, 0);
    if (!$id) {
        return 0;
    }

    $post = get_post($id);

    return $post && $post->post_type === 'post' && $post->post_status === 'publish' ? $id : 0;
}

/**
 * Titel als Klartext: ohne Tags, Entities, Texturize und ohne "Geschuetzt:"-Praefix.
 */
function plain_title(int $id): string
{
    return html_entity_decode(wp_strip_all_tags(get_post_field('post_title', $id)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Darf der Beitrag gepinnt werden? null wenn ja, sonst der Fehler.
 */
function check(int $id, string $type, string $status): ?WP_Error
{
    // Status zuerst, auch fuer den schon gepinnten Beitrag: Wechselt er im
    // selben Request den Status, waere der Pin nach dem Speichern ungueltig.
    if ($type !== 'post' || $status !== 'publish') {
        return new WP_Error(
            'shft_pp_not_published',
            'Nur veröffentlichte Beiträge können gepinnt werden.',
            ['status' => 400]
        );
    }

    $current = pinned_id();

    if ($current && $current !== $id) {
        return new WP_Error(
            'shft_pp_taken',
            sprintf('Bereits gepinnt: „%s“. Erst dort lösen.', plain_title($current)),
            ['status' => 409]
        );
    }

    return null;
}

/**
 * Pin setzen bzw. loesen. Nur fuer veroeffentlichte Beitraege und nur einmal.
 */
function set_pinned(bool $val, WP_Post $post): bool|WP_Error
{
    if (!$val) {
        if (pinned_id() === $post->ID) {
            update_option(OPTION, 0, true);
        }
        return true;
    }

    $err = check($post->ID, $post->post_type, $post->post_status);
    if ($err) {
        return $err;
    }

    update_option(OPTION, $post->ID, true);

    return true;
}

/**
 * Einmalige Uebernahme eines Pins aus Version 1.0 (Post-Meta _shft_pinned).
 */
function migrate(): void
{
    if (get_option(OPTION) !== false) {
        return;
    }

    $ids = get_posts([
        'post_type'        => 'post',
        'post_status'      => 'publish',
        'meta_key'         => LEGACY_META,
        'meta_value'       => '1',
        'fields'           => 'ids',
        'numberposts'      => 1,
        'suppress_filters' => true,
    ]);

    add_option(OPTION, $ids ? (int) $ids[0] : 0, '', true);
    delete_post_meta_by_key(LEGACY_META);
}
add_action('init', __NAMESPACE__ . '\\migrate');

function register_field(): void
{
    register_rest_field('post', FIELD, [
        'get_callback'    => static function (array $obj): bool {
            // Bei ?_fields= ohne "id" fehlt der Schluessel. Der Controller
            // setzt vor dem Aufruf den globalen Beitrag.
            $pin = pinned_id();
            return $pin && (int) ($obj['id'] ?? get_the_ID()) === $pin;
        },
        'update_callback' => static function ($val, WP_Post $post) {
            if (!current_user_can('edit_post', $post->ID)) {
                return new WP_Error('rest_forbidden', 'Keine Berechtigung.', ['status' => 403]);
            }
            return set_pinned((bool) $val, $post);
        },
        'schema'          => [
            'description' => 'Beitrag ist an die erste Stelle des Kategorie-Archives gepinnt.',
            'type'        => 'boolean',
            'context'     => ['edit'],
        ],
    ]);
}
add_action('rest_api_init', __NAMESPACE__ . '\\register_field');

/**
 * Status, mit dem wp_insert_post() tatsaechlich speichert: "publish" mit
 * Datum in der Zukunft wird zu "future", "future" mit Datum in der
 * Vergangenheit zu "publish". Ein leeres Datum setzt Core auf jetzt.
 */
function effective_status(string $status, string $date_gmt): string
{
    if ($status !== 'publish' && $status !== 'future') {
        return $status;
    }

    $future = $date_gmt !== ''
        && $date_gmt !== '0000-00-00 00:00:00'
        && strtotime($date_gmt) - strtotime(gmdate('Y-m-d H:i:s')) >= MINUTE_IN_SECONDS;

    return $future ? 'future' : 'publish';
}

/**
 * Konflikte vor dem Speichern ablehnen. Der update_callback des REST-Felds
 * laeuft erst nach wp_update_post() - ein Fehler dort kaeme, nachdem der Rest
 * des Beitrags schon gespeichert ist.
 */
function check_before_save($prepared, WP_REST_Request $request)
{
    if (!$prepared instanceof \stdClass || !isset($request[FIELD]) || !rest_sanitize_boolean($request[FIELD])) {
        return $prepared;
    }

    $id     = (int) ($request['id'] ?? 0);
    $status = $prepared->post_status ?? ($id ? get_post_status($id) : 'draft');
    // property_exists statt ??: "date": null setzt das Datum zurueck und
    // darf nicht auf das gespeicherte Datum zurueckfallen.
    $date   = property_exists($prepared, 'post_date_gmt')
        ? $prepared->post_date_gmt
        : ($id ? get_post_field('post_date_gmt', $id) : '');

    return check($id, 'post', effective_status((string) $status, (string) $date)) ?? $prepared;
}
// Moeglichst spaet: Filter anderer Plugins erwarten ein stdClass, kein WP_Error.
add_filter('rest_pre_insert_post', __NAMESPACE__ . '\\check_before_save', PHP_INT_MAX, 2);

function unpin_on_status(string $new, string $old, WP_Post $post): void
{
    if ($old === 'publish' && $new !== 'publish' && $post->ID === (int) get_option(OPTION, 0)) {
        update_option(OPTION, 0, true);
    }
}
add_action('transition_post_status', __NAMESPACE__ . '\\unpin_on_status', 10, 3);

function unpin_on_delete(int $id): void
{
    if ($id === (int) get_option(OPTION, 0)) {
        update_option(OPTION, 0, true);
    }
}
add_action('deleted_post', __NAMESPACE__ . '\\unpin_on_delete');

// Filter-Callbacks ohne Typen: Andere Plugins und Themes reichen hier teils
// null oder Strings durch, ein TypeError waere ein Fatal Error im Frontend.
function orderby($orderby, $q)
{
    if (is_admin() || !$q instanceof WP_Query || !$q->is_main_query() || !$q->is_category()) {
        return $orderby;
    }

    $id = pinned_id();
    if (!$id) {
        return $orderby;
    }

    global $wpdb;
    $pin = $wpdb->prepare("{$wpdb->posts}.ID = %d DESC", $id);

    return $orderby ? "{$pin}, {$orderby}" : $pin;
}
add_filter('posts_orderby', __NAMESPACE__ . '\\orderby', 10, 2);

/**
 * Klasse is-pinned am gepinnten Beitrag, zum Hervorheben per CSS.
 */
function post_class($classes, $cls, $id)
{
    if (!is_array($classes) || is_admin()) {
        return $classes;
    }

    $pin = pinned_id();
    if ($pin && (int) $id === $pin) {
        $classes[] = 'is-pinned';
    }

    return $classes;
}
add_filter('post_class', __NAMESPACE__ . '\\post_class', 10, 3);

function enqueue_editor(): void
{
    $scr = get_current_screen();
    if (!$scr || $scr->post_type !== 'post') {
        return;
    }

    $file = plugin_dir_path(__FILE__) . 'editor.js';
    wp_enqueue_script(
        'shft-pin-post',
        plugin_dir_url(__FILE__) . 'editor.js',
        ['wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data'],
        (string) filemtime($file),
        true
    );

    $id = pinned_id();
    wp_add_inline_script('shft-pin-post', 'window.shftPinPost = ' . wp_json_encode([
        'id'    => $id,
        'title' => $id ? plain_title($id) : '',
    ], JSON_HEX_TAG | JSON_HEX_AMP) . ';', 'before');
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor');
