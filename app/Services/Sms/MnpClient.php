<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Network lookup for campaign receivers through the ITS MNP API (legacy CallMNP_cURL). */
class MnpClient
{
    private const CHUNK = 10000;

    /**
     * @param  array<int, string>  $numbers
     * @return array{numbers: array<int, string>, networks: array<int, string>, rejected: int}
     */
    public function lookup(array $numbers): array
    {
        $result = ['numbers' => [], 'networks' => [], 'rejected' => 0];

        foreach (array_chunk($numbers, self::CHUNK) as $chunk) {
            $part = config('services.mnp.fake') ? $this->fake($chunk) : $this->call($chunk);

            array_push($result['numbers'], ...$part['numbers']);
            array_push($result['networks'], ...$part['networks']);
            $result['rejected'] += $part['rejected'];
        }

        return $result;
    }

    private function call(array $chunk): array
    {
        $response = Http::asForm()->timeout(180)->post(config('services.mnp.url'), [
            'apikey' => config('services.mnp.key'),
            'number' => implode(',', $chunk),
        ]);

        if (! $response->ok()) {
            throw new RuntimeException("MNP API error (HTTP {$response->status()}).");
        }

        $data = $response->json();
        $accepted = (int) ($data['acceptcount'] ?? 0);
        $rejected = (int) ($data['rejectcount'] ?? 0);

        if ($accepted + $rejected !== count($chunk)) {
            throw new RuntimeException('MNP contacts verification failed (counts do not match).');
        }

        $numbers = array_values(array_filter(explode(',', (string) ($data['number'] ?? ''))));
        $networks = array_values(array_filter(explode(',', (string) ($data['network'] ?? ''))));

        if (count($numbers) !== count($networks)) {
            throw new RuntimeException('MNP API returned mismatched numbers and networks.');
        }

        return ['numbers' => $numbers, 'networks' => $networks, 'rejected' => $rejected];
    }

    /** Local development: network from the number prefix, 9237 counted as Zong. */
    private function fake(array $chunk): array
    {
        $networks = array_map(fn ($number) => ($prefix = substr($number, 0, 4)) === '9237' ? '9231' : $prefix, $chunk);

        return ['numbers' => array_values($chunk), 'networks' => $networks, 'rejected' => 0];
    }
}