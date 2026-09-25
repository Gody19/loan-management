@extends('layouts.app')

@section('title', 'AI Feedback Review - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'AI Feedback Review',
        'subtitle' => 'Review submitted AI feedback and approve safe examples for the learning dataset.',
    ])
@endsection

@section('content')
    {{--
        Reviewer console for the Phase 11.6 governance pipeline.

        Security notes:
          - Every value rendered from a record is written with textContent or an
            escaped Blade echo. No innerHTML, no eval, no Function().
          - The criteria set, the reviewer identity, and the tenant scope are
            server-rendered. The browser cannot select a criterion outside the
            controlled set, name an evaluator, or widen the tenant scope.
          - Approve/reject posts to the protected endpoint, which re-derives
            authorization from the trusted AI context server-side.
    --}}

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="vicoba-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Review queue</span>
                    <button type="button" id="aiRefreshQueue" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div id="aiQueueStatus" class="small text-muted mb-3" role="status" aria-live="polite">
                        Loading feedback...
                    </div>
                    <div id="aiQueue" class="d-flex flex-column gap-3"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="vicoba-card mb-3">
                <div class="card-header">Feedback analytics</div>
                <div class="card-body">
                    <div id="aiAnalytics" class="small text-muted">Loading analytics...</div>
                </div>
            </div>

            <div class="vicoba-card">
                <div class="card-header">Approved dataset</div>
                <div class="card-body">
                    <p class="small text-muted">
                        Export contains only approved, sanitized examples within your scope.
                    </p>
                    @can('ai.feedback.export')
                        <a href="{{ route('ai.dataset.export') }}" class="btn btn-sm btn-primary" id="aiDatasetExport">
                            <i class="bi bi-download"></i> Export JSONL
                        </a>
                    @else
                        <p class="small text-muted mb-0">
                            You do not have permission to export the learning dataset.
                        </p>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <template id="aiQueueItemTemplate">
        <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge ai-item-type"></span>
                <span class="small text-muted ai-item-model"></span>
            </div>
            <div class="small mb-1"><strong>Question</strong></div>
            <div class="ai-item-question border-start ps-2 mb-2 small"></div>
            <div class="small mb-1"><strong>AI response</strong></div>
            <div class="ai-item-response border-start ps-2 mb-2 small"></div>
            <div class="small mb-1 d-none ai-correction-wrap"><strong>Submitted correction</strong></div>
            <div class="ai-item-correction border-start ps-2 mb-2 small d-none"></div>
            <div class="ai-review-form d-none">
                <div class="small mb-1"><strong>Evaluation criteria</strong></div>
                <div class="ai-criteria d-flex flex-column gap-1 mb-2"></div>
                <label class="form-label small mb-1" for="aiNotes">Reviewer notes</label>
                <textarea id="aiNotes" class="form-control form-control-sm ai-notes" rows="2" maxlength="2000"></textarea>
                <label class="form-label small mb-1 mt-2" for="aiRejectionReason">Rejection reason</label>
                <input id="aiRejectionReason" type="text" class="form-control form-control-sm ai-reason" maxlength="500">
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-success ai-approve">
                        <i class="bi bi-check2-circle"></i> Approve
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger ai-reject">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-primary ai-start">
                    <i class="bi bi-clipboard-check"></i> Review
                </button>
            </div>
            <div class="small mt-2 ai-item-status"></div>
        </div>
    </template>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            var ENDPOINTS = {
                queueUrl: @json(route('ai.evaluations.index')),
                analyticsUrl: @json(route('ai.dataset.analytics')),
                startTmpl: @json(route('ai.feedback.evaluation.store', 0)),
                decideTmpl: @json(route('ai.evaluations.update', 0))
            };

            var CRITERIA = @json(collect(\App\Enums\AiEvaluationCriterion::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->all());

            function csrf() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.content : '';
            }

            function post(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                }).then(function (response) {
                    return response.json()
                        .catch(function () {
                            return {};
                        })
                        .then(function (payload) {
                            return { ok: response.ok, status: response.status, payload: payload };
                        });
                });
            }

            function withId(template, id) {
                return template.replace(/\/0(\/|$|\?)/, '/' + encodeURIComponent(id) + '$1');
            }

            function clear(node) {
                node.replaceChildren();
            }

            function addText(parent, tag, className, text) {
                var node = document.createElement(tag);
                if (className) {
                    node.className = className;
                }
                node.textContent = text === null || text === undefined ? '' : String(text);
                parent.appendChild(node);
                return node;
            }

            function message(status, payload) {
                if (status === 403) {
                    return 'You do not have permission to perform that action.';
                }
                if (status === 409) {
                    return (payload && payload.message) || 'This item has already been finalized.';
                }
                if (status === 422) {
                    var errors = payload && payload.errors;
                    if (errors) {
                        var keys = Object.keys(errors);
                        if (keys.length && errors[keys[0]] && errors[keys[0]].length) {
                            return errors[keys[0]][0];
                        }
                    }
                    return (payload && payload.message) || 'That decision is not valid.';
                }
                if (status === 429) {
                    return 'Too many requests. Please wait a moment.';
                }
                return 'Something went wrong. Please try again.';
            }

            var queueNode = document.getElementById('aiQueue');
            var statusNode = document.getElementById('aiQueueStatus');
            var analyticsNode = document.getElementById('aiAnalytics');
            var template = document.getElementById('aiQueueItemTemplate');

            function renderQueue(items) {
                clear(queueNode);
                statusNode.textContent = items.length
                    ? items.length + ' item(s) awaiting review.'
                    : 'No feedback is waiting for review.';

                items.forEach(function (item) {
                    var node = template.content.cloneNode(true);

                    var badge = node.querySelector('.ai-item-type');
                    badge.textContent = item.type_label || item.type;
                    badge.classList.add('text-bg-secondary');

                    addText(node, 'span', 'small text-muted ai-item-model',
                        (item.provider || 'unknown') + ' / ' + (item.model || 'unknown'));

                    addText(node, 'div', 'ai-item-question', item.question || '(not available)');
                    addText(node, 'div', 'ai-item-response', item.response || '(not available)');

                    if (item.correction) {
                        node.querySelector('.ai-correction-wrap').classList.remove('d-none');
                        var correction = node.querySelector('.ai-item-correction');
                        correction.classList.remove('d-none');
                        correction.textContent = item.correction;
                    }

                    var status = node.querySelector('.ai-item-status');
                    status.textContent = item.evaluation
                        ? 'Evaluation: ' + item.evaluation.status
                        : 'Not yet reviewed.';

                    bind(node, item);
                    queueNode.appendChild(node);
                });
            }

            function bind(node, item) {
                var form = node.querySelector('.ai-review-form');
                var criteriaBox = node.querySelector('.ai-criteria');
                var itemStatus = node.querySelector('.ai-item-status');
                var inputs = {};

                CRITERIA.forEach(function (criterion) {
                    var wrap = document.createElement('div');
                    wrap.className = 'form-check form-check-inline';

                    var input = document.createElement('input');
                    input.className = 'form-check-input';
                    input.type = 'radio';
                    input.name = 'ai-criterion-' + item.feedback_id;
                    input.value = criterion.value;
                    input.id = 'ai-crit-' + item.feedback_id + '-' + criterion.value;
                    input.checked = criterion.value === 'correctness';

                    var label = document.createElement('label');
                    label.className = 'form-check-label small';
                    label.htmlFor = input.id;
                    label.textContent = criterion.label;

                    inputs[criterion.value] = input;

                    wrap.appendChild(input);
                    wrap.appendChild(label);
                    criteriaBox.appendChild(wrap);
                });

                var selected = function () {
                    var scores = {};
                    CRITERIA.forEach(function (criterion) {
                        scores[criterion.value] = inputs[criterion.value].checked ? 'pass' : 'fail';
                    });
                    return scores;
                };

                node.querySelector('.ai-start').addEventListener('click', function () {
                    post(withId(ENDPOINTS.startTmpl, item.feedback_id), {})
                        .then(function (result) {
                            if (!result.ok) {
                                itemStatus.textContent = message(result.status, result.payload);
                                return;
                            }
                            item.evaluation = result.payload.data;
                            form.classList.remove('d-none');
                            node.querySelector('.ai-start').disabled = true;
                            itemStatus.textContent = 'Review in progress.';
                        });
                });

                node.querySelector('.ai-approve').addEventListener('click', function () {
                    post(withId(ENDPOINTS.decideTmpl, item.evaluation ? item.evaluation.id : 0), {
                        decision: 'approved',
                        scores: selected(),
                        notes: node.querySelector('.ai-notes').value || null,
                        include_in_dataset: true
                    }).then(function (result) {
                        itemStatus.textContent = result.ok
                            ? 'Approved and added to the learning dataset.'
                            : message(result.status, result.payload);
                        if (result.ok) {
                            load();
                        }
                    });
                });

                node.querySelector('.ai-reject').addEventListener('click', function () {
                    post(withId(ENDPOINTS.decideTmpl, item.evaluation ? item.evaluation.id : 0), {
                        decision: 'rejected',
                        scores: selected(),
                        notes: node.querySelector('.ai-notes').value || null,
                        rejection_reason: node.querySelector('.ai-reason').value || null
                    }).then(function (result) {
                        itemStatus.textContent = result.ok
                            ? 'Rejected. It will not become a dataset example.'
                            : message(result.status, result.payload);
                        if (result.ok) {
                            load();
                        }
                    });
                });
            }

            function renderAnalytics(data) {
                clear(analyticsNode);

                addText(analyticsNode, 'div', 'mb-2', 'Total feedback: ' + data.total_feedback);
                addText(analyticsNode, 'div', 'mb-2', 'Approved: ' + data.approved + '  Rejected: ' + data.rejected);
                addText(analyticsNode, 'div', 'mb-2', 'Pending evaluations: ' + data.pending_evaluations);

                var byType = document.createElement('ul');
                byType.className = 'list-unstyled small mb-0';
                Object.keys(data.by_type || {}).forEach(function (key) {
                    var li = document.createElement('li');
                    li.textContent = key + ': ' + data.by_type[key];
                    byType.appendChild(li);
                });
                analyticsNode.appendChild(byType);
            }

            function load() {
                fetch(ENDPOINTS.queueUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                }).then(function (result) {
                    if (result.ok && result.payload) {
                        renderQueue(result.payload.data || []);
                    } else {
                        statusNode.textContent = message(result.status, result.payload);
                    }
                });

                fetch(ENDPOINTS.analyticsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (payload && payload.data) {
                        renderAnalytics(payload.data);
                    }
                });
            }

            document.getElementById('aiRefreshQueue').addEventListener('click', load);
            load();
        })();
    </script>
@endpush
