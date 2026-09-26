{{--
    Public landing-page AI assistant widget (Phase 11.7.1).

    Self-contained floating widget for UNAUTHENTICATED visitors only. It is the
    browser half of the session-anchored public assistant:

      POST /ai/public/chat                    ai.public.chat
      GET  /ai/public/conversations/current   ai.public.conversations.current

    Security expectations:
      * The visitor never sees or supplies a conversation id. The server keeps
        the uuid in the visitor session and only returns the plain numeric id
        of the current conversation for client-side bookkeeping.
      * AI output and every label are rendered via textContent — never via
        HTML string injection — so model output and stored text can never
        execute scripts or inject markup.
      * Every POST carries the Laravel CSRF token from the page meta tag.
      * The widget simply does not render when the public feature is off.
      * No financial value is computed client-side; numbers are displayed
        verbatim as returned by the server.

    Requires Bootstrap 5 + Bootstrap Icons + app-custom stylesheet on the host
    page (the landing layout). CSS and JS are inline and self-contained,
    matching the other widget partials.
--}}
@php
    $publicWidget = auth()->guest()
        && (bool) config('ai.enabled')
        && (bool) config('ai.public_chat.enabled', true);
    $publicSuggestions = $publicSuggestions ?? [
        'What is FinancePro?',
        'What is a VICOBA group?',
        'How do savings and loans work?',
        'What is the welfare fund?',
        'How do I get started?',
    ];
@endphp

@if ($publicWidget)
    <div class="ai-widget" id="aiPublicWidget">
        <button type="button" class="ai-widget-launcher" id="aiPublicLauncher"
                aria-controls="aiPublicPanel" aria-expanded="false"
                aria-label="Ask FinancePro AI a question">
            <i class="bi bi-chat-dots" id="aiPublicLauncherIcon" aria-hidden="true"></i>
        </button>

        <div class="ai-widget-panel" id="aiPublicPanel" hidden>
            <div class="ai-widget-header">
                <h6 class="ai-widget-title">
                    <i class="bi bi-stars me-2 text-primary" aria-hidden="true"></i>FinancePro AI Assistant
                </h6>
                <button type="button" class="ai-widget-close" id="aiPublicClose" aria-label="Minimize chat">
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
            <div class="ai-widget-body" id="aiPublicBody">
                <div class="ai-chat-shell">
                    <div id="aiPublicMessages" class="ai-messages" aria-live="polite">
                        <div id="aiPublicEmpty" class="d-flex align-items-center justify-content-center h-100 py-4">
                            <div class="text-center px-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                                    <i class="bi bi-stars"></i>
                                </div>
                                <h5 class="fw-semibold">Ask FinancePro AI</h5>
                                <p class="text-muted small mb-3">You can ask general questions about FinancePro, VICOBA groups and microfinance:</p>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                                    @forelse($publicSuggestions as $suggestion)
                                        <button type="button" class="ai-suggestion" data-ai-public-suggestion>{{ $suggestion }}</button>
                                    @empty
                                        <span class="text-muted small">Type your question below and press Enter.</span>
                                    @endforelse
                                </div>
                                <p class="text-muted small mb-0">
                                    <span class="d-inline-flex align-items-center gap-1"><i class="bi bi-info-circle" aria-hidden="true"></i>Public assistant &mdash; no access to your data. Sign in for questions about your own savings, shares, loans and welfare.</span>
                                </p>
                            </div>
                        </div>
                        <div id="aiPublicThread" class="d-none"></div>
                    </div>

                    <div id="aiPublicError" class="alert alert-danger ai-error-banner d-none mb-2 py-2" role="alert" style="font-size:0.875rem;"></div>

                    <form id="aiPublicComposer" autocomplete="off" class="mt-2">
                        <label class="visually-hidden" for="aiPublicInput">Your message</label>
                        <div class="input-group">
                            <textarea id="aiPublicInput" class="form-control ai-input" rows="1" maxlength="4000" placeholder="Ask FinancePro AI... (Enter to send, Shift+Enter for a new line)" aria-label="Your message"></textarea>
                            <button type="submit" id="aiPublicSend" class="btn btn-primary no-spinner" aria-label="Send message">
                                <i class="bi bi-send me-1"></i>Send
                            </button>
                        </div>
                        <div class="form-text small" id="aiPublicStatus" role="status" aria-live="polite"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ai-widget {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 1025;
        }
        .ai-widget-launcher {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: #0d6efd;
            color: #fff;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.35);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .ai-widget-launcher:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(13, 110, 253, 0.42);
        }
        .ai-widget-launcher:focus-visible {
            outline: 3px solid rgba(13, 110, 253, 0.35);
            outline-offset: 2px;
        }
        .ai-widget-panel {
            position: fixed;
            right: 16px;
            bottom: 84px;
            width: min(430px, calc(100vw - 32px));
            height: min(640px, calc(100vh - 120px));
            background: #fff;
            border-radius: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.18);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1025;
        }
        .ai-widget-panel[hidden] {
            display: none;
        }
        .ai-widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
        }
        .ai-widget-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .ai-widget-close {
            border: none;
            background: transparent;
            color: #6c757d;
            font-size: 1.25rem;
            line-height: 1;
            padding: 0.25rem 0.375rem;
            border-radius: 0.375rem;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .ai-widget-close:hover {
            color: #212529;
            background: #f1f3f5;
        }
        .ai-widget-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
        }
        .ai-widget-body .ai-chat-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .ai-widget-body .ai-messages {
            min-height: 220px;
            max-height: 46vh;
            overflow-y: auto;
            flex: 1;
        }
        .ai-bubble-row { display: flex; margin-bottom: 0.75rem; }
        .ai-row-user { justify-content: flex-end; }
        .ai-row-assistant { justify-content: flex-start; }
        .ai-bubble { max-width: 78%; padding: 0.625rem 0.875rem; border-radius: 0.9rem; }
        .ai-bubble-user { background: #0d6efd; color: #fff; border-bottom-right-radius: 0.25rem; }
        .ai-bubble-assistant { background: #f1f3f5; color: #212529; border: 1px solid #e9ecef; border-bottom-left-radius: 0.25rem; }
        .ai-bubble-text { white-space: pre-wrap; word-break: break-word; }
        .ai-bubble-time { margin-top: 0.25rem; font-size: 0.68rem; opacity: 0.65; }
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
        @media (max-width: 480px) {
            .ai-widget-panel {
                right: 8px;
                bottom: 80px;
                width: calc(100vw - 16px);
                height: calc(100vh - 104px);
            }
        }
    </style>

    <script>
        (function () {
            'use strict';

            // The public widget is embedded at most once per page. Guard every
            // init so a future layout that stacks widgets cannot double-bind.
            if (window.__financeProPublicAiChatBound) {
                return;
            }
            window.__financeProPublicAiChatBound = true;

            var ENDPOINTS = {
                chatUrl: @json(route('ai.public.chat')),
                currentUrl: @json(route('ai.public.conversations.current'))
            };

            var el = {
                widget: document.getElementById('aiPublicWidget'),
                launcher: document.getElementById('aiPublicLauncher'),
                panel: document.getElementById('aiPublicPanel'),
                close: document.getElementById('aiPublicClose'),
                icon: document.getElementById('aiPublicLauncherIcon'),
                messages: document.getElementById('aiPublicMessages'),
                empty: document.getElementById('aiPublicEmpty'),
                thread: document.getElementById('aiPublicThread'),
                error: document.getElementById('aiPublicError'),
                composer: document.getElementById('aiPublicComposer'),
                input: document.getElementById('aiPublicInput'),
                send: document.getElementById('aiPublicSend'),
                status: document.getElementById('aiPublicStatus')
            };

            if (!el.widget || !el.thread || !el.composer) {
                window.__financeProPublicAiChatBound = false;
                return;
            }

            var state = {
                open: false,
                sending: false,
                restored: false
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

            function bubble(role, text) {
                var wrap = document.createElement('div');
                wrap.className = 'ai-bubble-row ' + (role === 'user' ? 'ai-row-user' : 'ai-row-assistant');
                var inner = addEl(wrap, 'div', role === 'user' ? 'ai-bubble ai-bubble-user' : 'ai-bubble ai-bubble-assistant');
                addEl(inner, 'div', 'ai-bubble-text', text);
                el.thread.appendChild(wrap);
                return wrap;
            }

            function appendThinking() {
                var wrap = document.createElement('div');
                wrap.className = 'ai-bubble-row ai-row-assistant';
                wrap.id = 'aiPublicThinking';
                var inner = addEl(wrap, 'div', 'ai-bubble ai-bubble-assistant ai-thinking');
                addEl(inner, 'span', 'ai-dot', '.');
                addEl(inner, 'span', 'ai-dot', '.');
                addEl(inner, 'span', 'ai-dot', '.');
                addEl(inner, 'span', 'visually-hidden', 'AI Assistant is thinking...');
                el.thread.appendChild(wrap);
                scrollBottom();
                return wrap;
            }

            function removeThinking() {
                var node = document.getElementById('aiPublicThinking');
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

            function renderStored(messages) {
                if (!Array.isArray(messages)) {
                    return;
                }
                messages.forEach(function (message) {
                    if (!message) {
                        return;
                    }
                    bubble(message.role === 'user' ? 'user' : 'assistant', message.content || '');
                });
                setThreadVisibility();
                scrollBottom();
            }

            function messagesFromPayload(payload) {
                if (!payload || !payload.data || !Array.isArray(payload.data.messages)) {
                    return [];
                }
                return payload.data.messages;
            }

            function restoreConversation() {
                if (state.restored) {
                    return;
                }
                state.restored = true;

                fetch(ENDPOINTS.currentUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    if (result.ok) {
                        renderStored(messagesFromPayload(result.payload));
                    }
                }).catch(function () {
                    // Restore is best-effort. A failure just leaves the empty state.
                });
            }

            function httpMessage(status) {
                if (status === 422) {
                    return 'Your question could not be processed. Please rephrase and try again.';
                }
                if (status === 429) {
                    return 'Too many requests. Please wait a moment and try again.';
                }
                if (status === 503) {
                    return 'The AI assistant is temporarily unavailable. You can continue exploring FinancePro.';
                }
                if (status >= 500) {
                    return 'Something went wrong while processing your question. Please try again.';
                }
                return 'Something went wrong. Please try again.';
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
                setThreadVisibility();
                scrollBottom();

                fetch(ENDPOINTS.chatUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: text })
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    removeThinking();
                    scrollBottom();

                    if (result.ok && result.payload && result.payload.data) {
                        bubble('assistant', result.payload.data.content || '');
                        el.input.value = '';
                        scrollBottom();
                        return;
                    }
                    showError(httpMessage(result.status));
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

            function setOpen(open) {
                state.open = open;
                el.panel.hidden = !open;
                el.launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                el.launcher.setAttribute('aria-label', open ? 'Minimize chat' : 'Ask FinancePro AI a question');
                if (el.icon) {
                    el.icon.className = open ? 'bi bi-x-lg' : 'bi bi-chat-dots';
                }
                if (open) {
                    restoreConversation();
                    el.input.focus();
                }
            }

            el.launcher.addEventListener('click', function () {
                setOpen(el.panel.hidden);
            });

            if (el.close) {
                el.close.addEventListener('click', function () {
                    setOpen(false);
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !el.panel.hidden) {
                    setOpen(false);
                }
            });

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

            Array.prototype.forEach.call(el.empty.querySelectorAll('[data-ai-public-suggestion]'), function (button) {
                button.addEventListener('click', function () {
                    el.input.value = button.textContent || '';
                    sendMessage();
                });
            });
        })();
    </script>
@endif