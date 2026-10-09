<?php

return [
    'supabase_url' => rtrim((string) env('SUPABASE_URL', ''), '/'),
    'anon_key' => (string) env('SUPABASE_ANON_KEY', ''),
    'service_role_key' => (string) env('SUPABASE_SERVICE_ROLE_KEY', ''),
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('CENTRAL_ADMIN_EMAILS', '')),
    ))),
];
