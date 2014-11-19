<?php namespace GovTribe\Notify;

use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

trait NotifyTrait {

    /**
     * Return the channels a notification should be sent over
     * for an item a user is tracking.
     *
     * @param  string  $id
     * @return mixed
     */
    public function getChannelsForNotification($id)
    {
        $channels = [];

        if (!$this->notificationsConfigured($id))
        {
            $platforms = $this->user->platforms;

            if (in_array('web', $platforms)) $channels[] = 'email';
            if (in_array('ios', $platforms)) $channels[] = 'apns';
        }
        else
        {
            $settings = $this->getNotificationSettings($id);

            $channels = $settings[$id]['channels'];
        }

        return $channels;
    }

    /**
     * Will the user be notified for this item?
     *
     * @param  string  $id
     * @return bool
     */
    public function willBeNotified($id)
    {
        if ($this->isTracking($id))
        {
            if (!$this->notificationsConfigured($id))
            {
                return true;
            }
            else
            {
                $settings = $this->getNotificationSettings($id);

                return $settings['enabled'];
            }
        }
        else return false;
    }

    /**
     * Based on an item a user is following, determine
     * when a notification should be sent.
     *
     * @param  string  $id
     * @return Carbon\Carbon
     */
    public function getWhenToSendNotification($id)
    {
        return Carbon::now();
    }

    /**
     * Check if notifications are configured for this item.
     *
     * @param  string  $id
     * @return Carbon\Carbon
     */
    public function notificationsConfigured($id)
    {
        $settings = $this->notificationSettings;

        if (isset($settings[$id])) return true;

        return false;
    }

    /**
     * For HTML email notifications, get the correct
     * template to use when sending the notification.
     *
     * @param  string  $id
     * @return string
     */
    public function getTemplateNameForNotification($id)
    {
        return 'template.name';
    }

    /**
     * Add a notification setting for the user
     *
     * @param  array   $channels
     * @param  array   $NTI
     * @return string
     */
    public function addNotification(array $channels, array $NTI)
    {
        $settings = $this->notificationSettings;

        $settings[ $NTI['_id'] ] = [
            'enabled' => true,
            'channels' => $channels,
            'name' => $NTI['name'],
            'type' => $NTI['type'],
            '_id' => $NTI['_id'],
        ];

        $this->notificationSettings = $settings;
    }

    /**
     * Remove a notification setting.
     *
     * @param  string  $mode
     * @param  array   $channels
     * @param  array   $NTI
     * @return string
     */
    public function removeNotification(array $NTI)
    {
        $settings = $this->notificationSettings;

        if ($this->notificationsConfigured($NTI['_id']))
        {
            $settings[$NTI['_id']]['enabled'] = false;
        }
        else
        {
            $settings[ $NTI['_id'] ] = [
                'enabled' => false,
                'channels' => [],
                'name' => $NTI['name'],
                'type' => $NTI['type'],
                '_id' => $NTI['_id'],
            ];

        }

        $this->notificationSettings = $settings;
    }

    /**
     * Get notification settings for an item.
     *
     * @param  string  $mode
     * @param  array   $channels
     * @param  array   $NTI
     * @return string
     */
    public function getNotificationSettings($id)
    {
        $settings = $this->notificationSettings;

        return $settings[$id];
    }

    /**
     * Get the user's notifications settings attribute.
     *
     * @param  string  $mode
     * @param  array   $channels
     * @param  array   $NTI
     * @return string
     */
    public function getNotificationSettingsAttribute()
    {
        if (empty($this->attributes['notificationSettings']))
        {
            return [];
        }
        else return $this->attributes['notificationSettings'];
    }
}
