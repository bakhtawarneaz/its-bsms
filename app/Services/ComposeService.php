<?php

namespace App\Services;

use App\Repositories\ComposeRepository;
use App\Support\SmsNumbers;
use App\Support\SmsText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Enums\OtpVerification;
use App\Services\Sms\MnpClient;
use App\Support\NumberFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ComposeService
{
    public const QUICK_LIMIT = 1000;

    public function __construct(
        private readonly ComposeRepository $repository,
        private readonly ActivityLogger $activity,
        private readonly CampaignOtpService $otp,
        private readonly MnpClient $mnp,
    ) {
    }

    /** @return array{prepaid: bool, balance: int, allowInternational: bool, needsApproval: bool, masks: array<int, string>, promotionalMasks: array<int, string>} */
    public function context(int $userId): array
    {
        [$account, $masks] = $this->repository->context($userId);

        return [
            'prepaid'            => strtolower((string) ($account->account_type ?? '')) === 'prepaid',
            'balance'            => (int) ($account->balance ?? 0),
            'allowInternational' => (int) ($account->allow_int ?? 0) === 1,
            'needsApproval'      => (int) ($account->camp_approve ?? 0) === 1,
            'masks'              => array_map(fn ($row) => (string) $row->masking, $masks),
            'promotionalMasks'   => array_values(array_map(fn ($row) => (string) $row->masking, array_filter($masks, fn ($row) => (int) $row->promotional === 1))),
        ];
    }

    /**
     * Message checks shared by every compose screen; returns the cleaned message.
     *
     * @throws ValidationException
     */
    public function prepareMessage(string $message): string
    {
        if (SmsText::hasEmoji($message)) {
            throw ValidationException::withMessages(['message' => 'Emojis or symbols are not allowed in the SMS input. Please remove these before send SMS']);
        }

        $blocked = SmsText::blockedWords($message, $this->blockedWords());

        if ($blocked) {
            throw ValidationException::withMessages(['message' => 'Restricted text message please change the words: ( ' . implode(', ', $blocked) . ' )']);
        }

        return SmsText::clean($message);
    }

    /**
     * @return array{sent: int, rejected: int, duplicates: int, balance: int}
     *
     * @throws ValidationException
     */
    public function quick(int $userId, string $sender, string $receivers, string $message, string $ip): array
    {
        $message = $this->prepareMessage($message);
        $context = $this->context($userId);
        $parsed = SmsNumbers::parse($receivers, $context['allowInternational']);
        $count = count($parsed['numbers']);

        if ($count === 0 || $count > self::QUICK_LIMIT) {
            throw ValidationException::withMessages(['receivers' => 'The number(s) you provided looks invalid or more than ' . self::QUICK_LIMIT . '.']);
        }

        $cost = SmsText::parts($message);
        $result = $this->repository->quick($userId, trim($sender), $message, $cost, $parsed['numbers'], $ip);

        match ($result->status ?? null) {
            'OK'             => null,
            'MASK_NOT_FOUND' => throw ValidationException::withMessages(['sender' => 'Masking not Exist.(' . trim($sender) . ')']),
            'LOW_BALANCE'    => throw ValidationException::withMessages(['receivers' => 'You dont have sufficient Balance. Please Recharge your account.']),
            default          => throw new \RuntimeException('Some error occurred please retry.'),
        };

        $summary = "{$result->sent} Messages Sent, {$parsed['rejected']} Rejected, {$parsed['duplicates']} Duplicate";
        $this->activity->log("Qucik SMS - {$summary}. Sender {$sender}, cost {$cost} per message.");

        return [
            'sent'       => (int) $result->sent,
            'rejected'   => $parsed['rejected'],
            'duplicates' => $parsed['duplicates'],
            'balance'    => (int) $result->balance,
        ];
    }

    /** @return array<int, string> */
    private function blockedWords(): array
    {
        return Cache::remember('compose.blocked_words', now()->addMinutes(10), fn () => array_map(
            fn ($row) => (string) $row->blocked_word,
            $this->repository->blockedWords(),
        ));
    }

    public const CAMPAIGN_BATCH = 50000;

    public function groups(int $userId): array
    {
        return $this->repository->groups($userId);
    }

    /**
     * Validates a campaign, keeps it for 15 minutes and sends the campaign OTP.
     *
     * @return array<string, mixed> review shown with the OTP prompt
     *
     * @throws ValidationException
     */
    public function prepareCampaign(int $userId, array $data, ?UploadedFile $file, ?Carbon $scheduledAt): array
    {
        if (! $this->otp->hasRecipients()) {
            throw ValidationException::withMessages(['sender' => 'Please sign out and sign in again so the campaign OTP can reach your login mobile/email.']);
        }

        $message = $this->prepareMessage($data['message']);
        $sender = trim($data['sender']);
        $name = trim(SmsText::clean((string) ($data['camp_name'] ?? ''))) ?: $userId . '_' . now()->timestamp;
        $context = $this->context($userId);

        match ($this->repository->campaignCheck($userId, $name, $sender)->status ?? null) {
            'OK'             => null,
            'NAME_TAKEN'     => throw ValidationException::withMessages(['camp_name' => 'Campaign Name Already Exist.']),
            'MASK_NOT_FOUND' => throw ValidationException::withMessages(['sender' => "Masking not Exist. {$sender}"]),
            default          => throw new \RuntimeException('Campaign check failed.'),
        };

        // Typed numbers, groups / all contacts and the uploaded file are merged (legacy).
        $numbers = [];
        $rejected = 0;
        $duplicates = 0;
        $add = function (string $raw) use (&$numbers, &$rejected, &$duplicates, $context) {
            if (trim($raw) === '') {
                return;
            }

            $number = SmsNumbers::normalize($raw, $context['allowInternational']);

            if ($number === null) {
                $rejected++;
            } elseif (isset($numbers[$number])) {
                $duplicates++;
            } else {
                $numbers[$number] = true;
            }
        };

        foreach (preg_split('/[\r\n;,\/\\\\]+/', (string) ($data['receivers'] ?? '')) as $raw) {
            $add($raw);
        }

        $groupIds = array_map('intval', $data['groups'] ?? []);
        $all = (bool) ($data['all_contacts'] ?? false);

        if ($groupIds || $all) {
            foreach ($this->repository->groupNumbers($userId, $groupIds, $all) as $row) {
                $add((string) $row->numbers);
            }
        }

        if ($file) {
            foreach (NumberFile::numbers($file) as $raw) {
                $add($raw);
            }
        }

        $numbers = array_keys($numbers);

        if (! $numbers) {
            throw ValidationException::withMessages(['receivers' => 'The numbers you have provided looks invalid.']);
        }

        $cost = SmsText::parts($message);
        $credits = count($numbers) * $cost;

        if ($context['prepaid'] && $context['balance'] < $credits) {
            throw ValidationException::withMessages(['receivers' => 'You dont have sufficient Balance. Please Recharge your account.']);
        }

        $token = Str::random(40);
        Cache::put($this->campaignKey($userId, $token), [
            'name'    => $name,
            'sender'  => $sender,
            'message' => $message,
            'cost'    => $cost,
            'at'      => ($scheduledAt ?? now())->format('Y-m-d H:i:s'),
            'numbers' => $numbers,
        ], now()->addMinutes(15));

        try {
            $sent = $this->otp->ensureSent();
        } catch (\App\Exceptions\OtpDeliveryFailed) {
            Cache::forget($this->campaignKey($userId, $token));

            throw ValidationException::withMessages(['sender' => 'Campaign OTP could not be sent (SMS and email both failed).']);
        }

        return [
            'token'      => $token,
            'name'       => $name,
            'sender'     => $sender,
            'receivers'  => count($numbers),
            'rejected'   => $rejected,
            'duplicates' => $duplicates,
            'cost'       => $cost,
            'credits'    => $credits,
            'balance'    => $context['prepaid'] ? $context['balance'] - $credits : null,
            'scheduled'  => $scheduledAt?->format('d M Y, H:i'),
            'approval'   => $context['needsApproval'],
            'otpSent'    => $sent,
        ];
    }

    /**
     * Verifies the OTP, resolves networks through MNP and creates the campaign.
     *
     * @return array{sent: int, rejected: int, campaignId: int, balance: int, approval: bool}
     *
     * @throws ValidationException
     */
    public function confirmCampaign(int $userId, string $token, string $code, string $ip): array
    {
        $payload = Cache::get($this->campaignKey($userId, $token))
            ?? throw ValidationException::withMessages(['code' => 'This review has expired. Please submit the campaign again.']);

        $result = $this->otp->verify($code);

        if ($result !== OtpVerification::Valid) {
            $message = $result === OtpVerification::Invalid
                ? 'Invalid provided Campaign OTP. ' . $this->otp->triesLeft() . ' Tries left'
                : $result->message();

            throw ValidationException::withMessages(['code' => $message]);
        }

        // Network per number, then the mask's route/rate per network (unknown network → blocked).
        $mnp = $this->mnp->lookup($payload['numbers']);
        $routes = [];

        foreach ($this->repository->maskRoutes($payload['sender']) as $row) {
            $routes[(string) $row->network] = $row; // last row per network wins (legacy)
        }

        $byRoute = [];
        $totalRate = 0.0;

        foreach ($mnp['numbers'] as $i => $number) {
            $network = (string) $mnp['networks'][$i];
            $route = $routes[$network] ?? (object) ['route' => 0, 'operator' => 'block', 'otp_allow' => 0, 'rate' => 1.00];
            $key = $network;

            $byRoute[$key] ??= ['route' => $route, 'numbers' => []];
            $byRoute[$key]['numbers'][] = $number;
            $totalRate += (float) $route->rate * $payload['cost'];
        }

        $xml = '<batches>';

        foreach ($byRoute as $group) {
            $route = $group['route'];

            foreach (array_chunk($group['numbers'], self::CAMPAIGN_BATCH) as $chunk) {
                $xml .= sprintf(
                    '<b route="%d" operator="%s" otp="%d" rate="%s" count="%d">%s</b>',
                    (int) $route->route,
                    htmlspecialchars((string) $route->operator, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    (int) $route->otp_allow === 1 ? 1 : 0,
                    number_format((float) $route->rate, 2, '.', ''),
                    count($chunk),
                    implode(',', $chunk),
                );
            }
        }

        $xml .= '</batches>';
        $sent = count($mnp['numbers']);

        $created = $this->repository->campaign([
            'user_id'    => $userId,
            'camp_name'  => $payload['name'],
            'sender'     => $payload['sender'],
            'message'    => $payload['message'],
            'cost'       => $payload['cost'],
            'camp_time'  => $payload['at'],
            'total_sms'  => $sent,
            'total_rate' => round($totalRate, 2),
            'remote_ip'  => mb_substr($ip, 0, 25),
            'batches'    => $xml,
        ]);

        match ($created->status ?? null) {
            'OK'             => null,
            'NAME_TAKEN'     => throw ValidationException::withMessages(['code' => 'Campaign Name Already Exist.']),
            'MASK_NOT_FOUND' => throw ValidationException::withMessages(['code' => "Masking not Exist. {$payload['sender']}"]),
            'LOW_BALANCE'    => throw ValidationException::withMessages(['code' => 'You dont have sufficient Balance. Please Recharge your account.']),
            default          => throw new \RuntimeException('Campaign failed.'),
        };

        Cache::forget($this->campaignKey($userId, $token));

        $this->activity->log("Campaign SMS - Campaign ({$payload['name']}) Id {$created->camp_id}: {$sent} Messages Sent, {$mnp['rejected']} Rejected. Sender {$payload['sender']}, cost {$payload['cost']}.");

        return [
            'sent'       => $sent,
            'rejected'   => $mnp['rejected'],
            'campaignId' => (int) $created->camp_id,
            'balance'    => (int) $created->balance,
            'approval'   => (int) $created->needs_approval === 1,
        ];
    }

    public function cancelCampaign(int $userId, ?string $token): void
    {
        if ($token) {
            Cache::forget($this->campaignKey($userId, $token));
        }

        $this->otp->clear();
    }

    public function resendCampaignOtp(): bool
    {
        return $this->otp->resend();
    }

    private function campaignKey(int $userId, string $token): string
    {
        return "compose.campaign.{$userId}.{$token}";
    }
}