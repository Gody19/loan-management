<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Server-rendered chat page for the AI assistant.
 *
 * The page is a pure presentation shell. It renders the existing layout for
 * the acting audience (VICOBA Member portal vs staff/admin), ships a minimal
 * set of permission-aware example questions, and lets the bundled vanilla JS
 * consume the existing JSON endpoints:
 *
 *   GET  /ai/conversations        list conversations
 *   GET  /ai/conversations/{id}   open a conversation (messages)
 *   POST /ai/chat                 send a message (server-orchestrated)
 *
 * The browser never selects capabilities, tool names, arguments, tenant ids,
 * or model output — the server decides. Navigation visibility is UX only;
 * server-side authorization (permission:ai.view / AiGuardrailService) stays
 * authoritative.
 */
class AiChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $memberLayout = $user->hasRole('VICOBA Member');

        return view($memberLayout ? 'ai.member' : 'ai.index', [
            'suggestions' => $this->suggestions($user),
        ]);
    }

    /**
     * Role- and permission-aware example questions. These are static, safe
     * prompts rendered into the empty state — they are never tool instructions
     * and never bypass authorization. The server's intent routing decides what
     * (if anything) each question can access; examples are shown only for
     * capabilities the current user actually holds.
     *
     * @return string[]
     */
    protected function suggestions(User $user): array
    {
        $suggestions = [];

        $isMember = $user->hasRole('VICOBA Member');

        if ($isMember && $user->can('ai.loan.view')) {
            $suggestions[] = 'What is my current loan balance?';
        }

        if ($isMember && $user->can('ai.loan-repayments.view')) {
            $suggestions[] = 'Show me my recent repayment information.';
        }

        if ($isMember && $user->can('ai.member.view')) {
            $suggestions[] = 'What are my current savings?';
            $suggestions[] = 'What are my current shares?';
        }

        if ($user->can('ai.use')) {
            $suggestions[] = 'How are loan repayments scheduled?';
            $suggestions[] = 'How can I check my loan eligibility?';
            $suggestions[] = 'What can you help me with?';
        }

        if (! $isMember && $user->can('ai.portfolio.view')) {
            $suggestions[] = 'Show me our loan portfolio summary.';
        }

        if (! $isMember && $user->can('ai.delinquency.view')) {
            $suggestions[] = 'What is our portfolio at risk?';
            $suggestions[] = 'Which loans are delinquent?';
        }

        if (! $isMember && $user->can('ai.collection.view')) {
            $suggestions[] = 'What is our collection rate?';
        }

        if (! $isMember && $user->can('ai.accounting.view')) {
            $suggestions[] = 'Show me our income statement.';
        }

        if (! $isMember && $user->can('ai.anomaly.view')) {
            $suggestions[] = 'Have any anomalies been detected?';
        }

        return $suggestions;
    }
}