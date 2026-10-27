<?php

namespace App\Services;

use App\Repositories\ComposeRepository;
use App\Services\Sms\MnpClient;
use App\Support\PersonalizeFile;
use App\Support\SmsNumbers;
use App\Support\SmsText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Personalized SMS (legacy Add_Personalized_Msg_DAL): one message per file row, no OTP. */
class PersonalizeService
{
    public function __construct(
        private readonly ComposeService $compose,
        private readonly ComposeRepository $repository,
        private readonly ActivityLogger $activity,
        private readonly MnpClient $mnp,
    ) {
    }

    /**
     * @return array{sent: int, rejected: int, campaignId: int, credits: int, balance: int, approval: bool}
     *
     * @throws ValidationException
     */
    public function send(int $userId, array $data, UploadedFile $file, ?Carbon $scheduledAt, string $ip): array
    {
        @set_time_limit(600); // legacy max_execution_time for big files

        $template = $this->compose->prepareMessage($data['message']);
        $sender = trim($data['sender']);
        $name = trim(SmsText::clean($data['camp_name']));
        $context = $this->compose->context($userId);
        [$account] = $this->repository->context($userId);

        $parsed = PersonalizeFile::read($file);

        if ($parsed['truncated']) {
            throw ValidationException::withMessages(['file' => 'The file has more than ' . number_format(PersonalizeFile::MAX_ROWS) . ' rows. Please split it.']);
        }

        if ($parsed['columns'] < 2) {
            throw ValidationException::withMessages(['file' => 'Please include values for Message Keyword(s) in the file for Personalized Campaign.']);
        }

        if (PersonalizeFile::mostlyDuplicates($parsed['rows'])) {
            throw ValidationException::withMessages(['file' => 'Column number ( 1 ) in contact file looks with same values please include contact individual values.']);
        }

        // One message per valid row; invalid numbers are counted as rejected (legacy).
        $messages = [];
        $rejected = 0;
        $credits = 0;

        foreach ($parsed['rows'] as $index => $row) {
            $number = SmsNumbers::normalize($row[0], $context['allowInternational']);

            if ($number === null) {
                $rejected++;
                continue;
            }

            $text = self::xmlSafe(SmsText::clean(PersonalizeFile::fill($template, $row)));

            if (SmsText::hasEmoji($text)) {
                throw ValidationException::withMessages(['file' => 'Emojis or symbols are not allowed in the SMS input (file row ' . ($index + 2) . '). Please remove these before send SMS']);
            }

            $cost = SmsText::parts($text);
            $credits += $cost;
            $messages[] = [$number, $text, $cost];
        }

        if (! $messages) {
            throw ValidationException::withMessages(['file' => 'Please check contacts File looks invalid numbers.']);
        }

        if ($context['prepaid'] && $context['balance'] < $credits) {
            throw ValidationException::withMessages(['file' => 'You dont have sufficient Balance. Please Recharge your account.']);
        }

        // Network per unique number; numbers MNP rejects get no network → blocked (status 7), like legacy.
        $mnp = $this->mnp->lookup(array_values(array_unique(array_column($messages, 0))));
        $networks = array_combine($mnp['numbers'], $mnp['networks']) ?: [];

        $xml = '<rows>';

        foreach ($messages as [$number, $text, $cost]) {
            $xml .= sprintf(
                '<r n="%s" w="%s" c="%d">%s</r>',
                $number,
                htmlspecialchars((string) ($networks[$number] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $cost,
                htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            );
        }

        $xml .= '</rows>';

        $result = $this->repository->personalize([
            'user_id'      => $userId,
            'camp_name'    => $name,
            'sender'       => $sender,
            'message'      => $template,
            'camp_time'    => ($scheduledAt ?? now())->format('Y-m-d H:i:s'),
            'camp_cost'    => end($messages)[2],
            'prepaid'      => $context['prepaid'] ? 1 : 0,
            'camp_approve' => $context['needsApproval'] ? 1 : 0,
            'user_otp'     => (int) ($account->IsOtpAllow ?? 0) === 1 ? 1 : 0,
            'remote_ip'    => mb_substr($ip, 0, 25),
            'rows'         => $xml,
        ]);

        match ($result->status ?? null) {
            'OK'             => null,
            'NAME_TAKEN'     => throw ValidationException::withMessages(['camp_name' => 'Campaign Already Exist.']),
            'MASK_NOT_FOUND' => throw ValidationException::withMessages(['sender' => "Masking not Exist. {$sender}"]),
            'NO_ROUTES'      => throw ValidationException::withMessages(['sender' => 'Masking Routes Not Found.']),
            'EMPTY'          => throw ValidationException::withMessages(['file' => 'Valid numbers not found. Please place your numbers in first column.']),
            'LOW_BALANCE'    => throw ValidationException::withMessages(['file' => 'You dont have sufficient Balance. Please Recharge your account.']),
            default          => throw new \RuntimeException('Something went wrong. Campaign failed.'),
        };

        $sent = (int) $result->inserted;
        $this->activity->log("Personalized SMS - Campaign ({$name}) Id {$result->camp_id}: {$sent} Messages Sent, {$rejected} Rejected. Sender {$sender}, {$credits} credits.");

        return [
            'sent'       => $sent,
            'rejected'   => $rejected,
            'campaignId' => (int) $result->camp_id,
            'credits'    => (int) $result->credits,
            'balance'    => (int) $result->balance,
            'approval'   => (int) $result->needs_approval === 1,
        ];
    }

    /** Drop control characters that are not allowed inside XML. */
    private static function xmlSafe(string $text): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
    }
}