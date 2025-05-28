<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Support\Facades\Auth;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

final class CauserResolver implements CauserResolverInterface
{
    public function __construct(
        private readonly ?string $guard = null,
        private readonly ?string $modelClass = null
    ) {}

    public function resolve(): array
    {
        $guard = $this->guard ?? config('audit-logger.causer.guard');
        $auth = $guard ? Auth::guard($guard) : Auth::guard();

        if (! $auth->check()) {
            return ['type' => null, 'id' => null];
        }

        $user = $auth->user();

        if (! $user) {
            return ['type' => null, 'id' => null];
        }

        $type = $this->modelClass ?? config('audit-logger.causer.model') ?? get_class($user);
        $id = $user->getAuthIdentifier();

        return ['type' => $type, 'id' => $id];
    }
}
