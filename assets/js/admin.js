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

    // ── Botão copiar classe da aba de animações ─────────────────────────────
    $(document).on('click', '.gsap-copy-btn', function () {
        const btn  = $(this);
        const text = btn.data('copy');
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(text).then(function () {
            btn.addClass('copied');
            setTimeout(function () { btn.removeClass('copied'); }, 1800);
        });
    });

});
