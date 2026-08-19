export const Status = {
    Pending: 'pending',
    Uploading: 'uploading',
    Uploaded: 'uploaded',
    Error: 'error',
};

export const extensionOf = (name) => name.split('.').pop().toLowerCase();

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

export const readFileAsBase64 = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
        const result = reader.result;
        // result is a data URL like "data:...;base64,AAAA"; keep only the base64 part.
        resolve(result.slice(result.indexOf(',') + 1));
    };
    reader.onerror = () => reject(reader.error ?? new Error('Failed to read file'));
    reader.readAsDataURL(file);
});

export const metaFor = (file, translations) => {
    if (!file.ok) return translations[file.invalidReason] ?? translations.unsupportedFileType;
    switch (file.status) {
        case Status.Uploading:
            return translations.uploading;
        case Status.Uploaded:
            return translations.uploaded;
        case Status.Error:
            return file.error || translations.uploadFailed;
        default:
            return formatFileSize(file.size);
    }
};
