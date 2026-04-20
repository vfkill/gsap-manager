/* GSAP Manager — Admin JS */
jQuery(function ($) {

    // ── Show/hide campo de IDs selecionados ─────────────────────────────────
    $('input[name="gsap_manager_settings[load_on]"]').on('change', function () {
        if ($(this).val() === 'selected') {
            $('#gsap-selected-ids').removeClass('gsap-field--hidden');
        } else {
            $('#gsap-selected-ids').addClass('gsap-field--hidden');
        }
    });

    // ── Toggle visual dos plugin cards ──────────────────────────────────────
    $('.gsap-plugin-card input[type="checkbox"]').on('change', function () {
        $(this).closest('.gsap-plugin-card').toggleClass('is-active', this.checked);
    });

    // ── Mostrar/ocultar configurações do ScrollSmoother ─────────────────────
    $('input[name="gsap_manager_settings[plugins][ScrollSmoother]"]').on('change', function () {
        if (this.checked) {
            $('#gsap-smoother-settings').removeClass('gsap-field--hidden');
        } else {
            $('#gsap-smoother-settings').addClass('gsap-field--hidden');
        }
    });

    // ── Sincronizar color picker ↔ campo de texto ───────────────────────────
    function syncColorPair(pickerId, textId) {
        var $pick = $('#' + pickerId);
        var $text = $('#' + textId);
        if (!$pick.length || !$text.length) return;

        $pick.on('input change', function () {
            $text.val(this.value);
        });
        $text.on('input', function () {
            var val = this.value.trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                $pick.val(val);
            }
        });
    }
    syncColorPair('highlight_color', 'highlight_color_text');
    syncColorPair('progress_color', 'progress_color_text');

    // ── Botão copiar classe da aba de animações ─────────────────────────────
    $(document).on('click', '.gsap-copy-btn', function () {
        const btn  = $(this);
        const text = btn.data('copy');
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(text).then(function () {
            btn.addClass('copied');

            // Tooltip "Copiado!" acima do botão
            if (btn.find('.gsap-copied-tip').length) return;
            const tip = $('<span class="gsap-copied-tip">Copiado!</span>');
            btn.append(tip);
            setTimeout(function () {
                tip.addClass('gsap-copied-tip--out');
                setTimeout(function () {
                    tip.remove();
                    btn.removeClass('copied');
                }, 300);
            }, 1200);
        });
    });

    // ── Aba Animações por Classe: busca + chips de atalho ───────────────────
    (function initRefNav() {
        const $nav    = $('#gsap-ref-nav');
        if (!$nav.length) return;

        const $input  = $('#gsap-ref-search-input');
        const $clear  = $('#gsap-ref-search-clear');
        const $search = $nav.find('.gsap-ref-search');
        const $chips  = $nav.find('.gsap-ref-chip');
        const $noRes  = $('#gsap-ref-no-results');
        const $reset  = $('#gsap-ref-reset');
        const $groups = $('.gsap-ref-accordion[data-group]');

        // Salva o estado original (quais grupos abriam por padrão) pra restaurar ao limpar busca.
        const originalOpen = new Map();
        $groups.each(function () {
            originalOpen.set(this.id, this.open);
        });

        function applyFilter(term) {
            const q = term.trim().toLowerCase();
            const isFiltering = q.length > 0;
            let totalVisible = 0;

            $search.toggleClass('is-filled', isFiltering);

            $groups.each(function () {
                const $group = $(this);
                const $cards = $group.find('.gsap-ref-card');
                const $empty = $group.find('.gsap-ref-empty');
                let groupVisible = 0;

                $cards.each(function () {
                    const hay = (this.dataset.search || '').toLowerCase();
                    const match = !isFiltering || hay.indexOf(q) !== -1;
                    this.hidden = !match;
                    if (match) groupVisible++;
                });

                totalVisible += groupVisible;

                if (isFiltering) {
                    // Durante busca: abre grupos com resultado, fecha vazios, mostra msg quando vazio.
                    this.open = groupVisible > 0;
                    if ($empty.length) $empty.prop('hidden', groupVisible > 0);
                } else {
                    // Sem busca: restaura estado original.
                    this.open = originalOpen.get(this.id) || false;
                    if ($empty.length) $empty.prop('hidden', true);
                }
            });

            // Esconde meta-accordions (gatilhos/globais/performance) quando há busca ativa
            // — eles não entram no filtro pois não são cards de classe.
            $('.gsap-ref-accordion:not([data-group])').each(function () {
                this.hidden = isFiltering;
            });

            $noRes.prop('hidden', !isFiltering || totalVisible > 0);
        }

        $input.on('input', function () {
            applyFilter(this.value);
        });

        $clear.on('click', function () {
            $input.val('').trigger('input').focus();
        });

        $reset.on('click', function (e) {
            e.preventDefault();
            $input.val('').trigger('input').focus();
        });

        // Chips → abre accordion e rola até ele.
        $chips.on('click', function () {
            const slug = this.dataset.target;
            if (!slug) return;
            const $target = $('#gsap-ref-group-' + slug);
            if (!$target.length) return;

            // Limpa busca pra não mascarar cards do grupo alvo.
            if ($input.val()) {
                $input.val('').trigger('input');
            }

            $target[0].open = true;

            // Scroll suave com offset pra barra sticky + wp admin bar.
            const offset = 90;
            const top    = $target.offset().top - offset;
            window.scrollTo({ top, behavior: 'smooth' });

            // Flash visual pra localizar onde caiu.
            $target.addClass('is-target');
            setTimeout(function () { $target.removeClass('is-target'); }, 1200);
        });
    })();

});
