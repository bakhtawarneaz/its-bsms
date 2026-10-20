<?php

/*
| Sidebar navigation. Each item lists the user types that can see it.
| Items whose route does not exist yet are shown as disabled ("Soon").
*/

$everyone = ['superadmin', 'admin', 'reseller', 'user', 'reporting', 'campreporting', 'lcreporting', 'campapprover', 'gwuser'];
$reporting = ['superadmin', 'admin', 'reseller', 'user', 'reporting', 'campreporting', 'lcreporting', 'gwuser'];
$senders = ['superadmin', 'admin', 'reseller', 'user', 'campreporting', 'lcreporting'];
$owners = ['superadmin', 'admin', 'reseller', 'user'];

return [
    [
        'label' => 'Overview',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'roles' => $everyone],
        ],
    ],
    [
        'label' => 'Messaging',
        'items' => [
            ['label' => 'Send SMS', 'icon' => 'paper-airplane', 'route' => 'compose.index', 'roles' => $senders],
            ['label' => 'Campaigns', 'icon' => 'megaphone', 'route' => 'campaigns.index', 'roles' => [...$owners, 'campreporting', 'campapprover']],
            ['label' => 'Campaign approvals', 'icon' => 'check-badge', 'route' => 'campaigns.approvals', 'roles' => ['campapprover']],
            ['label' => 'Templates', 'icon' => 'document-text', 'route' => 'templates.index', 'roles' => $owners],
            ['label' => 'Broadcast', 'icon' => 'signal', 'route' => 'broadcast.index', 'roles' => ['user']],
            ['label' => 'Voice calls', 'icon' => 'phone', 'route' => 'voice.index', 'roles' => ['user']],
            ['label' => 'Circle channel', 'icon' => 'chat-bubble-left-right', 'route' => 'circle.index', 'roles' => ['user']],
        ],
    ],
    [
        'label' => 'Audience',
        'items' => [
            ['label' => 'Contacts', 'icon' => 'book-open', 'route' => 'contacts.index', 'roles' => $owners],
            ['label' => 'Groups', 'icon' => 'user-group', 'route' => 'groups.index', 'roles' => $owners],
        ],
    ],
    [
        'label' => 'Reports',
        'items' => [
            ['label' => 'Outbox', 'icon' => 'arrow-up-tray', 'route' => 'outbox.index', 'roles' => $reporting],
            ['label' => 'Inbox', 'icon' => 'inbox', 'route' => 'inbox.index', 'roles' => $reporting],
            ['label' => 'Summary', 'icon' => 'chart-bar', 'route' => 'summary.index', 'roles' => ['superadmin', 'admin', 'reseller', 'user', 'reporting', 'campreporting', 'gwuser']],
            ['label' => 'Missed calls', 'icon' => 'phone-x-mark', 'route' => 'missed-calls.index', 'roles' => ['user']],
            ['label' => 'Gateway outbox', 'icon' => 'server-stack', 'route' => 'gateway.outbox', 'roles' => ['superadmin']],
            ['label' => 'Gateway inbox', 'icon' => 'server', 'route' => 'gateway.inbox', 'roles' => ['superadmin']],
            ['label' => 'Traffic', 'icon' => 'arrows-right-left', 'route' => 'traffic.index', 'roles' => ['admin']],
        ],
    ],
    [
        'label' => 'Management',
        'items' => [
            // Admin/reseller scoped user lists come later; route is superadmin-only for now.
            ['label' => 'Users', 'icon' => 'users', 'route' => 'users.index', 'roles' => ['superadmin']],
            ['label' => 'Resellers', 'icon' => 'building-storefront', 'route' => 'resellers.index', 'roles' => ['superadmin']],
            ['label' => 'Gateway users', 'icon' => 'cpu-chip', 'route' => 'gateway.users', 'roles' => ['superadmin']],
            ['label' => 'Maskings', 'icon' => 'identification', 'route' => 'maskings.index', 'roles' => ['superadmin']],
            ['label' => 'SMS balance', 'icon' => 'banknotes', 'route' => 'balance.index', 'roles' => ['reseller', 'user']],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['label' => 'Activity logs', 'icon' => 'clock', 'route' => 'activity.index', 'roles' => $everyone],
            ['label' => 'API', 'icon' => 'code-bracket', 'route' => 'api-docs.index', 'roles' => ['user']],
        ],
    ],
];