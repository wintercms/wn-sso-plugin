let mix = require('laravel-mix');
mix.setPublicPath(__dirname);

mix.postCss('assets/src/css/sso.css', 'assets/dist/css/sso.css', [
    require('postcss-import'),
    require('tailwindcss/nesting'),
    require('tailwindcss'),
    require('autoprefixer'),
    require('postcss-prefixwrap')('.winter-sso')
]);
