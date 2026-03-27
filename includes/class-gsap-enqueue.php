<?php
defined( 'ABSPATH' ) || exit;

class GSAP_Enqueue {

    private array $settings;

    // CDN base — GSAP v3 no cdnjs
    const CDN_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/';

    // Mapeamento plugin → arquivo CDN
    const PLUGIN_FILES = [
        'ScrollTrigger'    => 'ScrollTrigger.min.js',
        'ScrollToPlugin'   => 'ScrollToPlugin.min.js',
        'Draggable'        => 'Draggable.min.js',
        'Flip'             => 'Flip.min.js',
        'MotionPathPlugin' => 'MotionPathPlugin.min.js',
        'TextPlugin'       => 'TextPlugin.min.js',
        'Observer'         => 'Observer.min.js',
        'CustomEase'       => 'CustomEase.min.js',
    ];

    public function __construct() {
        $this->settings = gsap_manager_get_settings();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        if ( ! $this->should_load() ) {
            return;
        }

        $s       = $this->settings;
        $footer  = (bool) $s['load_in_footer'];
        $version = sanitize_text_field( $s['gsap_version'] );

        // ── Core GSAP ───────────────────────────────────────────────────────
        if ( $s['source'] === 'cdn' ) {
            $gsap_url = self::CDN_BASE . $version . '/gsap.min.js';
        } else {
            $gsap_url = GSAP_MANAGER_URL . 'assets/js/vendor/gsap.min.js';
        }

        wp_enqueue_script(
            'gsap',
            $gsap_url,
            [],
            $version,
            $footer
        );

        // ── Plugins ─────────────────────────────────────────────────────────
        foreach ( self::PLUGIN_FILES as $name => $file ) {
            if ( empty( $s['plugins'][ $name ] ) ) {
                continue;
            }

            if ( $s['source'] === 'cdn' ) {
                $url = self::CDN_BASE . $version . '/' . $file;
            } else {
                $url = GSAP_MANAGER_URL . 'assets/js/vendor/' . $file;
            }

            wp_enqueue_script(
                'gsap-' . strtolower( $name ),
                $url,
                [ 'gsap' ],
                $version,
                $footer
            );
        }

        // ── Animações por classe (gsap-animations.js) ───────────────────────
        if ( ! empty( $s['auto_animations'] ) ) {
            // Dependências: gsap sempre; scrolltrigger se ativo
            $anim_deps = [ 'gsap' ];
            if ( ! empty( $s['plugins']['ScrollTrigger'] ) ) {
                $anim_deps[] = 'gsap-scrolltrigger';
            }

            wp_enqueue_style(
                'gsap-animations',
                GSAP_MANAGER_URL . 'assets/css/gsap-animations.css',
                [],
                GSAP_MANAGER_VERSION
            );

            wp_enqueue_script(
                'gsap-animations',
                GSAP_MANAGER_URL . 'assets/js/gsap-animations.js',
                $anim_deps,
                GSAP_MANAGER_VERSION,
                true // sempre no rodapé
            );
        }

        // ── Init customizado ────────────────────────────────────────────────
        if ( ! empty( $s['custom_init'] ) ) {
            wp_add_inline_script( 'gsap', wp_unslash( $s['custom_init'] ) );
        }
    }

    private function should_load(): bool {
        $s = $this->settings;

        if ( empty( $s['enabled'] ) ) {
            return false;
        }

        switch ( $s['load_on'] ) {
            case 'front':
                return is_front_page() || is_home();

            case 'selected':
                $ids = array_filter( array_map( 'intval', explode( ',', $s['selected_ids'] ) ) );
                return in_array( get_the_ID(), $ids, true );

            default: // 'all'
                return true;
        }
    }
}
