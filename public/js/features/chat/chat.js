import autoComplete from "../../../libraries/autocomplete";
import ChatMessageRenderer from "./message-renderer";
import {eventBus, Events} from "../../core/event-bus";
import {basePath} from "../../utils";

let activeSource = null;

eventBus.on(Events.PAGE_LOADED, () => {
    activeSource?.close();
    activeSource = null;
});

export default class Chat {
    constructor(rootNode) {
        this.chatWrapper = rootNode.querySelector('.chat--wrapper');
        this.form = rootNode.querySelector('form');
        this.button = this.form.querySelector('button.send-message');
        this.textInput = this.form.querySelector('input.message');
        this.spinner = this.form.querySelector('div.spinner');
        this.clearButton = rootNode.querySelector('button.clear-chat');

        this.placeholderIdle = this.textInput.getAttribute('data-placeholder-idle');
        this.placeholderProcessing = this.textInput.getAttribute('data-placeholder-processing');

        this.commands = JSON.parse(this.chatWrapper.getAttribute('data-chat-commands') || '{}');
        this.autoCompleteJS = null;

        this.basePath = basePath();
    }

    toggleElements(disabled) {
        this.textInput.disabled = disabled;
        this.button.disabled = disabled;

        this.button.classList.toggle('inline-flex', !disabled);
        this.button.classList.toggle('hidden', disabled);

        this.form.classList.toggle('disabled', disabled);
        this.spinner.classList.toggle('hidden', !disabled);

        this.textInput.value = '';
        this.textInput.placeholder = disabled ? this.placeholderProcessing : this.placeholderIdle;
    }

    initAutoComplete() {
        if (!this.commands || Object.keys(this.commands).length === 0) return;

        this.autoCompleteJS = new autoComplete({
            selector: () => this.textInput,
            data: { src: Object.keys(this.commands) },
            threshold: 1,
            trigger: query => query.startsWith('/'),
            resultsList: { tabSelect: true, position: 'beforebegin' },
            resultItem: {
                highlight: true,
                element: (item, data) => {
                    item.innerHTML = `
                        <div>${data.match}</div>
                        <div class="text-xs text-gray-500">${this.commands[data.value]}</div>
                    `;
                }
            },
            events: {
                input: {
                    focus: () => {
                        if (this.autoCompleteJS.input.value.length) this.autoCompleteJS.start();
                    }
                }
            }
        });

        this.autoCompleteJS.input.addEventListener('selection', event => {
            const feedback = event.detail;
            this.autoCompleteJS.input.value = this.commands[feedback.selection.value] || '';
        });
    }

    handleSSE(message) {
        const source = new EventSource(`${this.basePath}/chat/sse?message=${encodeURIComponent(message)}`);
        activeSource = source;
        let renderer = null;

        source.addEventListener('error', () => {
            source.close();
            if (activeSource === source) activeSource = null;
            this.toggleElements(false);
        });

        source.addEventListener('fullMessage', event => {
            this.chatWrapper.insertAdjacentHTML(
                'beforeend',
                event.data.replace(/\\n/g, '\n')
            );

            const messageEl = this.chatWrapper.querySelector('div.message-wrapper:last-child > div.message');
            renderer = new ChatMessageRenderer(messageEl);
        });

        source.addEventListener('removeThinking', () => {
            this.chatWrapper.querySelector('.thinking')?.remove();
        });

        source.addEventListener('agentResponse', event => {
            if (!renderer) return;

            renderer.append(event.data.replace(/\\n/g, '\n'));
        });

        source.addEventListener('done', () => {
            source.close();
            if (activeSource === source) activeSource = null;
            renderer?.renderFinal();
            this.toggleElements(false);
            this.textInput.focus();
        });
    }

    bindEvents() {
        this.form.addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData(this.form);
            this.toggleElements(true);
            this.handleSSE(formData.get('form[message]'));
        });

        this.textInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.form.requestSubmit();
            }
        });

        if (this.clearButton) {
            this.clearButton.addEventListener('click', () => this.clearChat());
        }
    }

    async clearChat() {
        if (!confirm('Are you sure you want to clear the chat history?')) {
            return;
        }

        try {
            await fetch(`${this.basePath}/chat/clear`, { method: 'POST' });
            this.chatWrapper.innerHTML = '';
        } catch (error) {
            console.error('Failed to clear chat:', error);
        }
    }

    parseExistingMessages() {
        const messages = this.chatWrapper.querySelectorAll('div.message');
        messages.forEach(messageEl => {
            if (messageEl.dataset.markdownParsed === 'true') return;

            const rawText = messageEl.textContent;
            if (!rawText) return;

            const renderer = new ChatMessageRenderer(messageEl);
            renderer.setText(rawText);
            renderer.renderFinal();
        });
    }

    render() {
        this.parseExistingMessages();
        this.initAutoComplete();
        this.bindEvents();
    }
}
