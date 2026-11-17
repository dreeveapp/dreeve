import {extensionOf, readFileAsBase64, metaFor} from "./upload-utils";

const ImageStatus = {
    New: 'new',
    Unchanged: 'unchanged',
    Removed: 'removed',
};

class ImageDropzoneUpload {
    constructor(root) {
        this.root = root;
        this.dropzone = root.querySelector('[data-dropzone]');
        this.input = root.querySelector('[data-input]');
        this.list = root.querySelector('[data-list]');
        this.listHeader = root.querySelector('[data-list-header]');
        this.count = root.querySelector('[data-count]');
        this.clearBtn = root.querySelector('[data-clear-btn]');
        this.field = root.querySelector('[data-images-field]');
        this.itemTemplate = root.querySelector('[data-item-template]');

        this.files = [];
        this.limitReached = false;
        this.totalExceeded = false;
    }

    init() {
        if (!this.root || !this.dropzone || !this.input || !this.field) return;

        this.supportedFileExtension = JSON.parse(this.root.getAttribute('data-supported-extensions'));
        this.maxImages = parseInt(this.root.getAttribute('data-max-images'), 10) || 1;
        this.maxFileSize = parseInt(this.root.getAttribute('data-max-file-size'), 10) || 0;
        this.maxTotalSize = parseInt(this.root.getAttribute('data-max-total-size'), 10) || 0;
        this.translations = JSON.parse(this.root.getAttribute('data-translations'));

        // These limits are fixed per component, so substitute the MB value once up front.
        const mb = (bytes) => Math.round(bytes / (1024 * 1024));
        if (this.translations.fileTooLarge) {
            this.translations.fileTooLarge = this.translations.fileTooLarge.replace('{max}', mb(this.maxFileSize));
        }
        if (this.translations.totalSizeExceeded) {
            this.translations.totalSizeExceeded = this.translations.totalSizeExceeded.replace('{max}', mb(this.maxTotalSize));
        }

        const existing = JSON.parse(this.root.getAttribute('data-existing-images') || '[]');
        this.files = existing.map((image) => ({
            status: ImageStatus.Unchanged,
            id: image.id,
            name: image.name,
            ok: true,
            preview: image.url,
            content: null,
        }));

        this.list.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-remove-file]');
            if (!btn) return;
            this.removeAt(Number(btn.dataset.index));
        });

        this.input.addEventListener('change', () => {
            this.addFiles(this.input.files);
            this.input.value = '';
        });

        ['dragenter', 'dragover'].forEach((evt) =>
            this.dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                this.dropzone.classList.add('is-dragging');
            }));
        ['dragleave', 'dragend', 'drop'].forEach((evt) =>
            this.dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                this.dropzone.classList.remove('is-dragging');
            }));
        this.dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer?.files) this.addFiles(e.dataTransfer.files);
        });

        this.clearBtn.addEventListener('click', () => this.clear());

        this.render();
        this.syncField();
    }

    // Images that occupy a slot (everything except those flagged for removal).
    visibleFiles() {
        return this.files.filter((f) => f.status !== ImageStatus.Removed);
    }

    async addFiles(fileList) {
        this.limitReached = false;
        this.totalExceeded = false;
        let total = this.files
            .filter((f) => f.ok && f.status === ImageStatus.New && f.size)
            .reduce((sum, f) => sum + f.size, 0);
        for (const file of Array.from(fileList)) {
            if (this.visibleFiles().length >= this.maxImages) {
                this.limitReached = true;
                break;
            }
            const supported = this.supportedFileExtension.includes(extensionOf(file.name));
            const tooLarge = this.maxFileSize > 0 && file.size > this.maxFileSize;
            if (supported && !tooLarge && this.maxTotalSize > 0 && total + file.size > this.maxTotalSize) {
                this.totalExceeded = true;
                break;
            }
            const ok = supported && !tooLarge;
            if (ok) total += file.size;
            this.files.push({
                status: ImageStatus.New,
                name: file.name,
                size: file.size,
                ok,
                invalidReason: !supported ? 'unsupportedFileType' : (tooLarge ? 'fileTooLarge' : null),
                preview: URL.createObjectURL(file),
                content: ok ? await readFileAsBase64(file) : null,
            });
        }
        this.render();
        this.syncField();
    }

    removeAt(index) {
        const item = this.files[index];
        if (!item) return;
        if (item.status === ImageStatus.New) {
            // Never persisted; drop it entirely.
            if (item.preview) URL.revokeObjectURL(item.preview);
            this.files.splice(index, 1);
        } else {
            item.status = ImageStatus.Removed;
        }
        this.limitReached = false;
        this.totalExceeded = false;
        this.render();
        this.syncField();
    }

    clear() {
        this.files = this.files.filter((f) => {
            if (f.status === ImageStatus.New) {
                if (f.preview) URL.revokeObjectURL(f.preview);
                return false;
            }
            return true;
        });
        this.limitReached = false;
        this.totalExceeded = false;
        this.render();
        this.syncField();
    }

    syncField() {
        const images = this.files
            .filter((f) => f.ok)
            .map((f) => f.status === ImageStatus.New
                ? {status: f.status, filename: f.name, content: f.content}
                : {status: f.status, id: f.id});
        this.field.value = JSON.stringify(images);
    }

    render() {
        this.list.innerHTML = '';
        const visible = this.visibleFiles();
        const hasFiles = visible.length > 0;
        const validCount = visible.filter((f) => f.ok).length;
        const atLimit = visible.length >= this.maxImages;

        this.list.hidden = !hasFiles;
        this.listHeader.hidden = !hasFiles;
        this.clearBtn.classList.toggle('hidden', !visible.some((f) => f.status === ImageStatus.New));
        this.dropzone.classList.toggle('is-disabled', atLimit);
        this.input.disabled = atLimit;

        this.count.textContent = this.countText(validCount);

        const entries = this.files
            .map((file, index) => ({file, index}))
            .filter(({file}) => file.status !== ImageStatus.Removed);
        const newest = entries.filter(({file}) => file.status === ImageStatus.New).reverse();
        const rest = entries.filter(({file}) => file.status !== ImageStatus.New);

        [...newest, ...rest].forEach(({file, index}) => {
            const li = this.itemTemplate.content.firstElementChild.cloneNode(true);
            if (!file.ok) li.classList.add('is-invalid');
            li.querySelector('[data-preview]').src = file.preview;
            li.querySelector('.name').textContent = file.name;
            li.querySelector('.meta').textContent = file.status === ImageStatus.New
                ? metaFor(file, this.translations)
                : this.translations.existingImage;
            li.querySelector('[data-remove-file]').dataset.index = index;
            this.list.appendChild(li);
        });
    }

    countText(validCount) {
        if (this.limitReached) {
            return this.translations.limitReached.replace('{max}', this.maxImages);
        }
        if (this.totalExceeded) {
            return this.translations.totalSizeExceeded;
        }
        return this.translations.imagesSelected.replace('{count}', validCount);
    }
}

export const initImageDropZones = (root = document) => {
    root.querySelectorAll('[data-image-dropzone]').forEach((el) => new ImageDropzoneUpload(el).init());
};
