(function () {
    'use strict';

    function initSignaturePad(form) {
        var canvas = form.querySelector('[data-signature-canvas]');
        var input = form.querySelector('[data-signature-input]');
        var clear = form.querySelector('[data-signature-clear]');
        if (!canvas || !input) {
            return;
        }

        var ctx = canvas.getContext('2d');
        var drawing = false;
        var hasInk = false;

        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111';

        function point(event) {
            var rect = canvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * (canvas.width / rect.width),
                y: (event.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        canvas.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            canvas.setPointerCapture(event.pointerId);
            drawing = true;
            var p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(p.x + 0.1, p.y + 0.1);
            ctx.stroke();
            hasInk = true;
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!drawing) {
                return;
            }
            var p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (name) {
            canvas.addEventListener(name, function () {
                drawing = false;
            });
        });

        if (clear) {
            clear.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasInk = false;
                input.value = '';
            });
        }

        form.addEventListener('submit', function (event) {
            if (!hasInk) {
                event.preventDefault();
                window.alert('Draw a signature before saving.');
                return;
            }
            input.value = canvas.toDataURL('image/png');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-signature-form]').forEach(initSignaturePad);
    });
})();
