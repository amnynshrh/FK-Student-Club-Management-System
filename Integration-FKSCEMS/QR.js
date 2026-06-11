(function () {
    function findQrImage(container) {
        return container.querySelector('img') || container.querySelector('canvas');
    }

    function canvasToPngDataUrl(canvas) {
        return canvas.toDataURL('image/png');
    }

    function imageToPngDataUrl(image, size) {
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        var context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);
        context.drawImage(image, 0, 0, size, size);
        return canvas.toDataURL('image/png');
    }

    window.EventQrGenerator = {
        createPng: function (options) {
            return new Promise(function (resolve, reject) {
                if (typeof QRCode === 'undefined') {
                    reject(new Error('QRCode.js is not loaded.'));
                    return;
                }

                var container = document.getElementById(options.containerId);
                if (!container) {
                    reject(new Error('QR container was not found.'));
                    return;
                }

                var size = options.size || 512;
                container.innerHTML = '';

                new QRCode(container, {
                    text: options.text,
                    width: size,
                    height: size,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });

                window.setTimeout(function () {
                    var qrElement = findQrImage(container);
                    if (!qrElement) {
                        reject(new Error('QR image was not generated.'));
                        return;
                    }

                    if (qrElement.tagName.toLowerCase() === 'canvas') {
                        resolve(canvasToPngDataUrl(qrElement));
                        return;
                    }

                    if (qrElement.complete) {
                        resolve(imageToPngDataUrl(qrElement, size));
                        return;
                    }

                    qrElement.onload = function () {
                        resolve(imageToPngDataUrl(qrElement, size));
                    };
                    qrElement.onerror = function () {
                        reject(new Error('Generated QR image could not be read.'));
                    };
                }, 150);
            });
        }
    };
})();
