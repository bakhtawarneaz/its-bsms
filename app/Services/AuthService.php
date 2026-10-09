<?php

namespace App\Services;

use App\Enums\LoginStatus;
use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 3;
    private const FAILED_ATTEMPTS_WINDOW_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly AuthRepository $repository,
    ) {}

    /**
     * Verify credentials and apply the failed-attempt / blocking policy.
     *
     * @return array{status: LoginStatus, user: ?object}
     */
    public function attempt(string $login, string $password, string $siteName): array
    {
        $result = $this->repository->attempt($login, $this->hashPassword($password), $siteName);
        $status = LoginStatus::tryFrom($result->login_status ?? '') ?? LoginStatus::Invalid;

        if ($status === LoginStatus::Invalid) {
            return ['status' => $this->registerFailedAttempt($login, $siteName), 'user' => null];
        }

        if ($status !== LoginStatus::Ok) {
            return ['status' => $status, 'user' => null];
        }

        RateLimiter::clear($this->throttleKey($login, $siteName));

        return ['status' => $status, 'user' => $result];
    }

    /**
     * Count a failed attempt and block the account once the limit is reached.
     */
    private function registerFailedAttempt(string $login, string $siteName): LoginStatus
    {
        $key = $this->throttleKey($login, $siteName);

        RateLimiter::hit($key, self::FAILED_ATTEMPTS_WINDOW_SECONDS);

        if (RateLimiter::attempts($key) < self::MAX_FAILED_ATTEMPTS) {
            return LoginStatus::Invalid;
        }

        RateLimiter::clear($key);

        return $this->repository->block($login, $siteName)
            ? LoginStatus::Blocked
            : LoginStatus::Invalid;
    }

    /**
     * Legacy hashing kept for compatibility with existing password data.
     */
    private function hashPassword(string $password): string
    {
        return md5(md5($password));
    }

    private function throttleKey(string $login, string $siteName): string
    {
        return 'login:' . $siteName . ':' . strtolower(trim($login));
    }
}