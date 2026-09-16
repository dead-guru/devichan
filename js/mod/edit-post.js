/* Attachment changes stay in the form until the post is saved. */
$(function() {
    async function isEditable(file) {
        const buf = await file.arrayBuffer();
        const bytes = new Uint8Array(buf);
        const view = new DataView(buf);
        const tag = (offset, length) => String.fromCharCode(...bytes.subarray(offset, offset + length));
        if (bytes[0] === 0xff && bytes[1] === 0xd8 || tag(0, 2) === 'BM') return true;
        if (tag(0, 4) === 'RIFF' && tag(8, 4) === 'WEBP')
            return tag(12, 4) !== 'VP8X' || !(bytes[20] & 2);
        if (tag(1, 3) === 'PNG') {
            for (let pos = 8; pos + 8 <= bytes.length; pos += view.getUint32(pos) + 12) {
                if (tag(pos + 4, 4) === 'acTL') return false;
                if (tag(pos + 4, 4) === 'IDAT') return true;
            }
        }
        if (tag(0, 3) === 'GIF') {
            let pos = 13 + (bytes[10] & 128 ? 3 * (2 << (bytes[10] & 7)) : 0);
            let frames = 0;
            while (pos < bytes.length) {
                const block = bytes[pos++];
                if (block === 0x3b) return frames === 1;
                if (block === 0x2c) {
                    if (++frames > 1) return false;
                    const packed = bytes[pos + 8];
                    pos += 9 + (packed & 128 ? 3 * (2 << (packed & 7)) : 0) + 1;
                } else if (block === 0x21) pos++;
                else return false;
                while (pos < bytes.length && bytes[pos]) pos += bytes[pos] + 1;
                pos++;
            }
        }
        return false;
    }

    $('.edit-attachment').each(function() {
        const row = this;
        const input = row.querySelector('input[type="file"]');
        const preview = row.querySelector('.attachment-preview');
        const edit = row.querySelector('.edit-attachment-image');
        const reset = row.querySelector('.reset-attachment');
        const replace = row.querySelector('.replace-attachment');
        const link = row.querySelector('.attachment-link');
        const previewLink = row.querySelector('.attachment-preview-link');
        input.hidden = true;
        replace.hidden = false;
        replace.addEventListener('click', () => input.click());
        let url = null;

        function showFile(file, drawn) {
            if (url) URL.revokeObjectURL(url);
            url = URL.createObjectURL(file);
            preview.src = file.type.startsWith('image/') ? url : row.closest('form').dataset.fileIcon;
            previewLink.href = url;
            previewLink.setAttribute('aria-label', file.name);
            if (link) {
                link.textContent = file.name;
                link.href = url;
            }
            edit.hidden = true;
            if (drawn) edit.hidden = false;
            else isEditable(file).then(editable => {
                if (input.files[0] === file) edit.hidden = !editable;
            });
            reset.hidden = false;
        }

        input.addEventListener('change', function() {
            if (input.files[0]) showFile(input.files[0], false);
            else reset.click();
        });
        reset.addEventListener('click', function() {
            input.value = '';
            if (url) URL.revokeObjectURL(url);
            url = null;
            preview.src = row.dataset.preview;
            previewLink.href = row.dataset.url;
            previewLink.setAttribute('aria-label', row.dataset.filename);
            if (link) {
                link.textContent = row.dataset.filename;
                link.href = row.dataset.url;
            }
            edit.hidden = row.dataset.editable !== '1';
            reset.hidden = true;
        });
        if (window.paintTool) {
            edit.hidden = row.dataset.editable !== '1';
            edit.addEventListener('click', function() {
                window.paintTool.open(url || row.dataset.url, {
                    onExport(blob) {
                        const name = (input.files[0] ? input.files[0].name : row.dataset.filename).replace(/\.[^.]+$/, '') + '.png';
                        const file = new File([blob], name, { type: 'image/png' });
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        input.files = transfer.files;
                        showFile(file, true);
                    }
                });
            });
        }
    });
});
