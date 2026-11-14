/* AgriSys — small UI behaviours (no framework required) */
(function () {
    'use strict';

    /* Sidebar drawer on small screens */
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.querySelector('.sidebar-backdrop');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (backdrop) backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (backdrop) backdrop.hidden = true;
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', openSidebar);
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (btn) {
        btn.addEventListener('click', closeSidebar);
    });
    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });

    /* Bootstrap toasts */
    document.querySelectorAll('.toast').forEach(function (el) {
        new bootstrap.Toast(el).show();
    });

    /* Auto-submit filter forms when a select changes */
    document.querySelectorAll('form[data-auto-submit] select').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    /* Live "estimated value" on the harvest form */
    var qty = document.getElementById('quantity_kg');
    var price = document.getElementById('selling_price_per_kg');
    var preview = document.getElementById('value-preview');
    if (qty && price && preview) {
        var update = function () {
            var q = parseFloat(qty.value);
            var p = parseFloat(price.value);
            if (isNaN(q) || isNaN(p) || q <= 0 || p < 0) {
                preview.textContent = '₱0.00';
                return;
            }
            preview.textContent = '₱' + (q * p).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        qty.addEventListener('input', update);
        price.addEventListener('input', update);
        update();
    }

    /* Confirm before submitting a delete form */
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    /* Fill the shared delete-confirmation dialog from the clicked button */
    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;
            var form = deleteModal.querySelector('#deleteForm');
            var name = deleteModal.querySelector('#deleteTargetName');
            if (form) form.setAttribute('action', trigger.getAttribute('data-delete-url') || '');
            if (name) name.textContent = trigger.getAttribute('data-delete-name') || 'this record';
        });
    }

    /* Print buttons */
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });
})();
