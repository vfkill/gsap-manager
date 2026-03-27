<?php
defined( 'ABSPATH' ) || exit;

class GSAP_Enqueue {

    private array $settings;

    // CDN base — GSAP v3 no cdnjs
    const CDN_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/';

    // CDN para plugins "bonus" (ex: ScrollSmoother) não disponíveis no cdnjs
    // Espelha o npm — inclui todos os plugins desde que o GSAP virou gratuito
    const UNPKG_BASE = 'https://unpkg.com/gsap@';

    // Mapeamento plugin → arquivo CDN (cdnjs)
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

    // Plugins que usam o CDN unpkg em vez do cdnjs
    const UNPKG_PLUGIN_FILES = [
        'ScrollSmoother' => 'dist/ScrollSmoother.min.js',
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

        // ── Plugins (cdnjs) ─────────────────────────────────────────────────
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

        // ── Plugins (unpkg — bonus plugins) ─────────────────────────────────
        foreach ( self::UNPKG_PLUGIN_FILES as $name => $file ) {
            if ( empty( $s['plugins'][ $name ] ) ) {
                continue;
            }

            // ScrollSmoother requer ScrollTrigger
            $deps = [ 'gsap' ];
            if ( $name === 'ScrollSmoother' && ! empty( $s['plugins']['ScrollTrigger'] ) ) {
                $deps[] = 'gsap-scrolltrigger';
            }

            if ( $s['source'] === 'cdn' ) {
                $url = self::UNPKG_BASE . $version . '/' . $file;
            } else {
                $url = GSAP_MANAGER_URL . 'assets/js/vendor/' . basename( $file );
            }

            wp_enqueue_script(
                'gsap-' . strtolower( $name ),
                $url,
                $deps,
                $version,
                $footer
            );
        }

        // ── ScrollSmoother: inicialização automática ─────────────────────────
        if ( ! empty( $s['plugins']['ScrollSmoother'] ) ) {
            $wrapper   = esc_js( $s['smoother_wrapper']   ?? '#smooth-wrapper' );
            $content   = esc_js( $s['smoother_content']   ?? '#smooth-content' );
            $smooth    = floatval( $s['smoother_smooth']   ?? 1.5 );
            $effects   = ! empty( $s['smoother_effects'] )   ? 'true' : 'false';
            $normalize = ! empty( $s['smoother_normalize'] ) ? 'true' : 'false';

            $init = "(function(){
    if(typeof ScrollSmoother==='undefined'||!document.querySelector('{$wrapper}')){return;}
    gsap.registerPlugin(ScrollTrigger,ScrollSmoother);
    window.smoother=ScrollSmoother.create({
        wrapper:'{$wrapper}',
        content:'{$content}',
        smooth:{$smooth},
        effects:{$effects},
        normalizeScroll:{$normalize}
    });
})();";
            wp_add_inline_script( 'gsap-scrollsmoother', $init );
        }

        // ── Animações por classe (gsap-animations.js) ───────────────────────
        if ( ! empty( $s['auto_animations'] ) ) {
            $anim_deps = [ 'gsap' ];
            if ( ! empty( $s['plugins']['ScrollTrigger'] ) ) {
                $anim_deps[] = 'gsap-scrolltrigger';
            }
            // Garante que ScrollSmoother (e seu init inline) rode antes das animações
            if ( ! empty( $s['plugins']['ScrollSmoother'] ) ) {
                $anim_deps[] = 'gsap-scrollsmoother';
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
