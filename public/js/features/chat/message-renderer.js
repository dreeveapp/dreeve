export default class ChatMessageRenderer {
    constructor(messageEl) {
        this.el = messageEl;
        this.buffer = '';
    }

    append(chunk) {
        this.buffer += chunk;
        this.renderStreaming();
    }

    renderStreaming() {
        const openFences = (this.buffer.match(/```/g) || []).length % 2 === 1;

        let text = this.buffer
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        if (!openFences) {
            text = text
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        }

        this.el.innerHTML = text.replace(/\n/g, '<br>');
    }

    renderFinal(sanitizedHtml) {
        this.el.dataset.markdownParsed = 'true';
        this.el.innerHTML = sanitizedHtml;
    }
}
