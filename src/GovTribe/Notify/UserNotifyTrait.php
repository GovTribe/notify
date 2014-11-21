<?php namespace GovTribe\Notify;

use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;

trait UserNotifyTrait {

    /**
     * Enable notifications for an item.
     *
     * @param  array  $nti
     * @return void
     */
    public function notify($nti)
    {
        extract($nti);

        $this->enableNotification($_id);
    }

    /**
     * Mute notifications for an item.
     *
     * @param  array  $nti
     * @return void
     */
    public function mute($nti)
    {
        extract($nti);

        $this->disableNotifcation($_id);
    }

    /**
     * Disable a notification setting.
     *
     * @param  string   $id
     * @param  string   $channel
     * @return void
     */
    protected function enableNotification($id, $channel = 'email')
    {
        $tracking = $this->tracking;

        foreach ($tracking as &$NTI)
        {
            if ($NTI['_id'] === $id)
            {
                $NTI['notifications']['channels'][$channel]['enabled'] = true;

                break;
            }
        }

        $this->tracking = $tracking;
    }

    /**
     * Disable a notification setting.
     *
     * @param  string   $id
     * @param  string   $channel
     * @return void
     */
    protected function disableNotifcation($id, $channel = 'email')
    {
        $tracking = $this->tracking;

        foreach ($tracking as &$NTI)
        {
            if ($NTI['_id'] === $id)
            {
                $NTI['notifications']['channels'][$channel]['enabled'] = false;

                break;
            }
        }

        $this->tracking = $tracking;
    }

    /**
     * Determine if a user will be notified if an item they track is updated.
     *
     * @param  string $id
     * @param  string   $channel
     * @return bool
     */
    public function willBeNotified($id, $channel = 'email')
    {
        $id = (string) $id;

        if ($settings = $this->getNotificationSettings($id))
        {
            return $settings['channels'][$channel]['enabled'];
        }
        else return false;
    }

    /**
     * Get the user's available channels.
     *
     * @param  string $id
     * @return mixed
     */
    protected function getNotificationSettings($id)
    {
        $trackedNTI = current(array_filter($this->tracking, function($item) use ($id)
        {
            if ($item['_id'] === $id) return true;
            return false;
        }));

        if (isset($trackedNTI['notifications']))
        {
            return $trackedNTI['notifications'];
        }
        else return false;
    }

    /**
     * Get the user's available channels.
     *
     * @return mixed
     */
    protected function getDefaultNotificationSettings()
    {
        $settings = [
            'triggers' => [],
            'channels' => [],
        ];

        if (in_array('web', $this->platforms))
        {
            $settings['channels']['email'] = [
                'enabled' => 'true',
                'frequency' => 'instant',
                'meta' => [
                    'format' => 'html',
                ],
            ];
        }

        if (in_array('ios', $this->platforms))
        {
            $settings['channels']['apns'] = [
                'enabled' => 'true',
                'frequency' => 'instant',
                'meta' => [],
            ];
        }

        return $settings;
    }

    /**
     * Sync the user's notification settings with items they track.
     *
     * @param  string  $mode
     * @param  array   $channels
     * @param  array   $NTI
     * @return string
     */
    public function syncNotificationsWithTracked()
    {
        $notificationSettings = $this->notificationSettings;
        $tracking = $this->tracking;

        foreach ($tracking as &$trackedNTI)
        {
            if (!isset($trackedNTI['notifications']))
            {
                $trackedNTI['notifications'] = $this->getDefaultNotificationSettings();
            }
        }

        $this->tracking = $tracking;
    }

    /**
     * Determine if a user can receive a push notification.
     *
     * @param bool   $canPush
     *
     * @return bool
     */
    public function canUserReceivePushMessage($canPush = true)
    {
        $user = $this;

        // No push device tokens.
        if (!$user->deviceTokens) $canPush = false;

        // Last push was within an hour.
        if ($user->lastPush instanceof MongoDate && (time() - $user->lastPush->sec) <= 3600) $canPush = false;

        // A push notification is already pending.
        if ($user->pendingPush === true) $canPush = false;

        return $canPush;
    }

    /**
     * Get the user's device tokens attribute.
     *
     * @return string
     */
    public function getDeviceTokensAttribute()
    {
        $tokens = array();

        if (isset($this->attributes['_push']['_devicetokens']))
        {
            $tokens = array_merge($tokens, $this->attributes['_push']['_devicetokens']);
        }

        return $tokens;
    }
}
