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

        $out['smoother_smooth']    = min( 10, max( 0, floatval( $input['smoother_smooth'] ?? 1.5 ) ) );
        $out['smoother_effects']   = ! empty( $input['smoother_effects'] );
        $out['smoother_normalize'] = ! empty( $input['smoother_normalize'] );
        $out['smoother_wrapper']   = sanitize_text_field( $input['smoother_wrapper'] ?? '#smooth-wrapper' ) ?: '#smooth-wrapper';
        $out['smoother_content']   = sanitize_text_field( $input['smoother_content'] ?? '#smooth-content' ) ?: '#smooth-content';

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
            'ScrollSmoother'     => [ 'desc' => 'Scroll suavizado com inércia e parallax (data-speed / data-lag). Requer ScrollTrigger.', 'popular' => true, 'bonus' => true ],
            'SplitText'          => [ 'desc' => 'Divide texto em linhas, palavras e caracteres para animação granular.', 'popular' => true, 'bonus' => true ],
            'MorphSVGPlugin'     => [ 'desc' => 'Transição suave entre qualquer forma SVG.', 'popular' => true, 'bonus' => true ],
            'DrawSVGPlugin'      => [ 'desc' => 'Anima o traçado de paths SVG como se estivesse sendo desenhado.', 'popular' => true, 'bonus' => true ],
            'InertiaPlugin'      => [ 'desc' => 'Adiciona momentum/inércia ao Draggable para movimentos físicos.', 'bonus' => true ],
            'ScrambleTextPlugin' => [ 'desc' => 'Embaralha texto com caracteres aleatórios enquanto revela o conteúdo final.', 'bonus' => true ],
            'CustomBounce'       => [ 'desc' => 'Gera easings de bounce customizados para uso com CustomEase.', 'bonus' => true ],
            'CustomWiggle'       => [ 'desc' => 'Gera easings de vibração (wiggle) customizados para uso com CustomEase.', 'bonus' => true ],
            'Physics2DPlugin'    => [ 'desc' => 'Física 2D real: gravidade, velocidade e fricção em animações.', 'bonus' => true ],
            'PhysicsPropsPlugin' => [ 'desc' => 'Aplica física (velocidade/aceleração) a qualquer propriedade numérica.', 'bonus' => true ],
            'MotionPathHelper'   => [ 'desc' => 'Interface visual para editar motion paths diretamente no browser (dev).', 'bonus' => true ],
            'GSDevTools'         => [ 'desc' => 'Player interativo para inspecionar e depurar timelines GSAP (dev).', 'bonus' => true ],
            'EaselPlugin'        => [ 'desc' => 'Integração com EaselJS/CreateJS para animar elementos canvas.', 'bonus' => true ],
            'PixiPlugin'         => [ 'desc' => 'Integração com Pixi.js para animar propriedades de display objects.', 'bonus' => true ],
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
                    <div class="gsap-plugins-grid">
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
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ScrollSmoother: configurações avançadas -->
                <div class="gsap-card <?php echo empty( $s['plugins']['ScrollSmoother'] ) ? 'gsap-field--hidden' : ''; ?>" id="gsap-smoother-settings">
                    <h2 class="gsap-card__title">Configurações do ScrollSmoother</h2>
                    <div class="gsap-notice gsap-notice--warn" style="margin-bottom:1rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span><strong>Download manual necessário.</strong> ScrollSmoother não está disponível via CDN público — baixe o arquivo <code>ScrollSmoother.min.js</code> em <a href="https://gsap.com/docs/v3/Plugins/ScrollSmoother/" target="_blank" rel="noopener">gsap.com</a> e coloque em <code>assets/js/vendor/</code> do plugin. A fonte "CDN" nas configurações gerais não afeta este plugin.</span>
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
                               value="<?php echo esc_attr( $s['smoother_smooth'] ?? 1.5 ); ?>"
                               class="gsap-input gsap-input--small"
                               min="0" max="10" step="0.1">
                        <span class="gsap-field__desc"><strong>0</strong> = sem suavidade (scroll nativo) · <strong>1</strong> = leve · <strong>2+</strong> = muito suave. Padrão: 1.5</span>
                    </div>

                    <div class="gsap-field gsap-field--toggle">
                        <div class="gsap-field__label">
                            <label>Habilitar Effects (<code>data-speed</code> / <code>data-lag</code>)</label>
                            <span class="gsap-field__desc">Permite parallax e lag em elementos usando atributos <code>data-speed="0.5"</code> e <code>data-lag="0.3"</code> diretamente no HTML.</span>
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
                    [ 'class' => 'gsap-word-reveal',     'desc' => 'Cada palavra sobe do clip individualmente.',               'req' => 'ScrollTrigger', 'ex' => '<h3 class="gsap-word-reveal">Frase por palavras</h3>' ],
                    [ 'class' => 'gsap-text-fade',       'desc' => 'Texto completo faz fade + sobe suavemente.',               'req' => 'ScrollTrigger', 'ex' => '<p class="gsap-text-fade">Parágrafo de texto</p>' ],
                    [ 'class' => 'gsap-typewriter',      'desc' => 'Digita o conteúdo do elemento como uma máquina de escrever.','req' => '',             'ex' => '<span class="gsap-typewriter">Texto digitado...</span>' ],
                    [ 'class' => 'gsap-text-blur',       'desc' => 'Começa borrado e vai nitidificando.',                      'req' => 'ScrollTrigger', 'ex' => '<h2 class="gsap-text-blur">Título desfocado</h2>' ],
                    [ 'class' => 'gsap-text-highlight',  'desc' => 'Sublinhado colorido varre o texto (use em &lt;span&gt;).',  'req' => 'ScrollTrigger', 'ex' => '<h2>Nosso <span class="gsap-text-highlight">diferencial</span></h2>' ],
                    [ 'class' => 'gsap-scramble',        'desc' => 'Texto embaralha com caracteres aleatórios até revelar.',   'req' => '',              'ex' => '<span class="gsap-scramble">Texto secreto</span>' ],
                ],
                'Imagens' => [
                    [ 'class' => 'gsap-img-reveal',      'desc' => 'Clip-path abre a imagem (padrão: da esquerda). <code>data-gsap-dir="right|top|bottom"</code>', 'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-reveal" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-zoom',        'desc' => 'Entra com zoom + fade.',                                   'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-zoom" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-fade',        'desc' => 'Fade simples na imagem.',                                  'req' => 'ScrollTrigger', 'ex' => '<img class="gsap-img-fade" src="foto.jpg">' ],
                    [ 'class' => 'gsap-img-parallax',    'desc' => 'Parallax no scroll. O elemento pai vira o container.',     'req' => 'ScrollTrigger', 'ex' => '<div style="overflow:hidden"><img class="gsap-img-parallax" src="foto.jpg"></div>' ],
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
                ],
                'Especiais' => [
                    [ 'class' => 'gsap-counter',         'desc' => 'Anima um número do zero até o valor. Suporta <code>data-gsap-prefix</code>, <code>data-gsap-suffix</code>, <code>data-gsap-from</code>.', 'req' => 'ScrollTrigger', 'ex' => '<span class="gsap-counter" data-gsap-suffix="%">94</span>' ],
                    [ 'class' => 'gsap-marquee',         'desc' => 'Faixa de conteúdo em loop horizontal. Use <code>data-gsap-speed</code> e <code>data-gsap-dir="right"</code>.', 'req' => '', 'ex' => '<div class="gsap-marquee" data-gsap-speed="50"><span>Item</span><span>Item</span></div>' ],
                    [ 'class' => 'gsap-parallax',        'desc' => 'Parallax genérico (não-imagem). O pai deve ter overflow:hidden.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-parallax">Elemento flutuante</div>' ],
                    [ 'class' => 'gsap-reveal-line',     'desc' => 'Linha cresce de largura zero. Perfeito para divisores.   <code>data-gsap-axis="height"</code> para vertical.', 'req' => 'ScrollTrigger', 'ex' => '<hr class="gsap-reveal-line">' ],
                    [ 'class' => 'gsap-progress',        'desc' => 'Barra de progresso animada. Define a largura alvo no estilo.', 'req' => 'ScrollTrigger', 'ex' => '<div class="gsap-progress" style="width:80%"></div>' ],
                ],
                'ScrollSmoother — Parallax' => [
                    [ 'class' => 'gsap-speed-slow', 'desc' => 'Parallax lento: move a 0.5× do scroll — efeito de fundo/profundidade. Use <code>data-gsap-speed="0.3"</code> para valor customizado.', 'req' => 'ScrollSmoother', 'ex' => '' ],
                    [ 'class' => 'gsap-speed-fast', 'desc' => 'Parallax rápido: move a 1.5× do scroll — efeito de primeiro plano. Use <code>data-gsap-speed="2"</code> para valor customizado.', 'req' => 'ScrollSmoother', 'ex' => '' ],
                ],
                'Hover' => [
                    [ 'class' => 'gsap-magnetic',        'desc' => 'O elemento atrai o cursor como um ímã. Ideal para botões.  <code>data-gsap-strength="0.4"</code>', 'req' => '', 'ex' => '<button class="gsap-magnetic">Clique aqui</button>' ],
                    [ 'class' => 'gsap-tilt',            'desc' => 'Inclinação 3D ao passar o mouse. <code>data-gsap-strength="14"</code>', 'req' => '', 'ex' => '<div class="gsap-tilt">Card 3D</div>' ],
                    [ 'class' => 'gsap-hover-lift',      'desc' => 'Levita suavemente ao hover. <code>data-gsap-distance="-8"</code>', 'req' => '', 'ex' => '<div class="gsap-hover-lift">Card flutuante</div>' ],
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
                        <div class="gsap-trigger-item">
                            <code>gsap-on-scroll</code>
                            <span>Idêntico ao padrão — mantido por compatibilidade.</span>
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
                    </div>
                    <p class="gsap-trigger-example">
                        Exemplo: <code>gsap-fade-up gsap-on-scroll gsap-delay-2 gsap-slow</code>
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
                    </div>
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
            <div class="gsap-card">
                <h2 class="gsap-card__title">Como Usar o GSAP no WordPress</h2>
                <p class="gsap-card__desc">O GSAP já está sendo carregado. Use-o dentro de blocos HTML customizados, temas ou outros plugins.</p>

                <div class="gsap-usage-section">
                    <h3>1. Animação básica</h3>
                    <pre class="gsap-code"><code>&lt;script&gt;
gsap.to(".meu-elemento", {
    x: 100,
    opacity: 1,
    duration: 1
});
&lt;/script&gt;</code></pre>
                </div>

                <div class="gsap-usage-section">
                    <h3>2. ScrollTrigger (ativar por scroll)</h3>
                    <pre class="gsap-code"><code>&lt;script&gt;
gsap.registerPlugin(ScrollTrigger);

gsap.from(".hero-title", {
    scrollTrigger: ".hero-title",
    y: 60,
    opacity: 0,
    duration: 1
});
&lt;/script&gt;</code></pre>
                </div>

                <div class="gsap-usage-section">
                    <h3>3. Timeline encadeada</h3>
                    <pre class="gsap-code"><code>&lt;script&gt;
const tl = gsap.timeline({ defaults: { duration: 0.6 } });

tl.from(".nav",    { y: -40, opacity: 0 })
  .from(".hero h1", { y: 30, opacity: 0 }, "-=0.3")
  .from(".hero p",  { y: 30, opacity: 0 }, "-=0.3")
  .from(".hero .btn", { scale: 0.8, opacity: 0 }, "-=0.2");
&lt;/script&gt;</code></pre>
                </div>

                <div class="gsap-usage-section">
                    <h3>4. Enqueue correto em temas/plugins filhos</h3>
                    <pre class="gsap-code"><code>// No functions.php do seu tema:
add_action('wp_enqueue_scripts', function() {
    // O GSAP Manager já carregou o GSAP com o handle 'gsap'
    // Use-o como dependência nos seus scripts:
    wp_enqueue_script(
        'meu-script',
        get_template_directory_uri() . '/js/animacoes.js',
        ['gsap', 'gsap-scrolltrigger'], // dependências
        '1.0',
        true
    );
});</code></pre>
                </div>

                <div class="gsap-usage-section">
                    <h3>5. Handles disponíveis</h3>
                    <div class="gsap-table-wrap">
                        <table class="gsap-table">
                            <thead><tr><th>Plugin</th><th>Handle WordPress</th></tr></thead>
                            <tbody>
                                <tr><td>GSAP Core</td><td><code>gsap</code></td></tr>
                                <tr><td>ScrollTrigger</td><td><code>gsap-scrolltrigger</code></td></tr>
                                <tr><td>ScrollToPlugin</td><td><code>gsap-scrolltoplugin</code></td></tr>
                                <tr><td>Draggable</td><td><code>gsap-draggable</code></td></tr>
                                <tr><td>Flip</td><td><code>gsap-flip</code></td></tr>
                                <tr><td>MotionPathPlugin</td><td><code>gsap-motionpathplugin</code></td></tr>
                                <tr><td>TextPlugin</td><td><code>gsap-textplugin</code></td></tr>
                                <tr><td>Observer</td><td><code>gsap-observer</code></td></tr>
                                <tr><td>CustomEase</td><td><code>gsap-customease</code></td></tr>
                                <tr><td>EasePack</td><td><code>gsap-easepack</code></td></tr>
                                <tr><td>CSSRulePlugin</td><td><code>gsap-cssruleplugin</code></td></tr>
                                <tr><td colspan="2" style="padding-top:.75rem;font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Bonus (local)</td></tr>
                                <tr><td>ScrollSmoother</td><td><code>gsap-scrollsmoother</code></td></tr>
                                <tr><td>SplitText</td><td><code>gsap-splittext</code></td></tr>
                                <tr><td>MorphSVGPlugin</td><td><code>gsap-morphsvgplugin</code></td></tr>
                                <tr><td>DrawSVGPlugin</td><td><code>gsap-drawsvgplugin</code></td></tr>
                                <tr><td>InertiaPlugin</td><td><code>gsap-inertiaplugin</code></td></tr>
                                <tr><td>ScrambleTextPlugin</td><td><code>gsap-scrambletextplugin</code></td></tr>
                                <tr><td>CustomBounce</td><td><code>gsap-custombounce</code></td></tr>
                                <tr><td>CustomWiggle</td><td><code>gsap-customwiggle</code></td></tr>
                                <tr><td>Physics2DPlugin</td><td><code>gsap-physics2dplugin</code></td></tr>
                                <tr><td>GSDevTools</td><td><code>gsap-gsdevtools</code></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div><!-- /gsap-tab-panel usage -->

        </div>
        <?php
    }
}
