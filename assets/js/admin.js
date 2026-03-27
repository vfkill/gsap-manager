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

    // ── Botão copiar código da aba de animações ─────────────────────────────
    $(document).on('click', '.gsap-copy-btn', function () {
        const btn  = $(this);
        const text = btn.data('copy');
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(text).then(function () {
            btn.addClass('copied').text('Copiado!');
            setTimeout(function () {
                btn.removeClass('copied').html(
                    '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copiar'
                );
            }, 1800);
        });
    });

});
