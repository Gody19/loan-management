{{--
    AI Assistant quick-launch widget.

    A floating launcher (bottom-right) that expands a minimizable chat panel.
    The panel reuses the existing self-contained chat partial
    (ai.partials.chat).

    Authenticated users with the ai.use capability get the full chat (stored
    conversations, feedback, data-driven answers). Guests on the landing page
    get the same partial in guest mode: a stateless public FAQ chat backed by
    POST /ai/chat/guest, with no conversation list, no feedback and no member
    data.

    CSS and JS are intentionally inline and self-contained, matching how the
    chat partial and landing navbar component are written. Requires Bootstrap
    5 + Bootstrap Icons + the app-custom stylesheet on the host page.
--}}
@php
    $widgetUser = auth()->user();
    $widgetCanChat = $widgetUser !== null
        && $widgetUser->can('ai.use')
        && (bool) config('ai.enabled');
    $widgetGuest = $widgetUser === null;
    $widgetGuestSuggestions = $suggestions ?? [
        'What is FinancePro?',
        'What is a VICOBA group?',
        'How do savings and loans work?',
        'What is the welfare fund?',
        'How do I get started?',
    ];
@endphp

@if ($widgetGuest || $widgetCanChat)
    <div class="ai-widget" id="aiWidget">
        <button type="button" class="ai-widget-launcher" id="aiWidgetLauncher"
                aria-controls="aiWidgetPanel" aria-expanded="false"
                aria-label="{{ $widgetGuest ? 'Ask FinancePro AI a question' : 'Open AI chat' }}">
            <i class="bi bi-chat-dots" id="aiWidgetLauncherIcon" aria-hidden="true"></i>
        </button>

        <div class="ai-widget-panel" id="aiWidgetPanel" hidden>
            <div class="ai-widget-header">
                <h6 class="ai-widget-title">
                    <i class="bi bi-stars me-2 text-primary" aria-hidden="true"></i>FinancePro AI Assistant
                </h6>
                <button type="button" class="ai-widget-close" id="aiWidgetClose" aria-label="Minimize chat">
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
            <div class="ai-widget-body" id="aiWidgetBody">
                @if ($widgetGuest)
                    @include('ai.partials.chat', [
                        'guest' => true,
                        'suggestions' => $widgetGuestSuggestions,
                    ])
                @else
                    @include('ai.partials.chat', ['suggestions' => $suggestions ?? []])
                @endif
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
        }
        .ai-widget-body .row {
            margin: 0;
        }
        .ai-widget-body .row > [class*="col-"] {
            padding: 0 0 0.75rem;
        }
        .ai-widget-body .ai-conv-list {
            max-height: 28vh;
        }
        .ai-widget-body .ai-messages {
            min-height: 220px;
            max-height: 42vh;
        }
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

            var launcher = document.getElementById('aiWidgetLauncher');
            var panel = document.getElementById('aiWidgetPanel');
            var closeBtn = document.getElementById('aiWidgetClose');

            if (!launcher || !panel) {
                return;
            }

            var icon = document.getElementById('aiWidgetLauncherIcon');

            function setOpen(open) {
                panel.hidden = !open;
                launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                launcher.setAttribute('aria-label', open ? 'Minimize chat' : 'Open AI chat');
                if (icon) {
                    icon.className = open ? 'bi bi-x-lg' : 'bi bi-chat-dots';
                }
            }

            launcher.addEventListener('click', function () {
                setOpen(panel.hidden);
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    setOpen(false);
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) {
                    setOpen(false);
                }
            });
        })();
    </script>
@endif