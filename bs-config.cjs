// Browsersync settings for npm run dev. The proxy, port and watched files are flags in package.json;
// this adds Herd's certificate for easm.test, so live reload at https://easm.test:5176 is trusted.
const path = require('path');

const certificates = path.join(process.env.USERPROFILE || process.env.HOME || '', '.config', 'herd', 'config', 'valet', 'Certificates');

module.exports = {
    https: {
        key: path.join(certificates, 'easm.test.key'),
        cert: path.join(certificates, 'easm.test.crt'),
    },
};
