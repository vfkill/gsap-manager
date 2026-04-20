<?php
defined( 'ABSPATH' ) || exit;

class GSAP_Enqueue {

    private array $settings;

    // CDN base — GSAP v3 no cdnjs
    const CDN_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/';

    // Plugins disponíveis no cdnjs (carregam via CDN ou local conforme configuração)
    const PLUGIN_FILES = [
        'ScrollTrigger'    => 'ScrollTrigger.min.js',
        'ScrollToPlugin'   => 'ScrollToPlugin.min.js',
        'Draggable'        => 'Draggable.min.js',
        'Flip'             => 'Flip.min.js',
        'MotionPathPlugin' => 'MotionPathPlugin.min.js',
        'TextPlugin'       => 'TextPlugin.min.js',
        'Observer'         => 'Observer.min.js',
        'CustomEase'       => 'CustomEase.min.js',
        'EasePack'         => 'EasePack.min.js',
        'CSSRulePlugin'    => 'CSSRulePlugin.min.js',
    ];

    // Plugins bonus — sempre carregados do local (assets/js/vendor/)
    const BONUS_PLUGIN_FILES = [
        'ScrollSmoother'     => 'ScrollSmoother.min.js',
        'SplitText'          => 'SplitText.min.js',
        'MorphSVGPlugin'     => 'MorphSVGPlugin.min.js',
        'DrawSVGPlugin'      => 'DrawSVGPlugin.min.js',
        'InertiaPlugin'      => 'InertiaPlugin.min.js',
        'ScrambleTextPlugin' => 'ScrambleTextPlugin.min.js',
        'CustomBounce'       => 'CustomBounce.min.js',
        'CustomWiggle'       => 'CustomWiggle.min.js',
        'Physics2DPlugin'    => 'Physics2DPlugin.min.js',
        'PhysicsPropsPlugin' => 'PhysicsPropsPlugin.min.js',
        'MotionPathHelper'   => 'MotionPathHelper.min.js',
        'GSDevTools'         => 'GSDevTools.min.js',
        'EaselPlugin'        => 'EaselPlugin.min.js',
        'PixiPlugin'         => 'PixiPlugin.min.js',
    ];

    public function __construct() {
        $this->settings = gsap_manager_get_settings();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        // Anti-FOUC gated — imprime classe `gsap-loading` em <html> com rescue-timeout.
        // Roda antes de tudo (prioridade 1) e só quando o plugin vai efetivamente carregar.
        // O CSS só esconde elementos .gsap-text-focus enquanto a classe estiver presente;
        // o setTimeout de 3s é seguro-contra-tudo: mesmo se o JS do plugin falhar por
        // completo, a classe some sozinha e o texto aparece (nunca invisível pra sempre).
        add_action( 'wp_head', [ $this, 'print_loading_gate' ], 1 );

        // Injeta smooth-wrapper/smooth-content automaticamente quando ScrollSmoother está ativo.
        // Hello Elementor (e a maioria dos temas modernos) chama wp_body_open() logo após <body>.
        if ( ! empty( $this->settings['plugins']['ScrollSmoother'] ) ) {
            add_action( 'wp_body_open', [ $this, 'smoother_wrapper_open'  ] );
            add_action( 'wp_footer',    [ $this, 'smoother_wrapper_close' ], 999 );
        }
    }

    public function print_loading_gate(): void {
        if ( ! $this->should_load() ) {
            return;
        }
        // Minificado: adiciona classe imediatamente; timeout 3s remove como rescue.
        echo "<script>(function(){var d=document.documentElement;d.classList.add('gsap-loading');setTimeout(function(){d.classList.remove('gsap-loading');},3000);})();</script>\n";
    }

    public function smoother_wrapper_open(): void {
        if ( ! $this->should_load_smoother() ) {
            return;
        }
        $wrapper = $this->smoother_id( $this->settings['smoother_wrapper'] ?? '#smooth-wrapper', 'smooth-wrapper' );
        $content = $this->smoother_id( $this->settings['smoother_content'] ?? '#smooth-content', 'smooth-content' );
        printf( '<div id="%s"><div id="%s">', esc_attr( $wrapper ), esc_attr( $content ) );
    }

    public function smoother_wrapper_close(): void {
        if ( ! $this->should_load_smoother() ) {
            return;
        }
        echo '</div></div>';
    }

    /**
     * Extrai o ID de um seletor CSS (#meu-id → meu-id).
     * Se o seletor não começar com #, retorna o fallback.
     */
    private function smoother_id( string $selector, string $fallback ): string {
        $selector = trim( $selector );
        if ( str_starts_with( $selector, '#' ) ) {
            return substr( $selector, 1 );
        }
        return $fallback;
    }

    public function enqueue(): void {
        if ( ! $this->should_load() ) {
            return;
        }

        $s       = $this->settings;
        $footer  = (bool) $s['load_in_footer'];
        $version = sanitize_text_field( $s['gsap_version'] );

        // Registra o handle de CSS logo de cara — wp_add_inline_style() falha
        // silenciosamente se o handle não existir, e vários blocos abaixo
        // (ScrollSmoother critical CSS, variáveis de cor) injetam via esse handle.
        wp_enqueue_style(
            'gsap-animations',
            GSAP_MANAGER_URL . 'assets/css/gsap-animations.css',
            [],
            GSAP_MANAGER_VERSION
        );

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

        // ── Plugins bonus (local — assets/js/vendor/) ───────────────────────────
        foreach ( self::BONUS_PLUGIN_FILES as $name => $file ) {
            if ( empty( $s['plugins'][ $name ] ) ) {
                continue;
            }

            $local_file = GSAP_MANAGER_PATH . 'assets/js/vendor/' . $file;
            if ( ! file_exists( $local_file ) ) {
                continue; // arquivo não encontrado — ignora silenciosamente
            }

            $deps = [ 'gsap' ];
            if ( ! empty( $s['plugins']['ScrollTrigger'] ) ) {
                // ScrollSmoother, InertiaPlugin e MotionPathHelper beneficiam do ScrollTrigger
                if ( in_array( $name, [ 'ScrollSmoother', 'InertiaPlugin', 'MotionPathHelper' ], true ) ) {
                    $deps[] = 'gsap-scrolltrigger';
                }
            }
            // CustomBounce e CustomWiggle dependem do CustomEase
            if ( in_array( $name, [ 'CustomBounce', 'CustomWiggle' ], true ) && ! empty( $s['plugins']['CustomEase'] ) ) {
                $deps[] = 'gsap-customease';
            }

            wp_enqueue_script(
                'gsap-' . strtolower( $name ),
                GSAP_MANAGER_URL . 'assets/js/vendor/' . $file,
                $deps,
                $version,
                $footer
            );
        }

        // ── ScrollSmoother: inicialização automática ─────────────────────────
        // Não inicializa em páginas do WooCommerce — o wrapper que envolve o DOM
        // inteiro e o transform:translateY que o ScrollSmoother aplica quebram:
        // mini-cart, sticky add-to-cart, redirecionamentos do checkout, abas de
        // produto e qualquer elemento position:fixed dentro do conteúdo.
        if ( ! empty( $s['plugins']['ScrollSmoother'] ) && $this->should_load_smoother() ) {
            $wrapper   = esc_js( $s['smoother_wrapper']   ?? '#smooth-wrapper' );
            $content   = esc_js( $s['smoother_content']   ?? '#smooth-content' );
            $smooth    = floatval( $s['smoother_smooth']   ?? 1 );
            $effects   = ! empty( $s['smoother_effects'] )   ? 'true' : 'false';
            $normalize = ! empty( $s['smoother_normalize'] ) ? 'true' : 'false';

            $init = "(function(){
    if(typeof ScrollSmoother==='undefined'||!document.querySelector('{$wrapper}')){return;}
    gsap.registerPlugin(ScrollTrigger,ScrollSmoother);

    // ── 1. Mata scroll-behavior:smooth do tema/Bootstrap ─────────────────────
    // Qualquer scroll-behavior:smooth no CSS conflita com o ScrollSmoother,
    // criando dois sistemas de scroll suavizado competindo entre si.
    gsap.set('html,body',{scrollBehavior:'auto'});

    // ── 2. Impede o browser de restaurar posição de scroll ───────────────────
    // O browser tenta rolar para a última posição salva ao recarregar a página,
    // o que conflita com o ScrollSmoother que controla o scroll via transform.
    if(history.scrollRestoration){history.scrollRestoration='manual';}

    // ── 3. Move o header para FORA do smooth-wrapper ─────────────────────────
    // PROBLEMA: ScrollSmoother aplica CSS transform no #smooth-content.
    // Por spec CSS, qualquer elemento com transform cria um novo containing block.
    // Elementos position:fixed DENTRO de um pai com transform ficam fixos
    // relativo ao pai transformado — não ao viewport. Resultado: o header
    // 'segue' o scroll e desaparece quando a página rola para baixo.
    // SOLUÇÃO OFICIAL GSAP: o header deve ser IRMÃO do smooth-wrapper no DOM,
    // completamente fora dele. Assim position:fixed funciona relativo ao viewport.
    (function(){
        var w=document.querySelector('{$wrapper}');
        if(!w||!w.parentNode){return;}
        var h=w.querySelector('header,[data-elementor-type=\"header\"],.elementor-location-header,#masthead');
        if(!h){return;}
        // Insere o header antes do smooth-wrapper no DOM (irmão, fora do wrapper)
        w.parentNode.insertBefore(h,w);
    })();

    window.smoother=ScrollSmoother.create({
        wrapper:'{$wrapper}',
        content:'{$content}',
        smooth:{$smooth},
        effects:{$effects},
        normalizeScroll:{$normalize}
    });
})();";
            wp_add_inline_script( 'gsap-scrollsmoother', $init );

            // scroll-behavior:auto já está no gsap-animations.css (html { ... }).
            // O border-top:1px solid transparent no #smooth-content era um
            // workaround pra margin-collapse, mas introduzia layout shift sutil
            // que interferia nas medições do ScrollTrigger em pin + scrub.
            // Removido — se margin-collapse reaparecer, aplicar no conteúdo
            // específico, não globalmente.
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

            wp_enqueue_script(
                'gsap-animations',
                GSAP_MANAGER_URL . 'assets/js/gsap-animations.js',
                $anim_deps,
                GSAP_MANAGER_VERSION,
                true // sempre no rodapé
            );

            // Injeta variáveis CSS de personalização visual
            $css_vars = [];
            if ( ! empty( $s['highlight_color'] ) ) {
                $css_vars[] = '--gsap-highlight-color:' . sanitize_hex_color( $s['highlight_color'] ) . ';';
            }
            if ( ! empty( $s['progress_color'] ) ) {
                $css_vars[] = '--gsap-progress-color:' . sanitize_hex_color( $s['progress_color'] ) . ';';
            }
            if ( ! empty( $css_vars ) ) {
                wp_add_inline_style(
                    'gsap-animations',
                    ':root{' . implode( '', $css_vars ) . '}'
                );
            }
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

    /**
     * Determina se o ScrollSmoother pode ser carregado na página atual.
     *
     * O ScrollSmoother NÃO deve rodar em páginas do WooCommerce porque:
     *
     * 1. Wrapper DOM: injeta #smooth-wrapper > #smooth-content em volta da
     *    página inteira. O ScrollSmoother aplica transform:translateY() nesse
     *    container, criando um novo containing block — elementos position:fixed
     *    dentro dele (mini-cart, sticky add-to-cart, notificações) param de
     *    funcionar relativo ao viewport.
     *
     * 2. overflow:hidden no body: o ScrollSmoother seta isso para controlar o
     *    scroll, fechando painéis off-canvas, dropdowns e modais do WooCommerce.
     *
     * 3. history.scrollRestoration='manual': o WooCommerce depende da restauração
     *    nativa de scroll para rolar até mensagens de erro após submit do checkout
     *    e para manter posição após AJAX.
     *
     * 4. Interceptação de cliques em âncoras: o handler no capture phase chama
     *    e.preventDefault() em qualquer link com '#', quebrando abas de produto
     *    (#tab-description, #tab-reviews), toggles do checkout e UI do WooCommerce
     *    que usa href="#" para acionar JS próprio.
     *
     * GSAP + ScrollTrigger continuam funcionando normalmente nessas páginas —
     * apenas o ScrollSmoother é desativado.
     */
    private function should_load_smoother(): bool {
        if ( ! $this->should_load() ) {
            return false;
        }

        // Desativa no editor e no preview do Elementor.
        //
        // O Elementor carrega a página em um iframe para edição. Nesse iframe
        // o wp_body_open dispara normalmente — sem essa verificação o ScrollSmoother
        // inicializa dentro do editor, aplica overflow:hidden no body do iframe e
        // registra um handler de captura de cliques (e.preventDefault em todos os
        // links com '#'), travando completamente a UI do Elementor.
        //
        // Detecção via duas estratégias complementares:
        // 1. $_GET['elementor-preview'] — Elementor sempre passa este parâmetro
        //    na URL do iframe de edição (ex: ?elementor-preview=22&ver=...).
        // 2. API do Elementor — is_preview_mode() cobre o preview do Theme Builder
        //    e outros contextos onde o parâmetro GET pode não estar presente.
        if ( $this->is_elementor_editor() ) {
            return false;
        }

        // Desativa em qualquer página gerenciada pelo WooCommerce.
        // is_woocommerce() cobre: loja, produto, categoria, tag.
        // As demais funções cobrem páginas especiais que is_woocommerce() não inclui.
        if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
            return false;
        }
        if ( function_exists( 'is_cart' ) && is_cart() ) {
            return false;
        }
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return false;
        }
        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            return false;
        }

        return true;
    }

    /**
     * Retorna true quando a requisição atual é o editor ou preview do Elementor.
     *
     * O Elementor possui dois contextos que carregam o frontend em um iframe:
     *
     * - Editor (edição ao vivo): URL contém ?elementor-preview=<post_id>
     *   Nesse contexto o usuário está arrastando widgets, clicando em painéis, etc.
     *   Qualquer script que intercepte cliques ou modifique o scroll trava a UI.
     *
     * - Preview do Theme Builder: is_preview_mode() retorna true quando o Elementor
     *   está renderizando um template (header, footer, single, archive) para preview.
     *   O parâmetro GET pode estar ausente nesses casos.
     */
    private function is_elementor_editor(): bool {
        // Verificação via parâmetro GET — disponível desde o início da requisição,
        // antes mesmo de qualquer plugin ser carregado.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['elementor-preview'] ) ) {
            return true;
        }

        // Verificação via API do Elementor — cobre previews e outros contextos
        // onde o parâmetro GET pode não estar presente.
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $plugin = \Elementor\Plugin::$instance;
            if (
                isset( $plugin->preview ) &&
                method_exists( $plugin->preview, 'is_preview_mode' ) &&
                $plugin->preview->is_preview_mode()
            ) {
                return true;
            }
        }

        return false;
    }
}
