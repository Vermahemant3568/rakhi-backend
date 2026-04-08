<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;

class SubscriptionExpiryCheck extends Command
{
    protected $signature   = 'rakhi:subscription-check';
    protected $description = 'Check expiring subscriptions and notify';

    public function handle(PushNotificationService $push): void
    {
        UserSubscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        UserSubscription::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'expired']);

        $expiring = UserSubscription::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(2)])
            ->with('user')
            ->get();

        foreach ($expiring as $sub) {
            if ($sub->user) {
                $push->sendToUser(
                    user:  $sub->user,
                    title: 'Your Rakhi subscription expires soon ⚠️',
                    body:  'Renew now to keep your health journey going!',
                    data:  ['screen' => 'premium']
                );
            }
        }

        $this->info('Subscription check done');
    }
}
