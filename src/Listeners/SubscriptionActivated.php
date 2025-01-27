<?php
/**
 * This file implements Subscription Activated.
 *
 * @author    Bilal Gultekin <bilal@gultekin.me>
 * @author    Justin Hartman <justin@22digital.co.za>
 * @copyright 2019 22 Digital
 * @license   MIT
 * @since     v0.1
 */

namespace TwentyTwoDigital\CashierFastspring\Listeners;

use TwentyTwoDigital\CashierFastspring\Events;
use TwentyTwoDigital\CashierFastspring\Subscription;
use TwentyTwoDigital\CashierFastspring\SubscriptionPeriod;

/**
 * This class describes a subscription activated.
 *
 * It is planned to listen following fastspring events:
 *  - subscription.canceled
 *  - subscription.deactivated
 *  - subscription.payment.overdue
 * It updates related subscription event.
 *
 * IMPORTANT: This class handles expansion enabled webhooks.
 *
 * {@inheritdoc}
 */
class SubscriptionActivated extends Base
{
    /**
     * Create the event listener.
     *
     * @return null
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param \TwentyTwoDigital\CashierFastspring\Events\SubscriptionActivated $event
     *
     * @return void
     */
    public function handle(Events\SubscriptionActivated $event)
    {
        $data = $event->data;

        // first look for is there any subscription
        $user = $this->getUserByFastspringId($data['account']['id']);
        $subscriptionName = isset($data['tags']['name']) ? $data['tags']['name'] : 'default';

        $subscription = $user->subscription();

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->user_id = $user->id;
            $subscription->name = $subscriptionName;
        }

        // fill
        $subscription->fastspring_id = $data['id'];
        $subscription->plan = $data['product']['product'];
        $subscription->state = $data['state'];
        $subscription->currency = $data['currency'];
        $subscription->quantity = $data['quantity'];
        $subscription->interval_unit = $data['intervalUnit'];
        $subscription->interval_length = $data['intervalLength'];

        // save
        $subscription->save();

        // save instructions as periods
        // since this is the first time subscription is created we dont need to
        // check if it is already existed
        $instructions = $data['instructions'];

        foreach ($instructions as $instruction) {
            // if end or start date is null don't insert
            if (is_null($instruction['periodStartDateInSeconds']) || is_null($instruction['periodEndDateInSeconds'])) {
                continue;
            }

            $subscriptionPeriod = SubscriptionPeriod::firstOrCreate([
                'subscription_id' => $subscription->id,
                'type'            => 'fastspring',
                'start_date'      => date('Y-m-d', $instruction['periodStartDateInSeconds']),
                'end_date'        => date('Y-m-d', $instruction['periodEndDateInSeconds']),
            ]);
        }
    }
}
