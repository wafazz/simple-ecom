/* ===========================================================================
   Storefront behaviour — Basic Custom E-Commerce
   ---------------------------------------------------------------------------
   Plain browser JS. No build step, no framework, no CDN (spec §5, §30).

   Everything here is PROGRESSIVE ENHANCEMENT. Each block upgrades markup that
   already works on its own: the variant picker upgrades a <select>, add-to-cart
   upgrades a real <form>, the filters upgrade a form with an Apply button. Turn
   JavaScript off and the shop still sells.

   Nothing here is trusted by the server. Prices and stock shown by the variant
   picker are display values; the cart re-reads both from the database on every
   add, so a tampered figure in this file buys nothing (spec §17).
   =========================================================================== */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrf ? csrf.getAttribute('content') : '';

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* -----------------------------------------------------------------------
       Toasts
       ----------------------------------------------------------------------- */

    function toast(message, opts) {
        opts = opts || {};

        var stack = $('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            // Announced to screen readers without stealing focus.
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }

        var el = document.createElement('div');
        el.className = 'shop-toast' + (opts.bad ? ' shop-toast--bad' : '');

        var icon = document.createElement('i');
        icon.className = 'bi ' + (opts.bad ? 'bi-exclamation-circle' : 'bi-check-circle');
        icon.setAttribute('aria-hidden', 'true');

        var body = document.createElement('div');
        // textContent, never innerHTML: the message may echo a product name.
        body.textContent = message;

        if (opts.link) {
            var a = document.createElement('a');
            a.href = opts.link.href;
            a.textContent = opts.link.text;
            body.appendChild(document.createElement('br'));
            body.appendChild(a);
        }

        el.appendChild(icon);
        el.appendChild(body);
        stack.appendChild(el);

        window.setTimeout(function () {
            el.classList.add('is-leaving');
            window.setTimeout(function () { el.remove(); }, 220);
        }, opts.bad ? 5200 : 3600);
    }

    /* -----------------------------------------------------------------------
       Header: know when the page has scrolled beneath it
       ----------------------------------------------------------------------- */

    var header = $('.shop-header');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-stuck', window.scrollY > 8);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* -----------------------------------------------------------------------
       Cart badge
       ----------------------------------------------------------------------- */

    function setCartCount(count) {
        $$('[data-cart-count]').forEach(function (el) {
            el.textContent = count;
            el.hidden = count < 1;

            // Restart the animation rather than letting a second add be silent.
            el.classList.remove('is-bumped');
            void el.offsetWidth;
            el.classList.add('is-bumped');
        });
    }

    /* -----------------------------------------------------------------------
       Add to cart without a reload

       The form is a real POST to the same route. fetch() only intercepts it;
       if anything goes wrong the handler lets the browser submit normally, so
       the customer is never stranded with a dead button.
       ----------------------------------------------------------------------- */

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('form[data-ajax-cart]')) return;

        e.preventDefault();

        var button = form.querySelector('[type="submit"]');
        var original = button ? button.innerHTML : '';

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding…';
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.ok) {
                    toast(result.data.message || 'That item could not be added.', { bad: true });
                    return;
                }

                setCartCount(result.data.cart_count);
                toast(result.data.message, { link: { href: form.dataset.cartUrl, text: 'View cart →' } });
            })
            .catch(function () {
                // Network failure, or a session that expired and returned HTML.
                // Fall back to the plain submit rather than guessing.
                form.removeAttribute('data-ajax-cart');
                form.submit();
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = original;
                }
            });
    });

    /* -----------------------------------------------------------------------
       Quantity stepper
       ----------------------------------------------------------------------- */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-qty-step]');
        if (!btn) return;

        var wrap = btn.closest('.qty');
        var input = wrap ? $('input', wrap) : null;
        if (!input) return;

        var step = parseInt(btn.dataset.qtyStep, 10);
        var min = parseInt(input.min || '1', 10);
        var max = parseInt(input.max || '99', 10);
        var next = Math.min(max, Math.max(min, (parseInt(input.value, 10) || min) + step));

        input.value = next;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    function syncQtyButtons(wrap) {
        var input = $('input', wrap);
        if (!input) return;

        var value = parseInt(input.value, 10) || 1;
        var minus = $('[data-qty-step="-1"]', wrap);
        var plus = $('[data-qty-step="1"]', wrap);

        if (minus) minus.disabled = value <= parseInt(input.min || '1', 10);
        if (plus) plus.disabled = value >= parseInt(input.max || '99', 10);
    }

    $$('.qty').forEach(function (wrap) {
        syncQtyButtons(wrap);
        wrap.addEventListener('change', function () { syncQtyButtons(wrap); });
    });

    /* -----------------------------------------------------------------------
       Product gallery
       ----------------------------------------------------------------------- */

    var gallery = $('[data-gallery]');
    if (gallery) {
        var main = $('.gallery__main', gallery);
        var mainImg = main ? $('img', main) : null;

        $$('.gallery__thumb', gallery).forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                if (!mainImg) return;

                mainImg.src = thumb.dataset.full;
                $$('.gallery__thumb', gallery).forEach(function (t) {
                    t.classList.toggle('is-active', t === thumb);
                });
            });
        });

        // Zoom follows the pointer: magnifying the centre while the cursor is
        // on a cuff is worse than no zoom at all.
        if (main && mainImg) {
            main.addEventListener('mousemove', function (e) {
                var rect = main.getBoundingClientRect();
                var x = ((e.clientX - rect.left) / rect.width) * 100;
                var y = ((e.clientY - rect.top) / rect.height) * 100;
                mainImg.style.transformOrigin = x + '% ' + y + '%';
            });

            main.addEventListener('mouseenter', function () { main.classList.add('is-zoomed'); });
            main.addEventListener('mouseleave', function () {
                main.classList.remove('is-zoomed');
                mainImg.style.transformOrigin = 'center';
            });
        }
    }

    /* -----------------------------------------------------------------------
       Variant picker

       Upgrades the <select name="variant_id"> that the page renders anyway.
       The select stays in the DOM (hidden) and remains the thing that is
       submitted — so what the customer clicked and what the server receives
       cannot drift apart.
       ----------------------------------------------------------------------- */

    var picker = $('[data-variant-picker]');
    if (picker) {
        var variants = JSON.parse(picker.dataset.variants || '[]');
        var select = $('[data-variant-select]');
        var priceOut = $('[data-variant-price]');
        var stockOut = $('[data-variant-stock]');
        var skuOut = $('[data-variant-sku]');
        var addBtn = $('[data-variant-add]');
        var qtyInput = $('[data-variant-qty]');
        var lowAt = parseInt(picker.dataset.lowStock || '5', 10);

        var chosen = { 1: null, 2: null };

        function matches(v) {
            return (chosen[1] === null || v.option1 === chosen[1])
                && (chosen[2] === null || v.option2 === chosen[2]);
        }

        /* With no swatches on the page the <select> IS the picker, and the
           swatch state would never resolve — leaving Add to cart disabled on a
           product whose only control was working perfectly. */
        var hasSwatches = $$('.swatch', picker).length > 0;

        function current() {
            if (!hasSwatches) {
                var id = select ? parseInt(select.value, 10) : NaN;
                return variants.filter(function (v) { return v.id === id; })[0] || null;
            }

            var found = variants.filter(matches);
            return found.length === 1 ? found[0] : null;
        }

        /* A value is available if SOME in-stock variant carries it alongside
           the other axis's current choice. That is what makes "Blue is sold
           out in L" visible the moment L is picked. */
        function available(axis, value) {
            return variants.some(function (v) {
                if (v['option' + axis] !== value) return false;
                if (v.stock < 1) return false;

                var other = axis === 1 ? 2 : 1;
                return chosen[other] === null || v['option' + other] === chosen[other];
            });
        }

        function paint() {
            $$('.swatch', picker).forEach(function (sw) {
                var axis = parseInt(sw.dataset.axis, 10);
                var value = sw.dataset.value;

                sw.classList.toggle('is-selected', chosen[axis] === value);
                sw.classList.toggle('is-unavailable', !available(axis, value));
                sw.setAttribute('aria-pressed', chosen[axis] === value ? 'true' : 'false');
            });

            $$('[data-chosen-axis]').forEach(function (out) {
                out.textContent = chosen[parseInt(out.dataset.chosenAxis, 10)] || '';
            });

            var v = current();

            if (!v) {
                if (addBtn) {
                    addBtn.disabled = true;
                    addBtn.textContent = 'Select an option';
                }
                if (stockOut) stockOut.textContent = '';
                if (skuOut) skuOut.textContent = '';
                return;
            }

            if (select) select.value = v.id;
            if (priceOut) priceOut.textContent = v.price;
            if (skuOut) skuOut.textContent = v.sku;

            if (qtyInput) {
                qtyInput.max = Math.max(v.stock, 1);
                if (parseInt(qtyInput.value, 10) > v.stock) qtyInput.value = Math.max(v.stock, 1);
                var wrap = qtyInput.closest('.qty');
                if (wrap) syncQtyButtons(wrap);
            }

            if (stockOut) {
                stockOut.className = 'stock-line ' + (
                    v.stock < 1 ? 'stock-line--out' : (v.stock <= lowAt ? 'stock-line--low' : 'stock-line--in')
                );
                stockOut.textContent = v.stock < 1
                    ? 'Out of stock'
                    : (v.stock <= lowAt ? 'Only ' + v.stock + ' left' : 'In stock');
            }

            if (addBtn) {
                addBtn.disabled = v.stock < 1;
                addBtn.textContent = v.stock < 1 ? 'Out of stock' : 'Add to cart';
            }
        }

        picker.addEventListener('click', function (e) {
            var sw = e.target.closest('.swatch');
            if (!sw || sw.classList.contains('is-unavailable')) return;

            var axis = parseInt(sw.dataset.axis, 10);
            // Clicking the chosen value again clears it, which is the only way
            // back to "show me everything on this axis".
            chosen[axis] = chosen[axis] === sw.dataset.value ? null : sw.dataset.value;
            paint();
        });

        if (select) select.addEventListener('change', paint);

        // A single-variant product has nothing to choose — select it outright.
        if (variants.length === 1) {
            chosen[1] = variants[0].option1 || null;
            chosen[2] = variants[0].option2 || null;
        }

        paint();
    }

    /* -----------------------------------------------------------------------
       Admin order list: select-all and the bulk bar

       The checkboxes and both buttons work without any of this — every one is
       a real form control posting to the same route. This only adds the
       select-all, the count, and hiding the bulk bar until something is ticked.
       ----------------------------------------------------------------------- */

    var orderTable = $('[data-order-table]');
    if (orderTable) {
        var selectAll = $('[data-select-all]', orderTable);
        var bar = $('[data-bulk-bar]');
        var countOut = $('[data-bulk-count]');
        var clear = $('[data-bulk-clear]');

        var rows = function () { return $$('[data-row-check]', orderTable); };

        function sync() {
            var boxes = rows();
            var checked = boxes.filter(function (b) { return b.checked; });

            if (countOut) countOut.textContent = checked.length;
            if (bar) bar.hidden = checked.length === 0;

            if (selectAll) {
                selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
                // Partial selection is neither on nor off, and showing it as
                // off invites a second click that clears everything.
                selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rows().forEach(function (b) { b.checked = selectAll.checked; });
                sync();
            });
        }

        orderTable.addEventListener('change', function (e) {
            if (e.target.matches('[data-row-check]')) sync();
        });

        if (clear) {
            clear.addEventListener('click', function () {
                rows().forEach(function (b) { b.checked = false; });
                sync();
            });
        }

        sync();
    }

    /* -----------------------------------------------------------------------
       Filter & sort

       The form works with its Apply button alone. Here it simply submits
       itself when a control changes, so the page behaves the way people
       expect a filter to.
       ----------------------------------------------------------------------- */

    var filters = $('[data-filter-form]');
    if (filters) {
        var apply = $('[data-filter-apply]', filters);
        if (apply) apply.hidden = true;

        var timer = null;
        var submit = function (delay) {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { filters.submit(); }, delay);
        };

        filters.addEventListener('change', function (e) {
            // range inputs fire change on every drag step in some browsers.
            submit(e.target.type === 'range' ? 320 : 0);
        });

        var priceOutput = $('[data-price-output]', filters);
        var priceInput = $('[data-price-input]', filters);
        if (priceOutput && priceInput) {
            priceInput.addEventListener('input', function () {
                priceOutput.textContent = parseFloat(priceInput.value).toFixed(2);
            });
        }
    }
})();
