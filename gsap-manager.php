<?php
/**
 * Plugin Name: GSAP Manager
 * Plugin URI:  https://github.com/
 * Description: Carrega o GSAP e seus plugins no WordPress com configurações flexíveis via painel administrativo.
 * Version:     2.2.0
 * Author:      Victor Kill
 * License:     GPL-2.0-or-later
 * Text Domain: gsap-manager
 */

defined( 'ABSPATH' ) || exit;

define( 'GSAP_MANAGER_VERSION', '2.2.0' );
define( 'GSAP_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSAP_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'GSAP_MANAGER_OPTION', 'gsap_manager_settings' );

// ─── Carregar includes ──────────────────────────────────────────────────────
require_once GSAP_MANAGER_PATH . 'includes/class-gsap-enqueue.php';
require_once GSAP_MANAGER_PATH . 'includes/class-gsap-admin.php';

// ─── Init ───────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    new GSAP_Enqueue();
    if ( is_admin() ) {
        new GSAP_Admin();
    }
} );

// ─── Defaults na ativação ───────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    if ( ! get_option( GSAP_MANAGER_OPTION ) ) {
        add_option( GSAP_MANAGER_OPTION, gsap_manager_defaults() );
    }
} );

function gsap_manager_defaults(): array {
    return [
        'enabled'          => true,
        'source'           => 'cdn',         // cdn | local
        'gsap_version'     => '3.12.5',
        'load_in_footer'   => true,
        'load_on'          => 'all',          // all | front | selected
        'selected_ids'     => '',
        'plugins'          => [
            'ScrollTrigger'    => true,
            'ScrollSmoother'   => false,
            'ScrollToPlugin'   => false,
            'Draggable'        => false,
            'Flip'             => false,
            'MotionPathPlugin' => false,
            'TextPlugin'       => false,
            'Observer'         => false,
            'CustomEase'       => false,
        ],
        'smoother_smooth'    => 1.5,
        'smoother_effects'   => false,
        'smoother_normalize' => false,
        'smoother_wrapper'   => '#smooth-wrapper',
        'smoother_content'   => '#smooth-content',
        'auto_animations'  => true,
        'custom_init'      => '',
    ];
}

function gsap_manager_get_settings(): array {
    $saved    = get_option( GSAP_MANAGER_OPTION, [] );
    $defaults = gsap_manager_defaults();

    // Merge profundo para a chave 'plugins'
    if ( isset( $saved['plugins'] ) && is_array( $saved['plugins'] ) ) {
        $saved['plugins'] = array_merge( $defaults['plugins'], $saved['plugins'] );
    }

    return array_merge( $defaults, $saved );
}
