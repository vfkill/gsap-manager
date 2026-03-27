/**
 * GSAP Manager — Animações por Classe  v1.7.0
 *
 * Atributos de controle (opcionais em qualquer elemento):
 *   data-gsap-duration  — duração em segundos    (ex: 1.2)
 *   data-gsap-delay     — atraso em segundos      (ex: 0.3)
 *   data-gsap-ease      — easing do GSAP          (ex: "elastic.out(1,0.5)")
 *   data-gsap-distance  — distância em px         (ex: 80)
 *   data-gsap-stagger   — stagger em segundos     (ex: 0.15)
 *   data-gsap-start     — ScrollTrigger start     (ex: "top 70%")
 *   data-gsap-scrub     — scrub no parallax       (ex: 1)
 *   data-gsap-dir       — direção (img-reveal)    (ex: "right")
 *   data-gsap-strength  — força do efeito         (ex: 0.5)
 *   data-gsap-from      — valor inicial (counter) (ex: 0)
 *   data-gsap-prefix    — prefixo (counter)       (ex: "R$")
 *   data-gsap-suffix    — sufixo (counter)        (ex: "%")
 *   data-gsap-speed     — velocidade (marquee)    (ex: 60)
 *   data-gsap-axis      — eixo (reveal-line)      (ex: "height")
 *
 * Classes de gatilho:
 *   (nenhuma)        → aguarda o elemento entrar na viewport (padrão)
 *   gsap-on-scroll   → igual ao padrão (mantido por compatibilidade)
 *   gsap-on-load     → anima imediatamente quando a página carrega
 */

(function () {
    'use strict';

    // ─── Bootstrap ──────────────────────────────────────────────────────────
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
        if (typeof ScrollTrigger !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger);
        }
        initTextAnimations();
        initImageAnimations();
        initElementAnimations();
        initStaggerAnimations();
        initSpecialAnimations();
        initHoverAnimations();
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
     * gsap-on-load  → força execução imediata (ignora scroll)
     * gsap-on-scroll → comportamento idêntico ao padrão (mantido por compatibilidade)
     *
     * Prioridade de motor:
     *   1. ScrollTrigger (GSAP)   — se estiver carregado
     *   2. IntersectionObserver   — fallback nativo
     *   3. Execução imediata      — último recurso
     */
    function playOnScroll(el, fn) {
        // Força execução imediata
        if (el.classList.contains('gsap-on-load')) {
            fn();
            return;
        }

        // Motor 1: ScrollTrigger
        if (typeof ScrollTrigger !== 'undefined') {
            ScrollTrigger.create({
                trigger: el,
                start:   str(el, 'start', 'top 88%'),
                once:    true,
                onEnter: fn,
            });
            return;
        }

        // Motor 2: IntersectionObserver (funciona mesmo sem ScrollTrigger)
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        obs.unobserve(entry.target);
                        fn();
                    }
                });
            }, { rootMargin: '0px 0px -12% 0px', threshold: 0 });
            io.observe(el);
            return;
        }

        // Motor 3: fallback — anima imediatamente
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
        var savedHTML = el.innerHTML;           // salva o container inteiro
        var target    = findTextTarget(el);     // encontra onde o texto e estilos realmente estão
        var styles    = captureStyles(target);  // captura estilos do elemento correto
        var text      = target.textContent;

        target.innerHTML = '';                  // limpa apenas o elemento de texto
        target.setAttribute('aria-label', text);

        var spans = Array.from(text).map(function (ch) {
            var span = document.createElement('span');
            span.style.display    = 'inline-block';
            span.style.willChange = 'clip-path, transform, opacity';
            applyStyles(span, styles);
            span.textContent = ch === ' ' ? '\u00A0' : ch;
            target.appendChild(span);
            return span;
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

        document.querySelectorAll('.gsap-char-reveal').forEach(function (el) {
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
        });

        document.querySelectorAll('.gsap-word-reveal').forEach(function (el) {
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
        });

        document.querySelectorAll('.gsap-text-fade').forEach(function (el) {
            playOnScroll(el, function () {
                gsap.from(el, {
                    y:        num(el, 'distance', 24),
                    opacity:  0,
                    duration: resolveDuration(el, 0.9),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power3.out'),
                });
            });
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
        });

        document.querySelectorAll('.gsap-text-highlight').forEach(function (el) {
            gsap.set(el, { backgroundSize: '0% 40%' });
            playOnScroll(el, function () {
                gsap.to(el, {
                    backgroundSize: '100% 40%',
                    duration: resolveDuration(el, 0.75),
                    delay:    resolveDelay(el),
                    ease:     str(el, 'ease', 'power2.inOut'),
                });
            });
        });

        document.querySelectorAll('.gsap-scramble').forEach(function (el) {
            var final    = el.textContent;
            var chars    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var duration = resolveDuration(el, 1.2);
            var delay    = resolveDelay(el);
            function play() {
                var start = null;
                function step(ts) {
                    if (!start) start = ts;
                    var prog  = Math.min((ts - start) / (duration * 1000), 1);
                    var fixed = Math.floor(prog * final.length);
                    el.textContent = final.split('').map(function (ch, i) {
                        if (ch === ' ') return ' ';
                        if (i < fixed) return ch;
                        return chars[Math.floor(Math.random() * chars.length)];
                    }).join('');
                    if (prog < 1) requestAnimationFrame(step);
                    else el.textContent = final;
                }
                setTimeout(function () { requestAnimationFrame(step); }, delay * 1000);
            }
            playOnScroll(el, play);
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
        ];

        map.forEach(function (item) {
            document.querySelectorAll('.' + item.cls).forEach(function (el) {
                var children = Array.from(el.children);
                if (!children.length) return;
                playOnScroll(el, function () {
                    gsap.from(children, Object.assign({}, item.from(), {
                        duration: resolveDuration(el, 0.7),
                        delay:    resolveDelay(el),
                        stagger:  num(el, 'stagger', item.stagger),
                        ease:     str(el, 'ease', 'power3.out'),
                    }));
                });
            });
        });
    }

    // ─── Animações Especiais ────────────────────────────────────────────────

    function initSpecialAnimations() {

        document.querySelectorAll('.gsap-counter').forEach(function (el) {
            var raw      = el.textContent.trim();
            var endVal   = parseFloat(raw.replace(/[^0-9.-]/g, '')) || num(el, 'to', 100);
            var startVal = num(el, 'from', 0);
            var decimals = raw.includes('.') ? (raw.split('.')[1] || '').length : 0;
            var prefix   = el.getAttribute('data-gsap-prefix') || '';
            var suffix   = el.getAttribute('data-gsap-suffix') || '';
            var obj      = { val: startVal };
            el.textContent = prefix + startVal.toFixed(decimals) + suffix;
            playOnScroll(el, function () {
                gsap.to(obj, {
                    val: endVal, duration: resolveDuration(el, 2), delay: resolveDelay(el),
                    ease: str(el, 'ease', 'power2.out'),
                    onUpdate: function () { el.textContent = prefix + obj.val.toFixed(decimals) + suffix; }
                });
            });
        });

        document.querySelectorAll('.gsap-marquee').forEach(function (el) {
            var speed = num(el, 'speed', 45);
            var dir   = str(el, 'dir', 'left') === 'right' ? 1 : -1;
            el.style.overflow = 'hidden';
            el.style.display  = 'flex';
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
            gsap.set([inner, clone], { x: dir < 0 ? 0 : -w });
            gsap.to([inner, clone], {
                x: dir < 0 ? -w : 0, duration: w / speed, ease: 'none', repeat: -1,
                modifiers: {
                    x: gsap.utils.unitize(function (x) {
                        return ((parseFloat(x) % w) + w) % w * dir + (dir > 0 ? -w : 0);
                    })
                }
            });
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

    // ─── Animações de Hover ──────────────────────────────────────────────────

    function initHoverAnimations() {

        document.querySelectorAll('.gsap-magnetic').forEach(function (el) {
            var strength = num(el, 'strength', 0.4);
            el.addEventListener('mousemove', function (e) {
                var r = el.getBoundingClientRect();
                gsap.to(el, {
                    x: (e.clientX - r.left - r.width / 2)  * strength,
                    y: (e.clientY - r.top  - r.height / 2) * strength,
                    duration: 0.4, ease: 'power3.out',
                });
            });
            el.addEventListener('mouseleave', function () {
                gsap.to(el, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1,0.4)' });
            });
        });

        document.querySelectorAll('.gsap-tilt').forEach(function (el) {
            var strength = num(el, 'strength', 14);
            el.style.cssText += ';transform-style:preserve-3d;';
            gsap.set(el, { transformPerspective: 900 });
            el.addEventListener('mousemove', function (e) {
                var r  = el.getBoundingClientRect();
                var rx = ((e.clientY - r.top)  / r.height - 0.5) * -strength;
                var ry = ((e.clientX - r.left) / r.width  - 0.5) *  strength;
                gsap.to(el, { rotationX: rx, rotationY: ry, duration: 0.3, ease: 'power2.out' });
            });
            el.addEventListener('mouseleave', function () {
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
    }

})();
