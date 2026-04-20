/**
 * GSAP Manager — Animações por Classe  v2.5.0
 *
 * Atributos de controle (opcionais em qualquer elemento):
 *   data-gsap-duration   — duração em segundos    (ex: 1.2)
 *   data-gsap-delay      — atraso em segundos      (ex: 0.3)
 *   data-gsap-ease       — easing do GSAP          (ex: "elastic.out(1,0.5)")
 *   data-gsap-distance   — distância em px         (ex: 80)
 *   data-gsap-stagger    — stagger em segundos     (ex: 0.15)
 *   data-gsap-start      — ScrollTrigger start     (ex: "top 70%")
 *   data-gsap-end        — ScrollTrigger end       (ex: "bottom 20%")  ← scrub
 *   data-gsap-scrub      — suavização do scrub     (ex: 1)
 *   data-gsap-dir        — direção (img-reveal)    (ex: "right")
 *   data-gsap-strength   — força do efeito         (ex: 0.5)
 *   data-gsap-from       — valor inicial (counter/zoom-reveal) (ex: 0 | 0.4)
 *   data-gsap-prefix     — prefixo (counter)       (ex: "R$")
 *   data-gsap-suffix     — sufixo (counter)        (ex: "%")
 *   data-gsap-speed      — velocidade (marquee)    (ex: 60)
 *   data-gsap-axis       — eixo (reveal-line)      (ex: "height")
 *   data-gsap-chars      — chars do scramble       (ex: "01", "!@#", "lowerCase")
 *   data-gsap-target     — seletor SVG alvo        (ex: "#shape-final")  ← morph
 *   data-gsap-separator  — separador de milhar     (ex: ".", ",")        ← counter
 *   data-gsap-from-color — cor inicial (char-color) (ex: "#616161")
 *   data-gsap-to-color   — cor final (char-color)   (ex: "#FFFFFF")  ← padrão: cor do Elementor
 *   data-gsap-blur       — intensidade inicial de blur em px (word-blur) (ex: 8)
 *   data-gsap-logo       — URL do SVG usado como máscara (mask-reveal)
 *   data-gsap-image      — URL da imagem de fundo (mask-reveal)
 *   data-gsap-mask-from  — mask-size inicial em % (mask-reveal, padrão 80)
 *   data-gsap-mask-to    — mask-size final em % (mask-reveal, padrão 110)
 *   data-gsap-overlay-opacity — opacidade final do overlay (mask-reveal, padrão 0.8)
 *   data-gsap-overlay-color   — cor do overlay (mask-reveal, padrão #ffffff)
 *   data-gsap-parallax   — desloc. Y da imagem interna em % (mask-reveal, padrão 20)
 *   data-gsap-scale-peak — escala máxima no centro (text-focus, padrão 2.1)
 *   data-gsap-y-peak     — deslocamento Y máximo em px (text-focus, padrão 60)
 *   data-gsap-rotation   — ângulo do leque em graus (text-focus, padrão 4)
 *   data-gsap-mobile-blur — "off" desabilita blur em <1024px (word-blur, text-focus)
 *
 * Classes de gatilho:
 *   (nenhuma)        → aguarda o elemento entrar na viewport (padrão)
 *   gsap-on-scroll   → igual ao padrão (mantido por compatibilidade — legado)
 *   gsap-on-load     → anima imediatamente quando a página carrega
 *   gsap-repeat      → re-anima toda vez que o elemento entra/sai da viewport
 *   gsap-char-scrub  → modifica gsap-char-reveal: progresso vinculado ao scroll (requer ScrollTrigger)
 *   gsap-word-scrub  → modifica gsap-word-reveal / gsap-word-blur: progresso vinculado ao scroll (requer ScrollTrigger)
 *   gsap-scrub       → modifica gsap-text-fade / gsap-text-blur / gsap-text-highlight: scrub genérico
 */

(function () {
    'use strict';

    // ─── Bootstrap ──────────────────────────────────────────────────────────
    // Setup estrutural do mask-reveal roda no DOMContentLoaded — antes do init()
    // e FORA da guarda reduced-motion, para garantir que a seção renderize
    // (ao menos estaticamente) mesmo quando o usuário desabilitou animações.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMaskRevealDOM);
    } else {
        setupMaskRevealDOM();
    }

    window.addEventListener('load', function () {
        var afterFonts = (document.fonts && document.fonts.ready)
            ? document.fonts.ready
            : { then: function (fn) { fn(); } };

        afterFonts.then(function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(init);
            });
        });
    });

    function init() {
        if (typeof gsap === 'undefined') {
            console.warn('[GSAP Manager] GSAP não encontrado.');
            return;
        }
        // Respeita a preferência do usuário por menos movimento (acessibilidade).
        // Com essa verificação, o GSAP não executa nenhuma animação quando
        // prefers-reduced-motion: reduce está ativo no sistema operacional.
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        if (typeof ScrollTrigger       !== 'undefined') { gsap.registerPlugin(ScrollTrigger); }
        if (typeof ScrambleTextPlugin  !== 'undefined') { gsap.registerPlugin(ScrambleTextPlugin); }
        if (typeof DrawSVGPlugin       !== 'undefined') { gsap.registerPlugin(DrawSVGPlugin); }
        if (typeof MorphSVGPlugin      !== 'undefined') { gsap.registerPlugin(MorphSVGPlugin); }
        initScrollSmootherEffects();
        initTextAnimations();
        initImageAnimations();
        initZoomReveal();
        initMaskReveal();
        initElementAnimations();
        initStaggerAnimations();
        initSpecialAnimations();
        initBonusAnimations();
        initHoverAnimations();
        initScrollFade();
        initVideoBgScrub();
        finalizeScrollSmoother();
    }

    // ─── ScrollSmoother: efeitos de parallax por classe ─────────────────────
    // gsap-speed-slow  → speed 0.6 (fundo/profundidade)
    // gsap-speed-fast  → speed 1.5 (primeiro plano)
    // data-gsap-speed  → velocidade customizada  (ex: data-gsap-speed="0.3")
    // data-gsap-lag    → atraso opcional          (ex: data-gsap-lag="0.2")
    //
    // Nota: lag NÃO é aplicado por padrão — com smooth ativo no ScrollSmoother,
    // o lag cria um momentum próprio que conflita com a inércia do scroll,
    // causando aceleração artificial no final do movimento.
    // Requer ScrollSmoother ativo com Effects habilitado nas configurações.
    function initScrollSmootherEffects() {
        if (typeof ScrollSmoother === 'undefined') { return; }
        var smoother = ScrollSmoother.get();
        if (!smoother) { return; }

        document.querySelectorAll('.gsap-speed-slow, .gsap-speed-fast').forEach(function (el) {
            var defaultSpeed = el.classList.contains('gsap-speed-fast') ? 1.5 : 0.6;
            var speed        = num(el, 'speed', defaultSpeed);
            var config       = { speed: speed };
            var lag          = num(el, 'lag', -1); // -1 = não definido
            if (lag >= 0) { config.lag = lag; }
            smoother.effects(el, config);
        });

        // ── Helper: altura do header (agora fora do smooth-wrapper) ─────────────
        function getHeaderOffset() {
            var h = document.querySelector('header,[data-elementor-type="header"],.elementor-location-header,#masthead');
            return h ? h.offsetHeight : 0;
        }

        // ── Âncoras: delega cliques em <a href="#..."> para o ScrollSmoother ──
        // Usa CAPTURE PHASE (true) para interceptar antes de qualquer handler do
        // Elementor/tema que possa chamar stopPropagation() e bloquear o evento.
        // O terceiro argumento do scrollTo ("top Xpx") compensa a altura do header
        // fixo para que a seção não fique escondida atrás dele.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link) { return; }
            var href = link.getAttribute('href');
            if (!href) { return; }
            var hashIndex = href.indexOf('#');
            if (hashIndex === -1) { return; }
            var id = href.slice(hashIndex);
            if (id.length < 2) { return; }
            // Só intercepta navegação na mesma página
            var pagePart = href.slice(0, hashIndex);
            if (pagePart && pagePart !== window.location.pathname && pagePart !== window.location.href.split('#')[0]) { return; }
            var target = document.querySelector(id);
            if (!target) { return; }
            e.preventDefault();
            smoother.scrollTo(target, true, 'top ' + getHeaderOffset() + 'px');
            if (history.pushState) { history.pushState(null, null, id); }
        }, true);

        // ── hashchange: digitação direta de hash na barra de endereços ────────
        window.addEventListener('hashchange', function () {
            if (!window.location.hash) { return; }
            var target = document.querySelector(window.location.hash);
            if (target) {
                smoother.scrollTo(target, true, 'top ' + getHeaderOffset() + 'px');
            }
        });
    }

    // ── ScrollTrigger.refresh() + hash inicial (chamado ao fim do init()) ─────
    function finalizeScrollSmoother() {
        if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
        if (typeof ScrollSmoother === 'undefined') { return; }
        var sm = ScrollSmoother.get();
        if (!sm || !window.location.hash) { return; }
        var hashTarget = document.querySelector(window.location.hash);
        if (!hashTarget) { return; }
        // Duplo rAF: garante que o ScrollTrigger.refresh() já calculou as posições
        // antes de rolar para o hash inicial da URL.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                var hEl = document.querySelector('header,[data-elementor-type="header"],.elementor-location-header,#masthead');
                var hH = hEl ? hEl.offsetHeight : 0;
                sm.scrollTo(hashTarget, false, 'top ' + hH + 'px');
            });
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    function num(el, attr, fallback) {
        var v = el.getAttribute('data-gsap-' + attr);
        return v !== null && v !== '' ? parseFloat(v) : fallback;
    }

    function str(el, attr, fallback) {
        var v = el.getAttribute('data-gsap-' + attr);
        return v !== null && v !== '' ? v : fallback;
    }

    function resolveDelay(el) {
        var attr = el.getAttribute('data-gsap-delay');
        if (attr !== null && attr !== '') return parseFloat(attr);
        for (var i = 1; i <= 5; i++) {
            if (el.classList.contains('gsap-delay-' + i)) return i * 0.1;
        }
        return 0;
    }

    function resolveDuration(el, defaultDuration) {
        var attr = el.getAttribute('data-gsap-duration');
        if (attr !== null && attr !== '') return parseFloat(attr);
        if (el.classList.contains('gsap-slow')) return defaultDuration * 1.8;
        if (el.classList.contains('gsap-fast')) return defaultDuration * 0.5;
        return defaultDuration;
    }

    /**
     * Executa fn() quando o elemento entra na viewport.
     *
     * PADRÃO: todas as animações aguardam o scroll — sem necessidade de
     * adicionar nenhuma classe de gatilho.
     *
     * gsap-on-load  → executa imediatamente (sem esconder, sem scroll)
     * gsap-on-scroll → comportamento idêntico ao padrão (mantido por compatibilidade)
     *
     * Prioridade de motor:
     *   1. ScrollTrigger (GSAP)   — se estiver carregado
     *   2. IntersectionObserver   — fallback nativo
     *   3. Execução imediata      — último recurso
     *
     * visibility: hidden → preserva o espaço no layout sem mostrar o conteúdo.
     * Removido no momento exato em que a animação começa — sem flash.
     */
    /**
     * playOnScroll(el, fn)
     *
     * Executa fn() quando o elemento entra na viewport.
     *
     * Gatilhos:
     *   (padrão)     → aguarda a viewport — anima uma vez
     *   gsap-on-load → executa imediatamente ao carregar
     *   gsap-repeat  → re-anima toda vez que o elemento entra/sai da viewport
     */
    function playOnScroll(el, fn) {
        // gsap-on-load: executa imediatamente sem esconder.
        // Limpa visibility inline caso o CSS esteja segurando o elemento
        // (ex.: .gsap-text-focus tem visibility:hidden pra evitar FOUC).
        if (el.classList.contains('gsap-on-load')) {
            el.style.visibility = 'visible';
            fn();
            return;
        }

        var repeat = el.classList.contains('gsap-repeat');

        // Esconde até a animação começar — preserva espaço (sem layout shift)
        el.style.visibility = 'hidden';

        function reveal() {
            el.style.visibility = 'visible';
            fn();
        }

        function reset() {
            // Re-esconde o elemento para que re-entre com animação na próxima vez
            el.style.visibility = 'hidden';
        }

        // Motor 1: ScrollTrigger
        if (typeof ScrollTrigger !== 'undefined') {
            ScrollTrigger.create({
                trigger:     el,
                start:       str(el, 'start', 'top 88%'),
                once:        !repeat,
                onEnter:     reveal,
                onLeave:     repeat ? reset  : undefined,
                onLeaveBack: repeat ? reset  : undefined,
                onEnterBack: repeat ? reveal : undefined,
            });
            return;
        }

        // Motor 2: IntersectionObserver (funciona mesmo sem ScrollTrigger)
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        if (!repeat) { obs.unobserve(entry.target); }
                        reveal();
                    } else if (repeat) {
                        reset();
                    }
                });
            }, { rootMargin: '0px 0px -12% 0px', threshold: 0 });
            io.observe(el);
            return;
        }

        // Motor 3: fallback — exibe e anima imediatamente
        el.style.visibility = 'visible';
        fn();
    }

    // ─── Captura e aplicação de estilos ─────────────────────────────────────

    /**
     * Encontra o elemento que realmente contém o texto e os estilos visuais.
     *
     * PROBLEMA: gsap-char-reveal pode estar num wrapper div (Elementor coloca
     * classes customizadas no container do widget, não no heading). Nesses casos,
     * o <h2> com font/color está DENTRO de el. Se operarmos sobre o wrapper:
     *   - captureStyles lê os estilos do wrapper (sem cor/fonte)
     *   - el.innerHTML = '' destrói o <h2> inteiro
     *   - as spans ficam dentro de um div sem estilo → tudo parece errado
     *   - onComplete r.revert restaura o <h2> → estilos voltam
     *
     * SOLUÇÃO: descer na árvore DOM até o elemento que tem nós de texto diretos
     * (o elemento que REALMENTE tem o texto e os estilos aplicados).
     */
    function findTextTarget(el) {
        // Verifica se este elemento tem nós de texto não-vazios diretamente
        for (var i = 0; i < el.childNodes.length; i++) {
            var node = el.childNodes[i];
            if (node.nodeType === 3 && node.textContent.trim().length > 0) {
                return el; // tem texto direto: este é o alvo
            }
        }
        // Desce para o primeiro filho elemento e tenta de novo
        if (el.firstElementChild) {
            return findTextTarget(el.firstElementChild);
        }
        return el; // fallback
    }

    /**
     * Captura TODOS os estilos visuais relevantes do elemento de texto.
     *
     * Capturamos explicitamente em vez de depender de herança CSS porque:
     * 1. O elemento alvo pode ser um descendente — estilos do tema/builder
     *    estão nele, não no elemento com gsap-char-reveal.
     * 2. Frameworks CSS (Elementor, Bootstrap, temas) frequentemente têm
     *    regras para 'span' que sobrescrevem herança (ex: span { color: initial }).
     * 3. A captura acontece AGORA (no scroll entry) quando todos os estilos
     *    do WordPress/Elementor já foram aplicados — timing correto.
     */
    function captureStyles(el) {
        var c = window.getComputedStyle(el);
        return {
            color:                c.color,
            fontSize:             c.fontSize,
            fontFamily:           c.fontFamily,
            fontWeight:           c.fontWeight,
            fontStyle:            c.fontStyle,
            fontVariant:          c.fontVariant,
            fontStretch:          c.fontStretch,
            lineHeight:           c.lineHeight,
            letterSpacing:        c.letterSpacing,
            textTransform:        c.textTransform,
            textShadow:           c.textShadow,
            // Gradient text (Elementor Text Gradient, temas modernos)
            webkitTextFillColor:  c.webkitTextFillColor  || '',
            webkitTextStrokeWidth:c.webkitTextStrokeWidth || '',
            webkitTextStrokeColor:c.webkitTextStrokeColor || '',
            backgroundImage:      c.backgroundImage       || '',
            backgroundClip:       c.backgroundClip        || '',
            webkitBackgroundClip: c.webkitBackgroundClip  || '',
            backgroundSize:       c.backgroundSize        || '',
            backgroundPosition:   c.backgroundPosition    || '',
        };
    }

    /**
     * Aplica os estilos capturados diretamente como inline style no span.
     * Inline style garante que nada no cascade do tema/builder possa sobrescrever.
     */
    function applyStyles(span, s) {
        span.style.fontSize      = s.fontSize;
        span.style.fontFamily    = s.fontFamily;
        span.style.fontWeight    = s.fontWeight;
        span.style.fontStyle     = s.fontStyle;
        span.style.fontVariant   = s.fontVariant;
        span.style.fontStretch   = s.fontStretch;
        span.style.lineHeight    = s.lineHeight;
        span.style.letterSpacing = s.letterSpacing;
        if (s.textTransform && s.textTransform !== 'none') span.style.textTransform = s.textTransform;
        if (s.textShadow    && s.textShadow    !== 'none') span.style.textShadow    = s.textShadow;

        // Texto com gradiente CSS: -webkit-text-fill-color: transparent + background gradient
        // A verificação inclui variações de formatação do rgba() entre browsers
        var alpha = s.webkitTextFillColor.match(/[\d.]+\)$/);
        var isFillTransparent = (alpha && parseFloat(alpha[0]) === 0) ||
                                 s.webkitTextFillColor === 'transparent';
        var hasGradient = s.backgroundImage && s.backgroundImage !== 'none';

        if (isFillTransparent && hasGradient) {
            span.style.backgroundImage      = s.backgroundImage;
            span.style.backgroundSize       = s.backgroundSize   || 'auto';
            span.style.backgroundPosition   = s.backgroundPosition || '0% 0%';
            span.style.webkitBackgroundClip = 'text';
            span.style.backgroundClip       = 'text';
            span.style.webkitTextFillColor  = 'transparent';
            span.style.color                = 'transparent';
        } else {
            span.style.color = s.color;
            if (s.webkitTextFillColor && !isFillTransparent) {
                span.style.webkitTextFillColor = s.webkitTextFillColor;
            }
        }

        if (s.webkitTextStrokeWidth && s.webkitTextStrokeWidth !== '0px') {
            span.style.webkitTextStrokeWidth = s.webkitTextStrokeWidth;
            span.style.webkitTextStrokeColor = s.webkitTextStrokeColor;
        }
    }

    function splitChars(el) {
        var savedHTML = el.innerHTML;
        var target    = findTextTarget(el);
        var styles    = captureStyles(target);
        var text      = target.textContent;

        target.innerHTML = '';
        target.setAttribute('aria-label', text);

        var spans = [];

        // Divide em palavras e espaços. Cada palavra recebe um wrapper
        // com white-space: nowrap para impedir que o browser quebre
        // a linha no meio de uma palavra (entre caracteres individuais).
        // Os espaços entre palavras ficam como nós de texto normais,
        // permitindo que a quebra de linha ocorra entre palavras — como
        // no texto original.
        text.split(/(\s+)/).forEach(function (token) {
            if (/^\s+$/.test(token)) {
                // Espaço entre palavras: nó de texto simples → quebra de linha natural
                target.appendChild(document.createTextNode(' '));
            } else if (token.length > 0) {
                // Wrapper da palavra — inline-block + nowrap evita break dentro da palavra
                var wordWrap = document.createElement('span');
                wordWrap.style.display    = 'inline-block';
                wordWrap.style.whiteSpace = 'nowrap';

                Array.from(token).forEach(function (ch) {
                    var span = document.createElement('span');
                    span.style.display    = 'inline-block';
                    span.style.willChange = 'clip-path, transform, opacity';
                    applyStyles(span, styles);
                    span.textContent = ch;
                    wordWrap.appendChild(span);
                    spans.push(span);
                });

                target.appendChild(wordWrap);
            }
        });

        return { spans: spans, revert: function () { el.innerHTML = savedHTML; } };
    }

    function splitWords(el) {
        var savedHTML = el.innerHTML;
        var target    = findTextTarget(el);
        var styles    = captureStyles(target);
        var text      = target.textContent;

        target.innerHTML = '';

        var spans = [];
        text.trim().split(/(\s+)/).forEach(function (token) {
            if (/^\s+$/.test(token)) {
                target.appendChild(document.createTextNode(token));
            } else {
                var span = document.createElement('span');
                span.style.display    = 'inline-block';
                span.style.willChange = 'clip-path, transform, opacity';
                applyStyles(span, styles);
                span.textContent = token;
                target.appendChild(span);
                spans.push(span);
            }
        });

        return { spans: spans, revert: function () { el.innerHTML = savedHTML; } };
    }

    // ─── Animações de Texto ─────────────────────────────────────────────────

    function initTextAnimations() {

        // gsap-char-reveal [+ gsap-char-scrub]
        // Sem gsap-char-scrub → dispara uma vez ao entrar na viewport.
        // Com gsap-char-scrub → progresso vinculado à posição do scroll (requer ScrollTrigger).
        document.querySelectorAll('.gsap-char-reveal').forEach(function (el) {
            if (el.classList.contains('gsap-char-scrub')) {
                if (typeof ScrollTrigger === 'undefined') {
                    console.warn('[GSAP Manager] gsap-char-scrub requer ScrollTrigger ativo nas configurações do plugin.');
                    return;
                }
                var r = splitChars(el);
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start',  'top 85%'),
                        end:     str(el, 'end',    'center 30%'),
                        scrub:   num(el, 'scrub',  1),
                    }
                }).from(r.spans, {
                    clipPath: 'inset(0 0 110% 0)',
                    y:        '25%',
                    opacity:  0,
                    stagger:  { each: num(el, 'stagger', 0.04), from: 'start' },
                    ease:     str(el, 'ease', 'none'),
                });
            } else {
                playOnScroll(el, function () {
                    var r = splitChars(el);
                    gsap.from(r.spans, {
                        clipPath:   'inset(0 0 110% 0)',
                        y:          '30%',
                        duration:   resolveDuration(el, 0.65),
                        delay:      resolveDelay(el),
                        stagger:    num(el, 'stagger', 0.028),
                        ease:       str(el, 'ease', 'power4.out'),
                        onComplete: r.revert,
                    });
                });
            }
        });

        // gsap-char-color
        // Revela cor char-a-char vinculado ao scroll (sempre scrub).
        // Cor inicial: cinza apagado (#616161). Cor final: cor computada do
        // elemento (a definida no Elementor) — sobrescritível por data-gsap-to-color.
        //
        //   data-gsap-from-color → cor inicial "apagada" (padrão: "#616161")
        //   data-gsap-to-color   → sobrescreve cor final  (padrão: cor original do texto)
        //   data-gsap-stagger    → atraso entre chars     (padrão: 0.02)
        //   data-gsap-start/end  → range do ScrollTrigger (padrão: "top 80%" / "bottom 50%")
        //   data-gsap-scrub      → suavização             (padrão: 1)
        document.querySelectorAll('.gsap-char-color').forEach(function (el) {
            if (typeof ScrollTrigger === 'undefined') {
                console.warn('[GSAP Manager] gsap-char-color requer ScrollTrigger ativo nas configurações do plugin.');
                return;
            }
            // Captura a cor original ANTES do split (evita pegar 'transparent' de gradiente)
            var origColor = window.getComputedStyle(findTextTarget(el)).color;
            var fromColor = str(el, 'from-color', '#616161');
            var toColor   = str(el, 'to-color', origColor);

            var r = splitChars(el);

            // applyStyles copia -webkit-text-fill-color do texto original nos spans.
            // Em WebKit/Chromium essa propriedade sobrescreve `color`, bloqueando
            // a animação. Forçamos currentColor para que `color` seja a cor efetiva.
            r.spans.forEach(function (span) {
                if (span.style.color !== 'transparent') {
                    span.style.webkitTextFillColor = 'currentColor';
                }
            });

            gsap.set(r.spans, { color: fromColor });

            gsap.timeline({
                scrollTrigger: {
                    trigger: el,
                    start:   str(el, 'start', 'top 80%'),
                    end:     str(el, 'end',   'bottom 50%'),
                    scrub:   num(el, 'scrub', 1),
                }
            }).to(r.spans, {
                color:    toColor,
                duration: 0.1,
                stagger:  num(el, 'stagger', 0.02),
                ease:     str(el, 'ease', 'none'),
            });
        });

        // gsap-word-reveal [+ gsap-word-scrub]
        // Sem gsap-word-scrub → dispara uma vez ao entrar na viewport.
        // Com gsap-word-scrub → progresso vinculado à posição do scroll (requer ScrollTrigger).
        document.querySelectorAll('.gsap-word-reveal').forEach(function (el) {
            if (el.classList.contains('gsap-word-scrub')) {
                if (typeof ScrollTrigger === 'undefined') {
                    console.warn('[GSAP Manager] gsap-word-scrub requer ScrollTrigger ativo nas configurações do plugin.');
                    return;
                }
                var r = splitWords(el);
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start',  'top 85%'),
                        end:     str(el, 'end',    'center 30%'),
                        scrub:   num(el, 'scrub',  1),
                    }
                }).from(r.spans, {
                    clipPath: 'inset(0 0 110% 0)',
                    y:        '25%',
                    opacity:  0,
                    stagger:  { each: num(el, 'stagger', 0.1), from: 'start' },
                    ease:     str(el, 'ease', 'none'),
                });
            } else {
                playOnScroll(el, function () {
                    var r = splitWords(el);
                    gsap.from(r.spans, {
                        clipPath:   'inset(0 0 110% 0)',
                        y:          '30%',
                        duration:   resolveDuration(el, 0.6),
                        delay:      resolveDelay(el),
                        stagger:    num(el, 'stagger', 0.07),
                        ease:       str(el, 'ease', 'power4.out'),
                        onComplete: r.revert,
                    });
                });
            }
        });

        // gsap-word-blur [+ gsap-word-scrub]
        // Cada palavra entra com opacity + blur + slide Y (entrando em foco).
        // Blur permanece ativo no mobile por padrão — para desativar em casos
        // de performance ruim em dispositivos fracos, use
        // data-gsap-mobile-blur="off" no elemento.
        //
        //   data-gsap-blur         → intensidade inicial do blur em px (padrão: 8)
        //   data-gsap-distance     → translate Y inicial em px         (padrão: 20)
        //   data-gsap-stagger      → intervalo entre palavras          (padrão: 0.03)
        //   data-gsap-duration     → duração por palavra               (padrão: 0.5)
        //   data-gsap-ease         → curva de easing                   (padrão: "power3.out")
        //   data-gsap-mobile-blur  → "off" desabilita blur em <1024px  (padrão: on)
        document.querySelectorAll('.gsap-word-blur').forEach(function (el) {
            var isMobile    = window.innerWidth < 1024;
            var mobileBlur  = str(el, 'mobile-blur', 'on') !== 'off';
            var useBlur     = !isMobile || mobileBlur;
            var blurPx      = num(el, 'blur', 8);
            var distance    = num(el, 'distance', 20);

            var fromState = { opacity: 0, y: distance };
            if (useBlur) { fromState.filter = 'blur(' + blurPx + 'px)'; }

            if (el.classList.contains('gsap-word-scrub')) {
                if (typeof ScrollTrigger === 'undefined') {
                    console.warn('[GSAP Manager] gsap-word-scrub requer ScrollTrigger ativo nas configurações do plugin.');
                    return;
                }
                var r = splitWords(el);
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start', 'top 85%'),
                        end:     str(el, 'end',   'center 30%'),
                        scrub:   num(el, 'scrub', 1),
                    }
                }).from(r.spans, Object.assign({}, fromState, {
                    stagger: { each: num(el, 'stagger', 0.03), from: 'start' },
                    ease:    str(el, 'ease', 'none'),
                }));
            } else {
                playOnScroll(el, function () {
                    var r = splitWords(el);
                    gsap.from(r.spans, Object.assign({}, fromState, {
                        duration:   resolveDuration(el, 0.5),
                        delay:      resolveDelay(el),
                        stagger:    num(el, 'stagger', 0.03),
                        ease:       str(el, 'ease', 'power3.out'),
                        onComplete: r.revert,
                    }));
                });
            }
        });

        // gsap-text-focus
        // "Foco gaussiano": cada palavra entra com chars em curva de sino —
        // letras do meio maiores, mais baixas e fora de foco, bordas menores e
        // próximas do baseline. Tudo se reorganiza pro estado final (scale:1,
        // y:0, blur:0) com stagger from:center. Inspirado em enumeramolecular.com.
        //
        //   data-gsap-scale-peak    → scale máximo no centro         (padrão: 2.1)
        //   data-gsap-y-peak        → deslocamento Y máximo em px    (padrão: 60)
        //   data-gsap-rotation      → ângulo do leque em graus       (padrão: 4)
        //   data-gsap-blur          → blur inicial em px             (padrão: 12)
        //   data-gsap-duration      → duração por palavra em s       (padrão: 1.8)
        //   data-gsap-stagger       → amount total do stagger em s   (padrão: 0.75)
        //   data-gsap-ease          → curva de easing                (padrão: "power2.inOut")
        //   data-gsap-mobile-blur   → "off" desabilita blur em <1024px (padrão: on)
        document.querySelectorAll('.gsap-text-focus').forEach(function (el) {
            var isMobile   = window.innerWidth < 1024;
            var mobileBlur = str(el, 'mobile-blur', 'on') !== 'off';
            var useBlur    = !isMobile || mobileBlur;

            var scalePeak    = num(el, 'scale-peak', 2.1);
            var yPeak        = num(el, 'y-peak',     60);
            var rotationPeak = num(el, 'rotation',   4);
            var blurPx       = num(el, 'blur',       12);

            // splitChars já trata: aria-label no target, wrapper por palavra
            // (white-space:nowrap para não quebrar dentro da palavra) e captura
            // de estilos. Agrupamos os chars retornados pelo parent (wrapper de
            // palavra) para aplicar a curva gaussiana por-palavra.
            playOnScroll(el, function () {
                var r      = splitChars(el);
                var groups = new Map();
                r.spans.forEach(function (ch) {
                    var word = ch.parentElement;
                    if (!groups.has(word)) { groups.set(word, []); }
                    groups.get(word).push(ch);
                });

                // Anima cada palavra separadamente — curva gaussiana é por-palavra
                groups.forEach(function (chars) {
                    var n = chars.length;
                    if (!n) { return; }

                    // Função triangular: pico no meio da palavra
                    function triangularT(e) {
                        return e < Math.ceil(n / 2)
                            ? e
                            : Math.ceil(n / 2) - Math.abs(Math.floor(n / 2) - e) - 1;
                    }

                    var fromState = {
                        transformOrigin: '50% 100%',
                        scale: function (i) { return gsap.utils.mapRange(0, Math.ceil(n / 2), 0.5, scalePeak, triangularT(i)); },
                        y:     function (i) { return gsap.utils.mapRange(0, Math.ceil(n / 2), 0,   yPeak,     triangularT(i)); },
                        rotation: function (i) {
                            var t = triangularT(i);
                            return i < n / 2
                                ? gsap.utils.mapRange(0, Math.ceil(n / 2), -rotationPeak, 0, t)
                                : gsap.utils.mapRange(0, Math.ceil(n / 2), 0, rotationPeak, t);
                        },
                        opacity: 0,
                    };
                    if (useBlur) { fromState.filter = 'blur(' + blurPx + 'px)'; }

                    var toState = {
                        y: 0, rotation: 0, scale: 1, opacity: 1,
                        ease:     str(el, 'ease', 'power2.inOut'),
                        duration: resolveDuration(el, 1.8),
                        delay:    resolveDelay(el),
                        stagger:  { amount: num(el, 'stagger', 0.75), from: 'center' },
                    };
                    if (useBlur) { toState.filter = 'blur(0px)'; }

                    gsap.fromTo(chars, fromState, toState);
                });
            });
        });

        document.querySelectorAll('.gsap-text-fade').forEach(function (el) {
            if (el.classList.contains('gsap-scrub')) {
                if (typeof ScrollTrigger === 'undefined') { return; }
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start', 'top 85%'),
                        end:     str(el, 'end',   'center 30%'),
                        scrub:   num(el, 'scrub', 1),
                    }
                }).from(el, {
                    y:       num(el, 'distance', 24),
                    opacity: 0,
                    ease:    str(el, 'ease', 'none'),
                });
            } else {
                playOnScroll(el, function () {
                    gsap.from(el, {
                        y:        num(el, 'distance', 24),
                        opacity:  0,
                        duration: resolveDuration(el, 0.9),
                        delay:    resolveDelay(el),
                        ease:     str(el, 'ease', 'power3.out'),
                    });
                });
            }
        });

        document.querySelectorAll('.gsap-typewriter').forEach(function (el) {
            var text     = el.textContent;
            el.textContent = '';
            el.style.cssText += ';border-right:2px solid currentColor;white-space:nowrap;overflow:hidden;display:inline-block;';
            var duration = resolveDuration(el, text.length * 0.045);
            var delay    = resolveDelay(el);
            function play() {
                var proxy = { val: 0 };
                gsap.to(proxy, {
                    val: 1, duration: duration, delay: delay, ease: 'none',
                    onUpdate: function () {
                        el.textContent = text.slice(0, Math.round(proxy.val * text.length));
                    },
                    onComplete: function () {
                        gsap.to(el, {
                            borderColor: 'transparent', repeat: 5, yoyo: true,
                            duration: 0.45, delay: 0.4,
                            onComplete: function () { el.style.borderRight = 'none'; },
                        });
                    }
                });
            }
            playOnScroll(el, play);
        });

        document.querySelectorAll('.gsap-text-blur').forEach(function (el) {
            if (el.classList.contains('gsap-scrub')) {
                if (typeof ScrollTrigger === 'undefined') { return; }
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start', 'top 85%'),
                        end:     str(el, 'end',   'center 30%'),
                        scrub:   num(el, 'scrub', 1),
                    }
                }).from(el, {
                    filter:  'blur(14px)',
                    opacity: 0,
                    y:       num(el, 'distance', 10),
                    ease:    str(el, 'ease', 'none'),
                });
            } else {
                playOnScroll(el, function () {
                    gsap.from(el, {
                        filter:   'blur(14px)',
                        opacity:  0,
                        y:        num(el, 'distance', 10),
                        duration: resolveDuration(el, 1.1),
                        delay:    resolveDelay(el),
                        ease:     str(el, 'ease', 'power2.out'),
                    });
                });
            }
        });

        document.querySelectorAll('.gsap-text-highlight').forEach(function (el) {
            gsap.set(el, { backgroundSize: '0% 40%' });
            if (el.classList.contains('gsap-scrub')) {
                if (typeof ScrollTrigger === 'undefined') { return; }
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start', 'top 85%'),
                        end:     str(el, 'end',   'center 30%'),
                        scrub:   num(el, 'scrub', 1),
                    }
                }).to(el, {
                    backgroundSize: '100% 40%',
                    ease: str(el, 'ease', 'none'),
                });
            } else {
                playOnScroll(el, function () {
                    gsap.to(el, {
                        backgroundSize: '100% 40%',
                        duration: resolveDuration(el, 0.75),
                        delay:    resolveDelay(el),
                        ease:     str(el, 'ease', 'power2.inOut'),
                    });
                });
            }
        });

    }

    // ─── Animações de Imagem ────────────────────────────────────────────────

    function initImageAnimations() {

        document.querySelectorAll('.gsap-img-reveal').forEach(function (el) {
            var dirMap = {
                left:   'inset(0 100% 0 0)',
                right:  'inset(0 0 0 100%)',
                top:    'inset(100% 0 0 0)',
                bottom: 'inset(0 0 100% 0)',
            };
            var clipStart = dirMap[str(el, 'dir', 'left')] || dirMap.left;
            playOnScroll(el, function () {
                gsap.from(el, {
                    clipPath: clipStart,
                    duration: resolveDuration(el, 1.1),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power4.inOut'),
                });
            });
        });

        document.querySelectorAll('.gsap-img-zoom').forEach(function (el) {
            playOnScroll(el, function () {
                gsap.from(el, {
                    scale:    num(el, 'scale', 1.18),
                    opacity:  0,
                    duration: resolveDuration(el, 1.2),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power3.out'),
                });
            });
        });

        document.querySelectorAll('.gsap-img-fade').forEach(function (el) {
            playOnScroll(el, function () {
                gsap.from(el, {
                    opacity:  0,
                    duration: resolveDuration(el, 1.0),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power2.out'),
                });
            });
        });

        document.querySelectorAll('.gsap-img-parallax').forEach(function (el) {
            if (typeof ScrollTrigger === 'undefined') return;
            var parent = el.parentElement || el;
            parent.style.overflow = 'hidden';
            gsap.to(el, {
                y: num(el, 'distance', -70), ease: 'none',
                scrollTrigger: { trigger: parent, start: 'top bottom', end: 'bottom top', scrub: num(el, 'scrub', 1.5) }
            });
        });

        // ─── Img Scroll Scale ───────────────────────────────────────────────
        // Replica exatamente o efeito do dsgngroup.it (tema dreamslab):
        // a imagem tem translate(x,y) + scale(from) no início e anima de volta
        // pra translate(0,0) + scale(to) com scrub. O offset + scale inicial
        // criam a sensação de "imagem voando de um canto" enquanto escala
        // até preencher o container.
        //
        // ESTRUTURA OBRIGATÓRIA:
        //   <div class="gsap-scale-container" style="height:100vh">
        //     <img class="gsap-img-scroll-scale-pin"
        //          data-gsap-x="222"
        //          data-gsap-y="-123"
        //          data-gsap-from-scale="0.578"
        //          src="foto.jpg"
        //          style="width:100%; height:100%; object-fit:cover">
        //   </div>
        //
        // A imagem DEVE estar em 100% do container (width:100% height:100%
        // object-fit:cover). O "tamanho inicial reduzido" vem do
        // data-gsap-from-scale (não da largura CSS).
        //
        // Variantes:
        //   .gsap-img-scroll-scale       — sem pin (escala enquanto cruza viewport)
        //   .gsap-img-scroll-scale-pin   — com pin (container trava no topo)
        //
        // Atributos:
        //   data-gsap-x           → offset X inicial em px (ou "50%" pra %)   (padrão: 0)
        //   data-gsap-y           → offset Y inicial em px (ou "50%" pra %)   (padrão: 0)
        //   data-gsap-from-scale  → escala inicial da imagem                  (padrão: 0.578)
        //   data-gsap-to-scale    → escala final                              (padrão: 1)
        //   data-gsap-container   → seletor CSS do container (override)
        //   data-gsap-start       → start do ScrollTrigger                    (padrão pin: "top top")
        //   data-gsap-end         → end do ScrollTrigger                      (padrão pin: "bottom+=100% top")
        //   data-gsap-scrub       → suavização                                (padrão: true, tight)
        //   data-gsap-min-width   → largura mínima em px pra rodar            (padrão: 0, roda sempre)
        //   data-gsap-debug       → "1" loga o container detectado no console
        function findScaleContainer(el) {
            // 1. Marcação explícita pela classe (preferido — 100% confiável)
            var marked = el.closest('.gsap-scale-container');
            if (marked && marked !== el) { return { node: marked, source: 'marked' }; }

            // 2. Override via data-attr
            var sel = str(el, 'container', '');
            if (sel) {
                var custom = el.closest(sel);
                if (custom && custom !== el) { return { node: custom, source: 'data-attr' }; }
            }

            // 3. Fallback auto: sobe no DOM achando o primeiro ancestor que
            // (a) ENVOLVE completamente a imagem e (b) é meaningfully maior.
            var elRect = el.getBoundingClientRect();
            var p = el.parentElement;
            for (var i = 0; i < 8 && p && p !== document.body; i++) {
                var pRect = p.getBoundingClientRect();
                var wraps = elRect.left   >= pRect.left   - 1 &&
                            elRect.right  <= pRect.right  + 1 &&
                            elRect.top    >= pRect.top    - 1 &&
                            elRect.bottom <= pRect.bottom + 1;
                if (wraps && (pRect.width > elRect.width * 1.3 || pRect.height > elRect.height * 1.3)) {
                    return { node: p, source: 'auto' };
                }
                p = p.parentElement;
            }
            return { node: el.parentElement, source: 'fallback' };
        }

        document.querySelectorAll('.gsap-img-scroll-scale, .gsap-img-scroll-scale-pin').forEach(function (el) {
            if (typeof ScrollTrigger === 'undefined') { return; }

            // Gate opcional por largura mínima (dsgngroup original só roda >1200px)
            var minW = num(el, 'min-width', 0);
            if (minW > 0 && window.innerWidth <= minW) { return; }

            var pinned = el.classList.contains('gsap-img-scroll-scale-pin');
            var found  = findScaleContainer(el);
            if (!found || !found.node || found.node === el) { return; }
            var container = found.node;

            if (found.source === 'fallback' && typeof console !== 'undefined') {
                console.warn('[gsap-img-scroll-scale] Container não detectado — adicione .gsap-scale-container no elemento pai desejado.', el);
            }
            if (str(el, 'debug', '') === '1') {
                console.log('[gsap-img-scroll-scale]', { image: el, container: container, via: found.source });
            }

            // Lê parâmetros (matching dsgngroup exatamente)
            var xRaw = el.getAttribute('data-gsap-x');
            var yRaw = el.getAttribute('data-gsap-y');
            var xStart     = xRaw ? parseFloat(xRaw) : 0;
            var yStart     = yRaw ? parseFloat(yRaw) : 0;
            var xUnit      = (xRaw && xRaw.indexOf('%') >= 0) ? '%' : 'px';
            var yUnit      = (yRaw && yRaw.indexOf('%') >= 0) ? '%' : 'px';
            var scaleStart = num(el, 'from-scale', 0.578);
            var scaleEnd   = num(el, 'to-scale',   1);

            el.style.willChange      = 'transform';
            container.style.overflow = 'hidden';

            var scrubVal = (function () {
                var v = el.getAttribute('data-gsap-scrub');
                if (v === null || v === '') { return true; }
                var n = parseFloat(v);
                return isNaN(n) ? true : n;
            })();

            // GSAP handling nativo de x/y/scale — mais performático que
            // setar el.style.transform manualmente em cada frame.
            gsap.fromTo(el,
                {
                    x:       xUnit === '%' ? xStart + '%' : xStart,
                    y:       yUnit === '%' ? yStart + '%' : yStart,
                    scale:   scaleStart,
                    force3D: true,
                },
                {
                    x:       0,
                    y:       0,
                    scale:   scaleEnd,
                    ease:    'none',
                    force3D: true,
                    scrollTrigger: {
                        trigger:    container,
                        start:      str(el, 'start', pinned ? 'top top'           : 'top bottom'),
                        end:        str(el, 'end',   pinned ? 'bottom+=100% top' : 'bottom top'),
                        scrub:      scrubVal,
                        pin:        pinned ? container : false,
                        pinSpacing: pinned,
                        invalidateOnRefresh: true,
                    }
                }
            );
        });
    }

    // ─── Zoom Reveal ────────────────────────────────────────────────────────
    // Aplica no CONTAINER (.gsap-zoom-reveal).
    // O elemento filho direto (img, video, ou primeiro filho) escala de
    // data-gsap-from (padrão 0.15) até 1 enquanto o scroll avança,
    // com o container pinado na tela durante toda a animação.
    //
    //   data-gsap-from   → escala inicial       (ex: 0.2)
    //   data-gsap-end    → distância de scroll  (ex: "+=200%")
    //   data-gsap-scrub  → suavização do scrub  (ex: 1.5)

    function initZoomReveal() {
        if (typeof ScrollTrigger === 'undefined') { return; }

        // ── Correção global: scroll-behavior:smooth no <html> quebra as
        // medições do ScrollTrigger.refresh() (comum no Bootstrap 5 / Elementor).
        document.documentElement.style.scrollBehavior = 'auto';

        document.querySelectorAll('.gsap-zoom-reveal').forEach(function (el) {
            // Escala sempre o PRIMEIRO FILHO DIRETO do container.
            // Isso cobre tanto <img> direta quanto wrappers do Elementor/Gutenberg
            // (.elementor-widget-image, .wp-block-image, etc.) que envolvem a imagem
            // em múltiplas camadas — escalando o wrapper o efeito de clipping funciona.
            var target = el.firstElementChild;
            if (!target) { return; }

            var fromScale = num(el, 'from', 0.4);
            var endVal    = str(el, 'end', '+=150%');
            var scrubVal  = num(el, 'scrub', 1);

            // Garante que o container ocupe 100vh, centralize o conteúdo
            // e recorte a imagem durante a escala.
            el.style.height         = '100vh';
            el.style.display        = 'flex';
            el.style.alignItems     = 'center';
            el.style.justifyContent = 'center';
            el.style.overflow       = 'hidden';

            // Remove transition dos elementos PAI (Elementor aplica transition:all
            // nas seções/containers, o que faz o browser tentar suavizar cada
            // update de transform que o GSAP faz a 60fps, causando comportamento errático).
            var anc = el.parentElement;
            while (anc && anc !== document.body) {
                anc.style.transitionProperty = 'none';
                anc = anc.parentElement;
            }

            // pinReparent:true move o elemento para o <body> durante o período
            // de pin, escapando qualquer ancestor com overflow:hidden, transform
            // ou will-change que impeça o position:fixed de funcionar corretamente.
            // É a solução recomendada pelo GSAP para ambientes WordPress/Elementor
            // onde os ancestors não podem ser modificados de forma confiável.
            gsap.fromTo(target,
                { scale: fromScale, transformOrigin: '50% 50%' },
                {
                    scale: 1,
                    ease:  'none',
                    scrollTrigger: {
                        trigger:             el,
                        start:               'top top',
                        end:                 endVal,
                        pin:                 true,
                        pinSpacing:          true,
                        pinReparent:         true,
                        anticipatePin:       1,
                        scrub:               scrubVal,
                        invalidateOnRefresh: true,
                    }
                }
            );
        });
    }

    // ─── Mask Reveal ────────────────────────────────────────────────────────
    // Hero com logo-máscara crescente + parallax interno + overlay branco.
    // Inspirado em https://dropedition.com/ — usa CSS mask-image + ScrollTrigger scrub.
    //
    // Uso no Elementor (widget HTML):
    //   <div class="gsap-mask-reveal"
    //        data-gsap-logo="URL_SVG"
    //        data-gsap-image="URL_IMG"></div>
    //
    // Atributos opcionais:
    //   data-gsap-distance="100"        — altura total da section em vh (padrão 100 = 1 viewport)
    //   data-gsap-mask-from="80"        — mask-size inicial em %   (padrão 80)
    //   data-gsap-mask-mobile-from="50" — override do mask-from em telas ≤768px
    //   data-gsap-mask-mobile-to="100"  — override do mask-to em telas ≤768px
    //   data-gsap-mask-to="110"         — mask-size final em %     (padrão 110)
    //   data-gsap-overlay-opacity="0.8" — opacidade final do overlay (padrão 0.8)
    //   data-gsap-overlay-color="#fff"  — cor do overlay             (padrão #ffffff)
    //   data-gsap-parallax="20"         — desloc. Y da imagem em %   (padrão 20)
    //   data-gsap-start / data-gsap-end / data-gsap-scrub — ScrollTrigger custom
    //
    // A estrutura DOM é gerada no DOMContentLoaded (fora da guarda reduced-motion)
    // para que a seção renderize estaticamente mesmo sem animação.
    function setupMaskRevealDOM() {
        var items = document.querySelectorAll('.gsap-mask-reveal');
        if (!items.length) { return; }

        items.forEach(function (el) {
            if (el.classList.contains('gsap-mask-reveal--init')) { return; }

            var logo  = el.getAttribute('data-gsap-logo')  || '';
            var image = el.getAttribute('data-gsap-image') || '';
            if (!logo || !image) {
                console.warn('[GSAP Manager] gsap-mask-reveal requer data-gsap-logo e data-gsap-image.', el);
                return;
            }

            // distance = altura total da section (em vh). Default 100 = hero
            // ocupa exatamente 1 viewport e o efeito de scrub acontece
            // enquanto o user rola por esses 100vh. Valores maiores (ex:
            // 200, 300) tornam o efeito mais longo (pin extra).
            var distance     = parseFloat(el.getAttribute('data-gsap-distance')) || 100;
            // mask-from/to: aceitam override mobile via data-gsap-mask-mobile-from
            // e data-gsap-mask-mobile-to (breakpoint 768px). Fallback: desktop.
            var isMobile     = window.matchMedia('(max-width: 768px)').matches;
            var maskFromAttr = (isMobile && el.hasAttribute('data-gsap-mask-mobile-from'))
                               ? 'mask-mobile-from' : 'mask-from';
            var maskFrom     = num(el, maskFromAttr, 80);
            var overlayColor = el.getAttribute('data-gsap-overlay-color') || '#ffffff';

            el.classList.add('gsap-mask-reveal--init');

            var scroller = document.createElement('div');
            scroller.className = 'gsap-mask-reveal__scroller';
            scroller.style.height = distance + 'vh';

            var sticky = document.createElement('div');
            sticky.className = 'gsap-mask-reveal__sticky';

            var bg = document.createElement('img');
            bg.className = 'gsap-mask-reveal__bg';
            bg.src = image;
            bg.alt = '';

            var overlay = document.createElement('div');
            overlay.className = 'gsap-mask-reveal__overlay';
            overlay.style.backgroundColor = overlayColor;

            var mask = document.createElement('div');
            mask.className = 'gsap-mask-reveal__mask';
            var maskUrl = 'url("' + logo + '")';
            mask.style.maskImage       = maskUrl;
            mask.style.webkitMaskImage = maskUrl;
            mask.style.maskSize        = maskFrom + '%';
            mask.style.webkitMaskSize  = maskFrom + '%';

            var maskImg = document.createElement('img');
            maskImg.className = 'gsap-mask-reveal__mask-image';
            maskImg.src = image;
            maskImg.alt = '';

            mask.appendChild(maskImg);
            sticky.appendChild(bg);
            sticky.appendChild(overlay);
            sticky.appendChild(mask);
            scroller.appendChild(sticky);
            el.appendChild(scroller);
        });
    }

    // Anexa o timeline GSAP em cada .gsap-mask-reveal--init que já teve a
    // estrutura gerada. Chamado dentro de init() (respeita reduced-motion).
    //
    // Usa gsap.matchMedia() — padrão oficial do GSAP pra animações responsivas:
    // ao cruzar o breakpoint (768px), o timeline antigo é revertido e o novo
    // é criado automaticamente com os valores do breakpoint atual.
    function initMaskReveal() {
        if (typeof ScrollTrigger === 'undefined') { return; }

        document.querySelectorAll('.gsap-mask-reveal--init').forEach(function (el) {
            var scroller = el.querySelector('.gsap-mask-reveal__scroller');
            var mask     = el.querySelector('.gsap-mask-reveal__mask');
            var maskImg  = el.querySelector('.gsap-mask-reveal__mask-image');
            var overlay  = el.querySelector('.gsap-mask-reveal__overlay');
            if (!scroller || !mask) { return; }

            var overlayOpacity = num(el, 'overlay-opacity', 0.8);
            var parallaxY      = num(el, 'parallax',        20);

            var scrubRaw = el.getAttribute('data-gsap-scrub');
            var scrub    = (scrubRaw === null || scrubRaw === '') ? true : parseFloat(scrubRaw);

            var startAttr = str(el, 'start', 'top top');
            var endAttr   = str(el, 'end',   'bottom top');

            function buildTimeline(isMobile) {
                var fromAttr = (isMobile && el.hasAttribute('data-gsap-mask-mobile-from'))
                               ? 'mask-mobile-from' : 'mask-from';
                var toAttr   = (isMobile && el.hasAttribute('data-gsap-mask-mobile-to'))
                               ? 'mask-mobile-to'   : 'mask-to';
                var maskFrom = num(el, fromAttr, 80);
                var maskTo   = num(el, toAttr,   110);

                // gsap.set dentro do matchMedia é revertido ao cruzar breakpoint.
                gsap.set(mask, {
                    maskSize:       maskFrom + '%',
                    webkitMaskSize: maskFrom + '%',
                });

                gsap.timeline({
                    scrollTrigger: {
                        trigger: scroller,
                        start:   startAttr,
                        end:     endAttr,
                        scrub:   scrub,
                    }
                })
                    .to(mask,    { maskSize: maskTo + '%', webkitMaskSize: maskTo + '%', ease: 'none' })
                    .to(maskImg, { yPercent: parallaxY,    ease: 'none' }, '<')
                    .to(overlay, { opacity:  overlayOpacity, ease: 'none' }, '<');
            }

            var mm = gsap.matchMedia();
            mm.add('(max-width: 768px)', function () { buildTimeline(true);  });
            mm.add('(min-width: 769px)', function () { buildTimeline(false); });
        });
    }

    // ─── Animações de Elemento ──────────────────────────────────────────────

    function initElementAnimations() {
        var map = [
            { cls: 'gsap-fade-up',     from: function (el) { return { y:  num(el,'distance',42), opacity: 0 }; } },
            { cls: 'gsap-fade-down',   from: function (el) { return { y: -num(el,'distance',42), opacity: 0 }; } },
            { cls: 'gsap-fade-left',   from: function (el) { return { x:  num(el,'distance',42), opacity: 0 }; } },
            { cls: 'gsap-fade-right',  from: function (el) { return { x: -num(el,'distance',42), opacity: 0 }; } },
            { cls: 'gsap-fade-in',     from: function ()   { return { opacity: 0 }; } },
            { cls: 'gsap-scale-in',    from: function (el) { return { scale: num(el,'scale',0.82), opacity: 0 }; } },
            { cls: 'gsap-scale-out',   from: function (el) { return { scale: num(el,'scale',1.18), opacity: 0 }; } },
            { cls: 'gsap-rotate-in',   from: function (el) { return { rotation: num(el,'rotation',8), opacity: 0, transformOrigin: str(el,'origin','left bottom') }; } },
            { cls: 'gsap-flip-in',     from: function ()   { return { rotationX: 90, opacity: 0, transformOrigin: 'top center', transformPerspective: 900 }; } },
            { cls: 'gsap-clip-left',   from: function ()   { return { clipPath: 'inset(0 100% 0 0)' }; } },
            { cls: 'gsap-clip-right',  from: function ()   { return { clipPath: 'inset(0 0 0 100%)' }; } },
            { cls: 'gsap-clip-top',    from: function ()   { return { clipPath: 'inset(100% 0 0 0)' }; } },
            { cls: 'gsap-clip-bottom', from: function ()   { return { clipPath: 'inset(0 0 100% 0)' }; } },
        ];

        map.forEach(function (item) {
            document.querySelectorAll('.' + item.cls).forEach(function (el) {
                var fromState = item.from(el);
                playOnScroll(el, function () {
                    gsap.from(el, Object.assign({}, fromState, {
                        duration: resolveDuration(el, 0.85),
                        delay:    resolveDelay(el),
                        ease:     str(el, 'ease', 'power3.out'),
                    }));
                });
            });
        });
    }

    // ─── Animações em Grupo (Stagger) ────────────────────────────────────────

    function initStaggerAnimations() {
        var map = [
            { cls: 'gsap-stagger',        from: function () { return { y: 44, opacity: 0 }; },                                       stagger: 0.1  },
            { cls: 'gsap-stagger-left',   from: function () { return { x: 44, opacity: 0 }; },                                       stagger: 0.1  },
            { cls: 'gsap-stagger-right',  from: function () { return { x: -44, opacity: 0 }; },                                      stagger: 0.1  },
            { cls: 'gsap-stagger-scale',  from: function () { return { scale: 0.8, opacity: 0 }; },                                  stagger: 0.12 },
            { cls: 'gsap-stagger-fade',   from: function () { return { opacity: 0 }; },                                              stagger: 0.1  },
            { cls: 'gsap-stagger-rotate', from: function () { return { rotation: 12, opacity: 0, transformOrigin: 'left bottom' }; }, stagger: 0.1  },
            { cls: 'gsap-stagger-center', init: function () { return { y: 44, opacity: 0 }; }, stagger: 0.1, staggerFrom: 'center' },
        ];

        map.forEach(function (item) {
            // Suporte retrocompatível: entradas antigas usam 'from', novas usam 'init'
            var fromFn = item.init || item.from;
            document.querySelectorAll('.' + item.cls).forEach(function (el) {
                var children = Array.from(el.children);
                if (!children.length) return;
                var staggerVal = item.staggerFrom
                    ? { each: num(el, 'stagger', item.stagger), from: item.staggerFrom }
                    : num(el, 'stagger', item.stagger);
                playOnScroll(el, function () {
                    gsap.from(children, Object.assign({}, fromFn(), {
                        duration: resolveDuration(el, 0.7),
                        delay:    resolveDelay(el),
                        stagger:  staggerVal,
                        ease:     str(el, 'ease', 'power3.out'),
                    }));
                });
            });
        });
    }

    // ─── Animações Especiais ────────────────────────────────────────────────

    function initSpecialAnimations() {

        document.querySelectorAll('.gsap-counter').forEach(function (el) {
            var raw       = el.textContent.trim();
            var endVal    = parseFloat(raw.replace(/[^0-9.-]/g, '')) || num(el, 'to', 100);
            var startVal  = num(el, 'from', 0);
            var decimals  = raw.includes('.') ? (raw.split('.')[1] || '').length : 0;
            var prefix    = el.getAttribute('data-gsap-prefix')    || '';
            var suffix    = el.getAttribute('data-gsap-suffix')    || '';
            var separator = el.getAttribute('data-gsap-separator') || '';
            var obj       = { val: startVal };

            function format(v) {
                var s = v.toFixed(decimals);
                if (separator) {
                    var parts = s.split('.');
                    parts[0]  = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, separator);
                    s = parts.join('.');
                }
                return prefix + s + suffix;
            }

            el.textContent = format(startVal);
            playOnScroll(el, function () {
                gsap.to(obj, {
                    val: endVal, duration: resolveDuration(el, 2), delay: resolveDelay(el),
                    ease: str(el, 'ease', 'power2.out'),
                    onUpdate: function () { el.textContent = format(obj.val); }
                });
            });
        });

        document.querySelectorAll('.gsap-marquee').forEach(function (el) {
            var speed = num(el, 'speed', 45);
            var dir   = str(el, 'dir', 'left') === 'right' ? 1 : -1;
            el.style.overflow   = 'hidden';
            el.style.display    = 'flex';
            el.style.whiteSpace = 'nowrap';
            var inner = el.querySelector('[class*="marquee__inner"], [class*="marquee-inner"]') || (function () {
                var wrap = document.createElement('div');
                wrap.style.cssText = 'display:flex;gap:inherit;flex-shrink:0;';
                while (el.firstChild) wrap.appendChild(el.firstChild);
                el.appendChild(wrap);
                return wrap;
            })();
            inner.style.flexShrink = '0';
            var clone = inner.cloneNode(true);
            el.appendChild(clone);
            var w = inner.scrollWidth;
            // Guarda: elemento oculto (accordion, tab) tem largura zero — evita duration:Infinity
            if (!w) { return; }
            gsap.set([inner, clone], { x: dir < 0 ? 0 : -w });
            var tween = gsap.to([inner, clone], {
                x: dir < 0 ? -w : 0, duration: w / speed, ease: 'none', repeat: -1,
                modifiers: {
                    x: gsap.utils.unitize(function (x) {
                        return ((parseFloat(x) % w) + w) % w * dir + (dir > 0 ? -w : 0);
                    })
                }
            });
            // Pausa no hover (acessibilidade WCAG 2.1 — 2.2.2 Pause, Stop, Hide)
            el.addEventListener('mouseenter', function () { tween.pause(); });
            el.addEventListener('mouseleave', function () { tween.play(); });
        });

        document.querySelectorAll('.gsap-parallax').forEach(function (el) {
            if (typeof ScrollTrigger === 'undefined') return;
            gsap.to(el, {
                y: num(el, 'distance', -60), ease: 'none',
                scrollTrigger: { trigger: el.parentElement || el, start: 'top bottom', end: 'bottom top', scrub: num(el, 'scrub', 1) }
            });
        });

        document.querySelectorAll('.gsap-reveal-line').forEach(function (el) {
            var axis = str(el, 'axis', 'width');
            var fromState = {};
            fromState[axis] = 0;
            playOnScroll(el, function () {
                gsap.from(el, Object.assign({}, fromState, {
                    duration: resolveDuration(el, 1.0),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power4.inOut'),
                }));
            });
        });

        document.querySelectorAll('.gsap-progress').forEach(function (el) {
            playOnScroll(el, function () {
                gsap.from(el, {
                    width: '0%', duration: resolveDuration(el, 1.2),
                    delay: resolveDelay(el), ease: str(el, 'ease', 'power3.out'),
                });
            });
        });
    }

    // ─── Animações com Plugins Bonus ────────────────────────────────────────

    function initBonusAnimations() {

        // gsap-scramble — ScrambleTextPlugin
        // Embaralha com caracteres aleatórios enquanto revela o texto original.
        // data-gsap-chars → conjunto de chars (padrão: "upperCase"). Ex: "01", "!@#$%", "lowerCase"
        document.querySelectorAll('.gsap-scramble').forEach(function (el) {
            if (typeof ScrambleTextPlugin === 'undefined') {
                console.warn('[GSAP Manager] gsap-scramble requer ScrambleTextPlugin ativo nas configurações do plugin.');
                return;
            }
            var originalText = el.textContent.trim();
            var chars        = el.getAttribute('data-gsap-chars') || 'upperCase';
            playOnScroll(el, function () {
                gsap.to(el, {
                    duration:     resolveDuration(el, 1.5),
                    delay:        resolveDelay(el),
                    scrambleText: {
                        text:        originalText,
                        chars:       chars,
                        revealDelay: 0.3,
                        speed:       0.7,
                    },
                });
            });
        });

        // gsap-draw-svg — DrawSVGPlugin
        // Anima o stroke de um path/shape SVG de 0% a 100%, como se estivesse sendo desenhado.
        // Suporta modificador gsap-scrub para vincular o progresso ao scroll.
        // data-gsap-start  → início do ScrollTrigger (ex: "top 80%")
        // data-gsap-end    → fim do ScrollTrigger    (ex: "bottom 30%")
        document.querySelectorAll('.gsap-draw-svg').forEach(function (el) {
            if (typeof DrawSVGPlugin === 'undefined') {
                console.warn('[GSAP Manager] gsap-draw-svg requer DrawSVGPlugin ativo nas configurações do plugin.');
                return;
            }
            if (el.classList.contains('gsap-scrub')) {
                if (typeof ScrollTrigger === 'undefined') {
                    console.warn('[GSAP Manager] gsap-draw-svg + gsap-scrub requer ScrollTrigger ativo.');
                    return;
                }
                gsap.timeline({
                    scrollTrigger: {
                        trigger: el,
                        start:   str(el, 'start', 'top 85%'),
                        end:     str(el, 'end',   'bottom 30%'),
                        scrub:   num(el, 'scrub', 1),
                    }
                }).fromTo(el, { drawSVG: '0%' }, { drawSVG: '100%', ease: str(el, 'ease', 'none') });
            } else {
                playOnScroll(el, function () {
                    gsap.fromTo(el,
                        { drawSVG: '0%' },
                        {
                            drawSVG:  '100%',
                            duration: resolveDuration(el, 2),
                            delay:    resolveDelay(el),
                            ease:     str(el, 'ease', 'power2.out'),
                        }
                    );
                });
            }
        });

        // gsap-morph-svg — MorphSVGPlugin
        // Faz a transição suave de uma forma SVG para outra ao entrar na viewport.
        // data-gsap-target → seletor CSS do elemento SVG de destino (obrigatório)
        // Exemplo: <path class="gsap-morph-svg" data-gsap-target="#shape-final" d="...">
        document.querySelectorAll('.gsap-morph-svg').forEach(function (el) {
            if (typeof MorphSVGPlugin === 'undefined') {
                console.warn('[GSAP Manager] gsap-morph-svg requer MorphSVGPlugin ativo nas configurações do plugin.');
                return;
            }
            var targetSel = el.getAttribute('data-gsap-target');
            if (!targetSel) {
                console.warn('[GSAP Manager] gsap-morph-svg requer o atributo data-gsap-target com o seletor da forma alvo.');
                return;
            }
            var target = document.querySelector(targetSel);
            if (!target) {
                console.warn('[GSAP Manager] gsap-morph-svg: elemento "' + targetSel + '" não encontrado no DOM.');
                return;
            }
            playOnScroll(el, function () {
                gsap.to(el, {
                    morphSVG: target,
                    duration: resolveDuration(el, 1.5),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power2.inOut'),
                });
            });
        });
    }

    // ─── Animações de Hover ──────────────────────────────────────────────────

    function initHoverAnimations() {

        document.querySelectorAll('.gsap-magnetic').forEach(function (el) {
            var strength = num(el, 'strength', 0.4);
            var rafId    = null;
            el.addEventListener('mousemove', function (e) {
                var cx = e.clientX, cy = e.clientY;
                if (rafId) { return; }
                rafId = requestAnimationFrame(function () {
                    rafId = null;
                    var r = el.getBoundingClientRect();
                    gsap.to(el, {
                        x: (cx - r.left - r.width  / 2) * strength,
                        y: (cy - r.top  - r.height / 2) * strength,
                        duration: 0.4, ease: 'power3.out',
                    });
                });
            });
            el.addEventListener('mouseleave', function () {
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                gsap.to(el, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1,0.4)' });
            });
        });

        document.querySelectorAll('.gsap-tilt').forEach(function (el) {
            var strength = num(el, 'strength', 14);
            var rafId    = null;
            el.style.cssText += ';transform-style:preserve-3d;';
            gsap.set(el, { transformPerspective: 900 });
            el.addEventListener('mousemove', function (e) {
                var cx = e.clientX, cy = e.clientY;
                if (rafId) { return; }
                rafId = requestAnimationFrame(function () {
                    rafId = null;
                    var r  = el.getBoundingClientRect();
                    var rx = ((cy - r.top)  / r.height - 0.5) * -strength;
                    var ry = ((cx - r.left) / r.width  - 0.5) *  strength;
                    gsap.to(el, { rotationX: rx, rotationY: ry, duration: 0.3, ease: 'power2.out' });
                });
            });
            el.addEventListener('mouseleave', function () {
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                gsap.to(el, { rotationX: 0, rotationY: 0, duration: 0.8, ease: 'elastic.out(1,0.3)' });
            });
        });

        document.querySelectorAll('.gsap-hover-lift').forEach(function (el) {
            var dist = num(el, 'distance', -8);
            el.addEventListener('mouseenter', function () {
                gsap.to(el, { y: dist, duration: 0.35, ease: 'power2.out' });
            });
            el.addEventListener('mouseleave', function () {
                gsap.to(el, { y: 0, duration: 0.5, ease: 'elastic.out(1,0.5)' });
            });
        });

        // gsap-char-stretch-hover: cada char escala em Y baseado na distância
        // do mouse, com decay nos vizinhos. Inspirado em dsgngroup.it
        // (style-caption-timeline). Fica melhor com fonts condensed (Six Caps,
        // Bebas Neue, Anton, Oswald) que já são alongadas verticalmente.
        document.querySelectorAll('.gsap-char-stretch-hover').forEach(function (el) {
            var r = splitChars(el);
            if (!r.spans.length) { return; }

            var scale     = num(el, 'scale',     0.2);
            var neighbors = Math.max(0, num(el, 'neighbors', 1) | 0);
            var dur       = num(el, 'duration',  0.4);

            // transform-origin: bottom center faz o char crescer só pra cima,
            // mantendo a baseline alinhada (efeito "esticar" em vez de "inchar").
            r.spans.forEach(function (s) { s.style.transformOrigin = 'bottom center'; });

            var ticking = false;

            el.addEventListener('mousemove', function (e) {
                if (ticking) { return; }
                ticking = true;
                requestAnimationFrame(function () {
                    ticking = false;

                    var rects = r.spans.map(function (s) { return s.getBoundingClientRect(); });

                    // Acha o char mais próximo do mouseX (baseado no centro).
                    var closestIdx  = -1;
                    var closestDist = Infinity;
                    rects.forEach(function (rect, i) {
                        var cx = rect.left + rect.width / 2;
                        var d  = Math.abs(e.clientX - cx);
                        if (d < closestDist) { closestDist = d; closestIdx = i; }
                    });
                    if (closestIdx === -1) { return; }

                    var hRect  = rects[closestIdx];
                    var mouseX = e.clientX - hRect.left;
                    var center = hRect.width / 2 || 1;
                    // Fórmula: no centro do char → scaleY = 1 + 2*scale; nas bordas → 1 + scale.
                    var hovered = (1 + scale) + (scale * (center - Math.abs(center - mouseX))) / center;

                    r.spans.forEach(function (s, i) {
                        var dist = Math.abs(i - closestIdx);
                        var target;
                        if (dist === 0) {
                            target = hovered;
                        } else if (dist <= neighbors) {
                            var nbRect = rects[i];
                            var dPx    = Math.min(Math.abs(e.clientX - nbRect.left), center);
                            var decay  = 1 / dist;   // 1º vizinho: full; 2º: metade; 3º: 1/3
                            target = 1 + decay * (scale * (center - dPx)) / center;
                        } else {
                            target = 1;
                        }
                        gsap.to(s, { scaleY: target, duration: dur, ease: 'power4.out' });
                    });
                });
            });

            el.addEventListener('mouseleave', function () {
                gsap.to(r.spans, { scaleY: 1, duration: dur, ease: 'power4.out' });
            });
        });
    }

    // ─── Fade de opacidade por scroll (scrub) ───────────────────────────────
    // gsap-scroll-fade-out → opacidade 1 → 0 conforme o scroll avança
    // gsap-scroll-fade-in  → opacidade 0 → 1 conforme o scroll avança
    // data-gsap-factor     → distância de scroll como múltiplo da viewport
    //                        (default 0.5 — 50vh de scroll completa o fade)
    //
    // out: ancora em scroll=0 (página topo) — o fade começa assim que o usuário
    //      inicia a rolagem, não quando o elemento se move. Ideal para hero.
    // in : ancora em 'top bottom' do próprio elemento — começa quando o
    //      elemento entra no viewport.
    //
    // Requer ScrollTrigger. Sem ele, o elemento fica em seu estado inicial
    // (out = visível, in = invisível via CSS).
    function initScrollFade() {
        if (typeof ScrollTrigger === 'undefined') { return; }
        var nodes = document.querySelectorAll('.gsap-scroll-fade-out, .gsap-scroll-fade-in');
        if (!nodes.length) { return; }

        nodes.forEach(function (el) {
            if (el.dataset.gsapScrollFadeInit === '1') { return; }
            el.dataset.gsapScrollFadeInit = '1';

            var isOut  = el.classList.contains('gsap-scroll-fade-out');
            var factor = num(el, 'factor', 0.5);
            if (!isFinite(factor) || factor <= 0) { factor = 0.5; }

            if (isOut) {
                // Ancora no scroll da página — recalcula no refresh (resize).
                gsap.to(el, {
                    opacity: 0,
                    ease: 'none',
                    scrollTrigger: {
                        start: 0,
                        end: function () { return factor * window.innerHeight; },
                        scrub: true,
                        invalidateOnRefresh: true,
                    },
                });
            } else {
                gsap.fromTo(el, { opacity: 0 }, {
                    opacity: 1,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: el,
                        start: 'top bottom',
                        end: '+=' + (factor * 100) + '%',
                        scrub: true,
                    },
                });
            }
        });
    }

    // ─── Vídeo em background com scrub por scroll ───────────────────────────
    // A classe gsap-video-bg vai NO CONTAINER (ex: seção do Elementor com
    // background video). O JS:
    //   1. Aguarda o <video> ser injetado no container (Elementor faz isso via JS).
    //   2. Remove autoplay/loop e congela o play — scrub controla video.currentTime.
    //   3. Envolve o container em .gsap-video-bg-wrapper > .gsap-video-bg-sticky.
    //   4. A altura do wrapper = 100vh + (duration × factor × 100vh) — vídeo
    //      mais longo exige mais scroll.
    //
    // Se houver múltiplos <video> no container (Elementor faz crossfade com 2),
    // só o primeiro é controlado; os demais são pausados e escondidos.
    //
    // data-gsap-factor    → fator de duração do scrub (default 0.5)
    // data-gsap-preload   → "true" baixa o vídeo via fetch + blob (scrub suave)
    function initVideoBgScrub() {
        var containers = document.querySelectorAll('.gsap-video-bg');
        if (!containers.length) { return; }

        function getScrollTop() {
            if (typeof ScrollSmoother !== 'undefined') {
                var sm = ScrollSmoother.get();
                if (sm) { return sm.scrollTop(); }
            }
            return window.pageYOffset || document.documentElement.scrollTop || 0;
        }

        containers.forEach(function (container) {
            if (container.dataset.gsapVideoBgInit === '1') { return; }

            // Acha o <video>. Se o próprio container é <video>, usa ele.
            // Senão, procura descendente (Elementor injeta via JS — pode demorar).
            function findVideo() {
                if (container.tagName === 'VIDEO') { return container; }
                return container.querySelector('video');
            }

            var tries = 0;
            (function wait() {
                var v = findVideo();
                // Só prossegue quando o vídeo existe E tem src resolvido
                // (Elementor seta src depois de criar a tag).
                if (v && (v.currentSrc || v.getAttribute('src'))) {
                    setup(v);
                    return;
                }
                if (++tries < 80) { setTimeout(wait, 100); }
            })();

            function setup(video) {
                if (container.dataset.gsapVideoBgInit === '1') { return; }
                container.dataset.gsapVideoBgInit = '1';

                var factor  = num(container, 'factor', 0.5);
                if (!isFinite(factor) || factor <= 0) { factor = 0.5; }
                var preload = container.getAttribute('data-gsap-preload') === 'true';

                // ── Congela o vídeo controlado ─────────────────────────────────
                video.removeAttribute('autoplay');
                video.removeAttribute('loop');
                video.autoplay = false;
                video.loop     = false;
                video.pause();

                // Override do play: só chamadas internas via ctrlPlay() passam.
                var _native = HTMLMediaElement.prototype.play.bind(video);
                var _ok     = false;
                video.play = function () {
                    if (_ok) { return _native(); }
                    return Promise.resolve();
                };
                function ctrlPlay() {
                    _ok = true;
                    var p = _native();
                    _ok = false;
                    return p;
                }

                // Rede de segurança: se o navegador disparar 'play' (autoplay
                // residual, extensão, script externo), pausa imediatamente —
                // exceto quando o play é nosso (ctrlPlay).
                video.addEventListener('play', function () {
                    if (!_ok) { video.pause(); }
                });

                // ── Outros <video> no container (Elementor crossfade) ──────────
                // Pausa e esconde — o scrub precisa de um único frame-source.
                var all = container.querySelectorAll('video');
                all.forEach(function (other) {
                    if (other === video) { return; }
                    other.removeAttribute('autoplay');
                    other.removeAttribute('loop');
                    other.autoplay = false;
                    other.loop     = false;
                    try { other.pause(); } catch (e) {}
                    other.style.display = 'none';
                });

                // ── Reestrutura: wrapper + sticky envolvem o CONTAINER ─────────
                var parent = container.parentNode;
                if (!parent) { return; }

                var wrapper = document.createElement('div');
                wrapper.className = 'gsap-video-bg-wrapper';

                var sticky = document.createElement('div');
                sticky.className = 'gsap-video-bg-sticky';

                parent.insertBefore(wrapper, container);
                wrapper.appendChild(sticky);
                sticky.appendChild(container);

                // ── Altura do wrapper = 100vh + duration × factor × 100vh ──────
                // Vídeo de 10s com factor 0.5 = 1vh + 5vh extras de scroll.
                var duration = 0;
                var ready    = false;

                function applyWrapperHeight() {
                    var h    = window.innerHeight;
                    var dur  = duration > 0 ? duration : 1;
                    var total = h + (h * dur * factor);
                    wrapper.style.height = total + 'px';
                }

                function onMeta() {
                    duration = video.duration || 0;
                    ready    = duration > 0;
                    applyWrapperHeight();
                    // Primeiro seek pra pintar o frame inicial (0 em alguns
                    // browsers fica preto — 0.001 garante o primeiro frame).
                    try { video.currentTime = 0.001; } catch (e) {}
                    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
                    onScroll();
                }

                if (video.readyState >= 1 && video.duration) {
                    onMeta();
                } else {
                    video.addEventListener('loadedmetadata', onMeta, { once: true });
                }

                // ── Scroll → seek ──────────────────────────────────────────────
                var ticking = false;
                function update() {
                    if (!ready) { return; }
                    var rect       = wrapper.getBoundingClientRect();
                    var scrollTop  = getScrollTop();
                    var wrapperTop = rect.top + scrollTop;
                    var scrollable = wrapper.offsetHeight - window.innerHeight;
                    if (scrollable <= 0) { return; }
                    var scrolled = scrollTop - wrapperTop;
                    if (scrolled < 0) { scrolled = 0; }
                    if (scrolled > scrollable) { scrolled = scrollable; }
                    var target = (scrolled / scrollable) * duration;
                    if (Math.abs(video.currentTime - target) > 0.01) {
                        video.currentTime = target;
                    }
                }
                function onScroll() {
                    if (ticking) { return; }
                    ticking = true;
                    requestAnimationFrame(function () {
                        update();
                        ticking = false;
                    });
                }
                function onResize() {
                    applyWrapperHeight();
                    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
                    onScroll();
                }

                window.addEventListener('scroll', onScroll, { passive: true });
                window.addEventListener('resize', onResize);
                if (typeof ScrollTrigger !== 'undefined') {
                    ScrollTrigger.addEventListener('refresh', onScroll);
                }

                // ── Blob preload (opt-in) ──────────────────────────────────────
                if (preload) {
                    var src = video.currentSrc || video.src;
                    if (src && src.indexOf('blob:') === -1) {
                        fetch(src)
                            .then(function (r) { return r.blob(); })
                            .then(function (blob) {
                                var t = video.currentTime;
                                video.src = URL.createObjectURL(blob);
                                video.load();
                                video.addEventListener('loadedmetadata', function () {
                                    video.currentTime = t || 0.001;
                                    onScroll();
                                }, { once: true });
                            })
                            .catch(function () { /* falha silenciosa */ });
                    }
                }

                // ── iOS unlock: toque destrava o decoder ───────────────────────
                document.documentElement.addEventListener('touchstart', function () {
                    ctrlPlay().then(function () { video.pause(); }).catch(function () {});
                }, { once: true, passive: true });
            }
        });
    }

})();
