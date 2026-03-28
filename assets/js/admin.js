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

});
