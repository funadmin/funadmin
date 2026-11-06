export default {
    states: { uploading: 'Uploading', ready: 'Ready', failed: 'Upload failed' }, sendHint: 'Enter to send · Shift + Enter for newline',
    approval: 'Approval mode', attach: 'Attach files', attachment: 'Private attachment', retry: 'Retry', remove: 'Remove', download: 'Download safely',
    privacy: 'Sensitive material in messages and attachments will be sent to the profile Provider. PNG/JPEG/WebP: 5MiB each; UTF-8 code/text: 128KiB each. Up to 4 files and 12MiB total.',
    invalid: 'File type, name, size or count exceeds the supported limits. The file was rejected.', uploadFailed: 'Upload failed or the file is not valid UTF-8 text. Retry or remove it; failed attachments are never silently skipped.',
    removeFailed: 'Could not remove attachment. Please retry.', sendFailed: 'Sending failed. Retry keeps the created message and task idempotency key to avoid duplicates.',
    incompatible: 'The current or fallback model has not declared support for these images. Update capabilities or remove the images before sending.', downloadFailed: 'Could not read private attachment. Check access and retry.',
    imageInput: 'Image input (administrator declaration)', maxImages: 'Maximum images', imageTokens: 'Fixed tokens per image'
  };
