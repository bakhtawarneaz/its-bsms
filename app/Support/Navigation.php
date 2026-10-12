<?php

namespace App\Support;

use App\Enums\UserType;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /**
     * Build the sidebar sections visible to the given user type.
     */
    public static function for(UserType $userType): array
    {
        $sections = [];

        foreach (config('navigation') as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! in_array($userType->value, $item['roles'], true)) {
                    continue;
                }

                $available = $item['route'] && Route::has($item['route']);

                $items[] = [
                    'label'  => $item['label'],
                    'icon'   => $item['icon'],
                    'url'    => $available ? route($item['route']) : null,
                    'active' => $available && request()->routeIs($item['route'], $item['route'] . '.*'),
                ];
            }

            if ($items) {
                $sections[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $sections;
    }
}