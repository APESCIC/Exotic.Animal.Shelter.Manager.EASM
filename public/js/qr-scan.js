(function () {
    'use strict';

    function initScanner(root) {
        var video = root.querySelector('[data-qr-video]');
        var start = root.querySelector('[data-qr-start]');
        var status = root.querySelector('[data-qr-status]');
        var allowed = root.getAttribute('data-allowed-prefix');
        var supported = 'BarcodeDetector' in window && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;

        if (!supported) {
            start.hidden = true;
            status.textContent = 'This browser cannot scan QR codes here. Open the phone\'s own camera app and point it at the label, or choose the enclosure below.';
            return;
        }

        start.addEventListener('click', function () {
            var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            start.disabled = true;
            status.textContent = 'Starting the camera…';

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (stream) {
                video.srcObject = stream;
                video.hidden = false;
                return video.play().then(function () {
                    status.textContent = 'Point the camera at the QR label.';
                    var timer = setInterval(function () {
                        detector.detect(video).then(function (codes) {
                            var match = codes.map(function (code) {
                                return code.rawValue;
                            }).filter(function (value) {
                                return value.indexOf(allowed) === 0;
                            })[0];

                            if (match) {
                                clearInterval(timer);
                                stream.getTracks().forEach(function (track) {
                                    track.stop();
                                });
                                status.textContent = 'Found it. Opening…';
                                window.location.assign(match);
                            } else if (codes.length) {
                                status.textContent = 'That QR code is not an EASM enclosure label.';
                            }
                        }).catch(function () {});
                    }, 300);
                });
            }).catch(function () {
                start.disabled = false;
                status.textContent = 'The camera could not start. Allow camera access, or choose the enclosure below.';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-qr-scan]').forEach(initScanner);
    });
})();
