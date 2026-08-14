// Override webpack's compile-time publicPath so dynamic imports resolve under subpath deployments.
const $jsDistUrl = document.querySelector('meta[name="js-dist-url"]');

__webpack_public_path__ = $jsDistUrl?.getAttribute('content') || '/js/dist/';

const $jsDistVersion = document.querySelector('meta[name="js-dist-version"]');
const assetVersion = $jsDistVersion?.getAttribute('content');

if (assetVersion) {
    const getChunkFilename = __webpack_get_script_filename__;
    __webpack_get_script_filename__ = (chunkId) => `${getChunkFilename(chunkId)}?${assetVersion}`;
}
