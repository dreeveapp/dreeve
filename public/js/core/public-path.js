// Override webpack's compile-time publicPath so dynamic imports resolve under subpath deployments.
const $jsDistUrl = document.querySelector('meta[name="js-dist-url"]');

__webpack_public_path__ = $jsDistUrl?.getAttribute('content') || '/js/dist/';
