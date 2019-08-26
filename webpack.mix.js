const mix = require('laravel-mix');

const themePath = 'client';
const cssPath = `${themePath}/src/css/`;
const destPath = `${themePath}/dist/`;

const SRC = {
  css: cssPath + 'main.scss',
};

const DEST = {
  css: destPath,
};

mix.setPublicPath(__dirname);

mix.options({
  processCssUrls: false,
});

mix.sass(SRC.css, DEST.css);
