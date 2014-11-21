<?php namespace GovTribe\Notify;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputArgument;
use GovTribe\Notify\Facades\Notify;
use Activity;
use Project;
use User;
use Kinvey;
use DB;
use Str;
use MongoDate;

class SendNotificationsFromEntityActivityCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'databot:send-notifications-from-entity-activity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send users notifications when entities they track are updated.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        Kinvey::setAuthMode('admin');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->info('Sending user notifications based on entity activity');

        $since = \Carbon\Carbon::now()->subMinutes(120)->timestamp;

        $sent = $this->sendNotificationsFromProjectActivity($since);

        $this->info('Sent ' . $sent . ' notification(s)');
    }

    /**
     * Send push notifications based on project activity.
     *
     * @param  int   $since
     * @return array
     */
    protected function sendNotificationsFromProjectActivity($since)
    {
        $sent = 0;

        $cursor = DB::connection((new Activity)->getConnectionName())->collection('activities')->raw(function($collection) use ($since)
        {
            return $collection->find([
                    'activityType' => 'project',
                    'created_at' => ['$gte' => new MongoDate($since)],
                ])
                ->sort(['created_at' => 1]);
        });

        while ($cursor->hasNext())
        {
            $activity = new Activity;
            $activity->setRawAttributes($cursor->getNext(), true);

            //if ($activity->sentNotifications === true) continue;

            // Find users that track the project.
            foreach ($activity->targets as $targetNTI)
            {
                if ($targetNTI['type'] === 'project') $projectId = (string) $targetNTI['_id'];
            }

            $trackers = User::where(['hording._id' => $projectId])->get();

            if($trackers->count())
            {
                $project = Project::find($projectId);
                $payload = $activity->getNotificationForPerspective($project);

                foreach ($trackers as $user)
                {
                    if ($user->willBeNotified($project->_id))
                    {
                        Notify::sendEmail($user, $payload['message'], $project);
                        Log::info('Notification sent to user ' . $user->username . ', message: ' . $payload['message']);
                        $this->info('Notification sent to user ' . $user->username . ', message: ' . $payload['message']);
                        ++$sent;
                    }
                }
            }

            $activity->sentNotifications = true;
            $activity->save();
        }

        return $sent;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }
}