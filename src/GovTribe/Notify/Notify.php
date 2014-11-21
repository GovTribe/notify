<?php namespace GovTribe\Notify;

use MongoDate;
use Carbon\Carbon;
use Simplon\Helium\Air;
use Simplon\Helium\PushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use User;
use Project;
use Activity;
use Str;

class Notify
{
    /**
     * Air.
     *
     * @var object Simplon\Helium\Air
     */
    protected $air;

    /**
     * Queue name.
     *
     * @var string
     */
    protected $queueName = 'action';

    /**
     * Construct an instance of the class.
     *
     * @param  object Simplon\Helium\Air
     *
     * @return void
     */
    public function __construct(Air $air)
    {
        $this->air = $air;
    }

    /**
     * Send a user a push notification.
     *
     * @param object User
     * @param string $message
     * @param array  $extra
     * @param bool   $now
     *
     * @return void
     */
    public function sendPush(User $user, $message, array $extra = array(), $now = false)
    {
        if ($user->canUserReceivePushMessage() === false) return;

        if ($now === true || Carbon::now()->eq($user->dayStart))
        {
            $this->pushNow($user, $message, $extra);
            Log::info('NotificationsManager: Sent push message to user ' . $user->username, [utf8_decode($message), $extra]);
        }
        else
        {
            $this->pushLater($user, $message, $extra, $user->dayStart);
            Log::info('NotificationsManager: Queued push message to user ' . $user->username, [utf8_decode($message), $extra]);
        }
    }

    /**
     * Send a user an email notification.
     *
     * @param object User
     * @param string $message
     * @param array  $target
     * @param bool   $now
     *
     * @return void
     */
    public function sendEmail(User $user, $message, $target)
    {
        if (!$message) return;

        $synopses = $target->getOriginal('synopses');
        $synopsis = end($synopses);

        $emailData = [
            'userName' => $user->first_name,
            'projectName' => $target->name,
            'updateDetails' => $message,
            'url' => 'https://govtribe.com/project/' . $target->slug . '/activity',
            'mute' => 'https://govtribe.com/project/' . $target->slug . '/mute',
            'synopsis' => $synopsis,
        ];

        $subject = 'Update: ' . Str::limit($target->name, 60);

        Mail::send(array('html' => 'govtribe-notify::email.projectactivity'), $emailData, function($message) use ($target, $subject, $user)
        {
            $message->to($user->email)->subject($subject);
        });
    }

    /**
     * Send a user a push notification.
     *
     * @param object User
     * @param string $message
     * @param array  $extra
     *
     * @return void
     */
    protected function pushNow(User $user, $message, array $extra = array())
    {
        $this->doPush($user, $message, $extra);
    }

    /**
     * Notify a user at some point in the future
     *
     * @param object User
     * @param string $message
     * @param array  $extra
     * @param object Carbon $deferUntil
     *
     * @return void
     */
    protected function pushLater(User $user, $message, array $extra = array(), Carbon $deferUntil)
    {
        $delay = Carbon::now()->diffInSeconds($deferUntil);

        Queue::later($delay, 'Notify@doPushFromQueue',
            array('userId' => $user->_id->__toString(), 'message' => $message, 'extra' => $extra),
            $this->queueName
        );

        $user->pendingPush = true;
        $user->save();
    }

    /**
     * Perform the push notification.
     *
     * @param object  User $user
     * @param string  $message
     * @param array  $extra
     *
     * @return bool
     */
    protected function doPush(User $user, $message, array $extra = array())
    {
        $pushNotifications   = array();
        $pushNotifications[] = PushNotification::init()
                                ->setDeviceTokens($user->deviceTokens)
                                ->setAlert($message)
                                ->setExtra($extra)
                                ->getData();

        $response = $this->air->setNotifications($pushNotifications)->sendSinglePush();

        $user->lastPush = time();
        $user->pendingPush = false;
        $user->save();

        return true;
    }

    /**
     * Perform the push notification from a queue job.
     *
     * @param object  $job
     * @param array   $data
     *
     * @return void
     */
    public function doPushFromQueue($job, $data)
    {
        extract($data);

        $user = User::where('_id', $userId)->first();

        $this->doPush($user, $message, $extra);

        $job->delete();
    }
}
