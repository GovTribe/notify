<?php namespace GovTribe\Notify;

use Illuminate\Support\ServiceProvider;
use Simplon\Helium\Air;

class NotifyServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->registerListeners();
		$this->package('govtribe/notify', 'govtribe-notify');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('notify', function()
		{
			$air = Air::init()
				->setApplicationKey($this->app['config']->get('services.apns.key'))
				->setApplicationSecret($this->app['config']->get('services.apns.secret'))
				->setApplicationMasterSecret($this->app['config']->get('services.apns.master'));

			return new Notify($air);
		});

		$this->app['SendNotificationsFromEntityActivityCommand'] = $this->app->share(function($app)
		{
			return new SendNotificationsFromEntityActivityCommand;
		});

		$this->commands('SendNotificationsFromEntityActivityCommand');
	}

	/**
	 * Register event listeners.
	 *
	 * @return void
	 */
	public function registerListeners()
	{
		$userModel = $this->app->config->get('auth.model');

		$this->app->events->listen('eloquent.saving: ' . $userModel, function($user)
		{
			$user->syncNotificationsWithTracked();
		});
	}
}
