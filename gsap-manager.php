<?php
/**
 * Plugin Name: GSAP Manager
 * Plugin URI:  https://github.com/vfkill/gsap-manager
 * Description: Carrega o GSAP e seus plugins no WordPress com configurações flexíveis via painel administrativo.
 * Version:     3.8.9
 * Author:      Victor Kill
 * License:     GPL-2.0-or-later
 * Text Domain: gsap-manager
 */

defined( 'ABSPATH' ) || exit;

// Versão: lê direto da header do plugin (fonte única da verdade — bump só aqui).
if ( ! defined( 'GSAP_MANAGER_VERSION' ) ) {
    $gsap_manager_data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
    define( 'GSAP_MANAGER_VERSION', $gsap_manager_data['Version'] );
}
define( 'GSAP_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSAP_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'GSAP_MANAGER_OPTION', 'gsap_manager_settings' );

// ─── Carregar includes ──────────────────────────────────────────────────────
require_once GSAP_MANAGER_PATH . 'includes/class-gsap-enqueue.php';
require_once GSAP_MANAGER_PATH . 'includes/class-gsap-admin.php';

// ─── Auto-update via GitHub (plugin-update-checker) ─────────────────────────
// Observa a branch `main` do repositório privado. Para autenticar, defina
// em wp-config.php (nunca commitado):
//   define( 'GSAP_MANAGER_GITHUB_TOKEN', 'ghp_xxx...' );
// Sem o token, o WP não consegue ler o repo privado — o update é silenciosamente
// ignorado e o plugin continua funcionando normalmente.
if ( file_exists( GSAP_MANAGER_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once GSAP_MANAGER_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

    $gsap_manager_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/vfkill/gsap-manager/',
        __FILE__,
        'gsap-manager'
    );
    $gsap_manager_updater->setBranch( 'main' );

    if ( defined( 'GSAP_MANAGER_GITHUB_TOKEN' ) && GSAP_MANAGER_GITHUB_TOKEN ) {
        $gsap_manager_updater->setAuthentication( GSAP_MANAGER_GITHUB_TOKEN );
    }
}

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
            // Plugins públicos (cdnjs)
            'ScrollTrigger'      => true,
            'ScrollToPlugin'     => false,
            'Draggable'          => false,
            'Flip'               => false,
            'MotionPathPlugin'   => false,
            'TextPlugin'         => false,
            'Observer'           => false,
            'CustomEase'         => false,
            'EasePack'           => false,
            'CSSRulePlugin'      => false,
            // Plugins bonus (local — assets/js/vendor/)
            'ScrollSmoother'     => false,
            'SplitText'          => false,
            'MorphSVGPlugin'     => false,
            'DrawSVGPlugin'      => false,
            'InertiaPlugin'      => false,
            'ScrambleTextPlugin' => false,
            'CustomBounce'       => false,
            'CustomWiggle'       => false,
            'Physics2DPlugin'    => false,
            'PhysicsPropsPlugin' => false,
            'MotionPathHelper'   => false,
            'GSDevTools'         => false,
            'EaselPlugin'        => false,
            'PixiPlugin'         => false,
        ],
        'smoother_smooth'    => 1,
        'smoother_effects'   => false,
        'smoother_normalize' => false,
        'smoother_wrapper'   => '#smooth-wrapper',
        'smoother_content'   => '#smooth-content',
        'highlight_color'  => '',
        'progress_color'   => '',
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
