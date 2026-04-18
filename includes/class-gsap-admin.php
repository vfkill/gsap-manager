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

            $anim_ref = [
                'Texto' => [
                    [ 'class' => 'gsap-char-reveal',     'desc' => 'Cada caractere sobe do clip, revelando o texto.',          'req' => 'ScrollTrigger', 'ex' => '<h2 class="gsap-char-reveal">Título Incrível</h2>' ],
                    [ 'class' => 'gsap-char-color',      'desc' => 'Char-a-char muda da cor inicial para a cor final do Elementor conforme o scroll. <code>data-gsap-from-color="#616161"</code> · <code>data-gsap-to-color</code> sobrescreve a cor final.', 'req' => 'ScrollTrigger', 'ex' => '<p class="gsap-char-color">Texto que ilumina ao rolar</p>' ],
                    [ 'class' => 'gsap-word-reveal',     'desc' => 'Cada palavra sobe do clip individualmente.',               'req' => 'ScrollTrigger', 'ex' => '<h3 class="gsap-word-reveal">Frase por palavras</h3>' ],
                    [ 'class' => 'gsap-word-blur',       'desc' => 'Palavras entram em foco com blur + slide + opacity. Mobile mantém blur por padrão — use <code>data-gsap-mobile-blur="off"</code> para desligar em dispositivos fracos. <code>data-gsap-blur="8"</code> · aceita <code>gsap-word-scrub</code> para scrub.', 'req' => 'ScrollTrigger', 'ex' => '<p class="gsap-word-blur">Palavras entrando em foco</p>' ],
                    [ 'class' => 'gsap-text-focus',      'desc' => 'Foco gaussiano: letras do meio de cada palavra entram maiores, mais baixas, rotacionadas em leque e desfocadas — reorganizam-se pro estado final com stagger do centro pra fora. Inspirado em enumeramolecular.com. Ajuste: <code>data-gsap-scale-peak="2.1"</code> · <code>data-gsap-y-peak="60"</code> · <code>data-gsap-rotation="4"</code> · <code>data-gsap-blur="12"</code> · <code>data-gsap-mobile-blur="off"</code> para desligar blur em mobile fraco.', 'req' => '', 'ex' => '<h1 class="gsap-text-focus">Clinical omics, simplified</h1>' ],
                    [ 'class' => 'gsap-text-fade',       'desc' => 'Texto completo faz fade + sobe suavemente.',               'req' => 'ScrollTrigger', 'ex' => '<p class="gsap-text-fade">Parágrafo de texto</p>' ],
                    [ 'class' => 'gsap-typewriter',      'desc' => 'Digita o conteúdo do elemento como uma máquina de escrever.','req' => '',             'ex' => '<span class="gsap-typewriter">Texto digitado...</span>' ],
                    [ 'class' => 'gsap-text-blur',       'desc' => 'Começa borrado e vai nitidificando.',                      'req' => 'ScrollTrigger', 'ex' => '<h2 class="gsap-text-blur">Título desfocado</h2>' ],
                    [ 'class' => 'gsap-text-highlight',  'desc' => 'Sublinhado colorido varre o texto (use em &lt;span&gt;).',  'req' => 'ScrollTrigger', 'ex' => '<h2>Nosso <span class="gsap-text-highlight">diferencial</span></h2>' ],
                    [ 'class' => 'gsap-scramble',        'desc' => 'Texto embaralha com caracteres aleatórios até revelar. <code>data-gsap-chars="01"</code> para charset customizado.', 'req' => 'ScrambleTextPlugin', 'ex' => '<span class="gsap-scramble">Texto secreto</span>' ],
                ],
                'Imagens' => [
                    [ 'class' => 'gsap-img-reveal',      'desc' => 'Clip-path abre a imagem (padrão: da esquerda). <code>data-gsap-dir="right|top|bottom"</code>', 'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-reveal" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-zoom',        'desc' => 'Entra com zoom + fade.',                                   'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-zoom" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-fade',        'desc' => 'Fade simples na imagem.',                                  'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-fade" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-parallax',    'desc' => 'Parallax no scroll. O elemento pai vira o container.',     'req' => 'ScrollTrigger', 'ex' => '<div style="overflow:hidden"><img class="gsap-img-parallax" src="foto.jpg"></div>' ],
                    [ 'class' => 'gsap-zoom-reveal',     'desc' => 'Pina a seção e escala o filho (img/vídeo) de pequeno até fullscreen enquanto o usuário scrolla. <code>data-gsap-from="0.15"</code> escala inicial · <code>data-gsap-end="+=150%"</code> distância de scroll · <code>data-gsap-scrub="1"</code>.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-zoom-reveal" style="height:100vh"><img src="foto.jpg"></div>' ],
                    [ 'class' => 'gsap-img-scroll-scale', 'desc' => 'Imagem escala até preencher o container conforme você scrolla (sem pin). Origin é auto-detectado pela posição da imagem (encostada à direita cresce pra esquerda, no topo cresce pra baixo, etc.). Scale inicial é calculado automaticamente pelo tamanho da imagem vs container.', 'req' => 'ScrollTrigger', 'ex' => '<div style="height:600px;display:flex;justify-content:flex-end;align-items:flex-start"><img class="gsap-img-scroll-scale" src="foto.jpg" style="width:60%"></div>' ],
                    [ 'class' => 'gsap-img-scroll-scale-pin', 'desc' => 'Igual ao <code>gsap-img-scroll-scale</code>, mas pina o container no topo do viewport até a imagem terminar de preencher (estilo dsgngroup.it). <code>data-gsap-end="+=100%"</code> distância de scroll · <code>data-gsap-scrub="1"</code> · <code>data-gsap-from</code> escala manual · <code>data-gsap-origin="top right"</code> origin manual.', 'req' => 'ScrollTrigger', 'ex' => '<div style="height:600px;display:flex;justify-content:flex-end;align-items:flex-start"><img class="gsap-img-scroll-scale-pin" src="foto.jpg" style="width:60%"></div>' ],
                ],
                'Elementos' => [
                    [ 'class' => 'gsap-fade-up',         'desc' => 'Fade + sobe para a posição original.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-fade-up">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-down',       'desc' => 'Fade + desce para a posição original.',                   'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-fade-down">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-left',       'desc' => 'Fade + vem da direita para posição.',                     'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-fade-left">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-right',      'desc' => 'Fade + vem da esquerda para posição.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-fade-right">Conteúdo</div>' ],
                    [ 'class' => 'gsap-fade-in',         'desc' => 'Fade simples, sem movimento.',                             'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-fade-in">Conteúdo</div>' ],
                    [ 'class' => 'gsap-scale-in',        'desc' => 'Cresce de ~82% até o tamanho real.',                      'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-scale-in">Card</div>' ],
                    [ 'class' => 'gsap-scale-out',       'desc' => 'Encolhe de ~118% até o tamanho real.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-scale-out">Card</div>' ],
                    [ 'class' => 'gsap-rotate-in',       'desc' => 'Leve rotação + fade.',                                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-rotate-in">Elemento</div>' ],
                    [ 'class' => 'gsap-flip-in',         'desc' => 'Rotação 3D no eixo X (virar página).',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-flip-in">Card 3D</div>' ],
                    [ 'class' => 'gsap-clip-left',       'desc' => 'Clip-path revela da esquerda para direita.',               'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-clip-left">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-right',      'desc' => 'Clip-path revela da direita para esquerda.',               'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-clip-right">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-top',        'desc' => 'Clip-path revela de cima para baixo.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-clip-top">Div revelada</div>' ],
                    [ 'class' => 'gsap-clip-bottom',     'desc' => 'Clip-path revela de baixo para cima.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-clip-bottom">Div revelada</div>' ],
                ],
                'Grupos (Stagger)' => [
                    [ 'class' => 'gsap-stagger',         'desc' => 'Filhos entram em cascata: fade + sobe.',                  'req' => 'ScrollTrigger', 'ex' => '<ul class="gsap-stagger"><li>Item 1</li><li>Item 2</li></ul>' ],
                    [ 'class' => 'gsap-stagger-left',    'desc' => 'Filhos entram em cascata da direita.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-left">...</div>' ],
                    [ 'class' => 'gsap-stagger-right',   'desc' => 'Filhos entram em cascata da esquerda.',                   'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-right">...</div>' ],
                    [ 'class' => 'gsap-stagger-scale',   'desc' => 'Filhos entram em cascata com escala.',                    'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-scale">...</div>' ],
                    [ 'class' => 'gsap-stagger-fade',    'desc' => 'Filhos entram em cascata com fade simples.',              'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-fade">...</div>' ],
                    [ 'class' => 'gsap-stagger-rotate',  'desc' => 'Filhos entram em cascata com rotação.',                   'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-rotate">...</div>' ],
                    [ 'class' => 'gsap-stagger-center',  'desc' => 'Filhos entram em cascata a partir do centro — expande para fora simultaneamente. <code>data-gsap-stagger="0.1"</code> para intervalo customizado.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-stagger-center">...</div>' ],
                ],
                'Especiais' => [
                    [ 'class' => 'gsap-counter',         'desc' => 'Anima um número do zero até o valor. Suporta <code>data-gsap-prefix</code>, <code>data-gsap-suffix</code>, <code>data-gsap-from</code>, <code>data-gsap-separator=","</code> (separador de milhar).', 'req' => 'ScrollTrigger', 'ex' => '<span class="gsap-counter" data-gsap-suffix=" mil" data-gsap-separator=".">1500</span>' ],
                    [ 'class' => 'gsap-marquee',         'desc' => 'Faixa de conteúdo em loop horizontal. Use <code>data-gsap-speed</code> e <code>data-gsap-dir="right"</code>.', 'req' => '', 'ex' => '<div class="gsap-marquee" data-gsap-speed="50"><span>Item</span><span>Item</span></div>' ],
                    [ 'class' => 'gsap-parallax',        'desc' => 'Parallax genérico (não-imagem). O pai deve ter overflow:hidden.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-parallax">Elemento flutuante</div>' ],
                    [ 'class' => 'gsap-reveal-line',     'desc' => 'Linha cresce de largura zero. Perfeito para divisores.   <code>data-gsap-axis="height"</code> para vertical.', 'req' => 'ScrollTrigger', 'ex' => '<hr class="gsap-reveal-line">' ],
                    [ 'class' => 'gsap-progress',        'desc' => 'Barra de progresso animada. Define a largura alvo no estilo.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-progress" style="width:80%"></div>' ],
                    [ 'class' => 'gsap-mask-reveal',     'desc' => 'Hero com logo-máscara crescente + parallax interno + overlay branco (inspirado em dropedition.com). Use num widget HTML: requer <code>data-gsap-logo</code> (SVG) e <code>data-gsap-image</code> (imagem). A JS gera toda a estrutura interna. Ajuste fino: <code>data-gsap-mask-from="80"</code>, <code>data-gsap-mask-to="110"</code>, <code>data-gsap-overlay-opacity="0.8"</code>, <code>data-gsap-parallax="20"</code>, <code>data-gsap-distance="300"</code> (vh do scroller).', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-mask-reveal" data-gsap-logo="URL_LOGO.svg" data-gsap-image="URL_HERO.jpg"></div>' ],
                ],
                'SVG' => [
                    [ 'class' => 'gsap-draw-svg',  'desc' => 'Anima o stroke de um path/shape SVG de 0% a 100%, como se estivesse sendo desenhado. Suporta <code>gsap-scrub</code> para vincular ao scroll.', 'req' => 'DrawSVGPlugin', 'ex' => '<path class="gsap-draw-svg" d="M10 80 Q 95 10 180 80">' ],
                    [ 'class' => 'gsap-morph-svg', 'desc' => 'Transição suave de uma forma SVG para outra ao entrar na viewport. Requer <code>data-gsap-target="#seletor"</code> apontando para o shape alvo.', 'req' => 'MorphSVGPlugin', 'ex' => '<path class="gsap-morph-svg" data-gsap-target="#shape-b" d="...">' ],
                ],
                'ScrollSmoother — Parallax' => [
                    [ 'class' => 'gsap-speed-slow', 'desc' => 'Parallax lento: move a 0.5× do scroll — efeito de fundo/profundidade. Use <code>data-gsap-speed="0.3"</code> para valor customizado.', 'req' => 'ScrollSmoother', 'ex' => '' ],
                    [ 'class' => 'gsap-speed-fast', 'desc' => 'Parallax rápido: move a 1.5× do scroll — efeito de primeiro plano. Use <code>data-gsap-speed="2"</code> para valor customizado.', 'req' => 'ScrollSmoother', 'ex' => '' ],
                ],
                'Hover' => [
                    [ 'class' => 'gsap-magnetic',        'desc' => 'O elemento atrai o cursor como um ímã. Ideal para botões.  <code>data-gsap-strength="0.4"</code>', 'req' => '', 'ex' => '<button class="gsap-magnetic">Clique aqui</button>' ],
                    [ 'class' => 'gsap-tilt',            'desc' => 'Inclinação 3D ao passar o mouse. <code>data-gsap-strength="14"</code>', 'req' => '', 'ex' => '<div class="gsap-tilt">Card 3D</div>' ],
                    [ 'class' => 'gsap-hover-lift',      'desc' => 'Levita suavemente ao hover. <code>data-gsap-distance="-8"</code>', 'req' => '', 'ex' => '<div class="gsap-hover-lift">Card flutuante</div>' ],
                    [ 'class' => 'gsap-char-stretch-hover', 'desc' => 'Cada caractere estica em Y conforme o mouse passa, com decay nos vizinhos. Fica incrível em fonts condensed (Six Caps, Bebas Neue, Anton, Oswald). <code>data-gsap-scale="0.2"</code> amplitude · <code>data-gsap-neighbors="1"</code> vizinhos afetados · <code>data-gsap-duration="0.4"</code>.', 'req' => '', 'ex' => '<h1 class="gsap-char-stretch-hover" style="font-family:\'Bebas Neue\'">TYPOGRAPHY</h1>' ],
                ],
            ];
            ?>
            <div class="gsap-tab-panel <?php echo $tab === 'animacoes' ? 'is-active' : ''; ?>">
            <div class="gsap-card">
                <h2 class="gsap-card__title">Animações por Classe — Referência Completa</h2>
                <p class="gsap-card__desc">Adicione as classes diretamente no campo <strong>CSS class</strong> do bloco ou widget. Nenhum HTML customizado necessário.</p>

                <div class="gsap-attrs-box gsap-attrs-box--trigger">
                    <h3>Classes de gatilho — <em>quando</em> a animação dispara</h3>
                    <div class="gsap-trigger-grid">
                        <div class="gsap-trigger-item gsap-trigger-item--muted">
                            <code>(nenhuma)</code>
                            <span>Comportamento padrão: aguarda o elemento entrar na viewport para animar.</span>
                        </div>
                        <div class="gsap-trigger-item gsap-trigger-item--deprecated">
                            <code>gsap-on-scroll</code>
                            <span class="gsap-badge gsap-badge--deprecated">legado</span>
                            <span>Idêntico ao comportamento padrão — mantido por compatibilidade. Não é necessário adicionar esta classe.</span>
                        </div>
                        <div class="gsap-trigger-item">
                            <code>gsap-on-load</code>
                            <span>Anima imediatamente ao carregar a página, sem aguardar o scroll. Ideal para elementos da hero.</span>
                        </div>
                        <div class="gsap-trigger-item">
                            <code>gsap-scrub</code>
                            <span>Modifica <code>gsap-text-fade</code>, <code>gsap-text-blur</code> e <code>gsap-text-highlight</code>: progresso vinculado ao scroll. Requer ScrollTrigger.</span>
                        </div>
                        <div class="gsap-trigger-item">
                            <code>gsap-char-scrub</code>
                            <span>Modifica <code>gsap-char-reveal</code>: revela/esconde caractere a caractere com o scroll. Requer ScrollTrigger.</span>
                        </div>
                        <div class="gsap-trigger-item">
                            <code>gsap-word-scrub</code>
                            <span>Modifica <code>gsap-word-reveal</code>: revela/esconde palavra a palavra com o scroll. Requer ScrollTrigger.</span>
                        </div>
                    </div>
                    <p class="gsap-trigger-example">
                        Exemplo: <code>gsap-char-reveal</code> → anima ao entrar na viewport &nbsp;|&nbsp;
                        <code>gsap-char-reveal gsap-on-load</code> → anima ao carregar &nbsp;|&nbsp;
                        <code>gsap-text-fade gsap-scrub</code> → fade vinculado ao scroll &nbsp;|&nbsp;
                        <code>gsap-char-reveal gsap-char-scrub</code> → chars vinculados ao scroll
                    </p>
                </div>

                <div class="gsap-attrs-box gsap-attrs-box--modifiers">
                    <h3>Classes modificadoras — <em>como</em> a animação se comporta</h3>
                    <div class="gsap-attrs-grid">
                        <div><code>gsap-delay-1</code> — atraso de 0.1s</div>
                        <div><code>gsap-delay-2</code> — atraso de 0.2s</div>
                        <div><code>gsap-delay-3</code> — atraso de 0.3s</div>
                        <div><code>gsap-delay-4</code> — atraso de 0.4s</div>
                        <div><code>gsap-delay-5</code> — atraso de 0.5s</div>
                        <div><code>gsap-slow</code> — duração 1.8× mais lenta</div>
                        <div><code>gsap-fast</code> — duração 2× mais rápida</div>
                        <div><code>gsap-repeat</code> — re-dispara ao entrar/sair da viewport</div>
                    </div>
                    <p class="gsap-trigger-example">
                        Exemplo: <code>gsap-fade-up gsap-on-scroll gsap-delay-2 gsap-slow</code> &nbsp;|&nbsp;
                        <code>gsap-scale-in gsap-repeat</code> — anima toda vez que o elemento aparece na tela
                    </p>
                </div>

                <div class="gsap-attrs-box">
                    <h3>Atributos avançados (opcional — para controle preciso)</h3>
                    <div class="gsap-attrs-grid">
                        <div><code>data-gsap-duration</code> — duração exata em segundos <span>ex: <em>1.2</em></span></div>
                        <div><code>data-gsap-delay</code> — atraso exato em segundos <span>ex: <em>0.3</em></span></div>
                        <div><code>data-gsap-ease</code> — curva de easing <span>ex: <em>"elastic.out(1,0.5)"</em></span></div>
                        <div><code>data-gsap-distance</code> — distância em px <span>ex: <em>80</em></span></div>
                        <div><code>data-gsap-stagger</code> — intervalo entre filhos <span>ex: <em>0.15</em></span></div>
                        <div><code>data-gsap-start</code> — posição do gatilho scroll <span>ex: <em>"top 70%"</em></span></div>
                        <div><code>data-gsap-chars</code> — charset do scramble <span>ex: <em>"01"</em>, <em>"upperCase"</em></span></div>
                        <div><code>data-gsap-target</code> — seletor do shape SVG alvo <span>ex: <em>"#shape-b"</em></span></div>
                        <div><code>data-gsap-separator</code> — separador de milhar no counter <span>ex: <em>"."</em>, <em>","</em></span></div>
                        <div><code>data-gsap-speed</code> — velocidade parallax no ScrollSmoother <span>ex: <em>0.5</em>, <em>1.8</em></span></div>
                        <div><code>data-gsap-from-color</code> — cor inicial em <code>gsap-char-color</code> <span>ex: <em>"#616161"</em></span></div>
                        <div><code>data-gsap-to-color</code> — cor final em <code>gsap-char-color</code> <span>ex: <em>"#FFFFFF"</em> (padrão: cor do Elementor)</span></div>
                        <div><code>data-gsap-blur</code> — intensidade inicial do blur em <code>gsap-word-blur</code> <span>ex: <em>8</em>, <em>14</em> (padrão: 8 · desktop)</span></div>
                        <div><code>data-gsap-logo</code> — URL do SVG usado como máscara em <code>gsap-mask-reveal</code> <span>ex: <em>"/wp-content/uploads/logo.svg"</em></span></div>
                        <div><code>data-gsap-image</code> — URL da imagem de fundo em <code>gsap-mask-reveal</code> <span>ex: <em>"/wp-content/uploads/hero.jpg"</em></span></div>
                        <div><code>data-gsap-distance</code> — altura total da section em vh (<code>gsap-mask-reveal</code>) <span>ex: <em>100</em> (padrão, 1 viewport)</span></div>
                        <div><code>data-gsap-scale</code> — amplitude do scaleY em <code>gsap-char-stretch-hover</code> <span>ex: <em>0.2</em> (padrão)</span></div>
                        <div><code>data-gsap-neighbors</code> — número de vizinhos afetados em <code>gsap-char-stretch-hover</code> <span>ex: <em>1</em> (padrão)</span></div>
                        <div><code>data-gsap-mask-from</code> / <code>data-gsap-mask-to</code> — tamanho inicial/final da máscara em % <span>ex: <em>80</em> → <em>110</em> (padrão)</span></div>
                        <div><code>data-gsap-overlay-opacity</code> — opacidade final do overlay em <code>gsap-mask-reveal</code> <span>ex: <em>0.8</em> (padrão)</span></div>
                        <div><code>data-gsap-overlay-color</code> — cor do overlay em <code>gsap-mask-reveal</code> <span>ex: <em>"#ffffff"</em> (padrão)</span></div>
                        <div><code>data-gsap-parallax</code> — desloc. Y interno da imagem em % (<code>gsap-mask-reveal</code>) <span>ex: <em>20</em> (padrão)</span></div>
                        <div><code>data-gsap-scale-peak</code> — escala máxima no centro em <code>gsap-text-focus</code> <span>ex: <em>2.1</em> (padrão)</span></div>
                        <div><code>data-gsap-y-peak</code> — deslocamento Y máximo em px em <code>gsap-text-focus</code> <span>ex: <em>60</em> (padrão)</span></div>
                        <div><code>data-gsap-rotation</code> — ângulo do leque em graus em <code>gsap-text-focus</code> <span>ex: <em>4</em> (padrão)</span></div>
                        <div><code>data-gsap-mobile-blur</code> — desliga blur em &lt;1024px em <code>gsap-word-blur</code> / <code>gsap-text-focus</code> <span>ex: <em>"off"</em> (padrão: on)</span></div>
                    </div>
                </div>

                <div class="gsap-perf-box">
                    <h3>⚡ Boas práticas de performance</h3>
                    <p class="gsap-perf-box__desc">
                        As animações com <code>filter: blur()</code> são bonitas mas custam GPU — principalmente no mobile.
                        Use com parcimônia para manter o site fluido em dispositivos fracos.
                    </p>
                    <div class="gsap-perf-grid">
                        <div>
                            <strong>Classes que usam blur:</strong>
                            <span><code>gsap-word-blur</code>, <code>gsap-text-focus</code>, <code>gsap-text-blur</code></span>
                        </div>
                        <div>
                            <strong>Recomendado:</strong>
                            <span>1 animação blur por página, de preferência no hero. O custo de blur depende da área e do raio — H1 com blur 12px é barato; blur grande em imagens é caro.</span>
                        </div>
                        <div>
                            <strong>Se usar em várias seções:</strong>
                            <span>Considere <code>data-gsap-mobile-blur="off"</code> nas secundárias para manter desktop com o efeito completo e aliviar o mobile.</span>
                        </div>
                        <div>
                            <strong>Teste no dispositivo real:</strong>
                            <span>DevTools não simula GPU de celular — teste no seu Android/iPhone antes de publicar se a página tiver muitos efeitos.</span>
                        </div>
                    </div>
                </div>

                <div class="gsap-howto-box">
                    <h3>Caso especial — <code>gsap-mask-reveal</code> (hero com logo-máscara)</h3>
                    <p class="gsap-howto-box__desc">
                        Diferente das outras classes (que você adiciona no campo <strong>CSS Classes</strong> de qualquer widget),
                        <code>gsap-mask-reveal</code> exige um widget <strong>HTML</strong> do Elementor porque o efeito monta uma estrutura
                        específica em múltiplas camadas. A JS gera toda a árvore interna a partir do snippet abaixo.
                    </p>

                    <ol class="gsap-howto-steps">
                        <li>No WP → <strong>Mídia</strong>, faça upload da <strong>logo em SVG</strong> e da <strong>imagem hero</strong> (jpg/webp grande, ≥1920px). Copie as URLs de cada arquivo.</li>
                        <li>No Elementor, arraste o widget <strong>HTML</strong> para a posição desejada (geralmente no topo da página, sem container ao redor).</li>
                        <li>Cole o snippet abaixo e troque as duas URLs pelas suas.</li>
                        <li>Publique e role a página — a logo cresce, a imagem faz parallax interno e o fundo desvanece pra branco enquanto a section sai da viewport.</li>
                    </ol>

                    <div class="gsap-howto-code">
                        <div class="gsap-howto-code__label">
                            <span>Snippet mínimo</span>
                            <button class="gsap-copy-btn" data-copy='&lt;div class=&quot;gsap-mask-reveal&quot;
     data-gsap-logo=&quot;https://seusite.com/wp-content/uploads/logo.svg&quot;
     data-gsap-image=&quot;https://seusite.com/wp-content/uploads/hero.jpg&quot;&gt;&lt;/div&gt;' title="Copiar">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                copiar
                            </button>
                        </div>
<pre class="gsap-howto-code__block"><code>&lt;div class="gsap-mask-reveal"
     data-gsap-logo="https://seusite.com/wp-content/uploads/logo.svg"
     data-gsap-image="https://seusite.com/wp-content/uploads/hero.jpg"&gt;&lt;/div&gt;</code></pre>
                    </div>

                    <div class="gsap-howto-code">
                        <div class="gsap-howto-code__label">
                            <span>Com customizações (todos opcionais)</span>
                        </div>
<pre class="gsap-howto-code__block"><code>&lt;div class="gsap-mask-reveal"
     data-gsap-logo="https://seusite.com/wp-content/uploads/logo.svg"
     data-gsap-image="https://seusite.com/wp-content/uploads/hero.jpg"
     data-gsap-distance="100"
     data-gsap-mask-from="80"
     data-gsap-mask-to="110"
     data-gsap-overlay-opacity="0.8"
     data-gsap-overlay-color="#ffffff"
     data-gsap-parallax="20"&gt;&lt;/div&gt;</code></pre>
                    </div>

                    <div class="gsap-howto-grid">
                        <div><code>data-gsap-logo</code> <span>URL do SVG — recortará a imagem no formato da logo. Use um SVG com fundo transparente e a silhueta em preto sólido.</span></div>
                        <div><code>data-gsap-image</code> <span>URL da imagem hero — mesma imagem é usada no fundo e dentro da máscara.</span></div>
                        <div><code>data-gsap-distance</code> <span>Altura total da section em vh (padrão: <em>100</em> = 1 viewport). Valores maiores (<em>200</em>, <em>300</em>) estendem o efeito — o hero fica pinado por mais tempo durante o scroll.</span></div>
                        <div><code>data-gsap-mask-from</code> <span>Tamanho inicial da logo em % (padrão: <em>80</em>). Valores menores → logo aparece menor no início.</span></div>
                        <div><code>data-gsap-mask-to</code> <span>Tamanho final da logo em % (padrão: <em>110</em>). Acima de 100 a logo "engole" toda a tela.</span></div>
                        <div><code>data-gsap-mask-mobile-from</code> <span>Override do <code>mask-from</code> em telas ≤768px. Útil quando a logo fica desproporcional no mobile.</span></div>
                        <div><code>data-gsap-mask-mobile-to</code> <span>Override do <code>mask-to</code> em telas ≤768px. Se omitido, usa o valor desktop.</span></div>
                        <div><code>data-gsap-overlay-opacity</code> <span>Opacidade final do overlay no fim do scroll (padrão: <em>0.8</em>). Use <em>1</em> para cobertura total, <em>0</em> para desligar.</span></div>
                        <div><code>data-gsap-overlay-color</code> <span>Cor do overlay (padrão: <em>#ffffff</em>). Pode ser preto, cor da marca, etc.</span></div>
                        <div><code>data-gsap-parallax</code> <span>Desloc. vertical da imagem dentro da máscara em % (padrão: <em>20</em>). <em>0</em> desliga o parallax.</span></div>
                    </div>

                    <p class="gsap-howto-tip">
                        <strong>Observação sobre duração:</strong> o padrão <code>data-gsap-distance="100"</code> consome <strong>1 viewport de scroll</strong> para completar o efeito (rápido e sem espaço vazio).
                        Se quiser um efeito mais longo (estilo dropedition.com), use <code>data-gsap-distance="200"</code> ou <code>"300"</code> — o hero fica pinado no topo por mais tempo enquanto o scrub acontece.
                    </p>
                </div>

                <?php foreach ( $anim_ref as $group => $items ) : ?>
                <div class="gsap-ref-group">
                    <h3 class="gsap-ref-group__title"><?php echo esc_html( $group ); ?></h3>
                    <div class="gsap-ref-list">
                        <?php foreach ( $items as $item ) : ?>
                        <div class="gsap-ref-item">
                            <div class="gsap-ref-item__header">
                                <button class="gsap-copy-btn" data-copy="<?php echo esc_attr( $item['class'] ); ?>" title="Copiar classe">
                                    <code class="gsap-ref-class">.<?php echo esc_html( $item['class'] ); ?></code>
                                    <svg class="gsap-copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                </button>
                                <?php if ( $item['req'] ) : ?>
                                <span class="gsap-ref-req" title="Requer este plugin ativo">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php echo esc_html( $item['req'] ); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="gsap-ref-item__desc"><?php echo wp_kses( $item['desc'], [ 'code' => [] ] ); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
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
