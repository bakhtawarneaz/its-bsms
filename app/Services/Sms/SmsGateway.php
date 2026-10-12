<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsGateway
{
    /**
     * Send a message through the ITS SMS gateway.
     * Returns true when the gateway accepts the message.
     */
    public function send(array|string $recipients, string $message, ?string $sender = null): bool
    {
        $config = config('services.sms_gateway');
        $recipients = implode(',', (array) $recipients);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($config['timeout'])
                ->post($config['url'], [
                    'action'      => 'sendmessage',
                    'username'    => $config['username'],
                    'password'    => $config['password'],
                    'originator'  => $sender ?? $config['sender'],
                    'messagedata' => $message,
                    'recipient'   => $recipients,
                    'sendondate'  => now()->format('Y-m-d H:i:s'),
                ]);
        } catch (ConnectionException $e) {
            Log::channel('sms')->error('SMS gateway unreachable', [
                'recipients' => $recipients,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }

        $accepted = $response->successful() && (string) $response->json('data.0.statuscode') === '0';

        // The message body is never logged because it may contain an OTP
        Log::channel('sms')->{$accepted ? 'info' : 'warning'}(
            $accepted ? 'SMS accepted by gateway' : 'SMS rejected by gateway',
            [
                'recipients'  => $recipients,
                'http_status' => $response->status(),
                'response'    => $response->json() ?? $response->body(),
            ]
        );

        return $accepted;
    }
}