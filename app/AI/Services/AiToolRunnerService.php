<?php

namespace App\AI\Services;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\Exceptions\AiToolException;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes a registered AI capability through its explicit handler.
 *
 * The handler class is resolved from the AiToolRegistry — a server-side
 * constant map the model can never influence. There is no dynamic execution:
 * model output can name a capability, never a class. Every run is audited
 * (requested / completed / denied / failed) with safe metadata only.
 */
class AiToolRunnerService
{
    /** Categories that represent access-control denials. */
    protected const DENIAL_CATEGORIES = ['unauthorized', 'not_found'];

    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws AiToolException
     */
    public function run(AiContextData $context, string $capability, array $arguments = [], ?User $user = null): array
    {
        $user = $user ?? auth()->user();

        $handlerClass = $this->registry->handler($capability);

        if ($handlerClass === null || ! is_a($handlerClass, AiToolInterface::class, true)) {
            throw new AiToolException('not_found');
        }

        $this->audit->log('ai.tool.requested', null, [], [
            'user_id' => $context->userId,
            'capability' => $capability,
            'argument_keys' => array_keys($arguments),
        ]);

        try {
            $tool = app($handlerClass);

            if (! $tool instanceof AiToolInterface) {
                throw new AiToolException('internal_error');
            }

            $result = $tool->execute($user, $context, $arguments);

            $this->audit->log('ai.tool.completed', null, [], [
                'user_id' => $context->userId,
                'capability' => $capability,
            ]);

            return $result;
        } catch (AiToolException $exception) {
            $this->audit->log(
                in_array($exception->category, self::DENIAL_CATEGORIES, true)
                    ? 'ai.tool.denied'
                    : 'ai.tool.failed',
                null,
                [],
                [
                    'user_id' => $context->userId,
                    'capability' => $capability,
                    'category' => $exception->category,
                ],
            );

            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('AI tool unexpected failure.', [
                'capability' => $capability,
                'exception' => $exception::class,
                'user_id' => $context->userId,
            ]);

            $this->audit->log('ai.tool.failed', null, [], [
                'user_id' => $context->userId,
                'capability' => $capability,
                'category' => 'internal_error',
            ]);

            throw new AiToolException('internal_error');
        }
    }
}