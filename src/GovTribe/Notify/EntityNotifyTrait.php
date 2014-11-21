<?php namespace GovTribe\Notify;

use Illuminate\Support\Facades\Auth;

trait EntityNotifyTrait {

    /**
     * Check if the use will be notified of updates to this entity.
     *
     * @return bool
     */
    public function willBeNotified()
    {
        if (!Auth::check() || empty(Auth::user()->tracking)) return false;

        return Auth::user()->willBeNotified($this->_id);
    }
}
