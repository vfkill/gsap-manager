<?php
defined( 'ABSPATH' ) || exit;

class GSAP_Admin {

    const PAGE_SLUG = 'gsap-manager';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    // ─── Menu ───────────────────────────────────────────────────────────────
    public function add_menu(): void {
        add_options_page(
            'GSAP Manager',
            'GSAP Manager',
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    // ─── Assets admin ───────────────────────────────────────────────────────
    public function enqueue_admin_assets( string $hook ): void {
        if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
            return;
        }
        wp_enqueue_style(
            'gsap-manager-admin',
            GSAP_MANAGER_URL . 'assets/css/admin.css',
            [],
            GSAP_MANAGER_VERSION
        );
        wp_enqueue_script(
            'gsap-manager-admin',
            GSAP_MANAGER_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            GSAP_MANAGER_VERSION,
            true
        );
    }

    // ─── Registro ───────────────────────────────────────────────────────────
    public function register_settings(): void {
        register_setting(
            'gsap_manager_group',
            GSAP_MANAGER_OPTION,
            [ $this, 'sanitize' ]
        );
    }

    public function sanitize( $input ): array {
        $defaults = gsap_manager_defaults();
        $out      = [];

        $out['enabled']        = ! empty( $input['enabled'] );
        $out['source']         = in_array( $input['source'] ?? '', [ 'cdn', 'local' ], true ) ? $input['source'] : 'cdn';
        $out['gsap_version']   = preg_replace( '/[^0-9\.]/', '', $input['gsap_version'] ?? '3.12.5' ) ?: '3.12.5';
        $out['load_in_footer'] = ! empty( $input['load_in_footer'] );
        $out['load_on']        = in_array( $input['load_on'] ?? '', [ 'all', 'front', 'selected' ], true ) ? $input['load_on'] : 'all';
        $out['selected_ids']   = preg_replace( '/[^0-9,\s]/', '', $input['selected_ids'] ?? '' );
        $out['custom_init']      = wp_kses( $input['custom_init'] ?? '', [] );
        $out['auto_animations']  = ! empty( $input['auto_animations'] );

        $out['plugins'] = [];
        foreach ( array_keys( $defaults['plugins'] ) as $plugin ) {
            $out['plugins'][ $plugin ] = ! empty( $input['plugins'][ $plugin ] );
        }

        $out['smoother_smooth']    = min( 10, max( 0, floatval( $input['smoother_smooth'] ?? 1 ) ) );
        $out['smoother_effects']   = ! empty( $input['smoother_effects'] );
        $out['smoother_normalize'] = ! empty( $input['smoother_normalize'] );
        $out['smoother_wrapper']   = sanitize_text_field( $input['smoother_wrapper'] ?? '#smooth-wrapper' ) ?: '#smooth-wrapper';
        $out['smoother_content']   = sanitize_text_field( $input['smoother_content'] ?? '#smooth-content' ) ?: '#smooth-content';

        $out['highlight_color'] = sanitize_hex_color( $input['highlight_color'] ?? '' ) ?: '';
        $out['progress_color']  = sanitize_hex_color( $input['progress_color'] ?? '' ) ?: '';

        return $out;
    }

    // ─── Render ─────────────────────────────────────────────────────────────
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $s        = gsap_manager_get_settings();
        $tab      = sanitize_key( $_GET['tab'] ?? 'settings' );
        $saved    = isset( $_GET['settings-updated'] );

        $bonus_label = ' <span class="gsap-badge gsap-badge--bonus">Bonus</span>';

        $plugins_info = [
            // ── Públicos (CDN ou local) ──────────────────────────────────────
            'ScrollTrigger'    => [ 'desc' => 'Animações ativadas por scroll. Essencial para a maioria dos projetos.', 'popular' => true ],
            'ScrollToPlugin'   => [ 'desc' => 'Scroll suave até elementos ou posições da página.' ],
            'Draggable'        => [ 'desc' => 'Torna elementos arrastáveis com física realista.' ],
            'Flip'             => [ 'desc' => 'Animações de layout FLIP (First Last Invert Play).' ],
            'MotionPathPlugin' => [ 'desc' => 'Anima elementos ao longo de caminhos SVG.' ],
            'TextPlugin'       => [ 'desc' => 'Anima texto caractere por caractere com GSAP.' ],
            'Observer'         => [ 'desc' => 'Detecta gestos, scroll e interações de forma unificada.' ],
            'CustomEase'       => [ 'desc' => 'Cria curvas de easing 100% customizadas.' ],
            'EasePack'         => [ 'desc' => 'Pacote com easings extras: SlowMo, ExpoScale, RoughEase e mais.' ],
            'CSSRulePlugin'    => [ 'desc' => 'Anima regras CSS diretamente em stylesheets (pseudo-elementos, hover, etc).' ],
            // ── Bonus (local — assets/js/vendor/) ───────────────────────────
            'ScrollSmoother'     => [ 'desc' => 'Scroll suavizado com inércia e parallax. Requer ScrollTrigger.', 'popular' => true, 'bonus' => true, 'classes' => [ 'gsap-speed-slow', 'gsap-speed-fast' ] ],
            'SplitText'          => [ 'desc' => 'Divide texto em linhas, palavras e caracteres para animação granular.', 'popular' => true, 'bonus' => true, 'custom_js' => true ],
            'MorphSVGPlugin'     => [ 'desc' => 'Transição suave entre qualquer forma SVG.', 'popular' => true, 'bonus' => true, 'classes' => [ 'gsap-morph-svg' ] ],
            'DrawSVGPlugin'      => [ 'desc' => 'Anima o traçado de paths SVG como se estivesse sendo desenhado.', 'popular' => true, 'bonus' => true, 'classes' => [ 'gsap-draw-svg' ] ],
            'InertiaPlugin'      => [ 'desc' => 'Adiciona momentum/inércia ao Draggable para movimentos físicos.', 'bonus' => true, 'custom_js' => true ],
            'ScrambleTextPlugin' => [ 'desc' => 'Embaralha texto com caracteres aleatórios enquanto revela o conteúdo final.', 'bonus' => true, 'classes' => [ 'gsap-scramble' ] ],
            'CustomBounce'       => [ 'desc' => 'Gera easings de bounce customizados para uso com CustomEase.', 'bonus' => true, 'custom_js' => true ],
            'CustomWiggle'       => [ 'desc' => 'Gera easings de vibração (wiggle) customizados para uso com CustomEase.', 'bonus' => true, 'custom_js' => true ],
            'Physics2DPlugin'    => [ 'desc' => 'Física 2D real: gravidade, velocidade e fricção em animações.', 'bonus' => true, 'custom_js' => true ],
            'PhysicsPropsPlugin' => [ 'desc' => 'Aplica física (velocidade/aceleração) a qualquer propriedade numérica.', 'bonus' => true, 'custom_js' => true ],
            'MotionPathHelper'   => [ 'desc' => 'Interface visual para editar motion paths diretamente no browser (dev).', 'bonus' => true, 'custom_js' => true ],
            'GSDevTools'         => [ 'desc' => 'Player interativo para inspecionar e depurar timelines GSAP (dev).', 'bonus' => true, 'custom_js' => true ],
            'EaselPlugin'        => [ 'desc' => 'Integração com EaselJS/CreateJS para animar elementos canvas.', 'bonus' => true, 'custom_js' => true ],
            'PixiPlugin'         => [ 'desc' => 'Integração com Pixi.js para animar propriedades de display objects.', 'bonus' => true, 'custom_js' => true ],
        ];
        ?>
        <div class="gsap-wrap">

            <!-- Header -->
            <div class="gsap-header">
                <div class="gsap-header__inner">
                    <div class="gsap-logo">
                        <svg width="32" height="32" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="100" rx="16" fill="#0AE448"/>
                            <path d="M22 50C22 34.536 34.536 22 50 22C65.464 22 78 34.536 78 50C78 65.464 65.464 78 50 78" stroke="#000" stroke-width="9" stroke-linecap="round"/>
                            <circle cx="50" cy="78" r="6" fill="#000"/>
                        </svg>
                        <div>
                            <h1>GSAP Manager</h1>
                            <span>v<?php echo esc_html( GSAP_MANAGER_VERSION ); ?></span>
                        </div>
                    </div>
                    <div class="gsap-status <?php echo $s['enabled'] ? 'gsap-status--on' : 'gsap-status--off'; ?>">
                        <span class="gsap-status__dot"></span>
                        <?php echo $s['enabled'] ? 'Ativo' : 'Inativo'; ?>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="gsap-tabs">
                <a href="?page=gsap-manager&tab=settings" class="gsap-tab <?php echo $tab === 'settings' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                    Configurações
                </a>
                <a href="?page=gsap-manager&tab=plugins" class="gsap-tab <?php echo $tab === 'plugins' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
                    Plugins GSAP
                </a>
                <a href="?page=gsap-manager&tab=animacoes" class="gsap-tab <?php echo $tab === 'animacoes' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9V3z"/></svg>
                    Animações por Classe
                </a>
                <a href="?page=gsap-manager&tab=usage" class="gsap-tab <?php echo $tab === 'usage' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    Como Usar
                </a>
            </div>

            <?php if ( $saved ) : ?>
            <div class="gsap-notice gsap-notice--success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Configurações salvas com sucesso!
            </div>
            <?php endif; ?>

            <!-- Conteúdo das tabs
                 IMPORTANTE: todos os painéis são sempre renderizados no HTML.
                 A visibilidade é controlada via CSS (.gsap-tab-panel / .is-active).
                 Isso garante que TODOS os campos do formulário sejam submetidos
                 independente de qual aba está ativa, evitando que configurações
                 de outras abas sejam sobrescritas com valores vazios.
            -->
            <form method="post" action="options.php" id="gsap-form">
                <?php settings_fields( 'gsap_manager_group' ); ?>

                <!-- TAB: Configurações -->
                <div class="gsap-tab-panel <?php echo $tab === 'settings' ? 'is-active' : ''; ?>">
                <div class="gsap-card">
                    <h2 class="gsap-card__title">Configurações Gerais</h2>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Habilitar GSAP</label>
                            <span class="gsap-field__desc">Carrega o GSAP nas páginas do site.</span>
                        </div>
                        <label class="gsap-toggle">
                            <input type="checkbox" name="<?php echo GSAP_MANAGER_OPTION; ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>>
                            <span class="gsap-toggle__slider"></span>
                        </label>
                    </div>

                    <div class="gsap-field">
                        <label class="gsap-label">Fonte dos arquivos</label>
                        <div class="gsap-radio-group">
                            <label class="gsap-radio">
                                <input type="radio" name="<?php echo GSAP_MANAGER_OPTION; ?>[source]" value="cdn" <?php checked( $s['source'], 'cdn' ); ?>>
                                <span class="gsap-radio__box">
                                    <span class="gsap-radio__title">CDN (Recomendado)</span>
                                    <span class="gsap-radio__desc">Carrega do cdnjs.cloudflare.com — cache do browser entre sites</span>
                                </span>
                            </label>
                            <label class="gsap-radio">
                                <input type="radio" name="<?php echo GSAP_MANAGER_OPTION; ?>[source]" value="local" <?php checked( $s['source'], 'local' ); ?>>
                                <span class="gsap-radio__box">
                                    <span class="gsap-radio__title">Local</span>
                                    <span class="gsap-radio__desc">Carrega dos arquivos do plugin — faça o download manual e coloque em <code>assets/js/vendor/</code></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="gsap-field">
                        <label class="gsap-label" for="gsap_version">Versão do GSAP</label>
                        <input type="text" id="gsap_version"
                               name="<?php echo GSAP_MANAGER_OPTION; ?>[gsap_version]"
                               value="<?php echo esc_attr( $s['gsap_version'] ); ?>"
                               class="gsap-input"
                               placeholder="3.12.5">
                        <span class="gsap-field__desc">Versão atual estável: <strong>3.12.5</strong>. Veja todas em <a href="https://cdnjs.com/libraries/gsap" target="_blank" rel="noopener">cdnjs.com</a>.</span>
                    </div>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Animações por Classe</label>
                            <span class="gsap-field__desc">Ativa o sistema de animações automáticas via classe CSS. Adicione <code>gsap-fade-up</code>, <code>gsap-char-reveal</code>, etc. em qualquer elemento HTML.</span>
                        </div>
                        <label class="gsap-toggle">
                            <input type="checkbox" name="<?php echo GSAP_MANAGER_OPTION; ?>[auto_animations]" value="1" <?php checked( ! empty( $s['auto_animations'] ) ); ?>>
                            <span class="gsap-toggle__slider"></span>
                        </label>
                    </div>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Carregar no rodapé</label>
                            <span class="gsap-field__desc">Recomendado para não bloquear a renderização.</span>
                        </div>
                        <label class="gsap-toggle">
                            <input type="checkbox" name="<?php echo GSAP_MANAGER_OPTION; ?>[load_in_footer]" value="1" <?php checked( $s['load_in_footer'] ); ?>>
                            <span class="gsap-toggle__slider"></span>
                        </label>
                    </div>

                    <div class="gsap-field">
                        <label class="gsap-label">Onde carregar</label>
                        <div class="gsap-radio-group gsap-radio-group--row">
                            <label class="gsap-radio gsap-radio--small">
                                <input type="radio" name="<?php echo GSAP_MANAGER_OPTION; ?>[load_on]" value="all" <?php checked( $s['load_on'], 'all' ); ?>>
                                <span class="gsap-radio__box">Em todo o site</span>
                            </label>
                            <label class="gsap-radio gsap-radio--small">
                                <input type="radio" name="<?php echo GSAP_MANAGER_OPTION; ?>[load_on]" value="front" <?php checked( $s['load_on'], 'front' ); ?>>
                                <span class="gsap-radio__box">Só na home</span>
                            </label>
                            <label class="gsap-radio gsap-radio--small">
                                <input type="radio" name="<?php echo GSAP_MANAGER_OPTION; ?>[load_on]" value="selected" <?php checked( $s['load_on'], 'selected' ); ?>>
                                <span class="gsap-radio__box">Páginas específicas</span>
                            </label>
                        </div>
                    </div>

                    <div class="gsap-field <?php echo $s['load_on'] !== 'selected' ? 'gsap-field--hidden' : ''; ?>" id="gsap-selected-ids">
                        <label class="gsap-label" for="selected_ids">IDs das páginas/posts</label>
                        <input type="text" id="selected_ids"
                               name="<?php echo GSAP_MANAGER_OPTION; ?>[selected_ids]"
                               value="<?php echo esc_attr( $s['selected_ids'] ); ?>"
                               class="gsap-input"
                               placeholder="Ex: 1, 42, 87">
                        <span class="gsap-field__desc">Separe os IDs por vírgula. Encontre o ID na URL da edição do post/página.</span>
                    </div>
                </div>

                <div class="gsap-card">
                    <h2 class="gsap-card__title">Personalização Visual</h2>
                    <p class="gsap-card__desc">Cores aplicadas pelas classes de animação. Deixe em branco para usar o padrão verde GSAP (<code>#0AE448</code>).</p>

                    <div class="gsap-field gsap-field--color">
                        <label class="gsap-label" for="highlight_color">Cor do destaque de texto</label>
                        <div class="gsap-color-row">
                            <input type="color" id="highlight_color"
                                   name="<?php echo GSAP_MANAGER_OPTION; ?>[highlight_color]"
                                   value="<?php echo esc_attr( $s['highlight_color'] ?: '#0AE448' ); ?>">
                            <input type="text" id="highlight_color_text"
                                   class="gsap-input gsap-input--color-text"
                                   value="<?php echo esc_attr( $s['highlight_color'] ?: '#0AE448' ); ?>"
                                   placeholder="#0AE448">
                        </div>
                        <span class="gsap-field__desc">Cor do sublinhado animado da classe <code>gsap-text-highlight</code>. Use a variável CSS <code>--gsap-highlight-color</code> no tema para sobrescrever por elemento.</span>
                    </div>

                    <div class="gsap-field gsap-field--color">
                        <label class="gsap-label" for="progress_color">Cor da barra de progresso</label>
                        <div class="gsap-color-row">
                            <input type="color" id="progress_color"
                                   name="<?php echo GSAP_MANAGER_OPTION; ?>[progress_color]"
                                   value="<?php echo esc_attr( $s['progress_color'] ?: '#0AE448' ); ?>">
                            <input type="text" id="progress_color_text"
                                   class="gsap-input gsap-input--color-text"
                                   value="<?php echo esc_attr( $s['progress_color'] ?: '#0AE448' ); ?>"
                                   placeholder="#0AE448">
                        </div>
                        <span class="gsap-field__desc">Cor da classe <code>gsap-progress</code>. Use a variável CSS <code>--gsap-progress-color</code> no tema para sobrescrever por elemento.</span>
                    </div>
                </div>

                <div class="gsap-card">
                    <h2 class="gsap-card__title">JavaScript de Inicialização</h2>
                    <p class="gsap-card__desc">Código executado após o carregamento do GSAP. Ideal para configurações globais como <code>gsap.defaults()</code> ou registro de plugins.</p>
                    <div class="gsap-field">
                        <textarea name="<?php echo GSAP_MANAGER_OPTION; ?>[custom_init]"
                                  id="gsap_custom_init"
                                  class="gsap-textarea"
                                  rows="8"
                                  placeholder="// Exemplo:
gsap.registerPlugin(ScrollTrigger);

gsap.defaults({
    ease: 'power2.out',
    duration: 0.8
});

ScrollTrigger.defaults({
    markers: false
});"><?php echo esc_textarea( $s['custom_init'] ); ?></textarea>
                    </div>
                </div>
                </div><!-- /gsap-tab-panel settings -->

                <!-- TAB: Plugins -->
                <div class="gsap-tab-panel <?php echo $tab === 'plugins' ? 'is-active' : ''; ?>">
                <div class="gsap-card">
                    <h2 class="gsap-card__title">Plugins GSAP</h2>
                    <p class="gsap-card__desc">Ative apenas os plugins que você utiliza para manter o carregamento enxuto.</p>
                    <h3 class="gsap-plugins-section-title">Plugins públicos <span>carregados via CDN ou local</span></h3>
                    <div class="gsap-plugins-grid">
                        <?php foreach ( $plugins_info as $name => $info ) : if ( ! empty( $info['bonus'] ) ) continue; ?>
                        <label class="gsap-plugin-card <?php echo ! empty( $s['plugins'][ $name ] ) ? 'is-active' : ''; ?>">
                            <input type="checkbox"
                                   name="<?php echo GSAP_MANAGER_OPTION; ?>[plugins][<?php echo esc_attr( $name ); ?>]"
                                   value="1"
                                   <?php checked( ! empty( $s['plugins'][ $name ] ) ); ?>>
                            <div class="gsap-plugin-card__header">
                                <span class="gsap-plugin-card__name"><?php echo esc_html( $name ); ?></span>
                                <?php if ( ! empty( $info['popular'] ) ) : ?>
                                <span class="gsap-badge">Popular</span>
                                <?php endif; ?>
                                <span class="gsap-plugin-card__check">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                            </div>
                            <p class="gsap-plugin-card__desc"><?php echo esc_html( $info['desc'] ); ?></p>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="gsap-plugins-section-title" style="margin-top:1.5rem">Plugins bonus <span>carregados do servidor local (assets/js/vendor/)</span></h3>
                    <div class="gsap-plugins-grid gsap-plugins-grid--bonus">
                        <?php foreach ( $plugins_info as $name => $info ) : if ( empty( $info['bonus'] ) ) continue; ?>
                        <label class="gsap-plugin-card gsap-plugin-card--bonus <?php echo ! empty( $s['plugins'][ $name ] ) ? 'is-active' : ''; ?>">
                            <input type="checkbox"
                                   name="<?php echo GSAP_MANAGER_OPTION; ?>[plugins][<?php echo esc_attr( $name ); ?>]"
                                   value="1"
                                   <?php checked( ! empty( $s['plugins'][ $name ] ) ); ?>>
                            <div class="gsap-plugin-card__header">
                                <span class="gsap-plugin-card__name"><?php echo esc_html( $name ); ?></span>
                                <?php if ( ! empty( $info['popular'] ) ) : ?>
                                <span class="gsap-badge">Popular</span>
                                <?php endif; ?>
                                <span class="gsap-badge gsap-badge--bonus">Bonus</span>
                                <span class="gsap-plugin-card__check">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                            </div>
                            <p class="gsap-plugin-card__desc"><?php echo esc_html( $info['desc'] ); ?></p>
                            <?php if ( ! empty( $info['classes'] ) ) : ?>
                            <p class="gsap-plugin-card__note gsap-plugin-card__note--classes">
                                <?php foreach ( $info['classes'] as $cls ) : ?>
                                <code><?php echo esc_html( $cls ); ?></code>
                                <?php endforeach; ?>
                            </p>
                            <?php elseif ( ! empty( $info['custom_js'] ) ) : ?>
                            <p class="gsap-plugin-card__note">JS customizado necessário</p>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ScrollSmoother: configurações avançadas -->
                <div class="gsap-card <?php echo empty( $s['plugins']['ScrollSmoother'] ) ? 'gsap-field--hidden' : ''; ?>" id="gsap-smoother-settings">
                    <h2 class="gsap-card__title">Configurações do ScrollSmoother</h2>
                    <div class="gsap-notice gsap-notice--info" style="margin-bottom:1rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>ScrollSmoother é um plugin bonus e é sempre carregado do servidor local (<code>assets/js/vendor/</code>). A configuração de fonte <strong>CDN / Local</strong> nas configurações gerais não afeta este plugin.</span>
                    </div>
                    <p class="gsap-card__desc">
                        Os elementos <code>smooth-wrapper</code> e <code>smooth-content</code> são injetados
                        <strong>automaticamente</strong> pelo plugin via <code>wp_body_open</code> — compatível
                        com Hello Elementor e demais temas modernos. Nenhuma alteração no tema é necessária.
                    </p>

                    <div class="gsap-field">
                        <label class="gsap-label" for="smoother_smooth">Suavidade do scroll</label>
                        <input type="number" id="smoother_smooth"
                               name="<?php echo GSAP_MANAGER_OPTION; ?>[smoother_smooth]"
                               value="<?php echo esc_attr( $s['smoother_smooth'] ?? 1 ); ?>"
                               class="gsap-input gsap-input--small"
                               min="0" max="10" step="0.1">
                        <span class="gsap-field__desc"><strong>0</strong> = sem suavidade (scroll nativo) · <strong>1</strong> = suave · <strong>2+</strong> = muito suave. Padrão: 1</span>
                    </div>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Habilitar Effects nativos (<code>data-speed</code> / <code>data-lag</code>)</label>
                            <span class="gsap-field__desc">Faz o ScrollSmoother escanear automaticamente o HTML em busca dos atributos <code>data-speed="0.5"</code> e <code>data-lag="0.3"</code> em qualquer elemento. <strong>Independente das classes <code>gsap-speed-slow</code> / <code>gsap-speed-fast</code></strong>, que funcionam sem esta opção.</span>
                        </div>
                        <label class="gsap-toggle">
                            <input type="checkbox" name="<?php echo GSAP_MANAGER_OPTION; ?>[smoother_effects]" value="1" <?php checked( ! empty( $s['smoother_effects'] ) ); ?>>
                            <span class="gsap-toggle__slider"></span>
                        </label>
                    </div>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Normalize Scroll</label>
                            <span class="gsap-field__desc">Normaliza o comportamento do scroll entre dispositivos (mouse, touch, trackpad). Recomendado para experiências altamente customizadas.</span>
                        </div>
                        <label class="gsap-toggle">
                            <input type="checkbox" name="<?php echo GSAP_MANAGER_OPTION; ?>[smoother_normalize]" value="1" <?php checked( ! empty( $s['smoother_normalize'] ) ); ?>>
                            <span class="gsap-toggle__slider"></span>
                        </label>
                    </div>

                    <div class="gsap-field">
                        <label class="gsap-label" for="smoother_wrapper">Seletor do Wrapper</label>
                        <input type="text" id="smoother_wrapper"
                               name="<?php echo GSAP_MANAGER_OPTION; ?>[smoother_wrapper]"
                               value="<?php echo esc_attr( $s['smoother_wrapper'] ?? '#smooth-wrapper' ); ?>"
                               class="gsap-input"
                               placeholder="#smooth-wrapper">
                        <span class="gsap-field__desc">CSS selector do elemento externo. Padrão: <code>#smooth-wrapper</code></span>
                    </div>

                    <div class="gsap-field">
                        <label class="gsap-label" for="smoother_content">Seletor do Content</label>
                        <input type="text" id="smoother_content"
                               name="<?php echo GSAP_MANAGER_OPTION; ?>[smoother_content]"
                               value="<?php echo esc_attr( $s['smoother_content'] ?? '#smooth-content' ); ?>"
                               class="gsap-input"
                               placeholder="#smooth-content">
                        <span class="gsap-field__desc">CSS selector do elemento interno. Padrão: <code>#smooth-content</code></span>
                    </div>
                </div>

                </div><!-- /gsap-tab-panel plugins -->

                <!-- Botão salvar — sempre no DOM; oculto via CSS nas abas sem formulário -->
                <div class="gsap-actions <?php echo in_array( $tab, [ 'animacoes', 'usage' ] ) ? 'gsap-field--hidden' : ''; ?>">
                    <?php submit_button( 'Salvar Configurações', 'primary', 'submit', false, [ 'class' => 'gsap-btn gsap-btn--primary' ] ); ?>
                </div>

            </form>

            <!-- TAB: Animações por Classe (fora do form — apenas conteúdo de referência) -->
            <?php if ( true ) : // sempre renderiza — visibilidade via CSS

            // Atributos globais — aceitos (quase) por toda classe de scroll/trigger.
            $anim_global_attrs = [
                [ 'name' => 'data-gsap-duration', 'desc' => 'Duração da animação em segundos',           'ex' => '1.2' ],
                [ 'name' => 'data-gsap-delay',    'desc' => 'Atraso antes de começar (segundos)',         'ex' => '0.3' ],
                [ 'name' => 'data-gsap-ease',     'desc' => 'Curva de easing do GSAP',                    'ex' => 'elastic.out(1,0.5)' ],
                [ 'name' => 'data-gsap-distance', 'desc' => 'Distância do movimento em px',               'ex' => '80' ],
                [ 'name' => 'data-gsap-stagger',  'desc' => 'Intervalo entre filhos (segundos)',          'ex' => '0.15' ],
                [ 'name' => 'data-gsap-start',    'desc' => 'Posição de início do ScrollTrigger',         'ex' => 'top 70%' ],
                [ 'name' => 'data-gsap-end',      'desc' => 'Posição de fim do ScrollTrigger',            'ex' => 'bottom 20%' ],
                [ 'name' => 'data-gsap-scrub',    'desc' => 'Suavização do scrub (segundos)',             'ex' => '1' ],
            ];

            // Referência completa: cada item tem class/short/req/attrs/example (+more opcional pra HTML extra).
            $anim_ref = [
                'Texto' => [
                    [
                        'class'   => 'gsap-char-reveal',
                        'short'   => 'Cada caractere sobe do clip, revelando o texto.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'combine' => [ 'gsap-char-scrub — revela char-a-char vinculado ao scroll' ],
                        'example' => '<h2 class="gsap-char-reveal">Título Incrível</h2>',
                    ],
                    [
                        'class'   => 'gsap-char-color',
                        'short'   => 'Char-a-char muda da cor inicial para a cor final do Elementor conforme o scroll.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-from-color', 'desc' => 'Cor inicial "apagada"',                 'ex' => '#616161' ],
                            [ 'name' => 'data-gsap-to-color',   'desc' => 'Sobrescreve a cor final (padrão: cor do Elementor)', 'ex' => '#FFFFFF' ],
                        ],
                        'example' => '<p class="gsap-char-color">Texto que ilumina ao rolar</p>',
                    ],
                    [
                        'class'   => 'gsap-word-reveal',
                        'short'   => 'Cada palavra sobe do clip individualmente.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'combine' => [ 'gsap-word-scrub — revela palavra-a-palavra vinculado ao scroll' ],
                        'example' => '<h3 class="gsap-word-reveal">Frase por palavras</h3>',
                    ],
                    [
                        'class'   => 'gsap-word-blur',
                        'short'   => 'Palavras entram em foco com blur + slide + opacity. Mobile mantém blur por padrão.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-blur',        'desc' => 'Intensidade inicial do blur em px (padrão: 8)', 'ex' => '14' ],
                            [ 'name' => 'data-gsap-mobile-blur', 'desc' => '"off" desliga blur em telas <1024px',           'ex' => 'off' ],
                        ],
                        'combine' => [ 'gsap-word-scrub — vinculado ao scroll' ],
                        'example' => '<p class="gsap-word-blur">Palavras entrando em foco</p>',
                    ],
                    [
                        'class'   => 'gsap-text-focus',
                        'short'   => 'Foco gaussiano: letras do meio de cada palavra entram maiores, rotacionadas e desfocadas — stagger do centro pra fora. Inspirado em enumeramolecular.com.',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-scale-peak',  'desc' => 'Escala máxima no centro (padrão: 2.1)', 'ex' => '2.1' ],
                            [ 'name' => 'data-gsap-y-peak',      'desc' => 'Deslocamento Y máximo em px (padrão: 60)', 'ex' => '60' ],
                            [ 'name' => 'data-gsap-rotation',    'desc' => 'Ângulo do leque em graus (padrão: 4)', 'ex' => '4' ],
                            [ 'name' => 'data-gsap-blur',        'desc' => 'Blur inicial em px (padrão: 12)', 'ex' => '12' ],
                            [ 'name' => 'data-gsap-mobile-blur', 'desc' => '"off" desliga blur em <1024px', 'ex' => 'off' ],
                        ],
                        'example' => '<h1 class="gsap-text-focus">Clinical omics, simplified</h1>',
                    ],
                    [
                        'class'   => 'gsap-text-fade',
                        'short'   => 'Texto completo faz fade + sobe suavemente.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'combine' => [ 'gsap-scrub — progresso vinculado ao scroll' ],
                        'example' => '<p class="gsap-text-fade">Parágrafo de texto</p>',
                    ],
                    [
                        'class'   => 'gsap-typewriter',
                        'short'   => 'Digita o conteúdo do elemento como uma máquina de escrever.',
                        'req'     => '',
                        'attrs'   => [],
                        'example' => '<span class="gsap-typewriter">Texto digitado...</span>',
                    ],
                    [
                        'class'   => 'gsap-text-blur',
                        'short'   => 'Começa borrado e vai nitidificando.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'combine' => [ 'gsap-scrub — progresso vinculado ao scroll' ],
                        'example' => '<h2 class="gsap-text-blur">Título desfocado</h2>',
                    ],
                    [
                        'class'   => 'gsap-text-highlight',
                        'short'   => 'Sublinhado colorido varre o texto (use em &lt;span&gt;).',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'combine' => [ 'gsap-scrub — progresso vinculado ao scroll' ],
                        'example' => '<h2>Nosso <span class="gsap-text-highlight">diferencial</span></h2>',
                    ],
                    [
                        'class'   => 'gsap-scramble',
                        'short'   => 'Texto embaralha com caracteres aleatórios até revelar.',
                        'req'     => 'ScrambleTextPlugin',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-chars', 'desc' => 'Charset customizado ou "upperCase"/"lowerCase"', 'ex' => '01' ],
                        ],
                        'example' => '<span class="gsap-scramble">Texto secreto</span>',
                    ],
                ],
                'Imagens' => [
                    [
                        'class'   => 'gsap-img-reveal',
                        'short'   => 'Clip-path abre a imagem (padrão: da esquerda).',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-dir', 'desc' => 'Direção da abertura', 'ex' => 'right|top|bottom' ],
                        ],
                        'example' => '<img class="gsap-img-reveal" src="foto.jpg">',
                    ],
                    [
                        'class'   => 'gsap-img-zoom',
                        'short'   => 'Entra com zoom + fade.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'example' => '<img class="gsap-img-zoom" src="foto.jpg">',
                    ],
                    [
                        'class'   => 'gsap-img-fade',
                        'short'   => 'Fade simples na imagem.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'example' => '<img class="gsap-img-fade" src="foto.jpg">',
                    ],
                    [
                        'class'   => 'gsap-img-parallax',
                        'short'   => 'Parallax no scroll. O elemento pai vira o container.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'example' => '<div style="overflow:hidden"><img class="gsap-img-parallax" src="foto.jpg"></div>',
                    ],
                    [
                        'class'   => 'gsap-zoom-reveal',
                        'short'   => 'Pina a seção e escala o filho (img/vídeo) de pequeno até fullscreen enquanto o usuário scrolla.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-from',  'desc' => 'Escala inicial',           'ex' => '0.15' ],
                            [ 'name' => 'data-gsap-end',   'desc' => 'Distância de scroll',      'ex' => '+=150%' ],
                            [ 'name' => 'data-gsap-scrub', 'desc' => 'Suavização do scrub',      'ex' => '1' ],
                        ],
                        'example' => '<div class="gsap-zoom-reveal" style="height:100vh"><img src="foto.jpg"></div>',
                    ],
                    [
                        'class'   => 'gsap-img-scroll-scale',
                        'short'   => 'Imagem voa de uma posição offset e escala até preencher o container (sem pin). Container deve ter <code>gsap-scale-container</code>; imagem com <code>width:100%;height:100%;object-fit:cover</code>.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-x',          'desc' => 'Offset X inicial em px (ou % com sufixo)', 'ex' => '222' ],
                            [ 'name' => 'data-gsap-y',          'desc' => 'Offset Y inicial em px (ou % com sufixo)', 'ex' => '-123' ],
                            [ 'name' => 'data-gsap-from-scale', 'desc' => 'Escala inicial da imagem (padrão: 0.578)', 'ex' => '0.578' ],
                            [ 'name' => 'data-gsap-to-scale',   'desc' => 'Escala final (padrão: 1)',                 'ex' => '1' ],
                            [ 'name' => 'data-gsap-container',  'desc' => 'Seletor CSS do container (override)',      'ex' => '.hero' ],
                            [ 'name' => 'data-gsap-scrub',      'desc' => 'Suavização (padrão: true, tight)',         'ex' => '1' ],
                            [ 'name' => 'data-gsap-min-width',  'desc' => 'Largura mínima pra rodar (padrão: 0)',     'ex' => '1200' ],
                            [ 'name' => 'data-gsap-debug',      'desc' => '"1" loga o container detectado',           'ex' => '1' ],
                        ],
                        'example' => '<div class="gsap-scale-container" style="height:100vh"><img class="gsap-img-scroll-scale" src="foto.jpg" data-gsap-x="222" data-gsap-y="-123" data-gsap-from-scale="0.578" style="width:100%;height:100%;object-fit:cover"></div>',
                    ],
                    [
                        'class'   => 'gsap-img-scroll-scale-pin',
                        'short'   => 'Igual ao <code>gsap-img-scroll-scale</code>, mas pina o container no topo do viewport até a animação terminar (estilo dsgngroup.it).',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-x',          'desc' => 'Offset X inicial em px',               'ex' => '222' ],
                            [ 'name' => 'data-gsap-y',          'desc' => 'Offset Y inicial em px',               'ex' => '-123' ],
                            [ 'name' => 'data-gsap-from-scale', 'desc' => 'Escala inicial',                       'ex' => '0.578' ],
                            [ 'name' => 'data-gsap-to-scale',   'desc' => 'Escala final',                         'ex' => '1' ],
                            [ 'name' => 'data-gsap-end',        'desc' => 'Duração do pin',                       'ex' => 'bottom+=100% top' ],
                            [ 'name' => 'data-gsap-min-width',  'desc' => 'Gate por largura',                     'ex' => '1200' ],
                            [ 'name' => 'data-gsap-debug',      'desc' => '"1" loga o container detectado',       'ex' => '1' ],
                        ],
                        'example' => '<div class="gsap-scale-container" style="height:100vh"><img class="gsap-img-scroll-scale-pin" src="foto.jpg" data-gsap-x="222" data-gsap-y="-123" data-gsap-from-scale="0.578" style="width:100%;height:100%;object-fit:cover"></div>',
                    ],
                ],
                'Elementos' => [
                    [ 'class' => 'gsap-fade-up',    'short' => 'Fade + sobe para a posição original.',     'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-fade-up">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-down',  'short' => 'Fade + desce para a posição original.',    'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-fade-down">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-left',  'short' => 'Fade + vem da direita para posição.',      'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-fade-left">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-right', 'short' => 'Fade + vem da esquerda para posição.',     'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-fade-right">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-in',    'short' => 'Fade simples, sem movimento.',             'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-fade-in">Conteúdo</div>' ],
                    [ 'class' => 'gsap-scale-in',   'short' => 'Cresce de ~82% até o tamanho real.',        'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-scale-in">Card</div>' ],
                    [ 'class' => 'gsap-scale-out',  'short' => 'Encolhe de ~118% até o tamanho real.',      'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-scale-out">Card</div>' ],
                    [ 'class' => 'gsap-rotate-in',  'short' => 'Leve rotação + fade.',                      'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-rotate-in">Elemento</div>' ],
                    [ 'class' => 'gsap-flip-in',    'short' => 'Rotação 3D no eixo X (virar página).',      'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-flip-in">Card 3D</div>' ],
                    [ 'class' => 'gsap-clip-left',  'short' => 'Clip-path revela da esquerda para direita.', 'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-clip-left">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-right', 'short' => 'Clip-path revela da direita para esquerda.', 'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-clip-right">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-top',   'short' => 'Clip-path revela de cima para baixo.',       'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-clip-top">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-bottom','short' => 'Clip-path revela de baixo para cima.',       'req' => 'ScrollTrigger', 'attrs' => [], 'example' => '<div class="gsap-clip-bottom">Div revelada</div>' ],
                ],
                'Grupos (Stagger)' => [
                    [ 'class' => 'gsap-stagger',        'short' => 'Filhos entram em cascata: fade + sobe.',         'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.15' ] ], 'example' => '<ul class="gsap-stagger"><li>Item 1</li><li>Item 2</li></ul>' ],
                    [ 'class' => 'gsap-stagger-left',   'short' => 'Filhos entram em cascata da direita.',           'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-left">...</div>' ],
                    [ 'class' => 'gsap-stagger-right',  'short' => 'Filhos entram em cascata da esquerda.',          'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-right">...</div>' ],
                    [ 'class' => 'gsap-stagger-scale', 'short' => 'Filhos entram em cascata com escala.',            'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-scale">...</div>' ],
                    [ 'class' => 'gsap-stagger-fade',  'short' => 'Filhos entram em cascata com fade simples.',      'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-fade">...</div>' ],
                    [ 'class' => 'gsap-stagger-rotate','short' => 'Filhos entram em cascata com rotação.',           'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-rotate">...</div>' ],
                    [ 'class' => 'gsap-stagger-center','short' => 'Filhos entram em cascata a partir do centro — expande para fora simultaneamente.', 'req' => 'ScrollTrigger', 'attrs' => [ [ 'name' => 'data-gsap-stagger', 'desc' => 'Intervalo entre filhos', 'ex' => '0.1' ] ], 'example' => '<div class="gsap-stagger-center">...</div>' ],
                ],
                'Especiais' => [
                    [
                        'class'   => 'gsap-counter',
                        'short'   => 'Anima um número do zero até o valor alvo.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-prefix',    'desc' => 'Texto antes do número',     'ex' => 'R$ ' ],
                            [ 'name' => 'data-gsap-suffix',    'desc' => 'Texto depois do número',    'ex' => ' mil' ],
                            [ 'name' => 'data-gsap-from',      'desc' => 'Valor inicial (padrão: 0)', 'ex' => '0' ],
                            [ 'name' => 'data-gsap-separator', 'desc' => 'Separador de milhar',       'ex' => '.' ],
                        ],
                        'example' => '<span class="gsap-counter" data-gsap-suffix=" mil" data-gsap-separator=".">1500</span>',
                    ],
                    [
                        'class'   => 'gsap-marquee',
                        'short'   => 'Faixa de conteúdo em loop horizontal.',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-speed', 'desc' => 'Velocidade do loop',              'ex' => '50' ],
                            [ 'name' => 'data-gsap-dir',   'desc' => 'Direção do loop',                  'ex' => 'right' ],
                        ],
                        'example' => '<div class="gsap-marquee" data-gsap-speed="50"><span>Item</span><span>Item</span></div>',
                    ],
                    [
                        'class'   => 'gsap-parallax',
                        'short'   => 'Parallax genérico (não-imagem). O pai deve ter <code>overflow:hidden</code>.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'example' => '<div class="gsap-parallax">Elemento flutuante</div>',
                    ],
                    [
                        'class'   => 'gsap-reveal-line',
                        'short'   => 'Linha cresce de largura zero. Perfeito para divisores.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-axis', 'desc' => 'Eixo da linha',  'ex' => 'height' ],
                        ],
                        'example' => '<hr class="gsap-reveal-line">',
                    ],
                    [
                        'class'   => 'gsap-progress',
                        'short'   => 'Barra de progresso animada. Define a largura alvo no estilo.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [],
                        'example' => '<div class="gsap-progress" style="width:80%"></div>',
                    ],
                    [
                        'class'   => 'gsap-mask-reveal',
                        'short'   => 'Hero com logo-máscara crescente + parallax interno + overlay (estilo dropedition.com). Use num widget <strong>HTML</strong> do Elementor — a JS gera toda a estrutura interna.',
                        'req'     => 'ScrollTrigger',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-logo',             'desc' => 'URL do SVG usado como máscara (obrigatório)',                       'ex' => '/wp-content/uploads/logo.svg' ],
                            [ 'name' => 'data-gsap-image',            'desc' => 'URL da imagem de fundo (obrigatório)',                              'ex' => '/wp-content/uploads/hero.jpg' ],
                            [ 'name' => 'data-gsap-distance',         'desc' => 'Altura total da section em vh (padrão: 100)',                       'ex' => '200' ],
                            [ 'name' => 'data-gsap-mask-from',        'desc' => 'Tamanho inicial da máscara em % (padrão: 80)',                      'ex' => '80' ],
                            [ 'name' => 'data-gsap-mask-to',          'desc' => 'Tamanho final da máscara em % (padrão: 110)',                       'ex' => '110' ],
                            [ 'name' => 'data-gsap-mask-mobile-from', 'desc' => 'Override do mask-from em telas ≤768px',                              'ex' => '50' ],
                            [ 'name' => 'data-gsap-mask-mobile-to',   'desc' => 'Override do mask-to em telas ≤768px',                                'ex' => '100' ],
                            [ 'name' => 'data-gsap-overlay-opacity',  'desc' => 'Opacidade final do overlay (padrão: 0.8)',                           'ex' => '0.8' ],
                            [ 'name' => 'data-gsap-overlay-color',    'desc' => 'Cor do overlay (padrão: #ffffff)',                                    'ex' => '#ffffff' ],
                            [ 'name' => 'data-gsap-parallax',         'desc' => 'Desloc. Y interno da imagem em % (padrão: 20)',                      'ex' => '20' ],
                        ],
                        'example' => '<div class="gsap-mask-reveal"
     data-gsap-logo="https://seusite.com/wp-content/uploads/logo.svg"
     data-gsap-image="https://seusite.com/wp-content/uploads/hero.jpg"></div>',
                        'howto'   => [
                            'Upload no WP → <strong>Mídia</strong>: logo em SVG (silhueta preta, fundo transparente) + imagem hero (jpg/webp ≥1920px). Copie as URLs.',
                            'No Elementor, arraste o widget <strong>HTML</strong> para a posição desejada (geralmente topo da página, sem container ao redor).',
                            'Cole o snippet acima e troque as duas URLs.',
                            'Publique e role a página — a logo cresce, a imagem faz parallax interno e o fundo desvanece pra branco enquanto a section sai da viewport.',
                        ],
                        'tip'     => '<strong>Duração:</strong> o padrão <code>data-gsap-distance="100"</code> consome 1 viewport de scroll. Use <code>"200"</code> ou <code>"300"</code> para efeito mais longo (estilo dropedition.com) — a section fica pinada por mais tempo.',
                    ],
                ],
                'SVG' => [
                    [
                        'class'   => 'gsap-draw-svg',
                        'short'   => 'Anima o stroke de um path/shape SVG de 0% a 100%, como se estivesse sendo desenhado.',
                        'req'     => 'DrawSVGPlugin',
                        'attrs'   => [],
                        'combine' => [ 'gsap-scrub — vincula o desenho ao scroll' ],
                        'example' => '<path class="gsap-draw-svg" d="M10 80 Q 95 10 180 80">',
                    ],
                    [
                        'class'   => 'gsap-morph-svg',
                        'short'   => 'Transição suave de uma forma SVG para outra ao entrar na viewport.',
                        'req'     => 'MorphSVGPlugin',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-target', 'desc' => 'Seletor do shape alvo (obrigatório)', 'ex' => '#shape-b' ],
                        ],
                        'example' => '<path class="gsap-morph-svg" data-gsap-target="#shape-b" d="...">',
                    ],
                ],
                'ScrollSmoother — Parallax' => [
                    [
                        'class'   => 'gsap-speed-slow',
                        'short'   => 'Parallax lento: move a 0.5× do scroll — efeito de fundo/profundidade.',
                        'req'     => 'ScrollSmoother',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-speed', 'desc' => 'Velocidade customizada (override)', 'ex' => '0.3' ],
                        ],
                        'example' => '<div class="gsap-speed-slow">Elemento distante</div>',
                    ],
                    [
                        'class'   => 'gsap-speed-fast',
                        'short'   => 'Parallax rápido: move a 1.5× do scroll — efeito de primeiro plano.',
                        'req'     => 'ScrollSmoother',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-speed', 'desc' => 'Velocidade customizada (override)', 'ex' => '2' ],
                        ],
                        'example' => '<div class="gsap-speed-fast">Elemento próximo</div>',
                    ],
                ],
                'Hover' => [
                    [
                        'class'   => 'gsap-magnetic',
                        'short'   => 'O elemento atrai o cursor como um ímã. Ideal para botões.',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-strength', 'desc' => 'Força do ímã (padrão: 0.3)', 'ex' => '0.4' ],
                        ],
                        'example' => '<button class="gsap-magnetic">Clique aqui</button>',
                    ],
                    [
                        'class'   => 'gsap-tilt',
                        'short'   => 'Inclinação 3D ao passar o mouse.',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-strength', 'desc' => 'Amplitude do tilt em graus', 'ex' => '14' ],
                        ],
                        'example' => '<div class="gsap-tilt">Card 3D</div>',
                    ],
                    [
                        'class'   => 'gsap-hover-lift',
                        'short'   => 'Levita suavemente ao hover.',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-distance', 'desc' => 'Distância do lift em px (negativo = sobe)', 'ex' => '-8' ],
                        ],
                        'example' => '<div class="gsap-hover-lift">Card flutuante</div>',
                    ],
                    [
                        'class'   => 'gsap-char-stretch-hover',
                        'short'   => 'Cada caractere estica em Y conforme o mouse passa, com decay nos vizinhos. Incrível em fonts condensed (Six Caps, Bebas Neue, Anton, Oswald).',
                        'req'     => '',
                        'attrs'   => [
                            [ 'name' => 'data-gsap-scale',     'desc' => 'Amplitude do scaleY (padrão: 0.2)',       'ex' => '0.2' ],
                            [ 'name' => 'data-gsap-neighbors', 'desc' => 'Vizinhos afetados (padrão: 1)',           'ex' => '1' ],
                            [ 'name' => 'data-gsap-duration',  'desc' => 'Duração por caractere em s (padrão: 0.4)', 'ex' => '0.4' ],
                        ],
                        'example' => '<h1 class="gsap-char-stretch-hover" style="font-family:\'Bebas Neue\'">TYPOGRAPHY</h1>',
                    ],
                ],
            ];

            // Helper: monta string de busca (nome + descrição + atributos) pra filtro client-side.
            $gsap_make_search = function ( array $item ): string {
                $parts = [ $item['class'], $item['short'] ];
                foreach ( $item['attrs'] ?? [] as $a ) {
                    $parts[] = $a['name'] . ' ' . ( $a['desc'] ?? '' );
                }
                return strtolower( wp_strip_all_tags( implode( ' ', $parts ) ) );
            };

            // Grupos abertos por padrão — mais usados.
            $open_by_default = [ 'Texto', 'Elementos' ];
            ?>
            <div class="gsap-tab-panel <?php echo $tab === 'animacoes' ? 'is-active' : ''; ?>">
            <div class="gsap-card">
                <h2 class="gsap-card__title">Animações por Classe — Referência</h2>
                <p class="gsap-card__desc">Adicione as classes diretamente no campo <strong>CSS class</strong> do bloco ou widget. Nenhum HTML customizado necessário — exceto <code>gsap-mask-reveal</code>, que pede widget HTML.</p>

                <!-- Barra sticky: busca + chips de atalho pra cada grupo -->
                <div class="gsap-ref-nav" id="gsap-ref-nav">
                    <div class="gsap-ref-search">
                        <svg class="gsap-ref-search__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="gsap-ref-search-input" placeholder="Buscar classe, descrição ou atributo..." autocomplete="off">
                        <button type="button" id="gsap-ref-search-clear" class="gsap-ref-search__clear" aria-label="Limpar busca">×</button>
                    </div>
                    <div class="gsap-ref-chips">
                        <?php foreach ( $anim_ref as $group => $items ) :
                            $slug = sanitize_title( $group ); ?>
                        <button type="button" class="gsap-ref-chip" data-target="<?php echo esc_attr( $slug ); ?>">
                            <?php echo esc_html( $group ); ?>
                            <span class="gsap-ref-chip__count"><?php echo count( $items ); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Classes auxiliares (gatilhos + modificadores) -->
                <details class="gsap-ref-accordion gsap-ref-accordion--meta" open>
                    <summary>
                        <span class="gsap-ref-accordion__title">Classes de gatilho &amp; modificadores</span>
                        <span class="gsap-ref-accordion__hint">quando e como a animação acontece</span>
                    </summary>
                    <div class="gsap-ref-accordion__body">
                        <div class="gsap-ref-meta-block">
                            <h4 class="gsap-ref-meta-block__title">Gatilhos — <em>quando</em> dispara</h4>
                            <div class="gsap-ref-meta-grid">
                                <div class="gsap-ref-meta-item gsap-ref-meta-item--muted">
                                    <code>(nenhuma)</code>
                                    <span>Padrão: aguarda o elemento entrar na viewport.</span>
                                </div>
                                <div class="gsap-ref-meta-item">
                                    <code>gsap-on-load</code>
                                    <span>Anima imediatamente ao carregar a página. Ideal pra hero.</span>
                                </div>
                                <div class="gsap-ref-meta-item">
                                    <code>gsap-scrub</code>
                                    <span>Modifica <code>gsap-text-fade</code>, <code>gsap-text-blur</code>, <code>gsap-text-highlight</code>: progresso vinculado ao scroll.</span>
                                </div>
                                <div class="gsap-ref-meta-item">
                                    <code>gsap-char-scrub</code>
                                    <span>Modifica <code>gsap-char-reveal</code>: revela/esconde char-a-char com o scroll.</span>
                                </div>
                                <div class="gsap-ref-meta-item">
                                    <code>gsap-word-scrub</code>
                                    <span>Modifica <code>gsap-word-reveal</code>: revela/esconde palavra-a-palavra com o scroll.</span>
                                </div>
                                <div class="gsap-ref-meta-item gsap-ref-meta-item--deprecated">
                                    <code>gsap-on-scroll</code>
                                    <span class="gsap-badge gsap-badge--deprecated">legado</span>
                                    <span>Idêntico ao padrão — mantido por compatibilidade.</span>
                                </div>
                            </div>
                        </div>

                        <div class="gsap-ref-meta-block">
                            <h4 class="gsap-ref-meta-block__title">Modificadores — <em>como</em> se comporta</h4>
                            <div class="gsap-ref-meta-grid">
                                <div class="gsap-ref-meta-item"><code>gsap-delay-1</code><span>atraso 0.1s</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-delay-2</code><span>atraso 0.2s</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-delay-3</code><span>atraso 0.3s</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-delay-4</code><span>atraso 0.4s</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-delay-5</code><span>atraso 0.5s</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-slow</code><span>duração 1.8× mais lenta</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-fast</code><span>duração 2× mais rápida</span></div>
                                <div class="gsap-ref-meta-item"><code>gsap-repeat</code><span>re-dispara ao entrar/sair da viewport</span></div>
                            </div>
                        </div>

                        <p class="gsap-ref-meta-example">
                            <strong>Como combinar:</strong>
                            <code>gsap-fade-up gsap-delay-2 gsap-slow</code> &nbsp;·&nbsp;
                            <code>gsap-scale-in gsap-repeat</code> &nbsp;·&nbsp;
                            <code>gsap-char-reveal gsap-char-scrub</code>
                        </p>
                    </div>
                </details>

                <!-- Atributos globais (aplicáveis na maioria das classes) -->
                <details class="gsap-ref-accordion gsap-ref-accordion--meta">
                    <summary>
                        <span class="gsap-ref-accordion__title">Atributos globais</span>
                        <span class="gsap-ref-accordion__hint"><?php echo count( $anim_global_attrs ); ?> atributos aplicáveis em quase todas as classes</span>
                    </summary>
                    <div class="gsap-ref-accordion__body">
                        <p class="gsap-ref-meta-desc">Estes <code>data-gsap-*</code> funcionam em praticamente toda classe de scroll/trigger. Override por elemento — quando não informados, os defaults do plugin são usados.</p>
                        <ul class="gsap-ref-attrs gsap-ref-attrs--wide">
                            <?php foreach ( $anim_global_attrs as $a ) : ?>
                            <li>
                                <code><?php echo esc_html( $a['name'] ); ?></code>
                                <span class="gsap-ref-attr__desc"><?php echo esc_html( $a['desc'] ); ?></span>
                                <?php if ( ! empty( $a['ex'] ) ) : ?>
                                <span class="gsap-ref-attr__ex">ex: <em><?php echo esc_html( $a['ex'] ); ?></em></span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </details>

                <!-- Grupos de classes -->
                <?php foreach ( $anim_ref as $group => $items ) :
                    $slug = sanitize_title( $group );
                    $is_open = in_array( $group, $open_by_default, true );
                ?>
                <details class="gsap-ref-accordion" id="gsap-ref-group-<?php echo esc_attr( $slug ); ?>" data-group="<?php echo esc_attr( $slug ); ?>" <?php echo $is_open ? 'open' : ''; ?>>
                    <summary>
                        <span class="gsap-ref-accordion__title"><?php echo esc_html( $group ); ?></span>
                        <span class="gsap-ref-accordion__count"><?php echo count( $items ); ?></span>
                    </summary>
                    <div class="gsap-ref-accordion__body">
                        <div class="gsap-ref-cards">
                            <?php foreach ( $items as $item ) :
                                $search_str = $gsap_make_search( $item );
                            ?>
                            <article class="gsap-ref-card" data-search="<?php echo esc_attr( $search_str ); ?>">
                                <header class="gsap-ref-card__head">
                                    <button class="gsap-copy-btn" data-copy="<?php echo esc_attr( $item['class'] ); ?>" title="Copiar classe">
                                        <code class="gsap-ref-class">.<?php echo esc_html( $item['class'] ); ?></code>
                                        <svg class="gsap-copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                    </button>
                                    <?php if ( ! empty( $item['req'] ) ) : ?>
                                    <span class="gsap-ref-req" title="Requer este plugin GSAP ativo">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php echo esc_html( $item['req'] ); ?>
                                    </span>
                                    <?php endif; ?>
                                </header>
                                <p class="gsap-ref-card__desc"><?php echo wp_kses( $item['short'], [ 'code' => [], 'strong' => [], 'em' => [] ] ); ?></p>

                                <?php if ( ! empty( $item['attrs'] ) ) : ?>
                                <details class="gsap-ref-card__section">
                                    <summary>
                                        <span>Atributos</span>
                                        <span class="gsap-ref-card__section-count"><?php echo count( $item['attrs'] ); ?></span>
                                    </summary>
                                    <ul class="gsap-ref-attrs">
                                        <?php foreach ( $item['attrs'] as $a ) : ?>
                                        <li>
                                            <code><?php echo esc_html( $a['name'] ); ?></code>
                                            <span class="gsap-ref-attr__desc"><?php echo wp_kses( $a['desc'], [ 'code' => [], 'strong' => [] ] ); ?></span>
                                            <?php if ( isset( $a['ex'] ) && $a['ex'] !== '' ) : ?>
                                            <span class="gsap-ref-attr__ex">ex: <em><?php echo esc_html( $a['ex'] ); ?></em></span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                                <?php endif; ?>

                                <?php if ( ! empty( $item['combine'] ) ) : ?>
                                <div class="gsap-ref-card__combine">
                                    <strong>Combina com:</strong>
                                    <?php foreach ( $item['combine'] as $c ) : ?>
                                    <span class="gsap-ref-combine-item"><?php echo wp_kses( $c, [ 'code' => [] ] ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <?php if ( ! empty( $item['example'] ) ) : ?>
                                <details class="gsap-ref-card__section">
                                    <summary><span>Exemplo</span></summary>
                                    <div class="gsap-ref-card__example">
                                        <button class="gsap-copy-btn gsap-ref-card__example-copy" data-copy="<?php echo esc_attr( $item['example'] ); ?>" title="Copiar snippet">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                            copiar
                                        </button>
                                        <pre><code><?php echo esc_html( $item['example'] ); ?></code></pre>
                                    </div>
                                </details>
                                <?php endif; ?>

                                <?php if ( ! empty( $item['howto'] ) ) : ?>
                                <details class="gsap-ref-card__section">
                                    <summary><span>Passo a passo</span></summary>
                                    <ol class="gsap-ref-card__howto">
                                        <?php foreach ( $item['howto'] as $step ) : ?>
                                        <li><?php echo wp_kses( $step, [ 'strong' => [], 'code' => [] ] ); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if ( ! empty( $item['tip'] ) ) : ?>
                                    <p class="gsap-ref-card__tip"><?php echo wp_kses( $item['tip'], [ 'strong' => [], 'code' => [] ] ); ?></p>
                                    <?php endif; ?>
                                </details>
                                <?php endif; ?>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="gsap-ref-empty" hidden>Nenhuma classe casa com a busca neste grupo.</p>
                    </div>
                </details>
                <?php endforeach; ?>

                <!-- Performance (rodapé, fechado por padrão) -->
                <details class="gsap-ref-accordion gsap-ref-accordion--perf">
                    <summary>
                        <span class="gsap-ref-accordion__title">⚡ Performance &amp; blur</span>
                        <span class="gsap-ref-accordion__hint">boas práticas para GPU/mobile</span>
                    </summary>
                    <div class="gsap-ref-accordion__body">
                        <p class="gsap-ref-meta-desc">
                            Animações com <code>filter: blur()</code> são bonitas mas custam GPU — principalmente em mobile. Use com parcimônia.
                        </p>
                        <div class="gsap-perf-grid">
                            <div>
                                <strong>Classes que usam blur:</strong>
                                <span><code>gsap-word-blur</code>, <code>gsap-text-focus</code>, <code>gsap-text-blur</code></span>
                            </div>
                            <div>
                                <strong>Recomendado:</strong>
                                <span>1 animação blur por página, de preferência no hero. H1 com blur 12px é barato; blur grande em imagens é caro.</span>
                            </div>
                            <div>
                                <strong>Em várias seções:</strong>
                                <span>Use <code>data-gsap-mobile-blur="off"</code> nas secundárias — mantém desktop completo e alivia o mobile.</span>
                            </div>
                            <div>
                                <strong>Teste no device real:</strong>
                                <span>DevTools não simula GPU de celular — teste no Android/iPhone antes de publicar.</span>
                            </div>
                        </div>
                    </div>
                </details>

                <!-- Aviso global de busca vazia -->
                <p class="gsap-ref-no-results" id="gsap-ref-no-results" hidden>
                    <strong>Nada encontrado.</strong> Tente outro termo ou <a href="#" id="gsap-ref-reset">limpe a busca</a>.
                </p>
            </div>
            </div><!-- /gsap-tab-panel animacoes -->
            <?php endif; ?>

            <!-- TAB: Como Usar -->
            <div class="gsap-tab-panel <?php echo $tab === 'usage' ? 'is-active' : ''; ?>">

            <!-- ── 1. O que é ──────────────────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">O que é o GSAP Manager?</h2>
                <p class="gsap-card__desc">
                    O <strong>GSAP Manager</strong> integra o <strong>GSAP (GreenSock Animation Platform)</strong> — a biblioteca de animações JavaScript mais utilizada na web — diretamente ao WordPress, sem precisar escrever código. O sistema de classes permite adicionar animações profissionais a qualquer elemento do seu site apenas digitando uma classe CSS no campo do seu construtor.
                </p>
                <div class="gsap-usage-features">
                    <div class="gsap-usage-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Carrega o GSAP e seus plugins automaticamente — sem configuração manual</span>
                    </div>
                    <div class="gsap-usage-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Animações ativadas por scroll, ao carregar, por hover e vinculadas ao scroll (scrub)</span>
                    </div>
                    <div class="gsap-usage-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Compatível com Elementor, Gutenberg, Hello Elementor e qualquer tema WordPress</span>
                    </div>
                    <div class="gsap-usage-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Plugins bonus (ScrollSmoother, DrawSVG, MorphSVG, ScrambleText…) com classes prontas</span>
                    </div>
                </div>
            </div>

            <!-- ── 2. Início Rápido ────────────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Início Rápido — 3 passos</h2>
                <div class="gsap-steps">
                    <div class="gsap-step">
                        <div class="gsap-step__num">1</div>
                        <h4>Ative o plugin</h4>
                        <p>Na aba <strong>Configurações</strong>, ligue <em>Habilitar GSAP</em> e <em>Animações por Classe</em>. Recomendado: marque <em>Carregar no rodapé</em>.</p>
                    </div>
                    <div class="gsap-step">
                        <div class="gsap-step__num">2</div>
                        <h4>Ative o ScrollTrigger</h4>
                        <p>Na aba <strong>Plugins GSAP</strong>, habilite o <em>ScrollTrigger</em>. Ele é necessário para a maioria das animações ativadas por scroll.</p>
                    </div>
                    <div class="gsap-step">
                        <div class="gsap-step__num">3</div>
                        <h4>Adicione uma classe</h4>
                        <p>No seu elemento (widget, bloco ou div), adicione a classe <code>gsap-fade-up</code>. Ao salvar, a animação dispara automaticamente ao scroll.</p>
                    </div>
                </div>
                <div class="gsap-tip">
                    <strong>É só isso!</strong> Nenhum JavaScript necessário para a maioria dos casos. Consulte a aba <strong>Animações por Classe</strong> para ver a lista completa de efeitos disponíveis.
                </div>
            </div>

            <!-- ── 3. Onde adicionar as classes ───────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Onde adicionar as classes</h2>
                <p class="gsap-card__desc">Cada plataforma tem um campo nativo para classes CSS. Não é preciso editar o HTML — use o campo do seu construtor favorito.</p>
                <div class="gsap-where-grid">
                    <div class="gsap-where-item">
                        <div class="gsap-where-item__label">&#9654; Elementor</div>
                        <p>Selecione o widget → aba <strong>Avançado</strong> → campo <strong>CSS Classes</strong>. Para atributos <code>data-gsap-*</code>, use <strong>Avançado → Atributos</strong>.</p>
                        <pre class="gsap-code gsap-code--sm"><code>gsap-fade-up gsap-slow</code></pre>
                    </div>
                    <div class="gsap-where-item">
                        <div class="gsap-where-item__label">&#9654; Gutenberg</div>
                        <p>Selecione o bloco → painel lateral direito → <strong>Avançado</strong> → campo <strong>Classes CSS adicionais</strong>.</p>
                        <pre class="gsap-code gsap-code--sm"><code>gsap-char-reveal gsap-delay-2</code></pre>
                    </div>
                    <div class="gsap-where-item">
                        <div class="gsap-where-item__label">&#9654; HTML puro</div>
                        <p>Adicione diretamente no atributo <code>class</code> do elemento HTML.</p>
                        <pre class="gsap-code gsap-code--sm"><code>&lt;div class="gsap-fade-up"&gt;
  Conteúdo
&lt;/div&gt;</code></pre>
                    </div>
                </div>
            </div>

            <!-- ── 4. Anatomia do sistema ─────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Como funciona — o sistema de classes</h2>
                <p class="gsap-card__desc">Uma animação completa é formada por até 4 partes combinadas no campo de classes. Apenas a <strong>classe de animação</strong> é obrigatória — todo o resto é opcional.</p>

                <div class="gsap-anatomy">
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--anim">gsap-fade-up</span>
                        <span class="gsap-anatomy__label">Animação<br><span class="gsap-anatomy__required">obrigatório</span></span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--plus">+</span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--mod">gsap-slow</span>
                        <span class="gsap-anatomy__label">Velocidade<br><span class="gsap-anatomy__optional">opcional</span></span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--plus">+</span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--delay">gsap-delay-2</span>
                        <span class="gsap-anatomy__label">Atraso<br><span class="gsap-anatomy__optional">opcional</span></span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--plus">+</span>
                    </div>
                    <div class="gsap-anatomy__part">
                        <span class="gsap-anatomy__badge gsap-anatomy__badge--trig">gsap-on-load</span>
                        <span class="gsap-anatomy__label">Gatilho<br><span class="gsap-anatomy__optional">opcional</span></span>
                    </div>
                </div>

                <p class="gsap-section-label">Exemplos reais</p>
                <div class="gsap-example-rows">
                    <div class="gsap-example-row">
                        <code>gsap-fade-up</code>
                        <span>Sobe com fade ao entrar na viewport — comportamento padrão</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-fade-up gsap-slow</code>
                        <span>Mesmo efeito, com duração 1.8× mais longa</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-char-reveal gsap-delay-3</code>
                        <span>Revela caractere por caractere, com 0.3s de atraso antes de começar</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-fade-up gsap-on-load gsap-slow</code>
                        <span>Sobe com fade ao carregar a página — sem esperar o scroll</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-text-fade gsap-scrub</code>
                        <span>Fade do texto vinculado ao progresso do scroll</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-char-reveal gsap-char-scrub</code>
                        <span>Caracteres revelados sincronizados com o scroll — efeito cinematográfico</span>
                    </div>
                    <div class="gsap-example-row">
                        <code>gsap-stagger gsap-fast gsap-delay-1</code>
                        <span>Filhos entram em cascata, rápido, com 0.1s de atraso inicial</span>
                    </div>
                </div>
            </div>

            <!-- ── 5. Modificadores de timing ────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Modificadores de timing</h2>
                <p class="gsap-card__desc">Adicione junto à classe de animação para ajustar velocidade e atraso sem nenhum código.</p>

                <p class="gsap-section-label">Velocidade</p>
                <div class="gsap-modifier-grid">
                    <div class="gsap-modifier-item">
                        <code>gsap-slow</code>
                        <span>Duração 1.8× mais longa — efeito suave e elaborado</span>
                    </div>
                    <div class="gsap-modifier-item">
                        <code>gsap-fast</code>
                        <span>Duração 2× mais curta — entrada ágil e snappy</span>
                    </div>
                </div>

                <p class="gsap-section-label" style="margin-top:1.25rem">Atraso (delay) — útil para sequenciar elementos na mesma seção</p>
                <div class="gsap-modifier-grid">
                    <div class="gsap-modifier-item"><code>gsap-delay-1</code><span>Aguarda 0.1s antes de animar</span></div>
                    <div class="gsap-modifier-item"><code>gsap-delay-2</code><span>Aguarda 0.2s antes de animar</span></div>
                    <div class="gsap-modifier-item"><code>gsap-delay-3</code><span>Aguarda 0.3s antes de animar</span></div>
                    <div class="gsap-modifier-item"><code>gsap-delay-4</code><span>Aguarda 0.4s antes de animar</span></div>
                    <div class="gsap-modifier-item"><code>gsap-delay-5</code><span>Aguarda 0.5s antes de animar</span></div>
                </div>
                <div class="gsap-tip">
                    Para durações ou atrasos exatos, use os atributos <code>data-gsap-duration="1.2"</code> e <code>data-gsap-delay="0.8"</code> — eles têm prioridade sobre as classes.
                </div>
            </div>

            <!-- ── 6. Gatilhos ───────────────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Quando animar — Gatilhos</h2>
                <p class="gsap-card__desc">Por padrão, toda animação aguarda o elemento entrar na viewport. Use classes de gatilho para mudar esse comportamento.</p>
                <div class="gsap-trigger-rows">
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge">(padrão)</div>
                        <div>
                            <strong>Entrada na viewport</strong>
                            <p>Dispara uma única vez quando o elemento aparece na tela ao rolar. Não é necessário nenhuma classe adicional — é o comportamento de todas as animações por padrão.</p>
                        </div>
                    </div>
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge gsap-trigger-row__badge--blue">gsap-on-load</div>
                        <div>
                            <strong>Ao carregar a página</strong>
                            <p>Inicia imediatamente, sem aguardar o scroll. Ideal para a seção <em>hero</em>: título principal, subtítulo, botão CTA, imagem de destaque.</p>
                        </div>
                    </div>
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge gsap-trigger-row__badge--yellow">gsap-scrub</div>
                        <div>
                            <strong>Progresso vinculado ao scroll</strong>
                            <p>O elemento anima conforme a página rola — avança ao descer, regride ao subir. Funciona com <code>gsap-text-fade</code>, <code>gsap-text-blur</code> e <code>gsap-text-highlight</code>.</p>
                        </div>
                    </div>
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge gsap-trigger-row__badge--yellow">gsap-char-scrub</div>
                        <div>
                            <strong>Caracteres vinculados ao scroll</strong>
                            <p>Modifica <code>gsap-char-reveal</code>: cada caractere é revelado conforme a página rola para baixo e ocultado ao subir. Efeito cinematográfico muito usado em títulos de destaque. Requer ScrollTrigger.</p>
                        </div>
                    </div>
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge gsap-trigger-row__badge--yellow">gsap-word-scrub</div>
                        <div>
                            <strong>Palavras vinculadas ao scroll</strong>
                            <p>Modifica <code>gsap-word-reveal</code>: cada palavra aparece ao rolar. Similar ao char-scrub, mas com granularidade de palavras — mais legível em textos longos. Requer ScrollTrigger.</p>
                        </div>
                    </div>
                    <div class="gsap-trigger-row">
                        <div class="gsap-trigger-row__badge gsap-trigger-row__badge--green">gsap-repeat</div>
                        <div>
                            <strong>Repetir a cada entrada</strong>
                            <p>Por padrão, as animações disparam uma única vez. Com <code>gsap-repeat</code>, a animação se reinicia toda vez que o elemento entra na viewport e se oculta quando sai. Ideal para seções que o usuário pode visitar múltiplas vezes ao rolar. Funciona melhor com animações de elemento (fade, scale, clip) — evite em char-reveal e word-reveal.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 7. Controle fino ──────────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Controle fino com atributos HTML</h2>
                <p class="gsap-card__desc">Para ajustes precisos além das classes de modificador, use os atributos <code>data-gsap-*</code> diretamente no elemento. No Elementor, acesse <strong>Avançado → Atributos customizados</strong>.</p>
                <div class="gsap-attrs-box" style="margin-top:1rem">
                    <div class="gsap-attrs-grid">
                        <div><code>data-gsap-duration="1.5"</code> — duração exata em segundos</div>
                        <div><code>data-gsap-delay="0.4"</code> — atraso exato em segundos</div>
                        <div><code>data-gsap-ease="elastic.out(1,0.5)"</code> — curva de easing GSAP</div>
                        <div><code>data-gsap-distance="80"</code> — deslocamento em px (fade-up/down/left/right)</div>
                        <div><code>data-gsap-stagger="0.12"</code> — intervalo entre filhos em grupos</div>
                        <div><code>data-gsap-start="top 60%"</code> — posição do gatilho no scroll</div>
                        <div><code>data-gsap-end="bottom 20%"</code> — fim do range (modo scrub)</div>
                        <div><code>data-gsap-scrub="2"</code> — suavidade do scrub em segundos</div>
                    </div>
                </div>
                <div class="gsap-tip">
                    <strong>Dica Elementor:</strong> Em <strong>Avançado → Atributos</strong>, insira a chave sem o prefixo <em>data-</em> (ex: <code>gsap-duration</code>) e o valor (<code>1.5</code>) nos campos separados. O Elementor adiciona o <code>data-</code> automaticamente.
                </div>
                <pre class="gsap-code" style="margin-top:1rem"><code>&lt;!-- Exemplo: título com controle preciso --&gt;
&lt;h2 class="gsap-char-reveal gsap-delay-1"
    data-gsap-duration="0.5"
    data-gsap-stagger="0.02"
    data-gsap-ease="power4.out"&gt;
    Título com controle preciso
&lt;/h2&gt;</code></pre>
            </div>

            <!-- ── 8. ScrollSmoother ─────────────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">ScrollSmoother — Scroll suavizado e Parallax</h2>
                <p class="gsap-card__desc">O ScrollSmoother adiciona inércia suave ao scroll da página e permite efeitos de parallax com classes simples. Requer o arquivo <code>ScrollSmoother.min.js</code> em <code>assets/js/vendor/</code> do plugin.</p>

                <p class="gsap-section-label">Como ativar</p>
                <div class="gsap-steps gsap-steps--two">
                    <div class="gsap-step">
                        <div class="gsap-step__num">1</div>
                        <h4>Habilite na aba Plugins</h4>
                        <p>Ative <strong>ScrollTrigger</strong> + <strong>ScrollSmoother</strong>. As configurações do ScrollSmoother aparecerão logo abaixo. Os wrappers HTML necessários são injetados <strong>automaticamente</strong> pelo plugin.</p>
                    </div>
                    <div class="gsap-step">
                        <div class="gsap-step__num">2</div>
                        <h4>Ajuste a suavidade</h4>
                        <p>Configure a <em>Suavidade</em> conforme preferido: <strong>0</strong> = scroll nativo, <strong>1</strong> = padrão, <strong>2+</strong> = muito suave. Valores altos criam um efeito mais "flutuante".</p>
                    </div>
                </div>

                <p class="gsap-section-label" style="margin-top:1.25rem">Classes de parallax</p>
                <div class="gsap-modifier-grid">
                    <div class="gsap-modifier-item">
                        <code>gsap-speed-slow</code>
                        <span>Move a 0.6× do scroll — efeito de fundo / profundidade</span>
                    </div>
                    <div class="gsap-modifier-item">
                        <code>gsap-speed-fast</code>
                        <span>Move a 1.5× do scroll — efeito de primeiro plano</span>
                    </div>
                </div>
                <div class="gsap-tip">
                    Use <code>data-gsap-speed="0.3"</code> no elemento para um valor customizado de velocidade parallax.
                </div>
            </div>

            <!-- ── 9. Plugins bonus com classes ──────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Plugins bonus com classes automáticas</h2>
                <p class="gsap-card__desc">Estes plugins bonus têm classes prontas no sistema. Habilite o plugin correspondente na aba <strong>Plugins GSAP</strong> e adicione a classe ao elemento.</p>
                <div class="gsap-bonus-list">

                    <div class="gsap-bonus-item">
                        <div class="gsap-bonus-item__header">
                            <span class="gsap-badge gsap-badge--bonus">ScrambleTextPlugin</span>
                            <code class="gsap-bonus-item__class">gsap-scramble</code>
                        </div>
                        <p>O texto embaralha com caracteres aleatórios e revela progressivamente o conteúdo real ao entrar na viewport. Use <code>data-gsap-chars="01"</code> para escolher o charset (padrão: letras maiúsculas). Outros valores: <code>"lowerCase"</code>, <code>"!@#$%"</code>, <code>"XO"</code>.</p>
                        <pre class="gsap-code gsap-code--sm"><code>&lt;span class="gsap-scramble"&gt;Texto secreto&lt;/span&gt;
&lt;span class="gsap-scramble" data-gsap-chars="01"&gt;Código binário&lt;/span&gt;</code></pre>
                    </div>

                    <div class="gsap-bonus-item">
                        <div class="gsap-bonus-item__header">
                            <span class="gsap-badge gsap-badge--bonus">DrawSVGPlugin</span>
                            <code class="gsap-bonus-item__class">gsap-draw-svg</code>
                        </div>
                        <p>Anima o stroke de um <code>&lt;path&gt;</code>, <code>&lt;circle&gt;</code>, <code>&lt;line&gt;</code> ou qualquer forma SVG como se estivesse sendo desenhado ao entrar na viewport. <strong>Importante:</strong> o elemento SVG deve ter <code>stroke</code> e <code>stroke-width</code> definidos no CSS ou inline. Combine com <code>gsap-scrub</code> para vincular o desenho ao scroll.</p>
                        <pre class="gsap-code gsap-code--sm"><code>&lt;!-- O elemento precisa ter stroke definido --&gt;
&lt;path class="gsap-draw-svg"
      stroke="#0AE448" stroke-width="3" fill="none"
      d="M10 80 Q 95 10 180 80"&gt;&lt;/path&gt;

&lt;!-- Vinculado ao scroll --&gt;
&lt;path class="gsap-draw-svg gsap-scrub"
      stroke="#fff" stroke-width="2" fill="none" d="..."&gt;&lt;/path&gt;</code></pre>
                    </div>

                    <div class="gsap-bonus-item">
                        <div class="gsap-bonus-item__header">
                            <span class="gsap-badge gsap-badge--bonus">MorphSVGPlugin</span>
                            <code class="gsap-bonus-item__class">gsap-morph-svg</code>
                        </div>
                        <p>Transita suavemente de uma forma SVG para outra ao entrar na viewport. Requer <code>data-gsap-target="#seletor"</code> apontando para o <code>&lt;path&gt;</code> de destino. A forma alvo pode ficar oculta no DOM (<code>display:none</code>) — ela é usada apenas como referência de dados.</p>
                        <pre class="gsap-code gsap-code--sm"><code>&lt;svg viewBox="0 0 100 100"&gt;
    &lt;!-- Forma inicial — recebe a classe e aponta pro alvo --&gt;
    &lt;path id="shape-a"
          class="gsap-morph-svg"
          data-gsap-target="#shape-b"
          d="M10 10 L90 10 L90 90 L10 90Z"&gt;&lt;/path&gt;

    &lt;!-- Forma alvo — pode estar oculta --&gt;
    &lt;path id="shape-b"
          style="display:none"
          d="M50 5 L95 95 L5 95Z"&gt;&lt;/path&gt;
&lt;/svg&gt;</code></pre>
                    </div>

                </div>
            </div>

            <!-- ── 10. Para desenvolvedores ──────────────────────────── -->
            <div class="gsap-card">
                <h2 class="gsap-card__title">Para desenvolvedores</h2>

                <p class="gsap-section-label">JavaScript de inicialização customizado</p>
                <p style="font-size:13px;color:var(--gsap-muted);margin:0 0 .75rem">Use o campo <strong>JavaScript de Inicialização</strong> na aba <strong>Configurações</strong> para executar código logo após o carregamento do GSAP — ideal para configurações globais ou animações que não usam o sistema de classes.</p>
                <pre class="gsap-code"><code>// Definir defaults globais
gsap.defaults({
    ease: 'power3.out',
    duration: 0.8
});

// Animação customizada via JS puro
gsap.from('.minha-hero', {
    y: 80, opacity: 0, duration: 1.2,
    scrollTrigger: { trigger: '.minha-hero', start: 'top 80%' }
});</code></pre>

                <p class="gsap-section-label" style="margin-top:1.5rem">Usar o GSAP em scripts próprios (wp_enqueue)</p>
                <p style="font-size:13px;color:var(--gsap-muted);margin:0 0 .75rem">O GSAP Manager registra todos os plugins com os handles corretos. Declare-os como dependência no seu <code>wp_enqueue_script()</code> para garantir a ordem de carregamento:</p>
                <pre class="gsap-code"><code>// No functions.php do tema:
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'meu-script',
        get_template_directory_uri() . '/js/animacoes.js',
        ['gsap', 'gsap-scrolltrigger'], // handles do GSAP Manager
        '1.0',
        true // carregar no rodapé
    );
});</code></pre>

                <p class="gsap-section-label" style="margin-top:1.5rem">Handles disponíveis</p>
                <div class="gsap-table-wrap">
                    <table class="gsap-table">
                        <thead><tr><th>Plugin</th><th>Handle</th><th>Plugin</th><th>Handle</th></tr></thead>
                        <tbody>
                            <tr><td>GSAP Core</td><td><code>gsap</code></td><td>ScrollSmoother</td><td><code>gsap-scrollsmoother</code></td></tr>
                            <tr><td>ScrollTrigger</td><td><code>gsap-scrolltrigger</code></td><td>SplitText</td><td><code>gsap-splittext</code></td></tr>
                            <tr><td>ScrollToPlugin</td><td><code>gsap-scrolltoplugin</code></td><td>MorphSVGPlugin</td><td><code>gsap-morphsvgplugin</code></td></tr>
                            <tr><td>Draggable</td><td><code>gsap-draggable</code></td><td>DrawSVGPlugin</td><td><code>gsap-drawsvgplugin</code></td></tr>
                            <tr><td>Flip</td><td><code>gsap-flip</code></td><td>InertiaPlugin</td><td><code>gsap-inertiaplugin</code></td></tr>
                            <tr><td>MotionPathPlugin</td><td><code>gsap-motionpathplugin</code></td><td>ScrambleTextPlugin</td><td><code>gsap-scrambletextplugin</code></td></tr>
                            <tr><td>TextPlugin</td><td><code>gsap-textplugin</code></td><td>CustomBounce</td><td><code>gsap-custombounce</code></td></tr>
                            <tr><td>Observer</td><td><code>gsap-observer</code></td><td>CustomWiggle</td><td><code>gsap-customwiggle</code></td></tr>
                            <tr><td>CustomEase</td><td><code>gsap-customease</code></td><td>Physics2DPlugin</td><td><code>gsap-physics2dplugin</code></td></tr>
                            <tr><td>EasePack</td><td><code>gsap-easepack</code></td><td>GSDevTools</td><td><code>gsap-gsdevtools</code></td></tr>
                            <tr><td>CSSRulePlugin</td><td><code>gsap-cssruleplugin</code></td><td>MotionPathHelper</td><td><code>gsap-motionpathhelper</code></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            </div><!-- /gsap-tab-panel usage -->

        </div>
        <?php
    }
}
