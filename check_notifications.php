<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Notification Check ===\n";
echo "Total notifications: " . \App\Models\Notification::count() . "\n\n";

$notifications = \App\Models\Notification::latest()->take(5)->get();
if ($notifications->count() > 0) {
    echo "Recent notifications:\n";
    foreach ($notifications as $n) {
        echo "ID: {$n->notification_id}, User: {$n->user_id}, Type: {$n->type}, Actor: {$n->actor_id}, Post: {$n->post_id}, Comment: {$n->comment_id}, Read: {$n->is_read}\n";
    }
} else {
    echo "No notifications found in database.\n";
}
