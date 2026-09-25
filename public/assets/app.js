(function () {
    'use strict';

    // Tema claro / oscuro
    var toggle = document.getElementById('themeToggle');
    function applyIcon() {
        if (!toggle) return;
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        toggle.innerHTML = dark ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
    }
    applyIcon();
    if (toggle) {
        toggle.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('payroll-theme', next); } catch (e) {}
            applyIcon();
            document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
        });
    }

    // Confirmación en formularios destructivos: <form data-confirm="¿Seguro?">
    document.addEventListener('submit', function (ev) {
        var msg = ev.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            ev.preventDefault();
        }
    });

    // Filas clickeables: <tr data-href="...">
    document.addEventListener('click', function (ev) {
        var row = ev.target.closest('tr[data-href]');
        if (row && !ev.target.closest('a, button, form, input, select')) {
            window.location = row.getAttribute('data-href');
        }
    });

    // Modales de edición: botones con data-fill='{"campo": "valor"}' completan el formulario del modal
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-fill]');
        if (!btn) return;
        var modal = document.querySelector(btn.getAttribute('data-bs-target'));
        if (!modal) return;
        var data = JSON.parse(btn.getAttribute('data-fill'));
        modal.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || el.name === '_csrf') return;
            if (!(el.name in data)) {
                if (el.type === 'checkbox') el.checked = el.defaultChecked; else if (el.type !== 'hidden') el.value = '';
                if (el.type === 'hidden' && el.name === 'id') el.value = '';
                return;
            }
            if (el.type === 'checkbox') el.checked = !!Number(data[el.name]);
            else el.value = data[el.name] === null ? '' : data[el.name];
        });
        var title = modal.querySelector('.modal-title');
        if (title && btn.getAttribute('data-title')) title.textContent = btn.getAttribute('data-title');
    });

    // Formulario de conceptos: mostrar el campo "base" solo para porcentajes
    var mode = document.getElementById('calc_mode');
    var baseGroup = document.getElementById('baseGroup');
    if (mode && baseGroup) {
        var hints = document.querySelectorAll('[data-mode-hint]');
        var sync = function () {
            baseGroup.classList.toggle('d-none', mode.value !== 'porcentaje');
            hints.forEach(function (h) { h.classList.toggle('d-none', h.getAttribute('data-mode-hint') !== mode.value); });
        };
        mode.addEventListener('change', sync);
        sync();
    }

    // Formulario de empleado: filtrar puestos por departamento y mostrar el básico de la categoría
    var dep = document.getElementById('department_id');
    var pos = document.getElementById('position_id');
    if (dep && pos) {
        var filterPositions = function () {
            Array.prototype.forEach.call(pos.options, function (o) {
                if (!o.value) return;
                var d = o.getAttribute('data-department');
                o.hidden = !!dep.value && !!d && d !== dep.value;
            });
            if (pos.selectedOptions[0] && pos.selectedOptions[0].hidden) pos.value = '';
        };
        dep.addEventListener('change', filterPositions);
        filterPositions();
    }
    var cat = document.getElementById('category_id');
    var catHint = document.getElementById('categorySalaryHint');
    if (cat && catHint) {
        var showSalary = function () {
            var o = cat.selectedOptions[0];
            catHint.textContent = o && o.value
                ? 'Básico vigente de la categoría: ' + o.getAttribute('data-salary') + '.'
                : 'Sin categoría: cargá un básico propio.';
        };
        cat.addEventListener('change', showSalary);
        showSalary();
    }
})();
