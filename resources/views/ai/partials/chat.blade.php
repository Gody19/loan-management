{{--
    AI Assistant chat UI.

    Pure presentation layer. The conversation list, message history and chat
    submission consume the existing JSON endpoints:

      GET  /ai/conversations          -> ai.conversations.index
      GET  /ai/conversations/{id}     -> ai.conversations.show
      POST /ai/chat                   -> ai.chat (server-side orchestration)

    Guest mode ($guest = true) is the public landing-page FAQ chat:
    POST /ai/chat/guest               -> ai.chat.guest (stateless, IP-throttled)

    Security expectations:
      * The browser never selects capabilities, tool names, tool arguments,
        tenant ids or model output. The server orchestrates what (if anything)
        may be consulted for a question.
      * AI output is rendered as plain text via textContent — never via HTML
        string injection — so model output can never execute scripts or inject
        markup.
      * Every POST carries the Laravel CSRF token from the meta tag. Guest mode
        is the only public chat surface; it is stateless, holds no conversation
        list and cannot reach member data or the knowledge base.
      * No financial value is computed client-side; numbers are only ever
        displayed verbatim as returned by the server.
--}}
<div class="ai-chat-shell">

    <style>
        .ai-conv-list {
            max-height: 60vh;
            overflow-y: auto;
        }
        .ai-conv-item {
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            border-radius: 0.5rem;
            padding: 0.5rem 0.625rem;
        }
        .ai-conv-item:hover { background: rgba(13, 110, 253, 0.06); }
        .ai-conv-item:focus-visible { outline: 2px solid #0d6efd; outline-offset: -2px; }
        .ai-conv-item.active { background: rgba(13, 110, 253, 0.12); }
        .ai-messages { min-height: 340px; max-height: 60vh; overflow-y: auto; }
        .ai-bubble-row { display: flex; margin-bottom: 0.75rem; }
        .ai-row-user { justify-content: flex-end; }
        .ai-row-assistant { justify-content: flex-start; }
        .ai-bubble { max-width: 78%; padding: 0.625rem 0.875rem; border-radius: 0.9rem; }
        .ai-bubble-user { background: #0d6efd; color: #fff; border-bottom-right-radius: 0.25rem; }
        .ai-bubble-assistant { background: #f1f3f5; color: #212529; border: 1px solid #e9ecef; border-bottom-left-radius: 0.25rem; }
        .ai-bubble-text { white-space: pre-wrap; word-break: break-word; }
        .ai-bubble-time { margin-top: 0.25rem; font-size: 0.68rem; opacity: 0.65; }
        .ai-error-banner { border-inline-start: 0;}
        .ai-thinking .ai-dot { animation: aiPulse 1.2s infinite ease-in-out; }
        .ai-thinking .ai-dot:nth-child(2) { animation-delay: 0.2s; }
        .ai-thinking .ai-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes aiPulse { 0%, 80%, 100% { opacity: 0.25; transform: scale(0.85);} 40% { opacity: 1; transform: scale(1);} }
        .ai-suggestion {
            border: 1px solid #dee2e6;
            background: #fff;
            color: #0d6efd;
            border-radius: 2rem;
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
        }
        .ai-suggestion:hover { background: #f8f9fa; }
        .ai-suggestion:focus-visible { outline: 2px solid #0d6efd; outline-offset: 2px; }
        .ai-input { resize: none; }
        .ai-feedback {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        .ai-feedback-btn { font-size: 0.75rem; padding: 0.2rem 0.55rem; }
        .ai-feedback-btn.active { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .ai-feedback-label { margin-inline-start: 0.25rem; }
    </style>

    <div class="row g-3">
        @if (! ($guest ?? false))
            {{-- Conversation list --}}
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card vicoba-card h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-chat-dots me-2 text-primary"></i>Conversations
                        </h6>
                        <button type="button" id="aiNewChat" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg me-1"></i>New Chat
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div id="aiConversationList" class="ai-conv-list" role="list" aria-label="Previous conversations">
                            <div id="aiConversationLoading" class="text-center text-muted small py-4">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading conversations...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Chat area --}}
        <div class="col-12 col-lg-8 col-xl-9">
            <div class="card vicoba-card">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-stars me-2 text-primary"></i>AI Assistant</h6>
                </div>

                <div class="card-body ai-messages" id="aiMessages" aria-live="polite">

                    {{-- Empty state (no conversation selected / brand new chat) --}}
                    <div id="aiEmptyState" class="d-flex align-items-center justify-content-center h-100 py-4">
                        <div class="text-center px-3">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                                <i class="bi bi-stars"></i>
                            </div>
                            <h5 class="fw-semibold">Ask FinancePro AI</h5>
                            @if ($guest ?? false)
                                <p class="text-muted small mb-3">You can ask general questions about FinancePro, VICOBA groups and microfinance:</p>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                                    @forelse($suggestions as $suggestion)
                                        <button type="button" class="ai-suggestion" data-ai-suggestion>{{ $suggestion }}</button>
                                    @empty
                                        <span class="text-muted small">Type your question below and press Enter.</span>
                                    @endforelse
                                </div>
                                <p class="text-muted small mb-0">
                                    This is a public assistant with no access to your data. Sign in to ask questions about your own savings, shares, loans and welfare.
                                </p>
                            @else
                                <p class="text-muted small mb-3">You can ask questions such as:</p>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                                    @forelse($suggestions as $suggestion)
                                        <button type="button" class="ai-suggestion" data-ai-suggestion>{{ $suggestion }}</button>
                                    @empty
                                        <span class="text-muted small">Start a new chat and type your question below.</span>
                                    @endforelse
                                </div>
                                <p class="text-muted small mb-0">
                                    Your questions are answered from the data you are authorized to see.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Active conversation thread (filled by JS, text render only) --}}
                    <div id="aiThread" class="d-none"></div>
                </div>

                <div class="card-footer bg-white border-top">
                    <div id="aiError" class="alert alert-danger ai-error-banner d-none mb-2 py-2" role="alert" style="font-size:0.875rem;"></div>

                    <form id="aiComposer" autocomplete="off">
                        <label class="visually-hidden" for="aiInput">Your message</label>
                        <div class="input-group">
                            <textarea id="aiInput" class="form-control ai-input" rows="1" maxlength="4000" placeholder="Ask FinancePro AI... (Enter to send, Shift+Enter for a new line)" aria-label="Your message"></textarea>
                            <button type="submit" id="aiSend" class="btn btn-primary no-spinner" aria-label="Send message">
                                <i class="bi bi-send me-1"></i>Send
                            </button>
                        </div>
                        <div class="form-text small" id="aiStatus" role="status" aria-live="polite"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        function whenDocumentReady(fn) {
            if (document.readyState !== 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }

        whenDocumentReady(function () {
            var ENDPOINTS = {
                listUrl: @json(route('ai.conversations.index')),
                showTmpl: @json(route('ai.conversations.show', '__ID__')),
                chatUrl: @json(($guest ?? false) ? route('ai.chat.guest') : route('ai.chat')),
                feedbackUrl: @json(route('ai.feedback.store')),
                canGiveFeedback: @json((! ($guest ?? false)) && auth()->check() && auth()->user()->can('ai.feedback.submit')),
                loginUrl: @json(route('login'))
            };

            var el = {
                list: document.getElementById('aiConversationList'),
                listLoading: document.getElementById('aiConversationLoading'),
                empty: document.getElementById('aiEmptyState'),
                thread: document.getElementById('aiThread'),
                messages: document.getElementById('aiMessages'),
                error: document.getElementById('aiError'),
                composer: document.getElementById('aiComposer'),
                input: document.getElementById('aiInput'),
                send: document.getElementById('aiSend'),
                newChat: document.getElementById('aiNewChat'),
                status: document.getElementById('aiStatus')
            };

            if (!el.thread || !el.composer) {
                return;
            }

            var state = {
                currentId: null,
                sending: false
            };

            function csrf() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.content : '';
            }

            function setStatus(text) {
                el.status.textContent = text || '';
            }

            function clearError() {
                el.error.classList.add('d-none');
                el.error.textContent = '';
            }

            function showError(message) {
                el.error.textContent = message;
                el.error.classList.remove('d-none');
            }

            function scrollBottom() {
                el.messages.scrollTop = el.messages.scrollHeight;
            }

            function addEl(parent, tag, className, text) {
                var node = document.createElement(tag);
                if (className) {
                    node.className = className;
                }
                if (text !== undefined && text !== null) {
                    node.textContent = text;
                }
                parent.appendChild(node);
                return node;
            }

            function bubble(role, text, title) {
                var wrap = document.createElement('div');
                wrap.className = 'ai-bubble-row ' + (role === 'user' ? 'ai-row-user' : 'ai-row-assistant');

                var inner = addEl(wrap, 'div', role === 'user' ? 'ai-bubble ai-bubble-user' : 'ai-bubble ai-bubble-assistant');
                addEl(inner, 'div', 'ai-bubble-text', text);

                if (title) {
                    addEl(inner, 'div', 'ai-bubble-time', title);
                }

                el.thread.appendChild(wrap);
                return wrap;
            }

            // Phase 11.6: feedback controls hang off an assistant bubble. The
            // message id is written as a data attribute from the server
            // response — never built from user input — and every label is
            // written with textContent so neither the AI response nor a stored
            // correction can inject markup.
            function feedbackControls(messageId, existing) {
                if (!ENDPOINTS.canGiveFeedback || !messageId) {
                    return null;
                }

                var row = document.createElement('div');
                row.className = 'ai-feedback';

                var selected = existing ? existing.type : null;

                var choices = [
                    { value: 'positive', label: 'Helpful', icon: 'bi-hand-thumbs-up' },
                    { value: 'negative', label: 'Not helpful', icon: 'bi-hand-thumbs-down' },
                    { value: 'correction', label: 'Correct / Explain', icon: 'bi-pencil-square' }
                ];

                choices.forEach(function (choice) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-secondary ai-feedback-btn' +
                        (selected === choice.value ? ' active' : '');
                    button.setAttribute('data-ai-feedback', choice.value);

                    var icon = document.createElement('i');
                    icon.className = 'bi ' + choice.icon;
                    button.appendChild(icon);

                    addEl(button, 'span', 'ai-feedback-label', choice.label);

                    button.addEventListener('click', function () {
                        submitFeedback(messageId, choice.value, button, row);
                    });

                    row.appendChild(button);
                });

                return row;
            }

            function submitFeedback(messageId, type, button, row) {
                var body = { message_id: messageId, type: type };

                if (type === 'correction') {
                    var correction = window.prompt(
                        'What was wrong with this answer? Your note is stored as data only and is reviewed by a human before it can be used.'
                    );

                    if (correction === null) {
                        return;
                    }

                    if (!correction.trim()) {
                        showError('A correction needs some text, or choose another option.');
                        return;
                    }

                    body.correction = correction;
                }

                Array.prototype.forEach.call(row.querySelectorAll('.ai-feedback-btn'), function (other) {
                    other.disabled = true;
                });

                fetch(ENDPOINTS.feedbackUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    if (result.ok && result.payload && result.payload.data) {
                        Array.prototype.forEach.call(row.querySelectorAll('.ai-feedback-btn'), function (other) {
                            other.classList.remove('active');
                            other.disabled = false;
                        });
                        button.classList.add('active');
                        setStatus('Thanks — your feedback was recorded.');
                        return;
                    }

                    if (result.status === 401) {
                        redirectLogin();
                        return;
                    }

                    Array.prototype.forEach.call(row.querySelectorAll('.ai-feedback-btn'), function (other) {
                        other.disabled = false;
                    });
                    showError(httpMessage(result.status, result.payload));
                }).catch(function () {
                    Array.prototype.forEach.call(row.querySelectorAll('.ai-feedback-btn'), function (other) {
                        other.disabled = false;
                    });
                    showError('Your feedback could not be sent. Please try again.');
                }).then(function () {
                    setStatus('');
                });
            }

            function assistantBubble(message) {
                var wrap = bubble('assistant', message.content || '', formatTime(message.created_at));

                if (message.id) {
                    wrap.setAttribute('data-ai-message-id', String(message.id));

                    var controls = feedbackControls(message.id, message.feedback);

                    if (controls) {
                        wrap.appendChild(controls);
                    }
                }

                return wrap;
            }

            function appendThinking() {
                var wrap = document.createElement('div');
                wrap.className = 'ai-bubble-row ai-row-assistant';
                wrap.id = 'aiThinking';

                var inner = addEl(wrap, 'div', 'ai-bubble ai-bubble-assistant ai-thinking');
                addEl(inner, 'span', 'ai-dot', '•');
                addEl(inner, 'span', 'ai-dot', '•');
                addEl(inner, 'span', 'ai-dot', '•');
                addEl(inner, 'span', 'visually-hidden', 'AI Assistant is thinking...');

                el.thread.appendChild(wrap);
                scrollBottom();
                return wrap;
            }

            function removeThinking() {
                var node = document.getElementById('aiThinking');
                if (node && node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }

            function formatTime(iso) {
                if (!iso) {
                    return '';
                }
                var date = new Date(iso);
                if (isNaN(date.getTime())) {
                    return '';
                }
                return date.toLocaleString();
            }

            function setThreadVisibility() {
                var hasMessages = el.thread.childElementCount > 0;
                el.thread.classList.toggle('d-none', !hasMessages);
                el.empty.classList.toggle('d-none', hasMessages);
            }

            function renderMessages(messages) {
                el.thread.replaceChildren();
                if (!Array.isArray(messages)) {
                    return;
                }
                messages.forEach(function (message) {
                    if (!message) {
                        return;
                    }
                    if (message.role === 'assistant') {
                        assistantBubble(message);
                        return;
                    }
                    if (message.role === 'user') {
                        bubble('user', message.content || '', formatTime(message.created_at));
                    }
                });
                setThreadVisibility();
                scrollBottom();
            }

            function showUrlFor(id) {
                return ENDPOINTS.showTmpl.replace('__ID__', window.encodeURIComponent(String(id)));
            }

            function openConversation(id) {
                clearError();
                setStatus('Loading conversation...');

                fetch(showUrlFor(id), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    if (result.ok && result.payload && result.payload.data) {
                        state.currentId = result.payload.data.id;
                        renderMessages(result.payload.messages);
                        highlightCurrent();
                        setStatus('');
                        return;
                    }
                    if (result.status === 401) {
                        redirectLogin();
                        return;
                    }
                    showError(httpMessage(result.status, result.payload));
                }).catch(function () {
                    showError('Something went wrong while loading this conversation. Please try again.');
                });
            }

            function renderConversationList(items) {
                el.list.replaceChildren();

                if (!items || items.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'text-center text-muted small py-4';
                    empty.textContent = 'No conversations yet. Start a new chat and your conversations will appear here.';
                    el.list.appendChild(empty);
                    return;
                }

                items.forEach(function (item) {
                    if (!item || !item.id) {
                        return;
                    }
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'ai-conv-item';
                    button.setAttribute('role', 'listitem');
                    button.dataset.conversationId = String(item.id);

                    var title = addEl(button, 'div', 'fw-medium text-truncate', item.title || 'Chat');
                    title.style.fontSize = '0.875rem';

                    if (item.updated_at) {
                        addEl(button, 'small', 'text-muted d-block', formatTime(item.updated_at));
                    }

                    button.addEventListener('click', function () {
                        openConversation(item.id);
                    });

                    el.list.appendChild(button);
                });

                highlightCurrent();
            }

            function highlightCurrent() {
                if (!el.list) {
                    return;
                }
                var current = String(state.currentId || '');
                Array.prototype.forEach.call(el.list.querySelectorAll('[data-conversation-id]'), function (button) {
                    button.classList.toggle('active', button.dataset.conversationId === current);
                });
            }

            function refreshConversations() {
                if (!el.list) {
                    return;
                }
                fetch(ENDPOINTS.listUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    if (el.listLoading) {
                        el.listLoading.parentNode && el.listLoading.parentNode.removeChild(el.listLoading);
                    }
                    if (result.ok) {
                        renderConversationList(result.payload && result.payload.data ? result.payload.data : []);
                        return;
                    }
                    addEl(el.list, 'div', 'text-center text-danger small py-4', 'Conversations could not be loaded. Please refresh the page.');
                }).catch(function () {
                    if (el.listLoading) {
                        el.listLoading.parentNode && el.listLoading.parentNode.removeChild(el.listLoading);
                    }
                    addEl(el.list, 'div', 'text-center text-danger small py-4', 'Conversations could not be loaded. Please refresh the page.');
                });
            }

            function startNewConversation() {
                clearError();
                state.currentId = null;
                el.thread.replaceChildren();
                setThreadVisibility();
                highlightCurrent();
                setStatus('');
                el.input.value = '';
                el.input.focus();
            }

            function httpMessage(status, payload) {
                if (status === 403) {
                    return 'You do not have permission to use the AI assistant.';
                }
                if (status === 422) {
                    var first = null;
                    if (payload && payload.errors) {
                        var keys = Object.keys(payload.errors);
                        if (keys.length > 0 && payload.errors[keys[0]] && payload.errors[keys[0]].length) {
                            first = payload.errors[keys[0]][0];
                        }
                    }
                    return first || 'Your question could not be processed. Please rephrase and try again.';
                }
                if (status === 429) {
                    return 'Too many requests. Please wait a moment and try again.';
                }
                if (status === 503) {
                    return 'The AI assistant is temporarily unavailable. You can continue using the existing FinancePro features.';
                }
                if (status >= 500) {
                    return 'Something went wrong while processing your question. Please try again.';
                }
                return 'Something went wrong. Please try again.';
            }

            function redirectLogin() {
                window.location.href = ENDPOINTS.loginUrl;
            }

            function sendMessage() {
                if (state.sending) {
                    return;
                }
                var text = el.input.value.trim();
                if (!text) {
                    return;
                }

                state.sending = true;
                el.send.disabled = true;
                el.input.disabled = true;
                setStatus('AI Assistant is thinking...');
                clearError();

                bubble('user', text);
                appendThinking();
                scrollBottom();

                var body = { message: text };
                if (state.currentId) {
                    body.conversation_id = state.currentId;
                }

                fetch(ENDPOINTS.chatUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    removeThinking();
                    scrollBottom();

                    if (result.ok && result.payload && result.payload.data) {
                        var data = result.payload.data;
                        if (!state.currentId || state.currentId !== data.conversation_id) {
                            state.currentId = data.conversation_id;
                        }
                        assistantBubble({ id: data.message_id, content: data.content || '' });
                        el.input.value = '';
                        refreshConversations();
                        scrollBottom();
                        return;
                    }

                    if (result.status === 401) {
                        redirectLogin();
                        return;
                    }
                    showError(httpMessage(result.status, result.payload));
                }).catch(function () {
                    removeThinking();
                    scrollBottom();
                    showError('Something went wrong while processing your question. Please try again.');
                }).then(function () {
                    state.sending = false;
                    el.send.disabled = false;
                    el.input.disabled = false;
                    setStatus('');
                    el.input.focus();
                });
            }

            el.composer.addEventListener('submit', function (event) {
                event.preventDefault();
                sendMessage();
            });

            el.input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage();
                }
            });

            if (el.newChat) {
                el.newChat.addEventListener('click', startNewConversation);
            }

            Array.prototype.forEach.call(el.empty.querySelectorAll('[data-ai-suggestion]'), function (button) {
                button.addEventListener('click', function () {
                    el.input.value = button.textContent || '';
                    sendMessage();
                });
            });

            if (el.list) {
                refreshConversations();
            }
            el.input.focus();
        });
    })();
</script>