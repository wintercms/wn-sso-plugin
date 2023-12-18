// Extend the base tailwind config to avoid conflicts
const config = require('../../winter/tailwindui/tailwind.config.js');

config.content = [
    './views/**/*.{php,htm}',
    './assets/src/js/**/*.{js,vue}',
];

config.theme.screens = {
    ...config?.theme?.screens ?? {},
};

config.corePlugins = {
    preflight: false,
};

module.exports = config;
